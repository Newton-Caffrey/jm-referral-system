<?php

namespace JMReferral\Pipeline;

/**
 * Human-readable waiting duration helpers (site-local, derived — never persisted).
 */
class PipelineWaitingTime
{
    /**
     * Format a waiting duration from an entered-at MySQL datetime.
     *
     * @return array{label: string, seconds: int|null, known: bool}
     */
    public static function from_entered_at(?string $entered_at, ?int $now_ts = null): array
    {
        $entered_at = null === $entered_at ? '' : trim($entered_at);
        if ('' === $entered_at || '0000-00-00 00:00:00' === $entered_at) {
            return [
                'label'   => __('Unknown / Legacy', 'jm-referral-system'),
                'seconds' => null,
                'known'   => false,
            ];
        }

        $entered_ts = strtotime($entered_at);
        if (false === $entered_ts) {
            return [
                'label'   => __('Unknown / Legacy', 'jm-referral-system'),
                'seconds' => null,
                'known'   => false,
            ];
        }

        $now_ts = $now_ts ?? (int) current_time('timestamp');
        $seconds = max(0, $now_ts - $entered_ts);

        return [
            'label'   => self::format_seconds($seconds),
            'seconds' => $seconds,
            'known'   => true,
        ];
    }

    public static function format_seconds(int $seconds): string
    {
        $seconds = max(0, $seconds);

        if ($seconds < 3600) {
            $mins = (int) floor($seconds / 60);

            return sprintf(
                /* translators: %d: minutes */
                _n('%dm', '%dm', max(1, $mins), 'jm-referral-system'),
                max(1, $mins)
            );
        }

        if ($seconds < DAY_IN_SECONDS) {
            $hours = (int) floor($seconds / HOUR_IN_SECONDS);
            $mins  = (int) floor(($seconds % HOUR_IN_SECONDS) / 60);
            if ($mins > 0) {
                return sprintf(
                    /* translators: 1: hours, 2: minutes */
                    __('%1$dh %2$dm', 'jm-referral-system'),
                    $hours,
                    $mins
                );
            }

            return sprintf(
                /* translators: %d: hours */
                _n('%dh', '%dh', $hours, 'jm-referral-system'),
                $hours
            );
        }

        $days  = (int) floor($seconds / DAY_IN_SECONDS);
        $hours = (int) floor(($seconds % DAY_IN_SECONDS) / HOUR_IN_SECONDS);
        if ($hours > 0) {
            return sprintf(
                /* translators: 1: days, 2: hours */
                __('%1$dd %2$dh', 'jm-referral-system'),
                $days,
                $hours
            );
        }

        return sprintf(
            /* translators: %d: days */
            _n('%dd', '%dd', $days, 'jm-referral-system'),
            $days
        );
    }

    public static function format_hours(int $hours): string
    {
        $hours = max(0, $hours);
        if ($hours < 24) {
            return sprintf(
                /* translators: %d: hours */
                _n('%d hour', '%d hours', $hours, 'jm-referral-system'),
                $hours
            );
        }

        $days = (int) floor($hours / 24);
        $rem  = $hours % 24;
        if ($rem > 0) {
            return sprintf(
                /* translators: 1: days, 2: hours */
                __('%1$d days %2$d hours', 'jm-referral-system'),
                $days,
                $rem
            );
        }

        return sprintf(
            /* translators: %d: days */
            _n('%d day', '%d days', $days, 'jm-referral-system'),
            $days
        );
    }
}
