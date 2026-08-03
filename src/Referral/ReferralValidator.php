<?php

namespace JMReferral\Referral;

use JMReferral\Services\ServiceTypeService;
use JMReferral\Users\UserProvider;
use JMReferral\Workflow\WorkflowStageService;

class ReferralValidator
{
    public function __construct(
        private UserProvider $user_provider,
        private ServiceTypeService $service_type_service,
        private WorkflowStageService $workflow_stage_service
    ) {
    }

    /**
     * Validates referral form input.
     *
     * @param array<string, string> $data Sanitized input data.
     * @return array<string, string> Field => error message map.
     */
    public function validate(array $data): array
    {
        $errors = [];

        if ('' === trim($data['client_name'] ?? '')) {
            $errors['client_name'] = __('Client name is required.', 'jm-referral-system');
        }

        $service_type_id         = absint($data['service_type_id'] ?? 0);
        $current_service_type_id = absint($data['current_service_type_id'] ?? 0);

        if ($service_type_id <= 0) {
            $errors['service_type_id'] = __('Please select a service type.', 'jm-referral-system');
        } elseif (! $this->service_type_service->is_selectable(
            $service_type_id,
            $current_service_type_id > 0 ? $current_service_type_id : null
        )) {
            $errors['service_type_id'] = __('Please select a valid service type.', 'jm-referral-system');
        }

        if (array_key_exists('workflow_stage_id', $data)) {
            $workflow_stage_id         = absint($data['workflow_stage_id'] ?? 0);
            $current_workflow_stage_id = absint($data['current_workflow_stage_id'] ?? 0);

            if ($workflow_stage_id <= 0) {
                $errors['workflow_stage_id'] = __('Please select a workflow stage.', 'jm-referral-system');
            } elseif (! $this->workflow_stage_service->is_selectable(
                $workflow_stage_id,
                $current_workflow_stage_id > 0 ? $current_workflow_stage_id : null
            )) {
                $errors['workflow_stage_id'] = __('Please select a valid workflow stage.', 'jm-referral-system');
            }
        }

        $client_email = $data['client_email'] ?? '';
        if ('' !== $client_email && ! is_email($client_email)) {
            $errors['client_email'] = __('Please enter a valid client email address.', 'jm-referral-system');
        }

        $referrer_email = $data['referrer_email'] ?? '';
        if ('' !== $referrer_email && ! is_email($referrer_email)) {
            $errors['referrer_email'] = __('Please enter a valid referrer email address.', 'jm-referral-system');
        }

        if (array_key_exists('status', $data)) {
            $allowed_statuses = [
                'new',
                'in_progress',
                'completed',
                'cancelled',
            ];

            if (! in_array($data['status'], $allowed_statuses, true)) {
                $errors['status'] = __('Please select a valid status.', 'jm-referral-system');
            }
        }

        $assigned_to = absint($data['assigned_to'] ?? 0);
        if ($assigned_to > 0 && ! $this->user_provider->is_assignable($assigned_to)) {
            $errors['assigned_to'] = __('Please select a valid assignee.', 'jm-referral-system');
        }

        $referral_source = (string) ($data['referral_source'] ?? '');
        if ('' === $referral_source) {
            $errors['referral_source'] = __('Please select a referral source.', 'jm-referral-system');
        } elseif (! ReferralSources::is_valid($referral_source)) {
            $errors['referral_source'] = __('Please select a valid referral source.', 'jm-referral-system');
        }

        $care_start_date = (string) ($data['care_start_date'] ?? '');
        if ('' !== $care_start_date && ! $this->is_valid_date($care_start_date)) {
            $errors['care_start_date'] = __('Please enter a valid care start date.', 'jm-referral-system');
        }

        $preferred_contact_method = (string) ($data['preferred_contact_method'] ?? '');
        if ('' !== $preferred_contact_method && ! PreferredContactMethods::is_valid($preferred_contact_method)) {
            $errors['preferred_contact_method'] = __('Please select a valid preferred contact method.', 'jm-referral-system');
        }

        return $errors;
    }

    /**
     * Validates a YYYY-MM-DD date string.
     */
    private function is_valid_date(string $date): bool
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return false;
        }

        $parts = explode('-', $date);
        if (3 !== count($parts)) {
            return false;
        }

        return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
    }
}
