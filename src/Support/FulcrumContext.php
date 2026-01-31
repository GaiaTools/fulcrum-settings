<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support;

use Illuminate\Support\Facades\App;

class FulcrumContext
{
    protected static bool $forced = false;

    protected static bool $revealMasked = false;

    protected static ?string $tenantId = null;

    /** @var array<string, mixed> */
    protected static array $attributes = [];

    public static function force(bool $force = true): void
    {
        self::$forced = $force;
    }

    public static function reveal(bool $reveal = true): void
    {
        self::$revealMasked = $reveal;
    }

    public static function shouldReveal(): bool
    {
        return self::$revealMasked;
    }

    public static function setTenantId(?string $tenantId): void
    {
        self::$tenantId = $tenantId;
    }

    public static function getTenantId(): ?string
    {
        return self::$tenantId;
    }

    public static function set(string $key, mixed $value): void
    {
        self::$attributes[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$attributes[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return self::$attributes;
    }

    public static function clear(): void
    {
        self::$attributes = [];
        self::$forced = false;
        self::$revealMasked = false;
        self::$tenantId = null;
    }

    public static function shouldForce(): bool
    {
        return match (true) {
            self::$forced => true,
            (bool) config('fulcrum.immutability.env_flag', false) => true,
            self::shouldForceFromCli() => true,
            default => false,
        };
    }

    protected static function shouldForceFromCli(): bool
    {
        if (! App::runningInConsole()) {
            return false;
        }

        $flagValue = config('fulcrum.immutability.cli_flag', 'force');
        $flagValue = is_string($flagValue) ? $flagValue : 'force';
        $flag = '--'.$flagValue;

        foreach ((array) ($_SERVER['argv'] ?? []) as $arg) {
            if (is_string($arg) && ($arg === $flag || str_starts_with($arg, $flag.'='))) {
                return true;
            }
        }

        return false;
    }
}
