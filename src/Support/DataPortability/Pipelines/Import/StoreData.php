<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\DataPortability\Pipelines\Import;

use GaiaTools\FulcrumSettings\Http\Requests\ImportRequest;
use GaiaTools\FulcrumSettings\Support\DataPortability\ImportManager;

class StoreData
{
    public function __construct(protected ImportManager $manager) {}

    public function handle(ImportRequest $request, \Closure $next)
    {
        $options = [
            'mode' => $request->input('mode', 'upsert'),
            'truncate' => $request->boolean('truncate'),
            'conflict_handling' => $request->input('conflict_handling', 'fail'),
            'connection' => $request->input('connection'),
            'chunk_size' => $request->integer('chunk_size', 1000),
        ];

        $result = $this->manager->import(
            $request->attributes->get('formatter'),
            $request->attributes->get('file_path'),
            array_filter($options, fn ($value) => $value !== null)
        );

        $request->attributes->set('import_result', ['success' => $result, 'count' => 0]); // Simplification for now

        return $next($request);
    }
}
