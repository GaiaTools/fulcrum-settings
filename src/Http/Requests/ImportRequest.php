<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Http\Requests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Gate;

class ImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ability = (string) config('fulcrum.portability.import_ability', 'importFulcrumSettings');

        return Gate::allows($ability);
    }

    protected function failedAuthorization()
    {
        if ($this->expectsJson() || $this->ajax()) {
            throw new AuthorizationException('This action is unauthorized.');
        }

        session()->flash('errors', collect(['This action is unauthorized.']));
        throw new HttpResponseException(redirect()->back()->withErrors('This action is unauthorized.'));
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|max:10240',
            'format' => 'sometimes|string|in:json,csv,xml',
            'mode' => 'sometimes|string|in:insert,upsert',
            'truncate' => 'sometimes|boolean',
            'conflict_handling' => 'sometimes|string|in:fail,skip,log',
            'connection' => 'sometimes|string',
            'chunk_size' => 'sometimes|integer|min:1',
        ];
    }
}
