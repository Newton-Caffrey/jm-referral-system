<?php

namespace JMReferral\Pipeline;

/**
 * Canonical acquisition pipeline stages (product-owned).
 *
 * Distinct from broad referral status (new/in_progress/completed/cancelled)
 * and from legacy/custom jmrs_workflow_stages rows.
 */
class PipelineStage
{
    public const INTEREST_REQUIRED = 'interest_required';
    public const ASSESSMENT_TO_SCHEDULE = 'assessment_to_schedule';
    public const ASSESSMENT_SCHEDULED = 'assessment_scheduled';
    public const ASSESSMENT_REVIEW_REQUIRED = 'assessment_review_required';
    public const PACKAGE_COST_REQUIRED = 'package_cost_required';
    public const AWAITING_LA_DECISION = 'awaiting_la_decision';
    public const TRANSITION_PLANNING = 'transition_planning';
    public const CARE_COMMENCED = 'care_commenced';
    public const DECLINED = 'declined';
    public const NOT_PROCEEDING = 'not_proceeding';

    public const KIND_ACTIVE = 'active';
    public const KIND_TERMINAL_SUCCESS = 'terminal_success';
    public const KIND_TERMINAL_CLOSED = 'terminal_closed';

    public const CHANGE_CREATED = 'created';
    public const CHANGE_TRANSITION = 'transition';
    public const CHANGE_OVERRIDE = 'override';

    public const FILTER_LEGACY = 'legacy';

