<?php

namespace App\Services;

use App\Models\Operation;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelDocumentService
{
    /**
     * @param  Collection<int, Operation>  $operations
     */
    public function operationsReport(Collection $operations): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setRightToLeft(true);
        $sheet->setTitle('العمليات');

        $sheet->fromArray(
            ['رقم العملية', 'المريض', 'الإجراء', 'الجراح', 'السعر', 'صافي المركز', 'تاريخ العملية', 'تاريخ الإنشاء'],
            null,
            'A1',
        );
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);

        $row = 2;
        $priceTotal = 0.0;
        $netTotal = 0.0;

        foreach ($operations as $operation) {
            $price = (float) ($operation->price ?? 0);
            $net = $price - (float) $operation->teamMembers->sum('entitlement_amount');
            $priceTotal += $price;
            $netTotal += $net;

            $sheet->fromArray([
                $operation->operation_number ?? ('#'.$operation->id),
                $operation->admission?->patient?->name ?? '—',
                $operation->procedure?->name_ar ?? '—',
                $operation->surgeon?->name ?? '—',
                $price,
                $net,
                $operation->scheduled_at?->format('Y-m-d H:i') ?? '—',
                $operation->created_at->format('Y-m-d H:i'),
            ], null, "A{$row}");
            $row++;
        }

        $sheet->fromArray(['الإجمالي', '', '', '', $priceTotal, $netTotal, '', ''], null, "A{$row}");
        $sheet->getStyle("A{$row}:H{$row}")->getFont()->setBold(true);

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        $filename = 'operations-report-'.now()->format('Y-m-d').'.xlsx';

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
