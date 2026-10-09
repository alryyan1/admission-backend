<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExcelDocumentService;
use App\Services\OperationService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelController extends Controller
{
    public function __construct(private readonly ExcelDocumentService $documents) {}

    public function operationsReport(Request $request, OperationService $operations): StreamedResponse
    {
        $list = $operations->filteredQuery($request)
            ->with(['procedure', 'surgeon', 'admission.patient', 'teamMembers'])
            ->latest('scheduled_at')
            ->get();

        return $this->documents->operationsReport($list);
    }
}
