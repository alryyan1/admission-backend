<?php

namespace App\Support\Pdf\Documents;

use App\Support\Pdf\LetterheadPdf;

/**
 * The patient discharge form (استمارة خروج): patient identifiers, admission
 * dates, room/stay charges and the financial settlement, laid out as
 * rounded, label-over-value field cards. Fields with no system source
 * (national ID, companion, payment notes) render as dashed "write here"
 * cards, and the discharge-type checkboxes are drawn empty for the
 * front-desk clerk to tick by hand. Draws directly with TCPDF's
 * Cell()/MultiCell()/RoundedRect()/Line() primitives.
 */
class DischargeFormPdf extends LetterheadPdf
{
    private const TITLE = 'استمارة خروج';

    private const INK = [15, 23, 42];

    private const MUTED = [100, 116, 139];

    private const BORDER = [203, 213, 225];

    private const FILL = [248, 250, 252];

    private const DUE = [185, 28, 28];

    private const SETTLED = [21, 128, 61];

    private const RADIUS = 1.4;

    public function __construct()
    {
        parent::__construct('P', 'A4');

        $this->setDocumentTitle(self::TITLE);
        $this->setTitle(self::TITLE);
        $this->AddPage();
        $this->drawWatermark();
        $this->setTextColor(...self::INK);
    }

    public function render(
        string $patientName,
        string $nationalId,
        string $phone,
        string $roomBed,
        string $companion,
        string $fileNumber,
        string $admittedAt,
        string $dischargedAt,
        string $roomType,
        string $dailyRoomPrice,
        string $stayDays,
        string $roomTotal,
        string $grandTotal,
        string $paidTotal,
        string $remaining,
        bool $hasBalanceDue,
    ): self {
        $this->sectionTitle('بيانات المريض');
        $this->fieldsRow([
            ['label' => 'اسم المريض رباعياً', 'value' => $patientName],
        ], 1);
        $this->fieldsRow([
            ['label' => 'الرقم الوطني', 'value' => $nationalId],
            ['label' => 'رقم الهاتف', 'value' => $phone],
        ]);
        $this->fieldsRow([
            ['label' => 'رقم الغرفة / السرير', 'value' => $roomBed],
            ['label' => 'رقم الملف', 'value' => $fileNumber],
        ]);
        $this->fieldsRow([
            ['label' => 'اسم المرافق ورقم هاتفه (إن وجد)', 'value' => $companion],
        ], 1);

        $this->sectionTitle('بيانات الدخول والخروج');
        $this->checkboxGroupsRow([
            ['label' => 'نوع الدخول / الخروج', 'options' => ['عملية', 'متابعة الحالة']],
            ['label' => 'نوع الخروج', 'options' => ['بعد العملية', 'بعد المتابعة']],
        ]);
        $this->fieldsRow([
            ['label' => 'تاريخ ووقت الدخول', 'value' => $admittedAt],
            ['label' => 'تاريخ ووقت الخروج', 'value' => $dischargedAt],
        ]);

        $this->sectionTitle('بيانات الغرفة والإقامة');
        $this->fieldsRow([
            ['label' => 'نوع الغرفة', 'value' => $roomType],
            ['label' => 'مبلغ الغرفة اليومي (جنيه سوداني)', 'value' => $dailyRoomPrice],
        ]);
        $this->fieldsRow([
            ['label' => 'عدد الأيام', 'value' => $stayDays],
            ['label' => 'إجمالي مبلغ الغرفة', 'value' => $roomTotal],
        ]);

        $this->sectionTitle('التسوية المالية (الجنيه السوداني)');
        $this->fieldsRow([
            ['label' => 'إجمالي الحساب', 'value' => $grandTotal],
            ['label' => 'إجمالي المبالغ المدفوعة', 'value' => $paidTotal, 'color' => self::SETTLED],
            [
                'label' => 'المبلغ المتبقي',
                'value' => $remaining,
                'color' => $hasBalanceDue ? self::DUE : self::SETTLED,
                'bold' => true,
            ],
        ], 3);
        $this->fieldsRow([
            ['label' => 'تفاصيل الدفعات / ملاحظات الحسابات', 'value' => '', 'blank' => true],
        ], 1, 12);

        $this->sectionTitle('أفراد وتوقيع');
        $this->signatureRow('توقيع موظف الاستقبال / الحسابات', 'توقيع المريض / المرافق');
        $this->Ln(3);
        $this->signatureRow('اعتماد المسؤول / ختم المركز', 'التاريخ والوقت');

        return $this;
    }

