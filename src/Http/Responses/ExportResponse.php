<?php

declare(strict_types=1);

namespace GaiaTools\FulcrumSettings\Http\Responses;

use GaiaTools\FulcrumSettings\Contracts\DataPortability\ExportResponse as Contract;

class ExportResponse implements Contract
{
    /**
     * @param  array<string, mixed>|null  $exportData
     */
    public function __construct(protected mixed $exportData = null) {}

    public function toResponse($request): \Symfony\Component\HttpFoundation\Response
    {
        if ($request->expectsJson() || $request->ajax()) {
            $downloadUrl = is_array($this->exportData) ? ($this->exportData['file_path'] ?? null) : null;

            return response()->json([
                'success' => true,
                'data' => $this->exportData,
                // In a real app, this might be a temporary download URL
                'download_url' => $downloadUrl,
            ]);
        }

        if (is_array($this->exportData)) {
            $filePath = $this->exportData['file_path'] ?? null;
            if (is_string($filePath)) {
                return response()->download($filePath)->deleteFileAfterSend(true);
            }
        }

        return redirect()->back()->withErrors('Export failed');
    }
}
