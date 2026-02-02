<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Contracts;

interface UserAgentResolver
{
    /**
     * Resolve user agent information from the given scope or request.
     *
     * @return array{
     *     device: string|null,
     *     browser: string|null,
     *     browser_version: string|null,
     *     os: string|null,
     *     os_version: string|null,
     *     is_mobile: bool,
     *     is_tablet: bool,
     *     is_desktop: bool,
     *     is_bot: bool
     * }
     */
    public function resolve(mixed $scope = null): array;
}
