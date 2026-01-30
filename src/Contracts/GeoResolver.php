<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Contracts;

use Illuminate\Http\Request;

interface GeoResolver
{
    /**
     * Resolve geographic information for the given request or IP address.
     *
     * Should return an array with keys like: 'country', 'region', 'city', 'ip'.
     *
     * @return array<string, mixed>
     */
    public function resolve(Request|string|array|null $input = null): array;
}
