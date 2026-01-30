<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Http\Controllers;

use GaiaTools\FulcrumSettings\Contracts\DataPortability\ExportResponse as ExportResponseContract;
use GaiaTools\FulcrumSettings\Contracts\DataPortability\ExportViewResponse as ExportViewResponseContract;
use GaiaTools\FulcrumSettings\Contracts\DataPortability\ImportResponse as ImportResponseContract;
use GaiaTools\FulcrumSettings\Contracts\DataPortability\ImportViewResponse as ImportViewResponseContract;
use GaiaTools\FulcrumSettings\Http\Requests\ExportRequest;
use GaiaTools\FulcrumSettings\Http\Requests\ImportRequest;
use GaiaTools\FulcrumSettings\Support\DataPortability\Pipelines\Export\PrepareExport;
use GaiaTools\FulcrumSettings\Support\DataPortability\Pipelines\Import\ProcessImport;
use GaiaTools\FulcrumSettings\Support\DataPortability\Pipelines\Import\StoreData;
use GaiaTools\FulcrumSettings\Support\DataPortability\Pipelines\Import\ValidateFile;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Routing\Controller;

class DataPortabilityController extends Controller
{
    public function showExport(Request $request): ExportViewResponseContract
    {
        return app(ExportViewResponseContract::class);
    }

    public function export(ExportRequest $request)
    {
        return (new Pipeline(app()))
            ->send($request)
            ->through([
                PrepareExport::class,
            ])
            ->then(function ($request) {
                return app(ExportResponseContract::class, ['exportData' => $request->attributes->get('export_data')]);
            });
    }

    public function showImport(Request $request): ImportViewResponseContract
    {
        return app(ImportViewResponseContract::class);
    }

    public function import(ImportRequest $request)
    {
        return (new Pipeline(app()))
            ->send($request)
            ->through([
                ValidateFile::class,
                ProcessImport::class,
                StoreData::class,
            ])
            ->then(function ($request) {
                return app(ImportResponseContract::class, ['importResult' => $request->attributes->get('import_result')]);
            });
    }
}
