<?php

namespace App\Support\Pdf\Documents;

use App\Support\Pdf\LetterheadPdf;

/**
 * The inpatient admission file (ملف التنويم): a clinical face sheet followed
 * by the admission's services, operations, payments and closing signatures.
 *
 * Laid out to the usual hospital medical-record conventions: every page
 * carries the patient identifiers, allergies are flagged in a distinct alert
 * colour, and each block of information sits under a titled band with
 * label-over-value fields. Draws directly with TCPDF's Cell()/MultiCell()/
 * Rect()/Line() primitives.
 */
class AdmissionFilePdf extends LetterheadPdf
{
    private const TITLE = 'ملف التنويم';

    private const INK = [17, 24, 39];

    private const MUTED = [75, 85, 99];

    private const ALERT = [153, 27, 27];

    private const BORDER_DARK = [71, 85, 105];

    private const BORDER = [180, 180, 180];

    private const PANEL_FILL = [248, 250, 252];

    private const SECTION_HEIGHT = 7;

    private const FACT_MIN_HEIGHT = 12;

    private const ROW_MIN_HEIGHT = 7;

    private string $patientName = '—';

    private string $fileNumber = '—';

    public function __construct()
    {
        parent::__construct('P', 'A4');

        $this->setDocumentTitle(self::TITLE);
        $this->setTitle(self::TITLE);
        $this->AddPage();
        $this->drawWatermark();
        $this->setTextColor(...self::INK);
    }

    /**
     * @param  list<array{title: string, rows: list<list<array{label: string, value: string, span?: int, alert?: bool}>>}>  $factSections
     * @param  list<array{title: string, headings: list<string>, ratios: list<float>, rows: list<list<string>>, empty: string}>  $tables
     * @param  list<array{label: string, text: string}>  $notes
     * @param  list<array{label: string, value: string, bold?: bool}>  $totals
     * @param  list<string>  $signatories
     */
    public function render(
        string $patientName,
        string $fileNumber,
        string $issuedAt,
        array $factSections,
        array $tables,
        array $notes,
        array $totals,
        ?string $balanceWords,
        array $signatories,
    ): self {
        $this->patientName = $patientName;
        $this->fileNumber = $fileNumber;
        $this->identifierStrip($issuedAt);

        foreach ($factSections as $section) {
            $this->sectionHeader($section['title']);
            $this->factGrid($section['rows']);
        }

        foreach ($tables as $table) {
            $this->sectionHeader($table['title']);
            $this->table($table['headings'], $table['ratios'], $table['rows'], $table['empty']);
        }

        foreach ($notes as $note) {
            $this->Ln(3);
            $this->textPanel($note['label'], $note['text']);
        }

        $this->summaryTotals('الملخص المالي', $totals);

        if ($balanceWords !== null) {
            $this->Ln(2);
            $this->textPanel('المبلغ المستحق كتابة', $balanceWords);
        }

        $this->signatures($signatories);

        return $this;
    }

    /**
     * The identifier line repeated at the top of every page: patient name,
     * file number and the date the file was printed.
     */
    private function identifierStrip(string $issuedAt): void
    {
        $height = 7;
        $width = $this->contentWidth() / 3;
        $y = $this->GetY();
        $edge = $this->rightEdge();

        $this->setFillColor(...self::PANEL_FILL);
        $this->Rect($this->leftMarginX(), $y, $this->contentWidth(), $height, 'DF', $this->strongBorder());

        $this->SetFont(config('pdf.font'), '', 9);
        $this->setTextColor(...self::INK);
        $this->setAbsXY($edge - 2, $y);
        $this->Cell($width, $height, 'المريض: '.$this->patientName, 0, 0, 'R');
        $this->setAbsXY($edge - $width, $y);
        $this->Cell($width, $height, 'رقم الملف: #'.$this->fileNumber, 0, 0, 'C');
        $this->setAbsXY($edge - (2 * $width) + 2, $y);
        $this->Cell($width, $height, 'تاريخ الطباعة: '.$issuedAt, 0, 0, 'L');

        $this->setAbsY($y + $height + 3);
    }

