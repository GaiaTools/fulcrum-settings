<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Http\Responses;

use GaiaTools\FulcrumSettings\Contracts\DataPortability\ImportResponse as Contract;

class ImportResponse implements Contract
{
    /**
     * @param  array<string, mixed>|null  $importResult
     */
    public function __construct(protected mixed $importResult = null) {}

    public function toResponse($request): \Symfony\Component\HttpFoundation\Response
    {
        $result = is_array($this->importResult) ? $this->importResult : [];

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Settings imported successfully',
                'data' => [
                    'imported_count' => $result['count'] ?? 0,
                    'errors' => $result['errors'] ?? [],
                    'warnings' => $result['warnings'] ?? [],
                ],
            ]);
        }

        return redirect()->back()
            ->with('success', 'Settings imported successfully')
            ->with('imported_count', $result['count'] ?? 0);
    }
}
