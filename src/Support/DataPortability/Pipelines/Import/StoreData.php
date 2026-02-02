<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Support\DataPortability\Pipelines\Import;

use GaiaTools\FulcrumSettings\Http\Requests\ImportRequest;
use GaiaTools\FulcrumSettings\Support\DataPortability\Formatters\Formatter;
use GaiaTools\FulcrumSettings\Support\DataPortability\ImportManager;

class StoreData
{
    public function __construct(protected ImportManager $manager) {}

    public function handle(ImportRequest $request, \Closure $next): mixed
    {
        $formatter = $request->attributes->get('formatter');
        if (! $formatter instanceof Formatter) {
            throw new \RuntimeException('Import formatter is missing.');
        }

        $filePath = $request->attributes->get('file_path');
        if (! is_string($filePath) || $filePath === '') {
            throw new \RuntimeException('Import file path is missing.');
        }

        $modeInput = $request->input('mode', 'upsert');
        $mode = is_string($modeInput) && in_array($modeInput, ['insert', 'upsert'], true) ? $modeInput : 'upsert';
        $conflictInput = $request->input('conflict_handling', 'fail');
        $conflictHandling = is_string($conflictInput) && in_array($conflictInput, ['fail', 'log', 'skip'], true) ? $conflictInput : 'fail';
        $connectionInput = $request->input('connection');

        $options = [
            'mode' => $mode,
            'truncate' => $request->boolean('truncate'),
            'conflict_handling' => $conflictHandling,
            'connection' => is_string($connectionInput) ? $connectionInput : null,
            'chunk_size' => $request->integer('chunk_size', 1000),
        ];

        $result = $this->manager->import(
            $formatter,
            $filePath,
            array_filter($options, fn ($value) => $value !== null)
        );

        $request->attributes->set('import_result', ['success' => $result, 'count' => 0]); // Simplification for now

        return $next($request);
    }
}
