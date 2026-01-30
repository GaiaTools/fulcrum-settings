<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Http\Responses;

use GaiaTools\FulcrumSettings\Contracts\DataPortability\ExportViewResponse as Contract;

class ExportViewResponse implements Contract
{
    public function toResponse($request)
    {
        return view('fulcrum::export.form', [
            'supportedFormats' => ['json', 'csv', 'xml'],
        ]);
    }
}
