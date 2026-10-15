<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Http\Middleware;

use Closure;
use GaiaTools\FulcrumSettings\Support\Modules\ModuleAccess;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    public function __construct(private readonly ModuleAccess $access) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $status = config('fulcrum.modules.denied_status', 404);
        abort_unless($this->access->allows($module, $request->user()), $status === 403 ? 403 : 404);

        return $next($request);
    }
}
