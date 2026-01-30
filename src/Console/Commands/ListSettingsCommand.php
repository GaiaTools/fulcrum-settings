<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Console\Commands;

use GaiaTools\FulcrumSettings\Facades\Fulcrum;
use GaiaTools\FulcrumSettings\Models\Scopes\TenantScope;
use GaiaTools\FulcrumSettings\Models\Setting;
use Illuminate\Console\Command;

class ListSettingsCommand extends Command
{
    protected $signature = 'fulcrum:list
                            {--tenant= : Filter settings by tenant ID}
                            {--no-tenants : List only settings that are not scoped to a tenant}';

    protected $description = 'List all Fulcrum settings';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $noTenants = $this->option('no-tenants');

        $query = Setting::withoutGlobalScope(TenantScope::class);

        if (Fulcrum::isMultiTenancyEnabled()) {
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            } elseif ($noTenants) {
                $query->whereNull('tenant_id');
            }
        }

        $settings = $query->orderBy('key')->get();

        if ($settings->isEmpty()) {
            $this->info('No settings found.');

            return 0;
        }

        $headers = ['Key', 'Type', 'Masked', 'Immutable', 'Description'];
        if (Fulcrum::isMultiTenancyEnabled()) {
            array_splice($headers, 2, 0, 'Tenant ID');
        }

        $data = $settings->map(function (Setting $setting) {
            $row = [
                $setting->key,
                $setting->type->value,
                $setting->masked ? 'Yes' : 'No',
                $setting->immutable ? 'Yes' : 'No',
                $setting->description ?: '-',
            ];

            if (Fulcrum::isMultiTenancyEnabled()) {
                array_splice($row, 2, 0, $setting->tenant_id ?: '-');
            }

            return $row;
        });

        $this->table($headers, $data);

        return 0;
    }
}
