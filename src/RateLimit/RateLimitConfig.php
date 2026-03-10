<?php

declare(strict_types=1);

namespace WordPress\InfomaniakAiToolkit\RateLimit;

/**
 * Manages rate limit configuration stored in the WordPress options table.
 *
 * Provides methods to read and write per-role rate limits from
 * the 'infomaniak_ai_rate_limits' option.
 *
 * @since 1.0.0
 */
class RateLimitConfig
{
    /**
     * WordPress option name for rate limit configuration.
     */
    private const OPTION_NAME = 'infomaniak_ai_rate_limits';

    /**
     * Valid time window values.
     */
    public const WINDOW_HOUR  = 'hour';
    public const WINDOW_DAY   = 'day';
    public const WINDOW_MONTH = 'month';

    /**
     * Returns the default limits per role.
     *
     * Administrators are unlimited (0). Other roles have sensible defaults.
     *
     * @since 1.0.0
     *
     * @return array<string, array{limit: int, window: string}>
     */
    /**
     * Special role key for unauthenticated visitors.
     */
    public const ROLE_GUEST = 'guest';

    public static function defaults(): array
    {
        return [
            'administrator'  => ['limit' => 0, 'window' => self::WINDOW_HOUR],
            'editor'         => ['limit' => 100, 'window' => self::WINDOW_HOUR],
            'author'         => ['limit' => 50, 'window' => self::WINDOW_HOUR],
            'contributor'    => ['limit' => 20, 'window' => self::WINDOW_HOUR],
            'subscriber'     => ['limit' => 10, 'window' => self::WINDOW_HOUR],
            self::ROLE_GUEST => ['limit' => 5, 'window' => self::WINDOW_HOUR],
        ];
    }

    /**
     * Returns all configured limits, merged with defaults.
     *
     * @since 1.0.0
     *
     * @return array<string, array{limit: int, window: string}>
     */
    public static function getAll(): array
    {
        $stored = get_option(self::OPTION_NAME, []);
        if (!is_array($stored)) {
            $stored = [];
        }
        return array_merge(self::defaults(), $stored);
    }

    /**
     * Returns the limit configuration for a specific role.
     *
     * @since 1.0.0
     *
     * @param string $role WordPress role slug.
     * @return array{limit: int, window: string}
     */
    public static function getForRole(string $role): array
    {
        $all = self::getAll();
        return $all[$role] ?? ['limit' => 10, 'window' => self::WINDOW_HOUR];
    }

    /**
     * Saves rate limit configuration.
     *
     * @since 1.0.0
     *
     * @param array<string, array{limit: int, window: string}> $limits
     */
    public static function save(array $limits): void
    {
        $sanitized = [];
        foreach ($limits as $role => $config) {
            $sanitized[sanitize_key($role)] = [
                'limit'  => max(0, (int) ($config['limit'] ?? 0)),
                'window' => self::sanitizeWindow($config['window'] ?? self::WINDOW_HOUR),
            ];
        }
        update_option(self::OPTION_NAME, $sanitized);
    }

    /**
     * Returns the valid window values.
     *
     * @since 1.0.0
     *
     * @return string[]
     */
    public static function validWindows(): array
    {
        return [self::WINDOW_HOUR, self::WINDOW_DAY, self::WINDOW_MONTH];
    }

    /**
     * Sanitizes a window value to ensure it is valid.
     *
     * @since 1.0.0
     *
     * @param string $window
     * @return string
     */
    public static function sanitizeWindow(string $window): string
    {
        return in_array($window, self::validWindows(), true) ? $window : self::WINDOW_HOUR;
    }

    /**
     * Converts a window identifier to a DateTime string for the window start.
     *
     * @since 1.0.0
     *
     * @param string $window One of WINDOW_HOUR, WINDOW_DAY, WINDOW_MONTH.
     * @return string MySQL datetime string (UTC) for the start of the current window.
     */
    public static function windowStart(string $window): string
    {
        $now = time();
        return gmdate('Y-m-d H:i:s', match ($window) {
            self::WINDOW_HOUR  => $now - HOUR_IN_SECONDS,
            self::WINDOW_DAY   => $now - DAY_IN_SECONDS,
            self::WINDOW_MONTH => $now - (30 * DAY_IN_SECONDS),
            default            => $now - HOUR_IN_SECONDS,
        });
    }

    /**
     * Returns the WordPress option name.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public static function optionName(): string
    {
        return self::OPTION_NAME;
    }
}
