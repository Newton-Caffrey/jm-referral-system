<?php

namespace JMReferral\Reports;

use JMReferral\Pipeline\PipelineWaitingTime;

/**
 * Average / median duration helpers for acquisition reporting.
 */
class AcquisitionTimingStats
{
    /**
     * @param array<int, int|float> $seconds_list Positive durations in seconds.
     * @return array{
     *     count: int,
     *     average_seconds: int|null,
     *     median_seconds: int|null,
     *     average_label: string,
     *     median_label: string,
     *     available: bool
     * }
     */
    public static function summarize(array $seconds_list): array
    {
        $clean = [];
        foreach ($seconds_list as $value) {
            if (! is_numeric($value)) {
                continue;
            }
            $seconds = (int) round((float) $value);
            if ($seconds < 0) {
                continue;
            }
            $clean[] = $seconds;
        }

        $count = count($clean);
        if (0 === $count) {
            return [
                'count'           => 0,
                'average_seconds' => null,
                'median_seconds'  => null,
                'average_label'   => __('Not Available', 'jm-referral-system'),
                'median_label'    => __('Not Available', 'jm-referral-system'),
                'available'       => false,
            ];
        }

        sort($clean, SORT_NUMERIC);
        $sum     = array_sum($clean);
        $average = (int) round($sum / $count);
        $median  = self::median($clean);

        return [
            'count'           => $count,
            'average_seconds' => $average,
            'median_seconds'  => $median,
            'average_label'   => PipelineWaitingTime::format_seconds($average),
            'median_label'    => PipelineWaitingTime::format_seconds($median),
            'available'       => true,
        ];
    }

    /**
     * @param array<int, int> $sorted Non-empty sorted list.
     */
    private static function median(array $sorted): int
    {
        $n = count($sorted);
        $mid = (int) floor(($n - 1) / 2);

        if (0 === $n % 2) {
            return (int) round(($sorted[$mid] + $sorted[$mid + 1]) / 2);
        }

        return (int) $sorted[$mid];
    }
}
