<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\DataPortability\Pipelines\Import;

use GaiaTools\FulcrumSettings\Http\Requests\ImportRequest;
use Illuminate\Validation\ValidationException;

class ValidateFile
{
    public function handle(ImportRequest $request, \Closure $next): mixed
    {
        if (! $request->hasFile('file') || ! $request->file('file')->isValid()) {
            throw ValidationException::withMessages(['file' => 'Invalid file provided']);
        }

        return $next($request);
    }
}