    /**
     * Canonical definitions keyed by slug.
     *
     * @return array<string, array{
     *     label: string,
     *     order: int,
     *     kind: string,
     *     next_action: string
     * }>
     */
    public static function definitions(): array
    {
        return [
            self::INTEREST_REQUIRED => [
                'label'       => __('Interest Response Required', 'jm-referral-system'),
                'order'       => 10,
                'kind'        => self::KIND_ACTIVE,
                'next_action' => __('Express interest / decide whether to proceed', 'jm-referral-system'),
            ],
            self::ASSESSMENT_TO_SCHEDULE => [
                'label'       => __('Assessment to Schedule', 'jm-referral-system'),
                'order'       => 20,
                'kind'        => self::KIND_ACTIVE,
                'next_action' => __('Schedule assessment', 'jm-referral-system'),
            ],
            self::ASSESSMENT_SCHEDULED => [
                'label'       => __('Assessment Scheduled', 'jm-referral-system'),
                'order'       => 30,
                'kind'        => self::KIND_ACTIVE,
                'next_action' => __('Complete assessment', 'jm-referral-system'),
            ],
            self::ASSESSMENT_REVIEW_REQUIRED => [
                'label'       => __('Assessment Outcome Review', 'jm-referral-system'),
                'order'       => 35,
                'kind'        => self::KIND_ACTIVE,
                'next_action' => __('Review assessment outcome and decide whether to proceed', 'jm-referral-system'),
            ],
            self::PACKAGE_COST_REQUIRED => [
                'label'       => __('Package Cost to Prepare', 'jm-referral-system'),
                'order'       => 40,
                'kind'        => self::KIND_ACTIVE,
                'next_action' => __('Prepare and send package cost', 'jm-referral-system'),
            ],
            self::AWAITING_LA_DECISION => [
                'label'       => __('Awaiting Local Authority Decision', 'jm-referral-system'),
                'order'       => 50,
                'kind'        => self::KIND_ACTIVE,
                'next_action' => __('Await/follow up Local Authority decision', 'jm-referral-system'),
            ],
            self::TRANSITION_PLANNING => [
                'label'       => __('Transition Planning', 'jm-referral-system'),
                'order'       => 60,
                'kind'        => self::KIND_ACTIVE,
                'next_action' => __('Plan transition and commence care', 'jm-referral-system'),
            ],
            self::CARE_COMMENCED => [
                'label'       => __('Placement / Care Commenced', 'jm-referral-system'),
                'order'       => 70,
                'kind'        => self::KIND_TERMINAL_SUCCESS,
                'next_action' => __('No pipeline action — care has commenced', 'jm-referral-system'),
            ],
            self::DECLINED => [
                'label'       => __('Declined', 'jm-referral-system'),
                'order'       => 80,
                'kind'        => self::KIND_TERMINAL_CLOSED,
                'next_action' => __('No pipeline action — referral declined', 'jm-referral-system'),
            ],
            self::NOT_PROCEEDING => [
                'label'       => __('Not Proceeding', 'jm-referral-system'),
                'order'       => 90,
                'kind'        => self::KIND_TERMINAL_CLOSED,
                'next_action' => __('No pipeline action — not proceeding', 'jm-referral-system'),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function slugs(): array
    {
        return array_keys(self::definitions());
    }

    public static function is_canonical(string $slug): bool
    {
        return isset(self::definitions()[$slug]);
    }

    public static function label(string $slug): string
    {
        $definitions = self::definitions();

        return $definitions[$slug]['label'] ?? $slug;
    }

    public static function order(string $slug): int
    {
        $definitions = self::definitions();

        return (int) ($definitions[$slug]['order'] ?? 0);
    }

    public static function kind(string $slug): ?string
    {
        $definitions = self::definitions();

        return isset($definitions[$slug]) ? (string) $definitions[$slug]['kind'] : null;
    }

    public static function next_action(string $slug): string
    {
        $definitions = self::definitions();

        return isset($definitions[$slug])
            ? (string) $definitions[$slug]['next_action']
            : '';
    }

    public static function is_active(string $slug): bool
    {
        return self::KIND_ACTIVE === self::kind($slug);
    }

    public static function is_terminal(string $slug): bool
    {
        $kind = self::kind($slug);

        return self::KIND_TERMINAL_SUCCESS === $kind || self::KIND_TERMINAL_CLOSED === $kind;
    }

    public static function is_terminal_success(string $slug): bool
    {
        return self::KIND_TERMINAL_SUCCESS === self::kind($slug);
    }

    public static function is_terminal_closed(string $slug): bool
    {
        return self::KIND_TERMINAL_CLOSED === self::kind($slug);
    }

    /**
     * Normal (non-override) allowed transitions: from_slug => list of to_slugs.
     *
     * @return array<string, array<int, string>>
     */
    public static function transitions(): array
    {
        return [
            self::INTEREST_REQUIRED => [
                self::ASSESSMENT_TO_SCHEDULE,
                self::NOT_PROCEEDING,
            ],
            self::ASSESSMENT_TO_SCHEDULE => [
                self::ASSESSMENT_SCHEDULED,
                self::NOT_PROCEEDING,
            ],
            self::ASSESSMENT_SCHEDULED => [
                self::PACKAGE_COST_REQUIRED,
                self::ASSESSMENT_REVIEW_REQUIRED,
                self::ASSESSMENT_TO_SCHEDULE,
                self::NOT_PROCEEDING,
            ],
            self::ASSESSMENT_REVIEW_REQUIRED => [
                self::PACKAGE_COST_REQUIRED,
                self::NOT_PROCEEDING,
            ],
            self::PACKAGE_COST_REQUIRED => [
                self::AWAITING_LA_DECISION,
                self::NOT_PROCEEDING,
            ],
            self::AWAITING_LA_DECISION => [
                self::TRANSITION_PLANNING,
                self::DECLINED,
                self::NOT_PROCEEDING,
            ],
            self::TRANSITION_PLANNING => [
                self::CARE_COMMENCED,
                self::NOT_PROCEEDING,
            ],
            self::CARE_COMMENCED => [],
            self::DECLINED => [],
            self::NOT_PROCEEDING => [],
        ];
    }

    public static function can_transition(string $from_slug, string $to_slug): bool
    {
        if (! self::is_canonical($from_slug) || ! self::is_canonical($to_slug)) {
            return false;
        }

        $allowed = self::transitions()[$from_slug] ?? [];

        return in_array($to_slug, $allowed, true);
    }

    /**
     * Default stage for newly created referrals.
     */
    public static function default_slug(): string
    {
        return self::INTEREST_REQUIRED;
    }

    /**
     * Filter dropdown options (canonical + legacy sentinel).
     *
     * @return array<string, string>
     */
    public static function filter_options(): array
    {
        $options = [
            self::FILTER_LEGACY => __('Legacy / non-pipeline stage', 'jm-referral-system'),
        ];

        foreach (self::definitions() as $slug => $definition) {
            $options[$slug] = (string) $definition['label'];
        }

        return $options;
    }

    /**
     * Seed rows for migration: slug, English name, order (stored untranslated).
     *
     * @return array<int, array{slug: string, name: string, order: int}>
     */
    public static function seed_rows(): array
    {
        return [
            ['slug' => self::INTEREST_REQUIRED, 'name' => 'Interest Response Required', 'order' => 10],
            ['slug' => self::ASSESSMENT_TO_SCHEDULE, 'name' => 'Assessment to Schedule', 'order' => 20],
            ['slug' => self::ASSESSMENT_SCHEDULED, 'name' => 'Assessment Scheduled', 'order' => 30],
            ['slug' => self::ASSESSMENT_REVIEW_REQUIRED, 'name' => 'Assessment Outcome Review', 'order' => 35],
            ['slug' => self::PACKAGE_COST_REQUIRED, 'name' => 'Package Cost to Prepare', 'order' => 40],
            ['slug' => self::AWAITING_LA_DECISION, 'name' => 'Awaiting Local Authority Decision', 'order' => 50],
            ['slug' => self::TRANSITION_PLANNING, 'name' => 'Transition Planning', 'order' => 60],
            ['slug' => self::CARE_COMMENCED, 'name' => 'Placement / Care Commenced', 'order' => 70],
            ['slug' => self::DECLINED, 'name' => 'Declined', 'order' => 80],
            ['slug' => self::NOT_PROCEEDING, 'name' => 'Not Proceeding', 'order' => 90],
        ];
    }
}
