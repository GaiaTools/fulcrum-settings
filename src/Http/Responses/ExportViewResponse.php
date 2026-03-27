<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Http\Responses;

use GaiaTools\FulcrumSettings\Contracts\DataPortability\ExportViewResponse as Contract;
use Symfony\Component\HttpFoundation\Response;

class ExportViewResponse implements Contract
{
    public function toResponse($request): Response
    {
        return response()->view('fulcrum::export.form', [
            'supportedFormats' => ['json', 'csv', 'xml'],
        ]);
    }
}
