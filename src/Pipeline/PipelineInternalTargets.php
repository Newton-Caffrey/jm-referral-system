<?php

namespace JMReferral\Pipeline;

/**
 * Optional internal waiting targets for active acquisition stages (hours).
 *
 * Blank / zero = no target. Never hard-code non-zero defaults.
 * These are operational targets, not contractual SLAs.
 */
class PipelineInternalTargets
{
    public const OPTION_KEY = 'jmrs_pipeline_internal_targets';

    public const MAX_HOURS = 8760;

    /**
     * Active stages that may have an internal target.
     *
     * @return array<int, string>
     */
    public static function configurable_stages(): array
    {
        return [
            PipelineStage::INTEREST_REQUIRED,
            PipelineStage::ASSESSMENT_TO_SCHEDULE,
            PipelineStage::ASSESSMENT_SCHEDULED,
            PipelineStage::ASSESSMENT_REVIEW_REQUIRED,
            PipelineStage::PACKAGE_COST_REQUIRED,
            PipelineStage::AWAITING_LA_DECISION,
            PipelineStage::TRANSITION_PLANNING,
        ];
    }

    /**
     * @return array<string, int|null> slug => hours or null when disabled
     */
    public static function defaults(): array
    {
        $defaults = [];
        foreach (self::configurable_stages() as $slug) {
            $defaults[$slug] = null;
        }

        return $defaults;
    }

    /**
     * @return array<string, int|null>
     */
    public static function all(): array
    {
        $stored = get_option(self::OPTION_KEY, []);
        if (! is_array($stored)) {
            $stored = [];
        }

        return self::sanitize($stored);
    }

    /**
     * Hours for a stage, or null when no target configured.
     */
    public static function hours_for(string $slug): ?int
    {
        $all = self::all();
        if (! array_key_exists($slug, $all)) {
            return null;
        }

        $hours = $all[$slug];

        return null === $hours || $hours <= 0 ? null : $hours;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool}
     */
    public static function update(array $input): array
    {
        $sanitized = self::sanitize($input);
        update_option(self::OPTION_KEY, $sanitized, false);

        return ['ok' => true];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, int|null>
     */
    public static function sanitize(array $input): array
    {
        $out = self::defaults();

        foreach (self::configurable_stages() as $slug) {
            if (! array_key_exists($slug, $input)) {
                continue;
            }

            $raw = $input[$slug];
            if (null === $raw || '' === $raw || false === $raw) {
                $out[$slug] = null;
                continue;
            }

            if (is_string($raw)) {
                $raw = trim($raw);
                if ('' === $raw) {
                    $out[$slug] = null;
                    continue;
                }
            }

            if (! is_numeric($raw)) {
                $out[$slug] = null;
                continue;
            }

            $hours = (int) $raw;
            if ($hours <= 0) {
                $out[$slug] = null;
                continue;
            }

            $out[$slug] = min(self::MAX_HOURS, $hours);
        }

        return $out;
    }
}
