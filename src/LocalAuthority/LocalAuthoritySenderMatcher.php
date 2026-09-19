<?php

namespace JMReferral\LocalAuthority;

/**
 * Provider-neutral sender recognition against configured Local Authority rules.
 *
 * Recognition means expected/recognised sender configuration — not cryptographic
 * authenticity verification. Email From addresses can be spoofed; future intake
 * should also consider provider authentication signals where available.
 */
class LocalAuthoritySenderMatcher
{
    public const NO_MATCH        = 'NO_MATCH';
    public const MATCH           = 'MATCH';
    public const AMBIGUOUS       = 'AMBIGUOUS';
    public const INVALID_SENDER  = 'INVALID_SENDER';

    public function __construct(
        private LocalAuthorityRepository $authority_repository,
        private SenderRuleRepository $rule_repository
    ) {
    }

    /**
     * Matches a sender email against active authority rules.
     *
     * Precedence: exact_email rules first, then domain rules (including label-boundary subdomains).
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
        $normalised = $this->normalise_sender_email($sender_email);

        if (null === $normalised) {
            return $this->result(self::INVALID_SENDER);
        }

        $exact_hits = $this->rule_repository->find_active_exact_email_matches($normalised);

        if (! empty($exact_hits)) {
            return $this->resolve_hits($exact_hits, SenderRuleRepository::TYPE_EXACT_EMAIL);
        }

        $host = $this->extract_host($normalised);
        if (null === $host) {
            return $this->result(self::NO_MATCH);
        }

        $domain_hits = [];
        foreach ($this->rule_repository->list_active_domain_rules() as $rule) {
            $domain = strtolower(trim((string) ($rule['rule_value'] ?? '')));
            if ('' === $domain) {
                continue;
            }

            if ($this->host_matches_domain($host, $domain)) {
                $domain_hits[] = $rule;
            }
        }

        if (! empty($domain_hits)) {
            return $this->resolve_hits($domain_hits, SenderRuleRepository::TYPE_DOMAIN);
        }

        return $this->result(self::NO_MATCH);
    }

    /**
     * Normalises a sender email for matching (lowercase, trim, valid).
     */
    public function normalise_sender_email(string $email): ?string
    {
        $email = strtolower(trim($email));

        if ('' === $email || false !== strpos($email, ' ')) {
            return null;
        }

        if (! is_email($email)) {
            return null;
        }

        return $email;
    }

    /**
     * Strict label-boundary domain match.
     *
     * Supports: host === domain OR host ends with "." + domain.
     * Rejects suffix spoofing such as coventry.gov.uk.attacker.com.
     */
    public function host_matches_domain(string $host, string $domain): bool
    {
        $host   = strtolower(trim($host));
        $domain = strtolower(trim($domain));

        if ('' === $host || '' === $domain) {
            return false;
        }

        if ($host === $domain) {
            return true;
        }

        $suffix = '.' . $domain;

        return strlen($host) > strlen($suffix)
            && substr($host, -strlen($suffix)) === $suffix;
    }

    /**
     * @param array<int, array<string, mixed>> $hits
     * @return array{
     *   status: string,
     *   authority: array<string, mixed>|null,
     *   matched_rule: array<string, mixed>|null,
     *   match_type: string|null,
     *   candidates: array<int, array{authority_id: int, authority_name?: string, rule: array<string, mixed>}>
     * }
     */
    private function resolve_hits(array $hits, string $match_type): array
    {
        $by_authority = [];

        foreach ($hits as $rule) {
            $authority_id = (int) ($rule['local_authority_id'] ?? 0);
            if ($authority_id <= 0) {
                continue;
            }

            if (! isset($by_authority[$authority_id])) {
                $by_authority[$authority_id] = $rule;
            }
        }

        $authority_ids = array_keys($by_authority);

        if (count($authority_ids) === 0) {
            return $this->result(self::NO_MATCH);
        }

        if (count($authority_ids) > 1) {
            $candidates = [];
            foreach ($by_authority as $authority_id => $rule) {
                $candidates[] = [
                    'authority_id'   => (int) $authority_id,
                    'authority_name' => (string) ($rule['authority_name'] ?? ''),
                    'rule'           => $rule,
                ];
            }

            return $this->result(self::AMBIGUOUS, null, null, $match_type, $candidates);
        }

        $authority_id = (int) $authority_ids[0];
        $rule         = $by_authority[$authority_id];
        $authority     = $this->authority_repository->findById($authority_id);

        if (null === $authority || 'active' !== ($authority['status'] ?? '')) {
            return $this->result(self::NO_MATCH);
        }

        return $this->result(self::MATCH, $authority, $rule, $match_type);
    }

    private function extract_host(string $email): ?string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return null;
        }

        $host = strtolower(trim($parts[1]));

        return '' !== $host ? $host : null;
    }

    /**
     * @param array<string, mixed>|null $authority
     * @param array<string, mixed>|null $rule
     * @param array<int, array{authority_id: int, authority_name?: string, rule: array<string, mixed>}> $candidates
     * @return array{
     *   status: string,
     *   authority: array<string, mixed>|null,
     *   matched_rule: array<string, mixed>|null,
     *   match_type: string|null,
     *   candidates: array<int, array{authority_id: int, authority_name?: string, rule: array<string, mixed>}>
     * }
     */
    private function result(
        string $status,
        ?array $authority = null,
        ?array $rule = null,
        ?string $match_type = null,
        array $candidates = []
    ): array {
        return [
            'status'       => $status,
            'authority'    => $authority,
            'matched_rule' => $rule,
            'match_type'   => $match_type,
            'candidates'   => $candidates,
        ];
    }
}
