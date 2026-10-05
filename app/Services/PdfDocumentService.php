<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\AdmissionDeposit;
use App\Models\Invoice;
use App\Models\Operation;
use App\Support\ArabicNumber;
use App\Support\Pdf\Documents\DepositReceiptPdf;
use App\Support\Pdf\Documents\OperationInvoicePdf;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class PdfDocumentService
{
    public function __construct(
        private readonly PdfRenderer $renderer,
        private readonly InvoiceService $invoices,
        private readonly RevenueCalculatorService $calculator,
    ) {}

    public function depositReceipt(AdmissionDeposit $deposit): Response
    {
        $deposit->loadMissing('paymentMethod', 'paidBy', 'admission.patient');
        $patientName = $deposit->admission->patient?->name ?? '—';
        $comment = $deposit->comment
            ? 'دفعة تحت حساب التنويم — '.$deposit->comment
            : 'دفعة تحت حساب التنويم';

        $pdf = (new DepositReceiptPdf)->render(
            receiptNumber: $deposit->id,
            patientName: $patientName,
            admissionId: $deposit->admission_id,
            paidAt: $this->dateTime($deposit->paid_at),
            amount: $this->money($deposit->amount),
            amountWords: ArabicNumber::amountToWords((float) $deposit->amount),
            paymentMethod: $deposit->paymentMethod?->name ?? 'نقدي',
            reason: $comment,
            recordedBy: $deposit->paidBy?->name ?? '—',
        );

        return $this->renderer->toResponse($pdf, "receipt-{$deposit->id}.pdf");
    }

    public function admissionInvoice(Admission $admission): Response
    {
        $admission->loadMissing('patient');
        $charges = $this->invoices->previewCharges($admission);

        return $this->renderInvoice(
            title: 'فاتورة مبدئية',
            filename: "invoice-preview-{$admission->id}.pdf",
            isFinal: false,
            invoiceNumber: null,
            issuedAt: $this->dateTime(now()),
            patientName: $admission->patient?->name ?? '—',
            admissionId: $admission->id,
            lines: collect($charges['requested_services'])->map(fn ($item) => [
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'total' => $this->money($item['total_price']),
            ])->all(),
            servicesTotal: $this->money($charges['services_total']),
            operationsTotal: $charges['operations_total'] > 0 ? $this->money($charges['operations_total']) : null,
            total: $this->money($charges['total']),
            depositsTotal: $this->money($charges['deposits_total']),
            balanceDue: $this->money($charges['balance_due']),
            totalWords: ArabicNumber::amountToWords((float) $charges['total']),
        );
    }

    public function finalInvoice(Invoice $invoice): Response
    {
        $invoice->loadMissing('items', 'admission.patient');

        return $this->renderInvoice(
            title: 'فاتورة نهائية',
            filename: "invoice-{$invoice->invoice_number}.pdf",
            isFinal: true,
            invoiceNumber: $invoice->invoice_number,
            issuedAt: $this->dateTime($invoice->issued_at),
            patientName: $invoice->admission->patient?->name ?? '—',
            admissionId: $invoice->admission_id,
            lines: $invoice->items->map(fn ($item) => [
                'name' => $item->description,
                'quantity' => $item->quantity,
                'total' => $this->money($item->total),
            ])->all(),
            servicesTotal: $this->money($invoice->subtotal),
            operationsTotal: null,
            total: $this->money($invoice->total),
            depositsTotal: null,
            balanceDue: null,
            totalWords: ArabicNumber::amountToWords((float) $invoice->total),
        );
    }

    public function operationInvoice(Operation $operation): Response
    {
        $operation->loadMissing('procedure', 'admission.patient');
        $price = (float) ($operation->price ?? 0);
        $name = 'عملية: '.($operation->procedure?->name_ar ?? ('#'.($operation->operation_number ?? $operation->id)));

        $pdf = (new OperationInvoicePdf)->render(
            patientName: $operation->admission->patient?->name ?? '—',
            admissionId: $operation->admission_id,
            issuedAt: $this->dateTime(now()),
            itemName: $name,
            price: $this->money($price),
            priceWords: ArabicNumber::amountToWords($price),
        );

        return $this->renderer->toResponse($pdf, "operation-invoice-{$operation->id}.pdf");
    }

    public function operationTeam(Operation $operation): Response
    {
        $operation->loadMissing('procedure', 'admission.patient', 'teamMembers.role', 'teamMembers.doctor', 'teamMembers.paymentMethod');

        $pdf = $this->renderer->make('فريق العملية');

        $pdf->metaRow(
            'المريض: '.($operation->admission->patient?->name ?? '—'),
            'العملية: '.($operation->procedure?->name_ar ?? ('#'.($operation->operation_number ?? $operation->id))),
            'تاريخ الإصدار: '.$this->dateTime(now())
        );
        $pdf->spacing(3);

        $cw = $pdf->contentWidth();
        $widths = [$cw * 0.20, $cw * 0.28, $cw * 0.16, $cw * 0.18, $cw * 0.18];
        $pdf->tableHeader([
            ['label' => 'الدور', 'width' => $widths[0]],
            ['label' => 'العضو', 'width' => $widths[1]],
            ['label' => 'الاستحقاق', 'width' => $widths[2]],
            ['label' => 'طريقة الدفع', 'width' => $widths[3]],
            ['label' => 'تاريخ الدفع', 'width' => $widths[4]],
        ]);

        if ($operation->teamMembers->isEmpty()) {
            $pdf->tableEmptyRow('لا يوجد أعضاء في الفريق');
        }

        foreach ($operation->teamMembers as $member) {
            $pdf->tableRow([
                $member->role?->name ?? '—',
                $member->doctor?->name ?? $member->name ?? '—',
                $member->entitlement_amount !== null ? $this->money($member->entitlement_amount) : '—',
                $member->paymentMethod?->name ?? '—',
                $member->entitlement_paid_at ? $this->date($member->entitlement_paid_at) : '—',
            ], $widths);
        }

        $pdf->totalsBlock([
            ['label' => 'سعر العملية', 'value' => $operation->price !== null ? $this->money($operation->price) : '—'],
            ['label' => 'إجمالي الاستحقاقات', 'value' => $this->money(round((float) $operation->teamMembers->sum('entitlement_amount'), 2)), 'bold' => true],
        ]);

        return $this->renderer->toResponse($pdf, "operation-team-{$operation->id}.pdf");
    }

    public function accountStatement(Admission $admission): Response
    {
        $admission->loadMissing('patient', 'requestedServices', 'deposits.paymentMethod', 'operations.procedure');

        $rows = collect();

        foreach ($admission->requestedServices as $service) {
            $rows->push([
                'date' => $service->created_at,
                'description' => $service->name.' × '.$service->quantity,
                'debit' => (float) $service->total_price,
                'credit' => 0.0,
            ]);
        }

        foreach ($admission->operations->whereNotNull('price') as $operation) {
            $rows->push([
                'date' => $operation->scheduled_at ?? $operation->created_at,
                'description' => 'عملية: '.($operation->procedure?->name_ar ?? ('#'.$operation->operation_number)),
                'debit' => (float) $operation->price,
                'credit' => 0.0,
            ]);
        }

        foreach ($admission->deposits as $deposit) {
            $rows->push([
                'date' => $deposit->paid_at,
                'description' => $deposit->paymentMethod?->name ? 'دفعة — '.$deposit->paymentMethod->name : 'دفعة',
                'debit' => 0.0,
                'credit' => (float) $deposit->amount,
            ]);
        }

        $sorted = $rows->sortBy(fn ($row) => Carbon::parse($row['date'])->timestamp)->values();

        $balance = 0.0;
        $statementRows = $sorted->map(function ($row) use (&$balance) {
            $balance += $row['debit'] - $row['credit'];

            return [
                'date' => $this->date($row['date']),
                'description' => $row['description'],
                'debit' => $row['debit'] > 0 ? $this->money($row['debit']) : '—',
                'credit' => $row['credit'] > 0 ? $this->money($row['credit']) : '—',
                'balance' => $this->money($balance),
            ];
        })->all();

        $totalDebit = round($sorted->sum('debit'), 2);
        $totalCredit = round($sorted->sum('credit'), 2);

        $pdf = $this->renderer->make('كشف الحساب');

        $pdf->metaRow(
            'المريض: '.($admission->patient?->name ?? '—'),
            'رقم التنويم: #'.$admission->id,
            'تاريخ الإصدار: '.$this->dateTime(now())
        );
        $pdf->spacing(3);

        $cw = $pdf->contentWidth();
        $widths = [$cw * 0.16, $cw * 0.40, $cw * 0.14, $cw * 0.14, $cw * 0.16];
        $pdf->tableHeader([
            ['label' => 'التاريخ', 'width' => $widths[0]],
            ['label' => 'البيان', 'width' => $widths[1]],
            ['label' => 'مدين', 'width' => $widths[2]],
            ['label' => 'دائن', 'width' => $widths[3]],
            ['label' => 'الرصيد', 'width' => $widths[4]],
        ]);

        if ($statementRows === []) {
            $pdf->tableEmptyRow('لا توجد حركات بعد');
        }

        foreach ($statementRows as $row) {
            $pdf->tableRow([$row['date'], $row['description'], $row['debit'], $row['credit'], $row['balance']], $widths);
        }

        $pdf->totalsBlock([
            ['label' => 'إجمالي المدين (الخدمات والعمليات)', 'value' => $this->money($totalDebit)],
            ['label' => 'إجمالي الدائن (الدفعات)', 'value' => $this->money($totalCredit)],
            ['label' => 'الرصيد المستحق', 'value' => $this->money(round($totalDebit - $totalCredit, 2)), 'bold' => true, 'big' => true],
        ]);

        return $this->renderer->toResponse($pdf, "account-statement-{$admission->id}.pdf");
    }

    public function admissionSummary(Admission $admission): Response
    {
        $admission->loadMissing([
            'patient',
            'admittingDoctor',
            'bed.room.ward.floor',
            'requestedServices',
            'deposits.paymentMethod',
            'operations.procedure',
            'operations.surgeon',
            'vitalSigns',
            'doctorOrders.doses',
        ]);

        $statusLabels = ['admitted' => 'نشطة', 'discharged' => 'مخرّجة', 'cancelled' => 'ملغاة'];
        $genderLabels = ['male' => 'ذكر', 'female' => 'أنثى'];
        $typeLabels = ['inpatient' => 'تنويم كامل', 'short_stay' => 'إقامة قصيرة'];
        $orderStatusLabels = ['active' => 'نشط', 'discontinued' => 'موقوف'];

        $patient = $admission->patient;
        $ward = $admission->bed?->room?->ward;

        $start = Carbon::parse($admission->admission_date);
        $end = $admission->discharge_date ? Carbon::parse($admission->discharge_date) : now();
        $stayDays = max(1, $start->diffInDays($end) + 1);

        $servicesTotal = round((float) $admission->requestedServices->sum('total_price'), 2);
        $operationsTotal = round((float) $admission->operations->whereNotNull('price')->sum('price'), 2);
        $depositsTotal = round((float) $admission->deposits->sum('amount'), 2);
        $balanceDue = round($servicesTotal + $operationsTotal - $depositsTotal, 2);

        $pdf = $this->renderer->make('ملخص تنويم شامل', 'P', 'A4');

        $pdf->metaRow(
            'المريض: '.($patient?->name ?? '—'),
            'رقم التنويم: #'.$admission->id,
            'تاريخ الإصدار: '.$this->dateTime(now())
        );
        $pdf->spacing(3);

        $cw = $pdf->contentWidth();
        $w3 = $cw / 3;

        $pdf->sectionTitle('بيانات المريض');
        $pdf->tableRow([
            'الاسم: '.($patient?->name ?? '—'),
            'النوع: '.($patient?->gender ? ($genderLabels[$patient->gender] ?? $patient->gender) : '—'),
            'العمر: '.($patient?->age_year !== null ? $patient->age_year.' سنة' : '—'),
        ], [$w3, $w3, $w3]);
        $pdf->tableRow([
            'فصيلة الدم: '.($patient?->blood_type ?? '—'),
            'الهاتف: '.($patient?->phone ?? '—'),
            'العنوان: '.($patient?->address ?? '—'),
        ], [$w3, $w3, $w3]);

        $pdf->sectionTitle('بيانات التنويم');
        $pdf->tableRow([
            'رقم التنويم: '.($admission->admission_number ?? '—'),
            'الحالة: '.($statusLabels[$admission->status] ?? $admission->status),
            'نوع التنويم: '.($admission->admission_type ? ($typeLabels[$admission->admission_type] ?? $admission->admission_type) : '—'),
        ], [$w3, $w3, $w3]);
        $pdf->tableRow([
            'تاريخ الدخول: '.$this->dateTime($admission->admission_date),
            'تاريخ الخروج: '.($admission->discharge_date ? $this->dateTime($admission->discharge_date) : '—'),
            'مدة الإقامة: '.$stayDays.' '.($stayDays === 1 ? 'يوم' : 'أيام'),
        ], [$w3, $w3, $w3]);
        $pdf->tableRow([
            'الطابق: '.($ward?->floor?->name ?? '—'),
            'القسم: '.($ward?->name ?? '—'),
            'الغرفة / السرير: '.($admission->bed?->room?->room_number ?? '—').' / '.($admission->bed?->bed_number ?? '—'),
        ], [$w3, $w3, $w3]);
        $pdf->tableRow([
            'الطبيب المعالج: '.($admission->admittingDoctor?->name ?? '—'),
            'التشخيص: '.($admission->diagnosis ?? '—'),
        ], [$w3, $w3 * 2]);

        if ($admission->discharge_summary && $admission->status === 'discharged') {
            $pdf->wordsBlock('ملخص الخروج', $admission->discharge_summary);
        }
        if ($admission->cancellation_reason && $admission->status === 'cancelled') {
            $pdf->wordsBlock('سبب الإلغاء', $admission->cancellation_reason);
        }

        $pdf->sectionTitle('العلامات الحيوية');
        $vitalWidths = [$cw * 0.20, $cw * 0.13, $cw * 0.13, $cw * 0.14, $cw * 0.18, $cw * 0.22];
        $pdf->tableHeader([
            ['label' => 'الوقت', 'width' => $vitalWidths[0]],
            ['label' => 'الحرارة', 'width' => $vitalWidths[1]],
            ['label' => 'النبض', 'width' => $vitalWidths[2]],
            ['label' => 'التنفس', 'width' => $vitalWidths[3]],
            ['label' => 'ضغط الدم', 'width' => $vitalWidths[4]],
            ['label' => 'الأكسجين', 'width' => $vitalWidths[5]],
        ]);
        if ($admission->vitalSigns->isEmpty()) {
            $pdf->tableEmptyRow('لا توجد تسجيلات علامات حيوية');
        }
        foreach ($admission->vitalSigns as $vital) {
            $pdf->tableRow([
                $this->dateTime($vital->recorded_at),
                (string) ($vital->temperature ?? '—'),
                (string) ($vital->pulse ?? '—'),
                (string) ($vital->respiration_rate ?? '—'),
                (string) ($vital->blood_pressure ?? '—'),
                $vital->oxygen_saturation !== null ? $vital->oxygen_saturation.'%' : '—',
            ], $vitalWidths);
        }

        $pdf->sectionTitle('أوامر الأطباء');
        $orderWidths = [$cw * 0.40, $cw * 0.18, $cw * 0.18, $cw * 0.12, $cw * 0.12];
        $pdf->tableHeader([
            ['label' => 'الأمر / الدواء', 'width' => $orderWidths[0]],
            ['label' => 'التكرار', 'width' => $orderWidths[1]],
            ['label' => 'طريقة الإعطاء', 'width' => $orderWidths[2]],
            ['label' => 'الحالة', 'width' => $orderWidths[3]],
            ['label' => 'الجرعات', 'width' => $orderWidths[4]],
        ]);
        if ($admission->doctorOrders->isEmpty()) {
            $pdf->tableEmptyRow('لا توجد أوامر طبية');
        }
        foreach ($admission->doctorOrders as $order) {
            $pdf->tableRow([
                $order->order_text,
                $order->frequency ?? '—',
                $order->route ?? '—',
                $orderStatusLabels[$order->status] ?? $order->status,
                (string) $order->doses->count(),
            ], $orderWidths);
        }

        $pdf->sectionTitle('العمليات الجراحية');
        $opWidths = [$cw * 0.14, $cw * 0.34, $cw * 0.20, $cw * 0.14, $cw * 0.18];
        $pdf->tableHeader([
            ['label' => 'رقم العملية', 'width' => $opWidths[0]],
            ['label' => 'الإجراء', 'width' => $opWidths[1]],
            ['label' => 'الجراح', 'width' => $opWidths[2]],
            ['label' => 'السعر', 'width' => $opWidths[3]],
            ['label' => 'تاريخ العملية', 'width' => $opWidths[4]],
        ]);
        if ($admission->operations->isEmpty()) {
            $pdf->tableEmptyRow('لا توجد عمليات مجدولة');
        }
        foreach ($admission->operations as $operation) {
            $pdf->tableRow([
                (string) ($operation->operation_number ?? '—'),
                $operation->procedure?->name_ar ?? '—',
                $operation->surgeon?->name ?? '—',
                $operation->price !== null ? $this->money($operation->price) : '—',
                $operation->scheduled_at ? $this->dateTime($operation->scheduled_at) : '—',
            ], $opWidths);
        }

        $pdf->sectionTitle('الخدمات المطلوبة');
        $svcWidths = [$cw * 0.42, $cw * 0.12, $cw * 0.22, $cw * 0.24];
        $pdf->tableHeader([
            ['label' => 'الخدمة', 'width' => $svcWidths[0]],
            ['label' => 'الكمية', 'width' => $svcWidths[1]],
            ['label' => 'سعر الوحدة', 'width' => $svcWidths[2]],
            ['label' => 'الإجمالي', 'width' => $svcWidths[3]],
        ]);
        if ($admission->requestedServices->isEmpty()) {
            $pdf->tableEmptyRow('لا توجد خدمات مطلوبة');
        }
        foreach ($admission->requestedServices as $service) {
            $pdf->tableRow([
                $service->name,
                (string) $service->quantity,
                $this->money($service->unit_price),
                $this->money($service->total_price),
            ], $svcWidths);
        }

        $pdf->sectionTitle('الدفعات');
        $depWidths = [$cw * 0.22, $cw * 0.40, $cw * 0.38];
        $pdf->tableHeader([
            ['label' => 'التاريخ', 'width' => $depWidths[0]],
            ['label' => 'طريقة الدفع', 'width' => $depWidths[1]],
            ['label' => 'المبلغ', 'width' => $depWidths[2]],
        ]);
        if ($admission->deposits->isEmpty()) {
            $pdf->tableEmptyRow('لا توجد دفعات مسجلة');
        }
        foreach ($admission->deposits as $deposit) {
            $pdf->tableRow([
                $this->date($deposit->paid_at),
                $deposit->paymentMethod?->name ?? '—',
                $this->money($deposit->amount),
            ], $depWidths);
        }

        $totalsRows = [
            ['label' => 'إجمالي الخدمات', 'value' => $this->money($servicesTotal)],
        ];
        if ($operationsTotal > 0) {
            $totalsRows[] = ['label' => 'إجمالي العمليات', 'value' => $this->money($operationsTotal)];
        }
        $totalsRows[] = ['label' => 'إجمالي الدفعات', 'value' => $this->money($depositsTotal)];
        $totalsRows[] = ['label' => 'الرصيد المستحق', 'value' => $this->money($balanceDue), 'bold' => true, 'big' => true];
        $pdf->totalsBlock($totalsRows);

        return $this->renderer->toResponse($pdf, "admission-summary-{$admission->id}.pdf", false);
    }

    public function revenueCalculator(Carbon $date, ?string $generatedBy, ?int $userId = null, ?string $userName = null): Response
    {
        $data = $this->calculator->calculate($date, $userId);
        $methods = $data['payment_methods'];

        $pdf = $this->renderer->make('حاسبة الإيرادات اليومية', 'L', 'A4');

        $pdf->metaRow(
            'التاريخ: '.$this->date($date),
            'تاريخ الطباعة: '.$this->dateTime(now()),
            'المستخدم: '.($generatedBy ?? '—')
        );
        $pdf->metaRow('', 'المستلم: '.($userId !== null ? ($userName ?? '—') : 'الكل'), '');
        $pdf->spacing(3);

        $cw = $pdf->contentWidth();
        $labelWidth = $cw * 0.22;
        $columnWidth = ($cw - $labelWidth) / (count($methods) + 1);
        $widths = array_merge([$labelWidth], array_fill(0, count($methods) + 1, $columnWidth));

        $headerColumns = [['label' => 'البيان', 'width' => $labelWidth]];
        foreach ($methods as $method) {
            $headerColumns[] = ['label' => $method, 'width' => $columnWidth];
        }
        $headerColumns[] = ['label' => 'الإجمالي', 'width' => $columnWidth];
        $pdf->tableHeader($headerColumns);

        foreach ($data['rows'] as $row) {
            $cells = [$row['label']];
            foreach ($methods as $method) {
                $cells[] = $this->money($row['amounts'][$method] ?? 0);
            }
            $cells[] = $this->money($row['total']);
            $pdf->tableRow($cells, $widths);
        }

        return $this->renderer->toResponse($pdf, "revenue-calculator-{$data['date']}.pdf");
    }

    public function paymentsReport(Carbon $from, Carbon $to, ?string $generatedBy, ?int $paidByUserId = null): Response
    {
        $payments = AdmissionDeposit::query()
            ->with(['admission.patient', 'paymentMethod'])
            ->whereBetween('paid_at', [$from, $to])
            ->when($paidByUserId !== null, fn ($query) => $query->where('paid_by', $paidByUserId))
            ->orderBy('paid_at')
            ->get();

        $pdf = $this->renderer->make('تقرير المدفوعات', 'L', 'A4');

        $pdf->metaRow(
            'من: '.$this->date($from),
            'إلى: '.$this->date($to),
            'تاريخ الطباعة: '.$this->dateTime(now())
        );
        $pdf->spacing(3);

        $cw = $pdf->contentWidth();
        $widths = [$cw * 0.16, $cw * 0.30, $cw * 0.14, $cw * 0.18, $cw * 0.22];
        $pdf->tableHeader([
            ['label' => 'التاريخ', 'width' => $widths[0]],
            ['label' => 'المريض', 'width' => $widths[1]],
            ['label' => 'رقم التنويم', 'width' => $widths[2]],
            ['label' => 'طريقة الدفع', 'width' => $widths[3]],
            ['label' => 'المبلغ', 'width' => $widths[4]],
        ]);

        if ($payments->isEmpty()) {
            $pdf->tableEmptyRow('لا توجد مدفوعات ضمن الفترة المحددة');
        }

        foreach ($payments as $deposit) {
            $pdf->tableRow([
                $this->dateTime($deposit->paid_at),
                $deposit->admission?->patient?->name ?? '—',
                '#'.$deposit->admission_id,
                $deposit->paymentMethod?->name ?? '—',
                $this->money($deposit->amount),
            ], $widths);
        }

        $pdf->totalsBlock([
            ['label' => 'عدد المدفوعات', 'value' => (string) $payments->count()],
            ['label' => 'إجمالي المدفوعات', 'value' => $this->money($payments->sum('amount')), 'bold' => true, 'big' => true],
        ]);

        if ($generatedBy) {
            $pdf->mutedCenter('أُعدّ بواسطة: '.$generatedBy);
        }

        return $this->renderer->toResponse($pdf, "payments-report-{$from->toDateString()}-{$to->toDateString()}.pdf");
    }

    /**
     * @param  array<int, array{name: string, quantity: int, total: string}>  $lines
     */
    private function renderInvoice(
        string $title,
        string $filename,
        bool $isFinal,
        ?string $invoiceNumber,
        string $issuedAt,
        string $patientName,
        int $admissionId,
        array $lines,
        string $servicesTotal,
        ?string $operationsTotal,
        string $total,
        ?string $depositsTotal,
        ?string $balanceDue,
        string $totalWords,
        string $itemsLabel = 'الخدمة',
        bool $hideSubtotals = false,
    ): Response {
        $pdf = $this->renderer->make($title);

        if (! $isFinal) {
            $pdf->warnCenter('مبدئية — غير نهائية، قابلة للتغيير');
        }

        $centerMeta = $isFinal && $invoiceNumber
            ? 'رقم الفاتورة: '.$invoiceNumber
            : 'رقم التنويم: #'.$admissionId;

        $pdf->metaRow('المريض: '.$patientName, $centerMeta, 'التاريخ: '.$issuedAt);
        $pdf->spacing(3);

        $nameWidth = $pdf->contentWidth() * 0.7;
        $totalWidth = $pdf->contentWidth() * 0.3;
        $pdf->tableHeader([
            ['label' => $itemsLabel, 'width' => $nameWidth],
            ['label' => $hideSubtotals ? 'السعر' : 'الإجمالي', 'width' => $totalWidth],
        ]);

        foreach ($lines as $line) {
            $name = $hideSubtotals ? $line['name'] : $line['name'].' × '.$line['quantity'];
            $pdf->tableRow([$name, $line['total']], [$nameWidth, $totalWidth]);
        }

        if (! $hideSubtotals) {
            $rows = [
                ['label' => 'إجمالي الخدمات', 'value' => $servicesTotal],
            ];
            if ($operationsTotal !== null) {
                $rows[] = ['label' => 'إجمالي العمليات', 'value' => $operationsTotal];
            }
            $rows[] = ['label' => 'الإجمالي الكلي', 'value' => $total];
            if ($depositsTotal !== null) {
                $rows[] = ['label' => 'الدفعات المسددة', 'value' => $depositsTotal];
            }
            if ($balanceDue !== null) {
                $rows[] = ['label' => 'المبلغ المتبقي', 'value' => $balanceDue, 'bold' => true, 'big' => true];
            }
            $pdf->totalsBlock($rows);
        }

        $pdf->wordsBlock('المبلغ كتابة', $totalWords);

        return $this->renderer->toResponse($pdf, $filename);
    }

    private function money(float|string|null $value): string
    {
        $formatted = number_format((float) $value, 2, '.', ',');

        return str_contains($formatted, '.') ? rtrim(rtrim($formatted, '0'), '.') : $formatted;
    }

    private function dateTime(mixed $value): string
    {
        return $value ? Carbon::parse($value)->timezone(config('app.timezone'))->format('d/m/Y h:i A') : '—';
    }

    private function date(mixed $value): string
    {
        return $value ? Carbon::parse($value)->timezone(config('app.timezone'))->format('d/m/Y') : '—';
    }
}
