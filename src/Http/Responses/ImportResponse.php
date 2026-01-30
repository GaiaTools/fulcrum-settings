<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Http\Responses;

use GaiaTools\FulcrumSettings\Contracts\DataPortability\ImportResponse as Contract;

class ImportResponse implements Contract
{
    public function __construct(protected mixed $importResult = null) {}

    public function toResponse($request)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Settings imported successfully',
                'data' => [
                    'imported_count' => $this->importResult['count'] ?? 0,
                    'errors' => $this->importResult['errors'] ?? [],
                    'warnings' => $this->importResult['warnings'] ?? [],
                ],
            ]);
        }

        return redirect()->back()
            ->with('success', 'Settings imported successfully')
            ->with('imported_count', $this->importResult['count'] ?? 0);
    }
}
