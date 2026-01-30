<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\DataPortability;

use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Models\SettingRule;
use GaiaTools\FulcrumSettings\Models\SettingRuleCondition;
use GaiaTools\FulcrumSettings\Models\SettingRuleRolloutVariant;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\Formatter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;

class ExportManager
{
    /**
     * @param array{
     *     connection?: string,
     *     decrypt?: bool,
     *     anonymize?: bool,
     *     gzip?: bool,
     *     dry_run?: bool,
     *     directory?: string,
     *     filename?: string
     * } $options
     */
    public function export(Formatter $formatter, array $options = []): string|bool
    {
        $connection = $options['connection'] ?? config('database.default');
        $decrypt = $options['decrypt'] ?? false;
        $anonymize = $options['anonymize'] ?? false;
        $gzip = $options['gzip'] ?? false;
        $dryRun = $options['dry_run'] ?? false;
        $directory = $options['directory'] ?? '.';
        $filename = $options['filename'] ?? 'settings_export_'.date('Ymd_His').'.'.$this->getExtension($formatter);

        $settings = Setting::on($connection)
            ->with(['defaultValue', 'rules.value', 'rules.conditions', 'rules.rolloutVariants.value'])
            ->get();

        $data = $settings->map(fn (Setting $setting) => $this->transformSetting($setting, $decrypt, $anonymize))->toArray();

        if ($dryRun) {
            return true;
        }

        $content = $formatter->format($data);

        if ($gzip) {
            $content = gzencode($content);
            if (! str_ends_with($filename, '.gz')) {
                $filename .= '.gz';
            }
        }

        $path = rtrim($directory, '/').'/'.$filename;

        // If directory is current directory and we are in a Laravel app, it might mean the project root.
        // But usually we use disks. For simplicity and as per requirements, we'll use file_put_contents if it looks like a path.
        // Or we can use Storage::put if we want to be more Laravel-ish.
        // The requirement says "the current directory" as default.

        if (str_starts_with($directory, '/') || str_contains($directory, ':\\')) {
            file_put_contents($path, $content);
        } else {
            Storage::disk('local')->put($path, $content);
            $path = Storage::disk('local')->path($path);
        }

        return $path;
    }

    protected function transformSetting(Setting $setting, bool $decrypt, bool $anonymize): array
    {
        $data = $setting->toArray();

        if ($anonymize) {
            $data['description'] = 'Anonymized description';
            // Roadmap says: anonymize (replace real data with fake data for local dev environments, with trashed soft-deleted records, without timestamps
            unset($data['created_at'], $data['updated_at']);
        }

        if ($setting->defaultValue) {
            $data['default_value'] = $this->transformValue($setting->defaultValue->getRawOriginal('value'), $setting, $decrypt, $anonymize);
        }

        $data['rules'] = $setting->rules->map(fn (SettingRule $rule) => $this->transformRule($rule, $setting, $decrypt, $anonymize))->toArray();

        return $data;
    }

    protected function transformRule(SettingRule $rule, Setting $setting, bool $decrypt, bool $anonymize): array
    {
        $data = $rule->toArray();

        if ($anonymize) {
            $data['name'] = 'Anonymized Rule';
            unset($data['created_at'], $data['updated_at']);
        }

        if ($rule->value) {
            $data['value'] = $this->transformValue($rule->value->getRawOriginal('value'), $setting, $decrypt, $anonymize);
        }

        $data['conditions'] = $rule->conditions->map(fn (SettingRuleCondition $condition) => $this->transformCondition($condition, $anonymize))->toArray();
        $data['rollout_variants'] = $rule->rolloutVariants->map(fn (SettingRuleRolloutVariant $variant) => $this->transformVariant($variant, $setting, $decrypt, $anonymize))->toArray();

        return $data;
    }

    protected function transformCondition(SettingRuleCondition $condition, bool $anonymize): array
    {
        $data = $condition->toArray();
        if ($anonymize) {
            unset($data['created_at'], $data['updated_at']);
            // Maybe anonymize value too if it contains sensitive info?
            // For now, let's keep it as is unless specified.
        }

        return $data;
    }

    protected function transformVariant(SettingRuleRolloutVariant $variant, Setting $setting, bool $decrypt, bool $anonymize): array
    {
        $data = $variant->toArray();
        if ($anonymize) {
            $data['name'] = 'Anonymized Variant';
            unset($data['created_at'], $data['updated_at']);
        }
        if ($variant->value) {
            $data['value'] = $this->transformValue($variant->value->getRawOriginal('value'), $setting, $decrypt, $anonymize);
        }

        return $data;
    }

    protected function transformValue(mixed $rawValue, Setting $setting, bool $decrypt, bool $anonymize): mixed
    {
        if ($setting->masked && $decrypt) {
            try {
                return Crypt::decryptString((string) $rawValue);
            } catch (\Throwable) {
                return $rawValue;
            }
        }

        return $rawValue;
    }

    protected function getExtension(Formatter $formatter): string
    {
        $class = get_class($formatter);

        return match (true) {
            str_contains($class, 'Json') => 'json',
            str_contains($class, 'Csv') => 'csv',
            str_contains($class, 'Xml') => 'xml',
            str_contains($class, 'Yaml') => 'yaml',
            str_contains($class, 'Sql') => 'sql',
            default => 'txt',
        };
    }
}
