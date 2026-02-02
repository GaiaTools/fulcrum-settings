<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Models\Concerns;

use GaiaTools\FulcrumSettings\Models\Setting;
use GaiaTools\FulcrumSettings\Support\FulcrumContext;
use GaiaTools\FulcrumSettings\Support\MaskedValue;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Gate;

trait HasMaskedValue
{
    /**
     * Resolve the parent setting model.
     */
    abstract public function resolveSetting(): ?Setting;

    /**
     * Apply masking logic to a value.
     */
    protected function applyMasking(mixed $value): mixed
    {
        $setting = $this->resolveSetting();

        if ($setting?->masked) {
            $revealFlag = FulcrumContext::shouldReveal();
            $ability = config()->string('fulcrum.masking.ability', 'viewSettingValue');

            $allowed = Gate::allows($ability, $setting);
            $isCli = App::runningInConsole();

            if ($revealFlag && ($allowed || $isCli)) {
                return $value;
            }

            $maskedValue = config()->string('fulcrum.masking.placeholder', '********');

            return new MaskedValue($maskedValue);
        }

        return $value;
    }
}
