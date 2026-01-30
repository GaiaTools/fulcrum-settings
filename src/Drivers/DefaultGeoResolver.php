<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Drivers;

use GaiaTools\FulcrumSettings\Contracts\GeoResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Request as RequestFacade;

class DefaultGeoResolver implements GeoResolver
{
    /**
     * {@inheritDoc}
     */
    public function resolve(Request|string|array|null $input = null): array
    {
        $ip = $this->resolveIp($input);

        // By default, we don't have a GeoIP provider, so we return basic info.
        // Users can override this with a real provider (e.g. MaxMind, IPinfo).
        return [
            'ip' => $ip,
            'country' => null,
            'region' => null,
            'city' => null,
        ];
    }

    /**
     * @param  array<int|string, mixed>|Request|string|null  $input
     */
    protected function resolveIp(Request|string|array|null $input): ?string
    {
        if (is_string($input)) {
            return $input;
        }

        if (is_array($input)) {
            $ip = $input['ip'] ?? null;

            return is_string($ip) ? $ip : null;
        }

        if ($input instanceof Request) {
            return $input->ip();
        }

        return RequestFacade::ip();
    }
}
