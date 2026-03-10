<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiProvider\RateLimit;

use WordPress\InfomaniakAiProvider\Usage\UsageTracker;

/**
 * Enforces per-role rate limits on AI preset execution.
 *
 * Checks the user's usage count within the configured time window
 * against the limit set for their WordPress role. Returns a WP_Error
 * if the limit is exceeded, or null if the request is allowed.
 *
 * @since 1.0.0
 */
class RateLimiter
{
    /**
     * Per-request cache to avoid repeated DB queries.
     *
     * @var array<string, int>
     */
    private static array $countCache = [];

    /**
     * Checks whether the current user is allowed to execute a preset.
     *
     * Returns null if allowed, or a WP_Error if rate limited.
     *
     * @since 1.0.0
     *
     * @param string|null $presetName Optional preset name for filter context.
     * @return \WP_Error|null Null if allowed, WP_Error if rate limited.
     */
    public static function check(?string $presetName = null): ?\WP_Error
    {
        $userId = get_current_user_id();

        if ($userId === 0) {
            return self::checkGuest($presetName);
        }

        $user = get_userdata($userId);
        if (!$user || empty($user->roles)) {
            return null;
        }

        $role   = $user->roles[0];
        $config = RateLimitConfig::getForRole($role);
        $limit  = $config['limit'];
        $window = $config['window'];

        // 0 means unlimited.
        if ($limit === 0) {
            return self::applyFilter(null, $userId, $presetName, [
                'role'   => $role,
                'limit'  => 0,
                'window' => $window,
                'count'  => 0,
            ]);
        }

        $windowStart = RateLimitConfig::windowStart($window);

        // Per-request cache.
        $cacheKey = "{$userId}:{$windowStart}";
        if (!isset(self::$countCache[$cacheKey])) {
            self::$countCache[$cacheKey] = UsageTracker::query()
                ->forUser($userId)
                ->from($windowStart)
                ->count();
        }

        $currentCount = self::$countCache[$cacheKey];

        $context = [
            'role'   => $role,
            'limit'  => $limit,
            'window' => $window,
            'count'  => $currentCount,
        ];

        if ($currentCount >= $limit) {
            $result = new \WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    /* translators: 1: limit number, 2: time window */
                    __('Rate limit exceeded. You are allowed %1$d requests per %2$s.', 'ai-provider-for-infomaniak'),
                    $limit,
                    $window
                ),
                [
                    'status'      => 429,
                    'limit'       => $limit,
                    'window'      => $window,
                    'remaining'   => 0,
                    'retry_after' => self::retryAfterSeconds($window),
                ]
            );

