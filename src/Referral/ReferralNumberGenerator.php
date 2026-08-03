<?php

namespace JMReferral\Referral;

class ReferralNumberGenerator
{
    public function __construct(
        private ReferralRepository $repository
    ) {
    }

    /**
     * Generates a referral number in the format JM-YYYYMMDD-XXXX.
     */
    public function generate(): string
    {
        $prefix = 'JM-' . current_time('Ymd') . '-';
        $count  = $this->repository->count_by_number_prefix($prefix);
        $sequence = str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);

        return $prefix . $sequence;
    }
}
