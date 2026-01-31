<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Console\Commands;

use GaiaTools\FulcrumSettings\Console\Commands\Concerns\InteractsWithCommandOptions;
use GaiaTools\FulcrumSettings\Contracts\SettingResolver;
use GaiaTools\FulcrumSettings\Facades\Fulcrum;
use GaiaTools\FulcrumSettings\Support\MaskedValue;
use Illuminate\Console\Command;

class GetSettingCommand extends Command
{
    use InteractsWithCommandOptions;

    protected $signature = 'fulcrum:get
                            {key : The setting key to retrieve}
                            {--tenant= : The tenant ID for scoped resolution}
                            {--reveal : Reveal masked values (requires authorization)}
                            {--scope= : Additional scope/identifier for rollout evaluation}';

    protected $description = 'Get a setting value';

    public function handle(): int
    {
        $key = $this->getStringArgument('key');
        if ($key === null || $key === '') {
            $this->error('A valid setting key is required.');

            return 1;
        }

        $tenantId = $this->getStringOption('tenant');
        $reveal = $this->getBoolOption('reveal');
        $scope = $this->option('scope');

        /** @var SettingResolver|null $resolver */
        $resolver = Fulcrum::getFacadeRoot();
        if ($resolver === null) {
            $this->error('Setting resolver is not available.');

            return 1;
        }

        if ($tenantId !== null && $tenantId !== '') {
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
        return match (true) {
            $value instanceof MaskedValue => (string) $value,
            is_array($value) || is_object($value) => json_encode($value, JSON_PRETTY_PRINT) ?: '',
            is_bool($value) => $value ? 'true' : 'false',
            is_scalar($value) => (string) $value,
            is_object($value) && method_exists($value, '__toString') => (string) $value,
            default => '',
        };
    }
}
