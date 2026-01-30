<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Http\Responses;

use GaiaTools\FulcrumSettings\Contracts\DataPortability\ExportResponse as Contract;

class ExportResponse implements Contract
{
    public function __construct(protected mixed $exportData = null) {}

    public function toResponse($request)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'data' => $this->exportData,
                // In a real app, this might be a temporary download URL
                'download_url' => $this->exportData['file_path'] ?? null,
            ]);
        }

        if (isset($this->exportData['file_path']) && is_string($this->exportData['file_path'])) {
            return response()->download($this->exportData['file_path'])->deleteFileAfterSend(true);
        }

        return redirect()->back()->withErrors('Export failed');
    }
}
