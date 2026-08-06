<?php

namespace JMReferral\Portal\Clinical;

use JMReferral\Portal\PortalRouter;

/**
 * Routes staff-portal clinical requests (care plan review, medications, care
 * team, schedules, visits) to their dedicated handlers.
 */
class ClinicalDispatcher
{
    public function __construct(
        private PortalViewHost $view_host,
        private CarePlanReviewHandler $care_plan_review_handler,
        private MedicationHandler $medication_handler,
        private CareTeamHandler $care_team_handler,
        private ScheduleHandler $schedule_handler,
        private VisitHandler $visit_handler
    ) {
    }

    public function dispatch(string $route): void
    {
        $referral_id = absint(get_query_var(PortalRouter::QV_ID));
        $entity_id   = absint(get_query_var(PortalRouter::QV_ENTITY));

        match ($route) {
            'care_plan_review' => $this->care_plan_review_handler->handle($referral_id),
            'medication_new'   => $this->medication_handler->handle_new($referral_id),
            'medication_edit'  => $this->medication_handler->handle_edit($referral_id, $entity_id),
            'care_team_new'    => $this->care_team_handler->handle_new($referral_id),
            'care_team_edit'   => $this->care_team_handler->handle_edit($referral_id, $entity_id),
            'schedule_new'     => $this->schedule_handler->handle_new($referral_id),
            'schedule_edit'    => $this->schedule_handler->handle_edit($referral_id, $entity_id),
            'schedule_generate' => $this->schedule_handler->handle_generate($referral_id, $entity_id),
            'visit_new'        => $this->visit_handler->handle_new($referral_id),
            'visit_edit'       => $this->visit_handler->handle_edit($referral_id, $entity_id),
            'visit_execute'    => $this->visit_handler->handle_execute($referral_id, $entity_id),
            'visit_review'     => $this->visit_handler->handle_review($referral_id, $entity_id),
            default            => $this->view_host->render_portal_error('404', __('Not Found', 'jm-referral-system'), 404),
        };
    }
}