    /**
     * A shaded, bordered band naming the block that follows.
     */
    private function sectionHeader(string $title): void
    {
        $this->fitOrNewPage(self::SECTION_HEIGHT + 15);
        $this->Ln(3);
        $y = $this->GetY();

        $this->setFillColor(...self::PANEL_FILL);
        $this->Rect($this->leftMarginX(), $y, $this->contentWidth(), self::SECTION_HEIGHT, 'DF', $this->strongBorder());

        $this->SetFont(config('pdf.font'), 'B', 10);
        $this->setTextColor(...self::INK);
        $this->setAbsXY($this->rightEdge() - 4, $y);
        $this->Cell($this->contentWidth() - 8, self::SECTION_HEIGHT, $title, 0, 0, 'R');

        $this->setAbsY($y + self::SECTION_HEIGHT);
    }

    /**
     * A grid of label-over-value fields, three units wide. A field may span
     * several units, and an alert field (e.g. allergies) is drawn in the alert
     * colour so it cannot be missed.
     *
     * @param  list<list<array{label: string, value: string, span?: int, alert?: bool}>>  $rows
     */
    private function factGrid(array $rows): void
    {
        $unitWidth = $this->contentWidth() / 3;

        foreach ($rows as $facts) {
            $rowHeight = self::FACT_MIN_HEIGHT;
            foreach ($facts as $fact) {
                $textWidth = $unitWidth * ($fact['span'] ?? 1) - 4;
                $rowHeight = max($rowHeight, $this->factHeight($fact['label'], $fact['value'], $textWidth));
            }

            $this->fitOrNewPage($rowHeight);
            $y = $this->GetY();
            $x = $this->rightEdge();

            foreach ($facts as $fact) {
                $width = $unitWidth * ($fact['span'] ?? 1);
                $x -= $width;
                $alert = $fact['alert'] ?? false;
                $this->Rect($x, $y, $width, $rowHeight, 'D', $alert ? $this->alertBorder() : $this->cellBorder());
                $this->drawFact($fact['label'], $fact['value'], $x + 2, $y + 1.5, $width - 4, $alert);
            }

            $this->setAbsY($y + $rowHeight);
        }
    }

    private function factHeight(string $label, string $value, float $textWidth): float
    {
        $this->SetFont(config('pdf.font'), 'B', 8.5);
        $labelHeight = $this->getStringHeight($textWidth, $label);

        $this->SetFont(config('pdf.font'), '', 10);
        $valueHeight = $this->getStringHeight($textWidth, $value);

        return $labelHeight + $valueHeight + 3;
    }

    private function drawFact(string $label, string $value, float $x, float $y, float $textWidth, bool $alert): void
    {
        $this->SetFont(config('pdf.font'), 'B', 8.5);
        $this->setTextColor(...($alert ? self::ALERT : self::MUTED));
        $labelHeight = $this->getStringHeight($textWidth, $label);
        $this->setAbsXY($x, $y);
        $this->MultiCell($textWidth, $labelHeight, $label, 0, 'R', false, 0, null, null, true, 0, false, true, 0, 'T');

        $this->SetFont(config('pdf.font'), $alert ? 'B' : '', 10);
        $this->setTextColor(...($alert ? self::ALERT : self::INK));
        $valueHeight = $this->getStringHeight($textWidth, $value);
        $this->setAbsXY($x, $y + $labelHeight);
        $this->MultiCell($textWidth, $valueHeight, $value, 0, 'R', false, 0, null, null, true, 0, false, true, 0, 'T');

        $this->setTextColor(...self::INK);
    }

