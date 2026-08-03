<?php

namespace JMReferral\Admin\Pages;

use JMReferral\Permissions\Capabilities;
use JMReferral\Referral\ReferralController;
use JMReferral\Services\ServiceTypeService;
use JMReferral\Users\UserProvider;

class AddReferralPage
{
    public function __construct(
        private UserProvider $user_provider,
        private ServiceTypeService $service_type_service
    ) {
    }

    public function render(): void
    {
        if (! Capabilities::current_user_can(Capabilities::CREATE_REFERRALS)) {
            wp_die(esc_html__('You do not have permission to create referrals.', 'jm-referral-system'));
        }

        $form_state       = ReferralController::get_form_state();
        $data             = $form_state['data'];
        $errors           = $form_state['errors'];
        $assignable_users = $this->user_provider->get_assignable_users();
        $service_types    = $this->service_type_service->get_active();

        include JMRS_PLUGIN_PATH . 'templates/referrals/create.php';
    }
}
