<?php

namespace JMReferral\Referral;

use JMReferral\Permissions\AccessPolicy;
use JMReferral\Permissions\Capabilities;

class ReferralNoteController
{
    private const FORM_TRANSIENT_PREFIX = 'jmrs_referral_note_form_';

    public function __construct(
        private ReferralNoteService $note_service,
        private ReferralRepository $referral_repository,
        private AccessPolicy $access_policy
    ) {
    }

    /**
     * Registers note-related hooks.
     */
    public function register(): void
    {
        add_action('admin_init', [$this, 'handle_create']);
        add_action('admin_notices', [$this, 'render_notices']);
    }

    /**
     * Handles Add Note form submissions from the referral details page.
     */
    public function handle_create(): void
    {
        if (! isset($_POST['jmrs_submit_note'])) {
            return;
        }

        if (! Capabilities::current_user_can(Capabilities::ADD_NOTES)) {
            wp_die(esc_html__('You do not have permission to add notes.', 'jm-referral-system'));
        }

        $referral_id = isset($_POST['jmrs_referral_id']) ? absint($_POST['jmrs_referral_id']) : 0;

        check_admin_referer('jmrs_add_note_' . $referral_id, 'jmrs_add_note_nonce');

        $referral = $this->referral_repository->find($referral_id);

        if (null === $referral || ! $this->access_policy->can_view_referral($referral)) {
            wp_die(esc_html__('You do not have permission to add notes to this referral.', 'jm-referral-system'));
        }

        if ($this->access_policy->is_referral_archived($referral)) {
            wp_die(esc_html__('Archived referrals are read-only.', 'jm-referral-system'));
        }

        $note = isset($_POST['jmrs_note'])
            ? sanitize_textarea_field(wp_unslash($_POST['jmrs_note']))
            : '';

        $result = $this->note_service->add_note($referral_id, $note);

        if (false === $result) {
            $this->store_form_state(
                $referral_id,
                $note,
                [
                    'general' => __('Unable to save the note. Please try again.', 'jm-referral-system'),
                ]
            );

            $this->redirect_to_view($referral_id);
        }

        if (isset($result['errors']) && is_array($result['errors'])) {
            $this->store_form_state($referral_id, $note, $result['errors']);
            $this->redirect_to_view($referral_id);
        }

        $this->redirect_to_view($referral_id, true);
    }

    /**
     * Renders note success/error notices on the view screen.
     */
    public function render_notices(): void
    {
        if (! $this->is_view_screen()) {
            return;
        }

        if (isset($_GET['jmrs_note_added']) && '1' === $_GET['jmrs_note_added']) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__('Internal note added successfully.', 'jm-referral-system');
            echo '</p></div>';
        }

        $referral_id = isset($_GET['referral_id']) ? absint($_GET['referral_id']) : 0;
        $state       = self::get_form_state($referral_id, false);
        $errors      = $state['errors'];

        if (empty($errors)) {
            return;
        }

        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Please fix the following errors:', 'jm-referral-system');
        echo '</p><ul>';

        foreach ($errors as $message) {
            echo '<li>' . esc_html($message) . '</li>';
        }

        echo '</ul></div>';
    }

    /**
     * Returns sticky note form data/errors for the current user.
     *
     * @param bool $consume Whether to delete the transient after reading.
     * @return array{note: string, errors: array<string, string>}
     */
    public static function get_form_state(int $referral_id, bool $consume = true): array
    {
        $key   = self::FORM_TRANSIENT_PREFIX . get_current_user_id() . '_' . $referral_id;
        $state = get_transient($key);

        if (! is_array($state)) {
            return [
                'note'   => '',
                'errors' => [],
            ];
        }

        if ($consume) {
            delete_transient($key);
        }

        return [
            'note'   => isset($state['note']) ? (string) $state['note'] : '',
            'errors' => is_array($state['errors'] ?? null) ? $state['errors'] : [],
        ];
    }

    /**
     * @param array<string, string> $errors
     */
    private function store_form_state(int $referral_id, string $note, array $errors): void
    {
        set_transient(
            self::FORM_TRANSIENT_PREFIX . get_current_user_id() . '_' . $referral_id,
            [
                'note'   => $note,
                'errors' => $errors,
            ],
            MINUTE_IN_SECONDS * 5
        );
    }

    private function redirect_to_view(int $referral_id, bool $success = false): void
    {
        $args = [
            'page'        => 'jm-referrals-view',
            'referral_id' => $referral_id,
        ];

        if ($success) {
            $args['jmrs_note_added'] = '1';
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    private function is_view_screen(): bool
    {
        if (! is_admin()) {
            return false;
        }

        $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        return 'jm-referrals-view' === $page;
    }
}
