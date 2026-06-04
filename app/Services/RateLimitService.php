<?php

namespace App\Services;

use Illuminate\Support\Facades\RateLimiter;

class RateLimitService
{
    /**
     * Get the configured rate limit key for login attempts
     */
    public static function loginAttemptKey(string $email): string
    {
        return "login.attempts:{$email}";
    }

    /**
     * Get the configured rate limit key for withdrawals
     */
    public static function withdrawalKey(int $userId): string
    {
        return "withdrawal.{$userId}";
    }

    /**
     * Get the configured rate limit key for trades
     */
    public static function tradeKey(int $userId): string
    {
        return "trade.{$userId}";
    }

    /**
     * Get the configured rate limit key for password changes
     */
    public static function passwordChangeKey(int $userId): string
    {
        return "password.change.{$userId}";
    }

    /**
     * Check if login attempt is rate limited (5 per minute)
     */
    public static function isLoginLimited(string $email): bool
    {
        return RateLimiter::tooManyAttempts(self::loginAttemptKey($email), 5);
    }

    /**
     * Record a login attempt
     */
    public static function recordLoginAttempt(string $email): void
    {
        RateLimiter::hit(self::loginAttemptKey($email), 60);
    }

    /**
     * Clear login rate limit
     */
    public static function clearLoginLimit(string $email): void
    {
        RateLimiter::clear(self::loginAttemptKey($email));
    }

    /**
     * Check if withdrawal is rate limited (3 per hour)
     */
    public static function isWithdrawalLimited(int $userId): bool
    {
        return RateLimiter::tooManyAttempts(self::withdrawalKey($userId), 3);
    }

    /**
     * Record a withdrawal attempt
     */
    public static function recordWithdrawalAttempt(int $userId): void
    {
        RateLimiter::hit(self::withdrawalKey($userId), 3600);
    }

    /**
     * Check if trade is rate limited (30 per minute)
     */
    public static function isTradeLimited(int $userId): bool
    {
        return RateLimiter::tooManyAttempts(self::tradeKey($userId), 30);
    }

    /**
     * Record a trade attempt
     */
    public static function recordTradeAttempt(int $userId): void
    {
        RateLimiter::hit(self::tradeKey($userId), 60);
    }

    /**
     * Get remaining attempts before rate limit
     */
    public static function getRemainingAttempts(string $key, int $limit): int
    {
        return max(0, $limit - RateLimiter::attempts($key));
    }
}
