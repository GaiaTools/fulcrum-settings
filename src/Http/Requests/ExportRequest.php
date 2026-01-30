<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Http\Requests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

class ExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = (string) config('fulcrum.portability.export_ability', 'exportFulcrumSettings');

        return Gate::allows($ability);
    }

    protected function failedAuthorization()
    {
        if ($this->expectsJson() || $this->ajax()) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        throw new HttpResponseException(redirect()->back()->withErrors('This action is unauthorized.'));
    }

    public function rules(): array
    {
        return [
            'format' => 'sometimes|string|in:json,csv,xml',
            'decrypt' => 'sometimes|boolean',
            'anonymize' => 'sometimes|boolean',
            'gzip' => 'sometimes|boolean',
            'connection' => 'sometimes|string',
        ];
    }
}
