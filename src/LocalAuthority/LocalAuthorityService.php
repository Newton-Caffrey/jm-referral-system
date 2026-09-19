<?php

namespace JMReferral\LocalAuthority;

class LocalAuthorityService
{
    private const ALLOWED_STATUSES = ['active', 'inactive'];

    private const MAX_NAME_LENGTH    = 255;
    private const MAX_CONTACT_LENGTH = 255;
    private const MAX_PHONE_LENGTH   = 50;
    private const MAX_WEBSITE_LENGTH = 255;
    private const MAX_NOTES_LENGTH   = 5000;

    public function __construct(
        private LocalAuthorityRepository $authority_repository,
        private SenderRuleRepository $rule_repository,
        private LocalAuthoritySenderMatcher $matcher
    ) {
    }

    /**
     * @param array{search?: string, status?: string} $args
     * @return array<int, array<string, mixed>>
     */
    public function list(array $args = []): array
    {
        $authorities = $this->authority_repository->list($args);

        if (empty($authorities)) {
            return [];
        }

        $ids    = array_map(static fn (array $row): int => (int) ($row['id'] ?? 0), $authorities);
        $counts = $this->rule_repository->count_by_authority_ids($ids);

        foreach ($authorities as &$row) {
            $id                 = (int) ($row['id'] ?? 0);
            $row['rule_count']  = $counts[$id] ?? 0;
        }
        unset($row);

        return $authorities;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        return $this->authority_repository->findById($id);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list_rules(int $local_authority_id): array
    {
        return $this->rule_repository->listForAuthority($local_authority_id);
    }

    /**
     * Future intake entry point — provider-neutral.
     *
     * @return array{
     *   status: string,
     *   authority: array<string, mixed>|null,
     *   matched_rule: array<string, mixed>|null,
     *   match_type: string|null,
     *   candidates: array<int, array{authority_id: int, authority_name?: string, rule: array<string, mixed>}>
     * }
     */
    public function matchSender(string $sender_email): array
    {
        return $this->matcher->matchSender($sender_email);
    }

    /**
     * @param array<string, string> $input
     * @return array{id: int, warnings?: array<int, string>}|array{errors: array<string, string>}|false
     */
    public function create(array $input): array|false
    {
        $errors = $this->validate_authority($input);

        if (! empty($errors)) {
            return ['errors' => $errors];
        }

        $now  = current_time('mysql');
        $name = trim((string) $input['name']);
        $slug = $this->unique_slug($name);

        $id = $this->authority_repository->create(
            [
                'name'          => $name,
                'slug'          => $slug,
                'status'        => $input['status'],
                'contact_name'  => $this->nullable_text($input['contact_name'] ?? ''),
                'contact_email' => $this->nullable_text($input['contact_email'] ?? ''),
                'contact_phone' => $this->nullable_text($input['contact_phone'] ?? ''),
                'website'       => $this->nullable_text($input['website'] ?? ''),
                'notes'         => $this->nullable_text($input['notes'] ?? ''),
                'created_at'    => $now,
                'updated_at'    => $now,
            ]
        );

        if (false === $id) {
            return false;
        }

        return ['id' => $id];
    }

    /**
     * @param array<string, string> $input
     * @return array{ok: true}|array{errors: array<string, string>}|false
     */
    public function update(int $id, array $input): array|false
    {
        $existing = $this->authority_repository->findById($id);

        if (null === $existing) {
            return false;
        }

        $errors = $this->validate_authority($input);

        if (! empty($errors)) {
            return ['errors' => $errors];
        }

        $name = trim((string) $input['name']);
        $slug = (string) ($existing['slug'] ?? '');

        if ($name !== (string) ($existing['name'] ?? '')) {
            $slug = $this->unique_slug($name, $id);
        }

        $updated = $this->authority_repository->update(
            $id,
            [
                'name'          => $name,
                'slug'          => $slug,
                'status'        => $input['status'],
                'contact_name'  => $this->nullable_text($input['contact_name'] ?? ''),
                'contact_email' => $this->nullable_text($input['contact_email'] ?? ''),
                'contact_phone' => $this->nullable_text($input['contact_phone'] ?? ''),
                'website'       => $this->nullable_text($input['website'] ?? ''),
                'notes'         => $this->nullable_text($input['notes'] ?? ''),
                'updated_at'    => current_time('mysql'),
            ]
        );

        if (! $updated) {
            return false;
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: true}|array{errors: array<string, string>}|false
     */
    public function activate(int $id): array|false
    {
        return $this->set_authority_status($id, 'active');
    }

    /**
     * @return array{ok: true}|array{errors: array<string, string>}|false
     */
    public function deactivate(int $id): array|false
    {
        return $this->set_authority_status($id, 'inactive');
    }

    /**
     * @param array<string, string> $input
     * @return array{id: int, warnings: array<int, string>}|array{errors: array<string, string>}|false
     */
    public function add_sender_rule(int $local_authority_id, array $input): array|false
    {
        $authority = $this->authority_repository->findById($local_authority_id);

        if (null === $authority) {
            return [
                'errors' => [
                    'general' => __('Local Authority not found.', 'jm-referral-system'),
                ],
            ];
        }

        $rule_type = (string) ($input['rule_type'] ?? '');
        if (! in_array($rule_type, [SenderRuleRepository::TYPE_EXACT_EMAIL, SenderRuleRepository::TYPE_DOMAIN], true)) {
            return [
                'errors' => [
                    'rule_type' => __('Please select a valid rule type.', 'jm-referral-system'),
                ],
            ];
        }

        $normalised = $this->normalise_rule_value($rule_type, (string) ($input['rule_value'] ?? ''));
        if (is_array($normalised) && isset($normalised['error'])) {
            return [
                'errors' => [
                    'rule_value' => $normalised['error'],
                ],
            ];
        }

        $rule_value = (string) $normalised;

        $duplicate = $this->rule_repository->find_duplicate($local_authority_id, $rule_type, $rule_value);
        if (null !== $duplicate) {
            return [
                'errors' => [
                    'rule_value' => __('This rule already exists for this organisation.', 'jm-referral-system'),
                ],
            ];
        }

        $status = (string) ($input['status'] ?? 'active');
        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            $status = 'active';
        }

        $now = current_time('mysql');
        $id  = $this->rule_repository->create(
            [
                'local_authority_id' => $local_authority_id,
                'rule_type'          => $rule_type,
                'rule_value'         => $rule_value,
                'status'             => $status,
                'created_at'         => $now,
                'updated_at'         => $now,
            ]
        );

        if (false === $id) {
            return false;
        }

        $warnings = [];
        if ('active' === $status) {
            $warnings = $this->overlap_warnings($local_authority_id, $rule_type, $rule_value);
        }

        return [
            'id'       => $id,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array{ok: true}|array{errors: array<string, string>}|false
     */
    public function activate_rule(int $rule_id, int $expected_authority_id): array|false
    {
        return $this->set_rule_status($rule_id, $expected_authority_id, 'active');
    }

    /**
     * @return array{ok: true}|array{errors: array<string, string>}|false
     */
    public function deactivate_rule(int $rule_id, int $expected_authority_id): array|false
    {
        return $this->set_rule_status($rule_id, $expected_authority_id, 'inactive');
    }

    /**
     * Hard delete of a single rule (capability-gated at controller; not default UI).
     *
     * @return array{ok: true}|array{errors: array<string, string>}|false
     */
    public function delete_rule(int $rule_id, int $expected_authority_id): array|false
    {
        $rule = $this->rule_repository->find($rule_id);

        if (null === $rule) {
            return false;
        }

        if ((int) ($rule['local_authority_id'] ?? 0) !== $expected_authority_id) {
            return [
                'errors' => [
                    'general' => __('Sender rule does not belong to this organisation.', 'jm-referral-system'),
                ],
            ];
        }

        if (! $this->rule_repository->delete($rule_id)) {
            return false;
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: true}|array{errors: array<string, string>}|false
     */
    private function set_authority_status(int $id, string $status): array|false
    {
        $existing = $this->authority_repository->findById($id);

        if (null === $existing) {
            return false;
        }

        if (! $this->authority_repository->set_status($id, $status, current_time('mysql'))) {
            return false;
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok: true}|array{errors: array<string, string>}|false
     */
    private function set_rule_status(int $rule_id, int $expected_authority_id, string $status): array|false
    {
        $rule = $this->rule_repository->find($rule_id);

        if (null === $rule) {
            return false;
        }

        if ((int) ($rule['local_authority_id'] ?? 0) !== $expected_authority_id) {
            return [
                'errors' => [
                    'general' => __('Sender rule does not belong to this organisation.', 'jm-referral-system'),
                ],
            ];
        }

        if (! $this->rule_repository->set_status($rule_id, $status, current_time('mysql'))) {
            return false;
        }

        return ['ok' => true];
    }

    /**
     * @param array<string, string> $input
     * @return array<string, string>
     */
    private function validate_authority(array $input): array
    {
        $errors = [];

        $name = trim((string) ($input['name'] ?? ''));
        if ('' === $name) {
            $errors['name'] = __('Name is required.', 'jm-referral-system');
        } elseif (strlen($name) > self::MAX_NAME_LENGTH) {
            $errors['name'] = __('Name is too long.', 'jm-referral-system');
        } elseif ($name !== wp_strip_all_tags($name)) {
            $errors['name'] = __('Name must be plain text without markup.', 'jm-referral-system');
        }

        $status = (string) ($input['status'] ?? '');
        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            $errors['status'] = __('Please select a valid status.', 'jm-referral-system');
        }

        $contact_name = trim((string) ($input['contact_name'] ?? ''));
        if ('' !== $contact_name) {
            if (strlen($contact_name) > self::MAX_CONTACT_LENGTH) {
                $errors['contact_name'] = __('Contact name is too long.', 'jm-referral-system');
            } elseif ($contact_name !== wp_strip_all_tags($contact_name)) {
                $errors['contact_name'] = __('Contact name must be plain text.', 'jm-referral-system');
            }
        }

        $contact_email = trim((string) ($input['contact_email'] ?? ''));
        if ('' !== $contact_email && ! is_email($contact_email)) {
            $errors['contact_email'] = __('Please enter a valid contact email.', 'jm-referral-system');
        }

        $contact_phone = trim((string) ($input['contact_phone'] ?? ''));
        if ('' !== $contact_phone) {
            if (strlen($contact_phone) > self::MAX_PHONE_LENGTH) {
                $errors['contact_phone'] = __('Contact phone is too long.', 'jm-referral-system');
            } elseif ($contact_phone !== wp_strip_all_tags($contact_phone)) {
                $errors['contact_phone'] = __('Contact phone must be plain text.', 'jm-referral-system');
            }
        }

        $website = trim((string) ($input['website'] ?? ''));
        if ('' !== $website) {
            $website_error = $this->validate_website($website);
            if (null !== $website_error) {
                $errors['website'] = $website_error;
            }
        }

        $notes = (string) ($input['notes'] ?? '');
        if ('' !== $notes) {
            if (strlen($notes) > self::MAX_NOTES_LENGTH) {
                $errors['notes'] = __('Notes are too long.', 'jm-referral-system');
            } elseif ($notes !== wp_strip_all_tags($notes)) {
                $errors['notes'] = __('Notes must be plain text without HTML.', 'jm-referral-system');
            }
        }

        return $errors;
    }

    private function validate_website(string $website): ?string
    {
        if (strlen($website) > self::MAX_WEBSITE_LENGTH) {
            return __('Website URL is too long.', 'jm-referral-system');
        }

        $lower = strtolower($website);
        if (str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'data:')) {
            return __('Website must use http or https only.', 'jm-referral-system');
        }

        if (! preg_match('#^https?://#i', $website)) {
            return __('Website must start with http:// or https://.', 'jm-referral-system');
        }

        if (false === filter_var($website, FILTER_VALIDATE_URL)) {
            return __('Please enter a valid website URL.', 'jm-referral-system');
        }

        return null;
    }

    /**
     * @return string|array{error: string}
     */
    public function normalise_rule_value(string $rule_type, string $raw): string|array
    {
        $raw = trim($raw);

        if (SenderRuleRepository::TYPE_EXACT_EMAIL === $rule_type) {
            $email = strtolower($raw);
            if ('' === $email || ! is_email($email)) {
                return [
                    'error' => __('Please enter a valid sender email address.', 'jm-referral-system'),
                ];
            }

            return $email;
        }

        if (SenderRuleRepository::TYPE_DOMAIN === $rule_type) {
            return $this->normalise_domain($raw);
        }

        return [
            'error' => __('Unknown rule type.', 'jm-referral-system'),
        ];
    }

    /**
     * @return string|array{error: string}
     */
    private function normalise_domain(string $raw): string|array
    {
        $value = strtolower(trim($raw));

        if ('' === $value) {
            return [
                'error' => __('Please enter a domain.', 'jm-referral-system'),
            ];
        }

        if (str_contains($value, '@') || str_contains($value, '*') || str_contains($value, ' ')) {
            return [
                'error' => __('Domain must be a hostname only (no email, wildcards, or spaces).', 'jm-referral-system'),
            ];
        }

        if (preg_match('#^(javascript|data):#i', $value)) {
            return [
                'error' => __('Domain contains an unsafe scheme.', 'jm-referral-system'),
            ];
        }

        if (preg_match('#^https?://#i', $value)) {
            return [
                'error' => __('Enter the hostname only (for example coventry.gov.uk), not a full URL.', 'jm-referral-system'),
            ];
        }

        if (str_contains($value, '/') || str_contains($value, '?') || str_contains($value, '#')) {
            return [
                'error' => __('Domain must not include a path or query string.', 'jm-referral-system'),
            ];
        }

        // Strip accidental leading dots.
        $value = ltrim($value, '.');

        if ('' === $value || ! preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/i', $value)) {
            return [
                'error' => __('Please enter a valid domain hostname.', 'jm-referral-system'),
            ];
        }

        if (! str_contains($value, '.')) {
            return [
                'error' => __('Please enter a full domain (for example authority.gov.uk).', 'jm-referral-system'),
            ];
        }

        return $value;
    }

    /**
     * @return array<int, string>
     */
    private function overlap_warnings(int $local_authority_id, string $rule_type, string $rule_value): array
    {
        $warnings = [];
        $others   = $this->rule_repository->find_active_by_type_value($rule_type, $rule_value);

        foreach ($others as $row) {
            if ((int) ($row['local_authority_id'] ?? 0) === $local_authority_id) {
                continue;
            }

            $name = (string) ($row['authority_name'] ?? __('another organisation', 'jm-referral-system'));
            $warnings[] = sprintf(
                /* translators: 1: rule type label, 2: rule value, 3: authority name */
                __('Warning: the same %1$s rule “%2$s” is already active for %3$s. Matching this sender may be ambiguous and require human review.', 'jm-referral-system'),
                SenderRuleRepository::TYPE_EXACT_EMAIL === $rule_type
                    ? __('exact email', 'jm-referral-system')
                    : __('domain', 'jm-referral-system'),
                $rule_value,
                $name
            );
        }

        if (SenderRuleRepository::TYPE_DOMAIN === $rule_type) {
            foreach ($this->rule_repository->list_active_domain_rules() as $row) {
                $other_id = (int) ($row['local_authority_id'] ?? 0);
                if ($other_id === $local_authority_id) {
                    continue;
                }

                $other_domain = strtolower(trim((string) ($row['rule_value'] ?? '')));
                if ('' === $other_domain || $other_domain === $rule_value) {
                    continue;
                }

                if (
                    $this->matcher->host_matches_domain($rule_value, $other_domain)
                    || $this->matcher->host_matches_domain($other_domain, $rule_value)
                ) {
                    $name = (string) ($row['authority_name'] ?? __('another organisation', 'jm-referral-system'));
                    $warnings[] = sprintf(
                        /* translators: 1: this domain, 2: other domain, 3: authority name */
                        __('Warning: domain “%1$s” may overlap with “%2$s” configured for %3$s. Some senders could match more than one organisation.', 'jm-referral-system'),
                        $rule_value,
                        $other_domain,
                        $name
                    );
                }
            }
        }

        return array_values(array_unique($warnings));
    }

    private function unique_slug(string $name, ?int $exclude_id = null): string
    {
        $base = sanitize_title($name);

        if ('' === $base) {
            $base = 'local-authority';
        }

        $slug  = $base;
        $index = 2;

        while (true) {
            $existing = $this->authority_repository->findBySlug($slug);

            if (null === $existing) {
                return $slug;
            }

            if (null !== $exclude_id && (int) ($existing['id'] ?? 0) === $exclude_id) {
                return $slug;
            }

            $slug = $base . '-' . $index;
            ++$index;
        }
    }

    private function nullable_text(string $value): ?string
    {
        $value = trim($value);

        return '' === $value ? null : $value;
    }
}
