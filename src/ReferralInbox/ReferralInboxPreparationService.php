<?php

namespace JMReferral\ReferralInbox;

use JMReferral\Referral\ReferralSources;
use JMReferral\Referral\ReferralValidator;
use JMReferral\Services\ServiceTypeService;
use JMReferral\Users\UserProvider;

/**
 * In-memory Referral Inbox preparation (Phase 5D.4).
 *
 * Builds a review form from advisory candidates and checks a submitted draft.
 * Does not create a referral, store a draft, or change the Inbox row.
 */
class ReferralInboxPreparationService
{
    public const READY             = 'ready';
    public const NOT_FOUND         = 'not_found';
    public const INVALID_STATE     = 'invalid_state';
    public const VALID             = 'valid';
    public const VALIDATION_ERROR  = 'validation_error';

    private const NAME_MAX  = 255;
    private const EMAIL_MAX = 190;
    private const PHONE_MAX = 50;
    private const NOTES_MAX = 5000;

    /**
     * @var array<int, string>
     */
    private const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    public function __construct(
        private ReferralInboxService $inbox_service,
        private ReferralInboxCandidateExtractor $extractor,
        private ReferralValidator $validator,
        private ServiceTypeService $service_type_service,
        private UserProvider $user_provider
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function present(int $inbox_id): array
    {
        $item = $this->inbox_service->find($inbox_id);
        if (null === $item) {
            return ['result' => self::NOT_FOUND];
        }

        if (ReferralInboxStatus::NEEDS_REVIEW !== (string) ($item['status'] ?? '')) {
            return $this->blocked($item);
        }

        $candidates = $this->extractor->extract($inbox_id);
        $values     = $this->initial_values($candidates);

        return $this->payload(self::READY, $item, $candidates, $values, []);
    }

    /**
     * Validate a posted draft. Submitted values replace suggestions for this response only.
     *
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public function validateDraft(int $inbox_id, array $post, bool $can_assign): array
    {
        $item = $this->inbox_service->find($inbox_id);
        if (null === $item) {
            return ['result' => self::NOT_FOUND];
        }

        if (ReferralInboxStatus::NEEDS_REVIEW !== (string) ($item['status'] ?? '')) {
            return $this->blocked($item);
        }

        $values = $this->sanitize_post($post, $can_assign);
        $errors = $this->validate_values($values);
        $candidates = $this->extractor->extract($inbox_id);
        $result = [] === $errors ? self::VALID : self::VALIDATION_ERROR;

        return $this->payload($result, $item, $candidates, $values, $errors);
    }

    /**
     * @param array<string, mixed> $item
     * @return array<string, mixed>
     */
    private function blocked(array $item): array
    {
        return [
            'result' => self::INVALID_STATE,
            'status' => (string) ($item['status'] ?? ''),
            'item'   => $item,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @param array<string, string> $values
     * @param array<string, string> $errors
     * @return array<string, mixed>
     */
    private function payload(
        string $result,
        array $item,
        ReferralInboxCandidateResult $candidates,
        array $values,
        array $errors
    ): array {
        return [
            'result'          => $result,
            'status'          => (string) ($item['status'] ?? ''),
            'item'            => $item,
            'values'          => $values,
            'errors'          => $errors,
            'warnings'        => $this->warnings($candidates),
            'alternatives'    => $this->alternatives($candidates),
            'field_notes'     => $this->field_notes($candidates),
            'service_hint'    => $this->hint_label($candidates->service_hint(), $this->service_labels()),
            'priority_hint'   => $this->hint_label($candidates->priority_hint(), $this->priority_labels()),
            'authority_note'  => $this->authority_note($candidates->referrer_organisation()),
            'authority_status_label' => LocalAuthorityOrigin::stored_summary(
                absint($item['local_authority_id'] ?? 0),
                $this->origin_value($item)
            ),
            'service_types'   => $this->service_type_service->get_active(),
            'referral_sources'=> ReferralSources::options(),
            'priorities'      => $this->priority_labels(),
            'assignable_users'=> $this->user_provider->get_assignable_users(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function initial_values(ReferralInboxCandidateResult $candidates): array
    {
        return [
            'client_name'           => $this->single_value($candidates->client_name()),
            'client_email'          => $this->single_value($candidates->client_email()),
            'client_phone'          => $this->single_value($candidates->client_phone()),
            'referrer_name'         => $this->single_value($candidates->referrer_name()),
            'referrer_email'        => $this->single_value($candidates->referrer_email()),
            'referrer_organisation' => $this->single_value($candidates->referrer_organisation()),
            'service_type_id'       => '0',
            'referral_source'       => '',
            'priority'              => '',
            'assigned_to'           => '0',
            'notes'                 => '',
        ];
    }

    private function single_value(ReferralInboxCandidateField $field): string
    {
        if (ReferralInboxCandidateField::STATE_SINGLE !== $field->state()) {
            return '';
        }

        return (string) $field->value();
    }

    /**
     * @return array<string, string>
     */
    private function warnings(ReferralInboxCandidateResult $candidates): array
    {
        $warnings = [];
        if (ReferralInboxCandidateField::STATE_AMBIGUOUS === $candidates->client_name()->state()) {
            $warnings['client_name'] = __('Multiple possible client names were found. Please enter the correct value.', 'jm-referral-system');
        }
        foreach ([
            'client_email' => $candidates->client_email(),
            'client_phone' => $candidates->client_phone(),
            'referrer_name' => $candidates->referrer_name(),
            'referrer_email' => $candidates->referrer_email(),
            'service_type_id' => $candidates->service_hint(),
            'priority' => $candidates->priority_hint(),
        ] as $key => $field) {
            if (ReferralInboxCandidateField::STATE_AMBIGUOUS === $field->state()) {
                $warnings[$key] = __('Multiple possible values were found. Please confirm manually.', 'jm-referral-system');
            }
        }

        return $warnings;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function alternatives(ReferralInboxCandidateResult $candidates): array
    {
        $out = [];
        foreach ([
            'client_name'   => $candidates->client_name(),
            'client_email'  => $candidates->client_email(),
            'client_phone'  => $candidates->client_phone(),
        ] as $key => $field) {
            if (ReferralInboxCandidateField::STATE_AMBIGUOUS === $field->state()) {
                $out[$key] = $field->alternatives();
            }
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private function field_notes(ReferralInboxCandidateResult $candidates): array
    {
        $notes = [];
        foreach ([
            'client_name'  => $candidates->client_name(),
            'client_email' => $candidates->client_email(),
            'client_phone' => $candidates->client_phone(),
        ] as $key => $field) {
            if (ReferralInboxCandidateField::STATE_SINGLE === $field->state()) {
                $notes[$key] = __('Suggested from referral message', 'jm-referral-system');
            }
        }
        foreach ([
            'referrer_name'  => $candidates->referrer_name(),
            'referrer_email' => $candidates->referrer_email(),
        ] as $key => $field) {
            if (ReferralInboxCandidateField::STATE_SINGLE === $field->state()) {
                $notes[$key] = __('Suggested from sender', 'jm-referral-system');
            }
        }

        return $notes;
    }

    /**
     * @param array<string, mixed> $item
     */
    private function origin_value(array $item): ?string
    {
        $origin = $item['local_authority_origin'] ?? null;
        if (! is_string($origin) || '' === trim($origin)) {
            return null;
        }

        return $origin;
    }

    private function authority_note(ReferralInboxCandidateField $field): string
    {
        if (ReferralInboxCandidateField::STATE_SINGLE !== $field->state()) {
            return '';
        }

        return match ($field->evidence_type()) {
            'staff_confirmed_authority' => __('From staff-confirmed Local Authority', 'jm-referral-system'),
            'suggested_authority' => __('From JMRS Local Authority suggestion', 'jm-referral-system'),
            'linked_authority_unknown_origin' => __('From linked Local Authority', 'jm-referral-system'),
            default => '',
        };
    }

    /**
     * @param array<string, string> $labels
     */
    private function hint_label(ReferralInboxCandidateField $field, array $labels): string
    {
        if (ReferralInboxCandidateField::STATE_SINGLE !== $field->state()) {
            return '';
        }

        $value = (string) $field->value();

        return $labels[$value] ?? '';
    }

    /**
     * @return array<string, string>
     */
    private function service_labels(): array
    {
        return [
            'supported_living'  => __('Supported Living', 'jm-referral-system'),
            'home_care'         => __('Home Care', 'jm-referral-system'),
            'residential_care'  => __('Residential Care', 'jm-referral-system'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function priority_labels(): array
    {
        return [
            'low'    => __('Low', 'jm-referral-system'),
            'medium' => __('Medium', 'jm-referral-system'),
            'high'   => __('High', 'jm-referral-system'),
            'urgent' => __('Urgent', 'jm-referral-system'),
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, string>
     */
    private function sanitize_post(array $post, bool $can_assign): array
    {
        $priority = $this->scalar_text($post['jmrs_prepare_priority'] ?? '');
        if (! in_array($priority, self::PRIORITIES, true)) {
            $priority = '';
        }

        $source = $this->scalar_text($post['jmrs_prepare_referral_source'] ?? '');
        if ('' !== $source && ! ReferralSources::is_valid($source)) {
            $source = '';
        }

        $assigned = '0';
        if ($can_assign) {
            $assigned = (string) absint($this->scalar_text($post['jmrs_prepare_assigned_to'] ?? '0'));
        }

        return [
            'client_name'           => $this->bound($this->scalar_text($post['jmrs_prepare_client_name'] ?? ''), self::NAME_MAX),
            'client_email'          => $this->bound($this->scalar_text($post['jmrs_prepare_client_email'] ?? ''), self::EMAIL_MAX),
            'client_phone'          => $this->bound($this->scalar_text($post['jmrs_prepare_client_phone'] ?? ''), self::PHONE_MAX),
            'referrer_name'         => $this->bound($this->scalar_text($post['jmrs_prepare_referrer_name'] ?? ''), self::NAME_MAX),
            'referrer_email'        => $this->bound($this->scalar_text($post['jmrs_prepare_referrer_email'] ?? ''), self::EMAIL_MAX),
            'referrer_organisation' => $this->bound($this->scalar_text($post['jmrs_prepare_referrer_organisation'] ?? ''), self::NAME_MAX),
            'service_type_id'       => (string) absint($this->scalar_text($post['jmrs_prepare_service_type_id'] ?? '0')),
            'referral_source'       => $source,
            'priority'              => $priority,
            'assigned_to'           => $assigned,
            'notes'                 => $this->bound($this->scalar_textarea($post['jmrs_prepare_notes'] ?? ''), self::NOTES_MAX),
        ];
    }

    /**
     * @param array<string, string> $values
     * @return array<string, string>
     */
    private function validate_values(array $values): array
    {
        $errors = $this->validator->validate([
            'client_name'           => $values['client_name'],
            'client_email'          => $values['client_email'],
            'referrer_email'        => $values['referrer_email'],
            'service_type_id'       => $values['service_type_id'],
            'referral_source'       => $values['referral_source'],
            'assigned_to'           => $values['assigned_to'],
            'care_start_date'       => '',
            'preferred_contact_method' => '',
        ]);

        if (! in_array($values['priority'], self::PRIORITIES, true)) {
            $errors['priority'] = __('Please select a priority.', 'jm-referral-system');
        }

        return $errors;
    }

    /**
     * Re-check an already sanitized payload. Does not read POST and does not write.
     *
     * @param array<string, string> $values
     * @return array<string, string>
     */
    public function revalidate_values(array $values, bool $can_assign): array
    {
        if (! $can_assign) {
            $values['assigned_to'] = '0';
        }

        return $this->validate_values($values);
    }

    private function scalar_text(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $text = sanitize_text_field(wp_unslash((string) $value));

        return trim($text);
    }

    private function scalar_textarea(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        return trim(sanitize_textarea_field(wp_unslash((string) $value)));
    }

    private function bound(string $value, int $max): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $max, 'UTF-8');
        }

        return substr($value, 0, $max);
    }
}
