<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\AdmissionDeposit;
use App\Models\Invoice;
use App\Models\Operation;
use App\Support\ArabicNumber;
use App\Support\Pdf\Documents\AdmissionFilePdf;
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
            'patient.insuranceCompany',
            'patient.admittingDoctor',
            'patient.referredByDoctor',
            'admittedBy',
            'bed.room.ward.floor',
            'requestedServices',
            'deposits.paymentMethod',
            'operations.procedure',
            'operations.surgeon',
        ]);

        $statusLabels = ['admitted' => 'نشطة', 'discharged' => 'مخرّجة', 'cancelled' => 'ملغاة'];
        $genderLabels = ['male' => 'ذكر', 'female' => 'أنثى'];

        $patient = $admission->patient;
        $company = $patient?->insuranceCompany;
        $ward = $admission->bed?->room?->ward;
        $notRecorded = 'لم تُسجَّل';

        $start = Carbon::parse($admission->admission_date);
        $end = $admission->discharge_date ? Carbon::parse($admission->discharge_date) : now();
        $stayDays = max(1, $start->diffInDays($end) + 1);

        $servicesTotal = round((float) $admission->requestedServices->sum('total_price'), 2);
        $operationsTotal = round((float) $admission->operations->whereNotNull('price')->sum('price'), 2);
        $depositsTotal = round((float) $admission->deposits->sum('amount'), 2);
        $balanceDue = round($servicesTotal + $operationsTotal - $depositsTotal, 2);

        $factSections = [
            [
                'title' => 'بيانات المريض',
                'rows' => [
                    [
                        ['label' => 'الاسم', 'value' => $patient?->name ?? '—'],
                        ['label' => 'الجنس', 'value' => $patient?->gender ? ($genderLabels[$patient->gender] ?? $patient->gender) : '—'],
                        ['label' => 'العمر', 'value' => $patient?->age_year !== null ? $patient->age_year.' سنة' : '—'],
                    ],
                    [
                        ['label' => 'رقم المريض', 'value' => $patient ? '#'.$patient->id : '—'],
                        ['label' => 'فصيلة الدم', 'value' => $patient?->blood_type ?? '—'],
                        ['label' => 'الهاتف', 'value' => $patient?->phone ?? '—'],
                    ],
                    [
                        ['label' => 'العنوان', 'value' => $patient?->address ?? '—', 'span' => 3],
                    ],
                ],
            ],
            [
                'title' => 'جهة الاتصال في الطوارئ',
                'rows' => [
                    [
                        ['label' => 'الاسم', 'value' => $patient?->emergency_contact_name ?? '—'],
                        ['label' => 'صلة القرابة', 'value' => $patient?->emergency_contact_relationship ?? '—'],
                        ['label' => 'الهاتف', 'value' => $patient?->emergency_contact_phone ?? '—'],
                    ],
                    [
                        ['label' => 'العنوان', 'value' => $patient?->emergency_contact_address ?? '—', 'span' => 3],
                    ],
                ],
            ],
            [
                'title' => 'التأمين الصحي',
                'rows' => [
                    [
                        ['label' => 'شركة التأمين', 'value' => $company?->name ?? 'بدون تأمين'],
                        ['label' => 'رقم البطاقة', 'value' => $patient?->insurance_card_number ?? '—'],
                        ['label' => 'نسبة التغطية', 'value' => $company ? ((float) $company->coverage_percentage).'%' : '—'],
                    ],
                ],
            ],
            [
                'title' => 'بيانات التنويم',
                'rows' => [
                    [
                        ['label' => 'رقم التنويم', 'value' => (string) ($admission->admission_number ?? '—')],
                        ['label' => 'نوع الدخول', 'value' => Admission::ENTRY_TYPES[$admission->entry_type] ?? '—'],
                        ['label' => 'الحالة', 'value' => $statusLabels[$admission->status] ?? $admission->status],
                    ],
                    [
                        ['label' => 'تاريخ الدخول', 'value' => $this->dateTime($admission->admission_date)],
                        ['label' => 'تاريخ الخروج', 'value' => $admission->discharge_date ? $this->dateTime($admission->discharge_date) : '—'],
                        ['label' => 'مدة الإقامة', 'value' => $stayDays.' '.($stayDays === 1 ? 'يوم' : 'أيام')],
                    ],
                    [
                        ['label' => 'الطابق', 'value' => $ward?->floor?->name ?? '—'],
                        ['label' => 'القسم', 'value' => $ward?->name ?? '—'],
                        ['label' => 'الغرفة / السرير', 'value' => ($admission->bed?->room?->room_number ?? '—').' / '.($admission->bed?->bed_number ?? '—')],
                    ],
                    [
                        ['label' => 'الطبيب المعالج', 'value' => $admission->patient?->admittingDoctor?->name ?? '—'],
                        ['label' => 'الطبيب المحيل', 'value' => $admission->patient?->referredByDoctor?->name ?? '—'],
                        ['label' => 'أُدخل بواسطة', 'value' => $admission->admittedBy?->name ?? '—'],
                    ],
                    ...($admission->entry_type === Admission::ENTRY_TYPE_HOSPITAL_TRANSFER ? [[
                        ['label' => 'المستشفى المحوِّل', 'value' => $admission->referring_hospital_name ?? '—', 'span' => 3],
                    ]] : []),
                ],
            ],
            [
                'title' => 'التاريخ الطبي',
                'rows' => [
                    [
                        ['label' => 'الحساسية', 'value' => $patient?->allergies ?? $notRecorded, 'span' => 3, 'alert' => true],
                    ],
                    [
                        ['label' => 'الأمراض المزمنة', 'value' => $patient?->chronic_diseases ?? $notRecorded],
                        ['label' => 'الأدوية الحالية', 'value' => $patient?->current_medications ?? $notRecorded],
                        ['label' => 'العمليات الجراحية السابقة', 'value' => $patient?->past_surgeries ?? $notRecorded],
                    ],
                    [
                        ['label' => 'التاريخ المرضي', 'value' => $patient?->medical_history ?? $notRecorded, 'span' => 3],
                    ],
                ],
            ],
            [
                'title' => 'التشخيص',
                'rows' => [
                    [
                        ['label' => 'التشخيص', 'value' => $admission->diagnosis ?? $notRecorded, 'span' => 3],
                    ],
                    [
                        ['label' => 'ملاحظات الدخول', 'value' => $admission->admission_notes ?? '—', 'span' => 3],
                    ],
                ],
            ],
        ];

        $tables = [
            [
                'title' => 'العمليات الجراحية',
                'headings' => ['رقم العملية', 'الإجراء', 'الجراح', 'السعر', 'تاريخ العملية'],
                'ratios' => [0.14, 0.34, 0.20, 0.14, 0.18],
                'rows' => $admission->operations->map(fn ($operation) => [
                    (string) ($operation->operation_number ?? '—'),
                    $operation->procedure?->name_ar ?? '—',
                    $operation->surgeon?->name ?? '—',
                    $operation->price !== null ? $this->amount($operation->price) : '—',
                    $operation->scheduled_at ? $this->dateTime($operation->scheduled_at) : '—',
                ])->values()->all(),
                'empty' => 'لا توجد عمليات مجدولة',
            ],
            [
                'title' => 'الخدمات المطلوبة',
                'headings' => ['الخدمة', 'الكمية', 'سعر الوحدة', 'الإجمالي'],
                'ratios' => [0.42, 0.12, 0.22, 0.24],
                'rows' => $admission->requestedServices->map(fn ($service) => [
                    $service->name,
                    (string) $service->quantity,
                    $this->amount($service->unit_price),
                    $this->amount($service->total_price),
                ])->values()->all(),
                'empty' => 'لا توجد خدمات مطلوبة',
            ],
            [
                'title' => 'الدفعات',
                'headings' => ['التاريخ', 'طريقة الدفع', 'المبلغ'],
                'ratios' => [0.22, 0.40, 0.38],
                'rows' => $admission->deposits->map(fn ($deposit) => [
                    $this->date($deposit->paid_at),
                    $deposit->paymentMethod?->name ?? '—',
                    $this->amount($deposit->amount),
                ])->values()->all(),
                'empty' => 'لا توجد دفعات مسجلة',
            ],
        ];

        $notes = [];
        if ($admission->discharge_summary && $admission->status === 'discharged') {
            $notes[] = ['label' => 'ملخص الخروج', 'text' => $admission->discharge_summary];
        }
        if ($admission->cancellation_reason && $admission->status === 'cancelled') {
            $notes[] = ['label' => 'سبب الإلغاء', 'text' => $admission->cancellation_reason];
        }

        $totals = [
            ['label' => 'إجمالي الخدمات', 'value' => $this->amount($servicesTotal)],
        ];
        if ($operationsTotal > 0) {
            $totals[] = ['label' => 'إجمالي العمليات', 'value' => $this->amount($operationsTotal)];
        }
        $totals[] = ['label' => 'إجمالي الدفعات', 'value' => $this->amount($depositsTotal)];
        $totals[] = ['label' => 'الرصيد المستحق', 'value' => $this->amount($balanceDue), 'bold' => true];

        $pdf = (new AdmissionFilePdf)->render(
            patientName: $patient?->name ?? '—',
            fileNumber: (string) $admission->id,
            issuedAt: $this->dateTime(now()),
            factSections: $factSections,
            tables: $tables,
            notes: $notes,
            totals: $totals,
            balanceWords: $balanceDue > 0 ? ArabicNumber::amountToWords($balanceDue) : null,
            signatories: ['الطبيب المعالج', 'موظف الاستقبال', 'المريض / ولي الأمر'],
        );

        return $this->renderer->toResponse($pdf, "admission-file-{$admission->id}.pdf", false);
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

    private function amount(float|string|null $value): string
    {
        return number_format((float) $value, 2, '.', ',');
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
