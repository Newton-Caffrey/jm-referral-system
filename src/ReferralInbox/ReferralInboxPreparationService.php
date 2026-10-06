<?php

namespace JMReferral\ReferralInbox;

use JMReferral\Referral\ReferralSources;
use JMReferral\Referral\ReferralValidator;
use JMReferral\ReferralInbox\Document\ExtractedDocument;
use JMReferral\ReferralInbox\Document\ReferralFormExtractionResult;
use JMReferral\ReferralInbox\Document\ReferralInboxDocumentService;
use JMReferral\Services\ServiceTypeService;
use JMReferral\Users\UserProvider;

/**
 * In-memory Referral Inbox preparation (Phase 5D.4).
 *
 * Builds a review form from advisory candidates and checks a submitted draft.
 * Does not create a referral, store a draft, or change the Inbox row.
 *
 * Phase 5E.1: when the Inbox item holds an uploaded referral form, suggestions
 * read from that file are merged in. They are advisory in the same way: one
 * clear value prefills, an ambiguous one is listed and left blank.
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
    private const ADDRESS_LINE_MAX = 255;
    private const CITY_MAX = 150;
    private const POSTCODE_MAX = 30;
    private const RELATIONSHIP_MAX = 150;
    private const CARE_REQUIREMENTS_MAX = 10000;

    /**
     * Form fields that an uploaded referral form can suggest, beyond the message candidates.
     *
     * @var array<int, string>
     */
    private const DOCUMENT_ONLY_FIELDS = [
        'client_date_of_birth',
        'address_line_1',
        'address_line_2',
        'city',
        'postcode',
        'referrer_phone',
        'relationship_to_client',
        'care_requirements',
        'care_start_date',
    ];

    /**
     * @var array<int, string>
     */
    private const PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    public function __construct(
        private ReferralInboxService $inbox_service,
        private ReferralInboxCandidateExtractor $extractor,
        private ReferralValidator $validator,
        private ServiceTypeService $service_type_service,
        private UserProvider $user_provider,
        private ?ReferralInboxDocumentService $document_service = null
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
        $document   = $this->document_candidates($inbox_id);
        $values     = $this->initial_values($candidates, $document);

        return $this->payload(self::READY, $item, $candidates, $document, $values, []);
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
        $document   = $this->document_candidates($inbox_id);
        $result = [] === $errors ? self::VALID : self::VALIDATION_ERROR;

        return $this->payload($result, $item, $candidates, $document, $values, $errors);
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
        ReferralFormExtractionResult $document,
        array $values,
        array $errors
    ): array {
        $service_hint = $this->hint_label($candidates->service_hint(), $this->service_labels());
        if ('' === $service_hint) {
            $service_hint = $this->hint_label($document->field('service_hint'), $this->service_labels());
        }

        $priority_hint = $this->hint_label($candidates->priority_hint(), $this->priority_labels());
        if ('' === $priority_hint) {
            $priority_hint = $this->hint_label($document->field('priority_hint'), $this->priority_labels());
        }

        return [
            'result'          => $result,
            'status'          => (string) ($item['status'] ?? ''),
            'item'            => $item,
            'values'          => $values,
            'errors'          => $errors,
            'warnings'        => $this->warnings($candidates, $document),
            'alternatives'    => $this->alternatives($candidates, $document),
            'field_notes'     => $this->field_notes($candidates, $document),
            'document'        => $this->document_summary($document),
            'service_hint'    => $service_hint,
            'priority_hint'   => $priority_hint,
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
    private function initial_values(ReferralInboxCandidateResult $candidates, ReferralFormExtractionResult $document): array
    {
        $values = [
            'client_name'           => $this->merged_value($candidates->client_name(), $document->field('client_name')),
            'client_email'          => $this->merged_value($candidates->client_email(), $document->field('client_email')),
            'client_phone'          => $this->merged_value($candidates->client_phone(), $document->field('client_phone')),
            'referrer_name'         => $this->merged_value($candidates->referrer_name(), $document->field('referrer_name')),
            'referrer_email'        => $this->merged_value($candidates->referrer_email(), $document->field('referrer_email')),
            'referrer_organisation' => $this->merged_value($candidates->referrer_organisation(), $document->field('referrer_organisation')),
            'service_type_id'       => '0',
            'referral_source'       => '',
            'priority'              => '',
            'assigned_to'           => '0',
            'notes'                 => '',
        ];

        foreach (self::DOCUMENT_ONLY_FIELDS as $key) {
            $values[$key] = $this->single_value($document->field($key));
        }

        return $values;
    }

    /**
     * Suggestions read from a stored referral form, or an empty result.
     */
    private function document_candidates(int $inbox_id): ReferralFormExtractionResult
    {
        if (! $this->document_service instanceof ReferralInboxDocumentService) {
            return ReferralFormExtractionResult::no_document();
        }

        return $this->document_service->candidates($inbox_id);
    }

    /**
     * The message candidate when it has one clear value, otherwise the form's.
     * Two candidates that disagree are not reconciled: the field stays blank.
     */
    private function merged_value(ReferralInboxCandidateField $message, ReferralInboxCandidateField $form): string
    {
        $from_message = $this->single_value($message);
        $from_form    = $this->single_value($form);

        if (ReferralInboxCandidateField::STATE_AMBIGUOUS === $message->state()
            || ReferralInboxCandidateField::STATE_AMBIGUOUS === $form->state()
        ) {
            return '';
        }

        if ('' !== $from_message && '' !== $from_form && 0 !== strcasecmp($from_message, $from_form)) {
            return '';
        }

        return '' !== $from_message ? $from_message : $from_form;
    }

    /**
     * Which source a prefilled shared field came from: 'message', 'form', or ''.
     */
    private function merged_origin(ReferralInboxCandidateField $message, ReferralInboxCandidateField $form): string
    {
        if ('' === $this->merged_value($message, $form)) {
            return '';
        }

        return '' !== $this->single_value($message) ? 'message' : 'form';
    }

    /**
     * True when the message and the form each give one value and they differ.
     */
    private function sources_disagree(ReferralInboxCandidateField $message, ReferralInboxCandidateField $form): bool
    {
        $from_message = $this->single_value($message);
        $from_form    = $this->single_value($form);

        return '' !== $from_message && '' !== $from_form && 0 !== strcasecmp($from_message, $from_form);
    }

    /**
     * @return array<string, array{0: ReferralInboxCandidateField, 1: ReferralInboxCandidateField}>
     */
    private function shared_fields(ReferralInboxCandidateResult $candidates, ReferralFormExtractionResult $document): array
    {
        return [
            'client_name'           => [$candidates->client_name(), $document->field('client_name')],
            'client_email'          => [$candidates->client_email(), $document->field('client_email')],
            'client_phone'          => [$candidates->client_phone(), $document->field('client_phone')],
            'referrer_name'         => [$candidates->referrer_name(), $document->field('referrer_name')],
            'referrer_email'        => [$candidates->referrer_email(), $document->field('referrer_email')],
            'referrer_organisation' => [$candidates->referrer_organisation(), $document->field('referrer_organisation')],
        ];
    }

    /**
     * What the preparation screen shows about the uploaded form.
     *
     * @return array<string, mixed>
     */
    private function document_summary(ReferralFormExtractionResult $document): array
    {
        if (! $document->has_document()) {
            return [
                'has_document' => false,
                'status'       => $document->status(),
                'filename'     => '',
                'message'      => '',
                'message_type' => '',
                'text'         => '',
                'found_count'  => 0,
            ];
        }

        $found = $document->single_count();

        [$message, $type] = match ($document->status()) {
            ExtractedDocument::STATUS_OK => $found > 0
                ? [
                    sprintf(
                        /* translators: %d: number of fields */
                        _n(
                            '%d field was filled in from the uploaded form. Check every field against the form before creating the referral.',
                            '%d fields were filled in from the uploaded form. Check every field against the form before creating the referral.',
                            $found,
                            'jm-referral-system'
                        ),
                        $found
                    ),
                    'info',
                ]
                : [
                    __('The form was read, but none of its labels were recognised. Enter the details by hand using the form text below. An administrator can add this form\'s wording under Settings.', 'jm-referral-system'),
                    'warning',
                ],
            ExtractedDocument::STATUS_NO_TEXT => [
                __('This file has no readable text. It is probably a scan or a photo, which cannot be read automatically. Enter the details by hand.', 'jm-referral-system'),
                'warning',
            ],
            ExtractedDocument::STATUS_UNSUPPORTED => [
                __('This server cannot read that type of file. Enter the details by hand.', 'jm-referral-system'),
                'warning',
            ],
            ReferralFormExtractionResult::STATUS_MISSING_FILE => [
                __('The uploaded file could not be found in private storage. Enter the details by hand.', 'jm-referral-system'),
                'warning',
            ],
            default => [
                __('The file could not be read. It may be password-protected or damaged. Enter the details by hand.', 'jm-referral-system'),
                'warning',
            ],
        };

        return [
            'has_document' => true,
            'status'       => $document->status(),
            'filename'     => $document->filename(),
            'message'      => $message,
            'message_type' => $type,
            'text'         => $document->text(),
            'found_count'  => $found,
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
    private function warnings(ReferralInboxCandidateResult $candidates, ReferralFormExtractionResult $document): array
    {
        $warnings = [];
        if (ReferralInboxCandidateField::STATE_AMBIGUOUS === $candidates->client_name()->state()
            || ReferralInboxCandidateField::STATE_AMBIGUOUS === $document->field('client_name')->state()
        ) {
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

        $form_fields = array_merge(
            ['client_email', 'client_phone', 'referrer_name', 'referrer_email', 'referrer_organisation'],
            self::DOCUMENT_ONLY_FIELDS
        );
        foreach ($form_fields as $key) {
            if (! isset($warnings[$key])
                && ReferralInboxCandidateField::STATE_AMBIGUOUS === $document->field($key)->state()
            ) {
                $warnings[$key] = __('Multiple possible values were found. Please confirm manually.', 'jm-referral-system');
            }
        }

        if (! isset($warnings['service_type_id'])
            && ReferralInboxCandidateField::STATE_AMBIGUOUS === $document->field('service_hint')->state()
        ) {
            $warnings['service_type_id'] = __('Multiple possible values were found. Please confirm manually.', 'jm-referral-system');
        }

        foreach ($this->shared_fields($candidates, $document) as $key => [$message, $form]) {
            if (! isset($warnings[$key]) && $this->sources_disagree($message, $form)) {
                $warnings[$key] = __('The message and the uploaded form give different values. Please enter the correct one.', 'jm-referral-system');
            }
        }

        return $warnings;
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function alternatives(ReferralInboxCandidateResult $candidates, ReferralFormExtractionResult $document): array
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

        $form_fields = array_merge(array_keys($this->shared_fields($candidates, $document)), self::DOCUMENT_ONLY_FIELDS);
        foreach ($form_fields as $key) {
            $field = $document->field($key);
            if (ReferralInboxCandidateField::STATE_AMBIGUOUS !== $field->state()) {
                continue;
            }
            $out[$key] = array_values(array_unique(array_merge($out[$key] ?? [], $field->alternatives())));
        }

        foreach ($this->shared_fields($candidates, $document) as $key => [$message, $form]) {
            if (! isset($out[$key]) && $this->sources_disagree($message, $form)) {
                $out[$key] = [$this->single_value($message), $this->single_value($form)];
            }
        }

        return $out;
    }

    /**
     * @return array<string, string>
     */
    private function field_notes(ReferralInboxCandidateResult $candidates, ReferralFormExtractionResult $document): array
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

        $from_form = __('Suggested from uploaded form', 'jm-referral-system');
        foreach ($this->shared_fields($candidates, $document) as $key => [$message, $form]) {
            $origin = $this->merged_origin($message, $form);
            if ('' === $origin) {
                // Nothing was prefilled, so there is nothing to attribute.
                unset($notes[$key]);
            } elseif ('form' === $origin) {
                $notes[$key] = $from_form;
            }
        }
        foreach (self::DOCUMENT_ONLY_FIELDS as $key) {
            if (ReferralInboxCandidateField::STATE_SINGLE === $document->field($key)->state()) {
                $notes[$key] = $from_form;
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
            'client_date_of_birth'  => $this->bound($this->scalar_text($post['jmrs_prepare_client_date_of_birth'] ?? ''), 10),
            'address_line_1'        => $this->bound($this->scalar_text($post['jmrs_prepare_address_line_1'] ?? ''), self::ADDRESS_LINE_MAX),
            'address_line_2'        => $this->bound($this->scalar_text($post['jmrs_prepare_address_line_2'] ?? ''), self::ADDRESS_LINE_MAX),
            'city'                  => $this->bound($this->scalar_text($post['jmrs_prepare_city'] ?? ''), self::CITY_MAX),
            'postcode'              => $this->bound($this->scalar_text($post['jmrs_prepare_postcode'] ?? ''), self::POSTCODE_MAX),
            'referrer_phone'        => $this->bound($this->scalar_text($post['jmrs_prepare_referrer_phone'] ?? ''), self::PHONE_MAX),
            'relationship_to_client'=> $this->bound($this->scalar_text($post['jmrs_prepare_relationship_to_client'] ?? ''), self::RELATIONSHIP_MAX),
            'care_requirements'     => $this->bound($this->scalar_textarea($post['jmrs_prepare_care_requirements'] ?? ''), self::CARE_REQUIREMENTS_MAX),
            'care_start_date'       => $this->bound($this->scalar_text($post['jmrs_prepare_care_start_date'] ?? ''), 10),
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
            'care_start_date'       => (string) ($values['care_start_date'] ?? ''),
            'preferred_contact_method' => '',
        ]);

        if (! in_array($values['priority'], self::PRIORITIES, true)) {
            $errors['priority'] = __('Please select a priority.', 'jm-referral-system');
        }

        $date_of_birth = (string) ($values['client_date_of_birth'] ?? '');
        if ('' !== $date_of_birth) {
            if (! $this->is_valid_date($date_of_birth)) {
                $errors['client_date_of_birth'] = __('Please enter a valid date of birth.', 'jm-referral-system');
            } elseif ($date_of_birth > current_time('Y-m-d')) {
                $errors['client_date_of_birth'] = __('Date of birth cannot be in the future.', 'jm-referral-system');
            }
        }

        return $errors;
    }

    /**
     * Validates a YYYY-MM-DD date string.
     */
    private function is_valid_date(string $date): bool
    {
        if (1 !== preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)) {
            return false;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
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
