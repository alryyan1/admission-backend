<?php

namespace App\Support\Pdf\Documents;

use App\Support\Pdf\LetterheadPdf;

/**
 * The short-stay chart (إقامة قصيرة): the one-time doctor assessment
 * (patient info, initial vitals, PMH/C-O/Plan, signature), the medications
 * table, the repeating vitals-monitoring table, and the intake/output chart.
 * Built from LetterheadPdf's generic label/table primitives rather than
 * bespoke drawing code, since this form is plain tables and label/value
 * rows (unlike DischargeFormPdf's rounded field cards).
 */
class ShortStayChartPdf extends LetterheadPdf
{
    private const TITLE = 'إقامة قصيرة';

    public function __construct()
    {
        parent::__construct('P', 'A4');

        $this->setDocumentTitle(self::TITLE);
        $this->setTitle(self::TITLE);
        $this->AddPage();
        $this->drawWatermark();
    }

    /**
     * @param  list<array{name: string, frequency: string, times: list<string>}>  $medicationRows
     * @param  list<array{time: string, hr: string, spo2: string, rr: string, temp: string, bp: string, mop: string, rbg: string}>  $vitalsRows
     * @param  list<array{time: string, feeding: string, iv_fluid: string, maintenance: string, urine_output: string, insensible_losses: string, total_intake: string, total_output: string, balance: string}>  $ioRows
     */
    public function render(
        string $patientName,
        string $age,
        string $gender,
        string $address,
        string $admittedAt,
        string $initialBp,
        string $initialSpo2,
        string $initialPr,
        string $initialRr,
        string $pmh,
        string $chiefComplaint,
        string $plan,
        array $medicationRows,
        array $vitalsRows,
        array $ioRows,
    ): self {
        $this->sectionTitle('بيانات المريض');
        $this->kvRow('اسم المريض', $patientName);
        $this->kvRow('العمر', $age);
        $this->kvRow('الجنس', $gender);
        $this->kvRow('العنوان', $address);
        $this->kvRow('تاريخ الدخول', $admittedAt);

        $this->sectionTitle('العلامات الحيوية الأولية');
        $this->metaRow('ضغط الدم: '.$initialBp, 'التشبع: '.$initialSpo2, 'النبض: '.$initialPr);
        $this->kvRow('معدل التنفس', $initialRr);

        $this->wordsBlock('التاريخ المرضي (PMH)', $pmh !== '' ? $pmh : '—');
        $this->wordsBlock('الشكوى الرئيسية (C/O)', $chiefComplaint !== '' ? $chiefComplaint : '—');
        $this->wordsBlock('الخطة العلاجية (Plan)', $plan !== '' ? $plan : '—');

        $this->signatureRow('توقيع الطبيب', 'التاريخ والوقت');

        $this->sectionTitle('جدول الأدوية');
        $medicationWidths = $this->columnWidths([0.22, 0.14, 0.128, 0.128, 0.128, 0.128, 0.128]);
        $this->tableHeader($this->columns(
            ['الدواء', 'التكرار', 'وقت 1', 'وقت 2', 'وقت 3', 'وقت 4', 'وقت 5'],
            $medicationWidths
        ));
        if ($medicationRows === []) {
            $this->tableEmptyRow('لا توجد أدوية مسجّلة');
        } else {
            foreach ($medicationRows as $row) {
                $this->tableRow([
                    $row['name'],
                    $row['frequency'],
                    ...$row['times'],
                ], $medicationWidths);
            }
        }

        $this->sectionTitle('متابعة العلامات الحيوية');
        $vitalsWidths = $this->columnWidths([0.16, 0.12, 0.12, 0.12, 0.12, 0.12, 0.12, 0.12]);
        $this->tableHeader($this->columns(
            ['الوقت', 'HR', 'SPo2', 'RR', 'Temp', 'BP', 'MOP', 'RBG'],
            $vitalsWidths
        ));
        if ($vitalsRows === []) {
            $this->tableEmptyRow('لا توجد تسجيلات بعد');
        } else {
            foreach ($vitalsRows as $row) {
                $this->tableRow([
                    $row['time'], $row['hr'], $row['spo2'], $row['rr'],
                    $row['temp'], $row['bp'], $row['mop'], $row['rbg'],
                ], $vitalsWidths);
            }
        }

        $this->sectionTitle('جدول السوائل (Input / Output)');
        $ioWidths = $this->columnWidths([0.12, 0.11, 0.11, 0.11, 0.11, 0.12, 0.11, 0.11, 0.1]);
        $this->tableHeader($this->columns(
            ['الوقت', 'التغذية', 'سوائل IV', 'المحافظ', 'البول', 'الفقد غير الملحوظ', 'إجمالي الوارد', 'إجمالي الصادر', 'الميزان'],
            $ioWidths
        ));
        if ($ioRows === []) {
            $this->tableEmptyRow('لا توجد تسجيلات بعد');
        } else {
            foreach ($ioRows as $row) {
                $this->tableRow([
                    $row['time'], $row['feeding'], $row['iv_fluid'], $row['maintenance'],
                    $row['urine_output'], $row['insensible_losses'], $row['total_intake'],
                    $row['total_output'], $row['balance'],
                ], $ioWidths);
            }
        }

        return $this;
    }

    /**
     * @param  list<float>  $fractions  Column widths as fractions of contentWidth(), summing to 1.0.
     * @return list<float>
     */
    private function columnWidths(array $fractions): array
    {
        $contentWidth = $this->contentWidth();

        return array_map(fn (float $fraction): float => $contentWidth * $fraction, $fractions);
    }

    /**
     * @param  list<string>  $labels
     * @param  list<float>  $widths
     * @return list<array{label: string, width: float}>
     */
    private function columns(array $labels, array $widths): array
    {
        return array_map(
            fn (string $label, float $width): array => ['label' => $label, 'width' => $width],
            $labels,
            $widths
        );
    }
}