    /**
     * A ruled table with a shaded header row. The header is repeated when the
     * table continues onto a new page.
     *
     * @param  list<string>  $headings
     * @param  list<float>  $ratios  each column's share of the content width
     * @param  list<list<string>>  $rows
     */
    private function table(array $headings, array $ratios, array $rows, string $emptyText): void
    {
        $widths = array_map(fn (float $ratio): float => $this->contentWidth() * $ratio, $ratios);
        $headerHeight = $this->rowHeight($headings, $widths, 'header');

        $this->fitOrNewPage($headerHeight + self::ROW_MIN_HEIGHT);
        $this->drawRow($headings, $widths, $headerHeight, 'header');

        if ($rows === []) {
            $emptyWidths = [$this->contentWidth()];
            $this->drawRow([$emptyText], $emptyWidths, $this->rowHeight([$emptyText], $emptyWidths, 'empty'), 'empty');

            return;
        }

        foreach ($rows as $cells) {
            $height = $this->rowHeight($cells, $widths, 'body');
            if ($this->fitOrNewPage($height)) {
                $this->drawRow($headings, $widths, $headerHeight, 'header');
            }
            $this->drawRow($cells, $widths, $height, 'body');
        }
    }

    /**
     * Full-width label/value rows with the amount column on the left, so the
     * figures line up under one another. The last row is emphasised as the
     * balance.
     *
     * @param  list<array{label: string, value: string, bold?: bool}>  $rows
     */
    private function summaryTotals(string $title, array $rows): void
    {
        $widths = [$this->contentWidth() * 0.7, $this->contentWidth() * 0.3];
        $blocks = array_map(function (array $row) use ($widths): array {
            $cells = [$row['label'], $row['value']];
            $variant = ($row['bold'] ?? false) ? 'total' : 'body';

            return [$cells, $variant, $this->rowHeight($cells, $widths, $variant)];
        }, $rows);

        $this->fitOrNewPage(array_sum(array_column($blocks, 2)) + self::SECTION_HEIGHT + 18);
        $this->sectionHeader($title);

        foreach ($blocks as [$cells, $variant, $height]) {
            $this->drawRow($cells, $widths, $height, $variant);
        }
    }

    /**
     * A bordered box with a bold label above wrapped body text.
     */
    private function textPanel(string $label, string $text): void
    {
        $labelHeight = 5;
        $this->SetFont(config('pdf.font'), '', 10);
        $textHeight = $this->getStringHeight($this->contentWidth() - 8, $text);
        $boxHeight = $labelHeight + $textHeight + 4;
        $this->fitOrNewPage($boxHeight);
        $y = $this->GetY();

        $this->Rect($this->leftMarginX(), $y, $this->contentWidth(), $boxHeight, 'D', $this->strongBorder());

        $this->SetFont(config('pdf.font'), 'B', 8.5);
        $this->setTextColor(...self::INK);
        $this->setAbsXY($this->rightEdge() - 4, $y + 1.5);
        $this->Cell($this->contentWidth() - 8, $labelHeight, $label, 0, 0, 'R');

        $this->SetFont(config('pdf.font'), '', 10);
        $this->setAbsXY($this->leftMarginX() + 4, $y + 1.5 + $labelHeight);
        $this->MultiCell($this->contentWidth() - 8, $textHeight, $text, 0, 'R', false, 0, null, null, true, 0, false, true, 0, 'T');

        $this->setAbsY($y + $boxHeight);
    }

    /**
     * Signature slots spread evenly across the page: a rule with a caption
     * beneath each one.
     *
     * @param  list<string>  $labels
     */
    private function signatures(array $labels): void
    {
        $this->fitOrNewPage(24);
        $this->Ln(12);
        $y = $this->GetY();
        $slotWidth = $this->contentWidth() / count($labels);

        foreach ($labels as $index => $label) {
            $slotRight = $this->rightEdge() - ($index * $slotWidth);
            $this->Line($slotRight - $slotWidth + 6, $y, $slotRight - 6, $y, $this->strongBorder());

            $this->SetFont(config('pdf.font'), '', 9);
            $this->setTextColor(...self::MUTED);
            $this->setAbsXY($slotRight, $y + 1.5);
            $this->Cell($slotWidth, 5, $label, 0, 0, 'C');
        }

        $this->setTextColor(...self::INK);
        $this->setAbsY($y + 1.5 + 5);
    }

