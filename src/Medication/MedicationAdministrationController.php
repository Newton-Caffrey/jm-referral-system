<?php

namespace JMReferral\Medication;

/**
 * Administration recording is handled through visit execution.
 * This controller exists for future standalone MAR endpoints and DI completeness.
 */
class MedicationAdministrationController
{
    public function __construct(
        private MedicationAdministrationService $administration_service
    ) {
    }

    public function register(): void
    {
        // Administrations are submitted with CareVisitController::handle_execute().
    }

    public function get_service(): MedicationAdministrationService
    {
        return $this->administration_service;
    }
}
