<?php

namespace JMReferral\Database;

/**
 * Fail-closed check that conversion write tables can roll back (Phase 5D.5).
 */
class TransactionEngineGuard
{
    /**
     * @return array<int, string>
     */
    public function critical_tables(): array
    {
        return [
            Tables::referrals_table(),
            Tables::referral_activity_table(),
            Tables::referral_stage_history_table(),
            Tables::referral_inbox_table(),
        ];
    }

    public function critical_tables_are_transactional(): bool
    {
        foreach ($this->critical_tables() as $table) {
            if ('InnoDB' !== $this->engine($table)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, string> Unprefixed logical name => live engine, or empty when unknown.
     */
    public function critical_engines(): array
    {
        return [
            'referrals'              => $this->engine(Tables::referrals_table()),
            'referral_activity'      => $this->engine(Tables::referral_activity_table()),
            'referral_stage_history' => $this->engine(Tables::referral_stage_history_table()),
            'referral_inbox'         => $this->engine(Tables::referral_inbox_table()),
        ];
    }

    private function engine(string $table): string
    {
        global $wpdb;

        $engine = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s',
                $table
            )
        );

        return is_string($engine) ? $engine : '';
    }
}
