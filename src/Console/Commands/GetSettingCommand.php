<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Console\Commands;

use GaiaTools\FulcrumSettings\Facades\Fulcrum;
use GaiaTools\FulcrumSettings\Support\MaskedValue;
use Illuminate\Console\Command;

class GetSettingCommand extends Command
{
    protected $signature = 'fulcrum:get
                            {key : The setting key to retrieve}
                            {--tenant= : The tenant ID for scoped resolution}
                            {--reveal : Reveal masked values (requires authorization)}
                            {--scope= : Additional scope/identifier for rollout evaluation}';

    protected $description = 'Get a setting value';

    public function handle(): int
    {
        $key = $this->argument('key');
        $tenantId = $this->option('tenant');
        $reveal = $this->option('reveal');
        $scope = $this->option('scope');

        $resolver = Fulcrum::getFacadeRoot();

        if ($tenantId) {
            $resolver = $resolver->forTenant($tenantId);
        }

        if ($reveal) {
            $resolver = $resolver->reveal();
        }

        $value = $resolver->get($key, null, $scope);

        if ($value === null) {
            $this->error("Setting [{$key}] not found.");

            return 1;
        }

        if ($value instanceof MaskedValue) {
            $this->warn("The value for [{$key}] is masked. Use --reveal to see the actual value.");
        }

        $this->line($this->formatValue($value));

        return 0;
    }

    protected function formatValue(mixed $value): string
    {
        if ($value instanceof MaskedValue) {
            return (string) $value;
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_PRETTY_PRINT);
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