    /**
     * A row of evenly-sized rounded field cards: a small muted label along
     * the top and its value beneath it — the form's primary building
     * block. A field with no value renders as a dashed "write here" card.
     *
     * @param  list<array{label: string, value: string, blank?: bool, bold?: bool, color?: array{int, int, int}}>  $fields
     */
    private function fieldsRow(array $fields, int $columns = 2, float $minHeight = 10): void
    {
        $width = $this->contentWidth() / $columns;

        $this->SetFont(config('pdf.font'), '', 7.5);
        $labelHeight = 0.0;
        foreach ($fields as $field) {
            $labelHeight = max($labelHeight, $this->getStringHeight($width - 6, $field['label']));
        }
        $height = max($minHeight, $labelHeight + 6.5);

        $this->breakIfNeeded($height + 2);
        $y = $this->GetY();
        $x = $this->rightEdge();

        foreach ($fields as $field) {
            $boxX = $x - $width;
            $this->drawField($field, $boxX, $y, $width, $height, $labelHeight);
            $x = $boxX;
        }

        $this->setAbsY($y + $height + 2);
    }

    /**
     * @param  array{label: string, value: string, blank?: bool, bold?: bool, color?: array{int, int, int}}  $field
     */
    private function drawField(array $field, float $x, float $y, float $width, float $height, float $labelHeight): void
    {
        $blank = ($field['blank'] ?? false) || $field['value'] === '';
        $border = ['width' => 0.25, 'color' => self::BORDER];
        if ($blank) {
            $border['dash'] = 1.1;
        }

        $this->setFillColor(...($blank ? [255, 255, 255] : self::FILL));
        $this->RoundedRect($x, $y, $width, $height, self::RADIUS, '1111', 'DF', $border);
        if ($blank) {
            // The dash pattern is engine-global line state, not scoped to this
            // Rect() call — reset it so it doesn't bleed into later solid lines
            // (signature rules, the header's divider on the next page, ...).
            $this->setLineStyle(['dash' => 0]);
        }

        $this->SetFont(config('pdf.font'), '', 7.5);
        $this->setTextColor(...self::MUTED);
        $this->setAbsXY($x + 3, $y + 1.8);
        $this->MultiCell($width - 6, $labelHeight, $field['label'], 0, 'R', false, 0, null, null, true, 0, false, true, 0, 'T');

        if (! $blank) {
            $bold = $field['bold'] ?? false;
            $this->SetFont(config('pdf.font'), $bold ? 'B' : '', $bold ? 12.5 : 11.5);
            $this->setTextColor(...($field['color'] ?? self::INK));
            $this->setAbsXY($x + $width - 3, $y + $labelHeight);
            $this->Cell($width - 6, $height - $labelHeight - 1, $field['value'], 0, 0, 'R');
        }

        $this->setTextColor(...self::INK);
    }

    /**
     * A row of rounded cards, one per group, each holding a label and a
     * line of empty checkboxes (hand-ticked by the front-desk clerk).
     *
     * @param  list<array{label: string, options: list<string>}>  $groups
     */
    private function checkboxGroupsRow(array $groups): void
    {
        $width = $this->contentWidth() / count($groups);

        $this->SetFont(config('pdf.font'), '', 7.5);
        $labelHeight = 0.0;
        foreach ($groups as $group) {
            $labelHeight = max($labelHeight, $this->getStringHeight($width - 6, $group['label']));
        }
        $height = $labelHeight + 9;

        $this->breakIfNeeded($height + 2);
        $y = $this->GetY();
        $x = $this->rightEdge();

        foreach ($groups as $group) {
            $boxX = $x - $width;
            $this->drawCheckboxGroup($group['label'], $group['options'], $boxX, $y, $width, $height, $labelHeight);
            $x = $boxX;
        }

        $this->setAbsY($y + $height + 2);
    }

    /**
     * @param  list<string>  $options
     */
    private function drawCheckboxGroup(string $label, array $options, float $x, float $y, float $width, float $height, float $labelHeight): void
    {
        $this->setFillColor(...self::FILL);
        $this->RoundedRect($x, $y, $width, $height, self::RADIUS, '1111', 'DF', ['width' => 0.25, 'color' => self::BORDER]);

        $this->SetFont(config('pdf.font'), '', 7.5);
        $this->setTextColor(...self::MUTED);
        $this->setAbsXY($x + 3, $y + 1.8);
        $this->MultiCell($width - 6, $labelHeight, $label, 0, 'R', false, 0, null, null, true, 0, false, true, 0, 'T');

        $optionY = $y + 2.5 + $labelHeight;
        $boxSize = 4;
        $cursor = $x + $width - 4;

        $this->SetFont(config('pdf.font'), '', 9.5);
        $this->setTextColor(...self::INK);

        foreach ($options as $option) {
            $textWidth = $this->GetStringWidth($option) + 1;

            $this->setAbsXY($cursor, $optionY + 0.3);
            $this->Cell($textWidth, 5, $option, 0, 0, 'R');
            $cursor -= $textWidth + 2.5;

            $this->Rect($cursor - $boxSize, $optionY, $boxSize, $boxSize, 'D', ['width' => 0.35, 'color' => self::MUTED]);
            $cursor -= $boxSize + 7;
        }

        $this->setTextColor(...self::INK);
    }
}
