<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\Telescope;

use GaiaTools\FulcrumSettings\Events\SettingResolved;
use GaiaTools\FulcrumSettings\Support\MaskedValue;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\Watchers\Watcher;

class SettingResolutionWatcher extends Watcher
{
    /**
     * Register the watcher.
     *
     * @param  \Illuminate\Contracts\Foundation\Application  $app
     */
    public function register($app): void
    {
        $app['events']->listen(SettingResolved::class, [$this, 'recordResolution']);
    }

    /**
     * Record a setting resolution.
     */
    public function recordResolution(SettingResolved $event): void
    {
        if (! Telescope::isRecording()) {
            return;
        }

        $value = $this->formatValue($event->value);
        $source = $this->formatSource($event);

        Telescope::recordLog(
            IncomingEntry::make([
                'level' => 'debug',
                'message' => $this->buildMessage($event, $source),
                'context' => $this->buildContext($event, $value, $source),
            ])->tags($this->buildTags($event))
        );
    }

    /**
     * Format the resolved value for display.
     */
    protected function formatValue(mixed $value): mixed
    {
        if ($value instanceof MaskedValue) {
            return '[MASKED]';
        }

        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            if (method_exists($value, 'toArray')) {
                return $value->toArray();
            }

            if (method_exists($value, '__toString')) {
                return (string) $value;
            }

            return '[Object: '.get_class($value).']';
        }

        return $value;
    }

    /**
     * Format the source description.
     */
    protected function formatSource(SettingResolved $event): string
    {
        if ($event->matchedRule) {
            return "rule:{$event->matchedRule->name}";
        }

        return $event->source;
    }

    /**
     * Build the log message.
     */
    protected function buildMessage(SettingResolved $event, string $source): string
    {
        $tenantInfo = $event->tenantId ? " [tenant:{$event->tenantId}]" : '';

        return "Fulcrum: {$event->key} → {$source}{$tenantInfo}";
    }

    /**
     * Build the context array for the log entry.
     *
     * @return array<string, mixed>
     */
    protected function buildContext(SettingResolved $event, mixed $value, string $source): array
    {
        $context = [
            'setting' => $event->key,
            'value' => $value,
            'source' => $source,
            'rules_evaluated' => $event->rulesEvaluated,
            'duration_ms' => round($event->durationMs, 2),
        ];

        if ($event->matchedRule) {
            $context['matched_rule'] = [
                'name' => $event->matchedRule->name,
                'priority' => $event->matchedRule->priority,
            ];
        }

        if ($event->tenantId) {
            $context['tenant_id'] = $event->tenantId;
        }

        if ($event->userId) {
            $context['user_id'] = $event->userId;
        }

        if ($event->scope && $this->shouldIncludeScope()) {
            $context['scope'] = $this->sanitizeScope($event->scope);
        }

        return $context;
    }

    /**
     * Build tags for filtering in Telescope.
     *
     * @return array<int, string>
     */
    protected function buildTags(SettingResolved $event): array
    {
        $tags = [
            'fulcrum',
            "setting:{$event->key}",
        ];

        if ($event->matchedRule) {
            $tags[] = "rule:{$event->matchedRule->name}";
        } else {
            $tags[] = 'source:default';
        }

        if ($event->tenantId) {
            $tags[] = "tenant:{$event->tenantId}";
        }

        return $tags;
    }

    /**
     * Determine if scope should be included in context.
     */
    protected function shouldIncludeScope(): bool
    {
        return $this->options['include_scope'] ?? false;
    }

    /**
     * Sanitize scope data for logging.
     *
     * @param  array<string, mixed>  $scope
     * @return array<string, mixed>
     */
    protected function sanitizeScope(array $scope): array
    {
        $sensitiveKeys = $this->options['sensitive_scope_keys'] ?? [
            'password',
            'token',
            'secret',
            'api_key',
            'apikey',
            'authorization',
        ];

        return collect($scope)
            ->map(function ($value, $key) use ($sensitiveKeys) {
                foreach ($sensitiveKeys as $sensitive) {
                    if (stripos($key, $sensitive) !== false) {
                        return '[REDACTED]';
                    }
                }

                return $value;
            })
            ->all();
    }
}
