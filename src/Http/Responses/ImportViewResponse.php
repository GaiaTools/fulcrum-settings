<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Http\Responses;

use GaiaTools\FulcrumSettings\Contracts\DataPortability\ImportViewResponse as Contract;

class ImportViewResponse implements Contract
{
    public function toResponse($request): \Symfony\Component\HttpFoundation\Response
    {
        return response()->view('fulcrum::import.form', [
            'supportedFormats' => ['json', 'csv', 'xml'],
            'maxFileSize' => '10MB',
        ]);
    }
}
