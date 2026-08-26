<?php

namespace JMReferral\Pipeline;

/**
 * Presentation-only grouping of canonical acquisition stages for the
 * Management Dashboard. Does not alter workflow or PipelineStage.
 *
 * HERE NOW rule: each active canonical slug maps to at most one visual stage.
 */
class VisualStageMap
{
    public const LA_REFERRALS = 'la_referrals';
    public const APPOINTMENT_SET = 'appointment_set';
    public const ASSESSMENT = 'assessment';
    public const PACKAGE_COSTING = 'package_costing';
    public const AUTHORITY_CONSIDERATION = 'authority_consideration';
    public const PLACEMENT_TRANSITION = 'placement_transition';

    /**
     * Ordered visual stages with labels, colours, and exclusive canonical slug sets.
     *
     * Mapping rationale (Phase 2A):
     * - interest_required → awaiting interest / LA referral decision
     * - assessment_to_schedule → appointment not yet booked (Appointment to Arrange)
     * - assessment_scheduled + assessment_review_required → Assessment work
     * - package_cost_required → Package Costing
     * - awaiting_la_decision → Authority Consideration
     * - transition_planning → Placement Transition (care_commenced excluded)
     *
     * @return array<string, array{
     *     key: string,
     *     order: int,
     *     name: string,
     *     colour: string,
     *     question: string,
     *     slugs: list<string>
     * }>
     */
    public static function definitions(): array
    {
        return [
            self::LA_REFERRALS => [
                'key'      => self::LA_REFERRALS,
                'order'    => 1,
                'name'     => __('Local authority referrals', 'jm-referral-system'),
                'colour'   => '#2B4C7E',
                'question' => __('Referrals awaiting an interest response.', 'jm-referral-system'),
                'slugs'    => [PipelineStage::INTEREST_REQUIRED],
            ],
            self::APPOINTMENT_SET => [
                'key'      => self::APPOINTMENT_SET,
                'order'    => 2,
                // Phase 4A: honest label — canonical stage is assessment_to_schedule (not yet booked).
                'name'     => __('Appointment to Arrange', 'jm-referral-system'),
                'colour'   => '#0E7C86',
                'question' => __('Assessment appointment still needs arranging (not yet booked).', 'jm-referral-system'),
                'slugs'    => [PipelineStage::ASSESSMENT_TO_SCHEDULE],
            ],
            self::ASSESSMENT => [
                'key'      => self::ASSESSMENT,
                'order'    => 3,
                'name'     => __('Assessment', 'jm-referral-system'),
                'colour'   => '#3F7D3A',
                'question' => __('Scheduled assessments and outcome reviews.', 'jm-referral-system'),
                'slugs'    => [
                    PipelineStage::ASSESSMENT_SCHEDULED,
                    PipelineStage::ASSESSMENT_REVIEW_REQUIRED,
                ],
            ],
            self::PACKAGE_COSTING => [
                'key'      => self::PACKAGE_COSTING,
                'order'    => 4,
                'name'     => __('Package costing', 'jm-referral-system'),
                'colour'   => '#A67C0D',
                'question' => __('Packages being prepared or sent.', 'jm-referral-system'),
                'slugs'    => [PipelineStage::PACKAGE_COST_REQUIRED],
            ],
            self::AUTHORITY_CONSIDERATION => [
                'key'      => self::AUTHORITY_CONSIDERATION,
                'order'    => 5,
                'name'     => __('Authority consideration', 'jm-referral-system'),
                'colour'   => '#C0532C',
                'question' => __('Packages awaiting a Local Authority decision.', 'jm-referral-system'),
                'slugs'    => [PipelineStage::AWAITING_LA_DECISION],
            ],
            self::PLACEMENT_TRANSITION => [
                'key'      => self::PLACEMENT_TRANSITION,
                'order'    => 6,
                'name'     => __('Placement transition', 'jm-referral-system'),
                'colour'   => '#7B3E96',
                'question' => __('Approved packages in transition planning (care not yet commenced).', 'jm-referral-system'),
                'slugs'    => [PipelineStage::TRANSITION_PLANNING],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * @return list<string>
     */
    public static function all_active_slugs(): array
    {
        $slugs = [];
        foreach (self::definitions() as $def) {
            foreach ($def['slugs'] as $slug) {
                $slugs[] = $slug;
            }
        }

        return array_values(array_unique($slugs));
    }

    public static function visual_key_for_slug(string $canonical_slug): ?string
    {
        foreach (self::definitions() as $key => $def) {
            if (in_array($canonical_slug, $def['slugs'], true)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function slugs_for_visual(string $visual_key): array
    {
        $defs = self::definitions();

        return isset($defs[$visual_key]) ? $defs[$visual_key]['slugs'] : [];
    }
}
