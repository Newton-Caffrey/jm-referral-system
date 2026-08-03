<?php

namespace JMReferral\Admin\Pages;

use JMReferral\Referral\ReferralListController;

class ReferralsPage
{
    public function __construct(
        private ReferralListController $list_controller
    ) {
    }

    public function render(): void
    {
        $this->list_controller->render();
    }
}