    /**
     * @param  list<string>  $cells
     * @param  list<float>  $widths
     */
    private function rowHeight(array $cells, array $widths, string $variant): float
    {
        $style = $this->rowStyle($variant);
        $this->SetFont(config('pdf.font'), $style['style'], $style['size']);

        $height = self::ROW_MIN_HEIGHT;
        foreach ($cells as $i => $text) {
            $height = max($height, $this->getStringHeight($widths[$i] - 4, $text) + 2);
        }

        return $height;
    }

    /**
     * Draws one row of bordered cells, right-to-left (matching RTL reading
     * order). Pass $startX to anchor the row somewhere other than the right
     * margin.
     *
     * @param  list<string>  $cells
     * @param  list<float>  $widths
     */
    private function drawRow(array $cells, array $widths, float $height, string $variant, ?float $startX = null): void
    {
        $style = $this->rowStyle($variant);
        $y = $this->GetY();
        $x = $startX ?? $this->rightEdge();

        foreach ($cells as $i => $text) {
            $x -= $widths[$i];

            if ($style['fill']) {
                $this->setFillColor(...self::PANEL_FILL);
            }
            $this->Rect($x, $y, $widths[$i], $height, $style['fill'] ? 'DF' : 'D', $this->cellBorder());

            $this->SetFont(config('pdf.font'), $style['style'], $style['size']);
            $this->setTextColor(...$style['ink']);
            $this->setAbsXY($x + 2, $y);
            $this->MultiCell($widths[$i] - 4, $height, (string) $text, 0, $style['align'], false, 0, null, null, true, 0, false, true, 0, 'M');
        }

        $this->setAbsY($y + $height);
    }

    /**
     * @return array{style: string, size: float, fill: bool, ink: array{int, int, int}, align: string}
     */
    private function rowStyle(string $variant): array
    {
        return match ($variant) {
            'header' => ['style' => 'B', 'size' => 9, 'fill' => true, 'ink' => self::INK, 'align' => 'R'],
            'total' => ['style' => 'B', 'size' => 10.5, 'fill' => true, 'ink' => self::INK, 'align' => 'R'],
            'empty' => ['style' => '', 'size' => 9, 'fill' => false, 'ink' => self::MUTED, 'align' => 'C'],
            default => ['style' => '', 'size' => 9.5, 'fill' => false, 'ink' => self::INK, 'align' => 'R'],
        };
    }

    /**
     * Starts a new page when the next block of the given height would cross
     * the bottom break margin, then repeats the patient identifier line.
     */
    private function fitOrNewPage(float $height): bool
    {
        if ($this->GetY() + $height <= $this->getPageHeight() - $this->getBreakMargin()) {
            return false;
        }

        $this->AddPage();
        $this->drawWatermark();
        $this->setTextColor(...self::INK);
        $this->identifierStrip(now()->format('d/m/Y h:i A'));

        return true;
    }

    /**
     * @return array{width: float, color: array{int, int, int}}
     */
    private function cellBorder(): array
    {
        return ['width' => 0.2, 'color' => self::BORDER];
    }

    /**
     * @return array{width: float, color: array{int, int, int}}
     */
    private function alertBorder(): array
    {
        return ['width' => 0.5, 'color' => self::ALERT];
    }

    /**
     * @return array{width: float, color: array{int, int, int}}
     */
    private function strongBorder(): array
    {
        return ['width' => 0.3, 'color' => self::BORDER_DARK];
    }
}