            return self::applyFilter($result, $userId, $presetName, $context);
        }

        // Increment cache proactively for multi-call scenarios in same request.
        self::$countCache[$cacheKey] = $currentCount + 1;

        return self::applyFilter(null, $userId, $presetName, $context);
    }

    /**
     * Returns the remaining requests for the current user (or guest).
     *
     * @since 1.0.0
     *
     * @return array{limit: int, remaining: int, window: string, reset: int}|null
     *         Null if unlimited.
     */
    public static function getRemainingForCurrentUser(): ?array
    {
        $userId = get_current_user_id();

        if ($userId === 0) {
            $config = RateLimitConfig::getForRole(RateLimitConfig::ROLE_GUEST);
            if ($config['limit'] === 0) {
                return null;
            }

            $transientKey = self::guestTransientKey($config['window']);
            $count = (int) get_transient($transientKey);

            return [
                'limit'     => $config['limit'],
                'remaining' => max(0, $config['limit'] - $count),
                'window'    => $config['window'],
                'reset'     => self::retryAfterSeconds($config['window']),
            ];
        }

        $user = get_userdata($userId);
        if (!$user || empty($user->roles)) {
            return null;
        }

        $role   = $user->roles[0];
        $config = RateLimitConfig::getForRole($role);

        if ($config['limit'] === 0) {
            return null;
        }

        $windowStart = RateLimitConfig::windowStart($config['window']);
        $count = UsageTracker::query()
            ->forUser($userId)
            ->from($windowStart)
            ->count();

        return [
            'limit'     => $config['limit'],
            'remaining' => max(0, $config['limit'] - $count),
            'window'    => $config['window'],
            'reset'     => self::retryAfterSeconds($config['window']),
        ];
    }

    /**
     * Clears the per-request count cache.
     *
     * @since 1.0.0
     */
    public static function clearCache(): void
    {
        self::$countCache = [];
    }

    /**
     * Checks rate limit for an unauthenticated guest using IP-based transients.
     *
     * @since 1.0.0
     *
     * @param string|null $presetName Optional preset name for filter context.
     * @return \WP_Error|null Null if allowed, WP_Error if rate limited.
     */
    private static function checkGuest(?string $presetName): ?\WP_Error
    {
        $config = RateLimitConfig::getForRole(RateLimitConfig::ROLE_GUEST);
        $limit  = $config['limit'];
        $window = $config['window'];

        // 0 means unlimited for guests.
        if ($limit === 0) {
            return self::applyFilter(null, 0, $presetName, [
                'role'   => RateLimitConfig::ROLE_GUEST,
                'limit'  => 0,
                'window' => $window,
                'count'  => 0,
            ]);
        }

        $transientKey = self::guestTransientKey($window);

        // Per-request cache for guests.
        if (!isset(self::$countCache[$transientKey])) {
            self::$countCache[$transientKey] = (int) get_transient($transientKey);
        }

        $currentCount = self::$countCache[$transientKey];

        $context = [
            'role'   => RateLimitConfig::ROLE_GUEST,
            'limit'  => $limit,
            'window' => $window,
            'count'  => $currentCount,
        ];

        if ($currentCount >= $limit) {
            $result = new \WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    /* translators: 1: limit number, 2: time window */
                    __('Rate limit exceeded. You are allowed %1$d requests per %2$s.', 'ai-provider-for-infomaniak'),
                    $limit,
                    $window
                ),
                [
                    'status'      => 429,
                    'limit'       => $limit,
                    'window'      => $window,
                    'remaining'   => 0,
                    'retry_after' => self::retryAfterSeconds($window),
                ]
            );

            return self::applyFilter($result, 0, $presetName, $context);
        }

        // Increment and persist.
        $newCount = $currentCount + 1;
        self::$countCache[$transientKey] = $newCount;
        set_transient($transientKey, $newCount, self::retryAfterSeconds($window));

        return self::applyFilter(null, 0, $presetName, $context);
    }

    /**
     * Applies the rate limit check filter.
     *
     * @param \WP_Error|null $result
     * @param int            $userId
     * @param string|null    $presetName
     * @param array          $context
     * @return \WP_Error|null
     */
    private static function applyFilter(?\WP_Error $result, int $userId, ?string $presetName, array $context): ?\WP_Error
    {
        /**
         * Filters the rate limit check result.
         *
         * Return null to allow the request, or a WP_Error to block it.
         *
         * @since 1.0.0
         *
         * @param \WP_Error|null $result     The check result (null = allowed).
         * @param int            $userId     The WordPress user ID (0 if guest).
         * @param string|null    $presetName The preset being executed.
         * @param array          $context    Additional context (role, limit, window, count).
         */
        return apply_filters('infomaniak_ai_rate_limit_check', $result, $userId, $presetName, $context);
    }

    /**
     * Returns the transient key for guest rate limiting based on IP.
     *
     * @param string $window The time window.
     * @return string Transient key.
     */
    private static function guestTransientKey(string $window): string
    {
        $ipHash = self::guestIpHash();
        return "infomaniak_ai_guest_{$window}_{$ipHash}";
    }

    /**
     * Returns a salted, hashed representation of the client IP address.
     *
     * Uses HMAC-SHA256 with wp_salt() to prevent brute-force reversal
     * of the IPv4 address space (~4B IPs). The hash is truncated to 12
     * hex characters to keep transient keys compact.
     *
     * GDPR note: the salt makes the hash non-reversible without access
     * to the site's secret keys, satisfying pseudonymization requirements.
     *
     * @return string 12-character hex HMAC of the client IP.
     */
    private static function guestIpHash(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            // Take the first IP (client) from the chain.
            $forwarded = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($forwarded[0]);
        }

        return substr(hash_hmac('sha256', $ip, wp_salt('auth')), 0, 12);
    }

    /**
     * Returns the Retry-After value in seconds for the given window.
     *
     * @param string $window
     * @return int
     */
    private static function retryAfterSeconds(string $window): int
    {
        return match ($window) {
            RateLimitConfig::WINDOW_HOUR  => HOUR_IN_SECONDS,
            RateLimitConfig::WINDOW_DAY   => DAY_IN_SECONDS,
            RateLimitConfig::WINDOW_MONTH => 30 * DAY_IN_SECONDS,
            default                       => HOUR_IN_SECONDS,
        };
    }
}
