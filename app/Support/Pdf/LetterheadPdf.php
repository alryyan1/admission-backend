<?php

namespace App\Support\Pdf;

use App\Models\FacilitySetting;
use Illuminate\Support\Facades\Storage;
use TCPDF;

class LetterheadPdf extends TCPDF
{
    private FacilitySetting $facility;

    private string $documentTitle = '';

    public function __construct(string $orientation = 'P', string $format = 'A4')
    {
        parent::__construct($orientation, 'mm', $format, true, 'UTF-8');

        $this->facility = FacilitySetting::current();

        $this->setCreator('Jawda Inpatient');
        $this->setAuthor($this->facility->name ?: 'Jawda Inpatient');
        $this->setRTL(true);
        $this->setFontSubsetting(true);
        $this->SetFont(config('pdf.font'), '', 10);
        $this->setMargins(15, 40, 15);
        $this->setHeaderMargin(6);
        $this->setFooterMargin(14);
        $this->setAutoPageBreak(true, 22);
        $this->setImageScale(1.25);
        $this->setCellPaddings(1.5, 1.5, 1.5, 1.5);
    }

    public function setDocumentTitle(string $title): void
    {
        $this->documentTitle = $title;
    }

    public function Header(): void // @phpcs:ignore
    {
        $pageWidth = $this->getPageWidth();
        $logo = $this->facilityImagePath('logo_path', (bool) $this->facility->use_logo);

        if ($logo !== null) {
            $this->Image($logo, 15, 8, 0, 18, '', '', 'T', false, 300, '', false, false, 0);
        }

        $this->SetFont(config('pdf.font'), 'B', 14);
        $this->setXY(15, 9);
        $this->Cell($pageWidth - 30, 8, $this->facility->name ?: 'اسم المنشأة الطبية', 0, 1, 'C');

        $contact = collect([$this->facility->phone, $this->facility->email])
            ->filter()
            ->implode('   -   ');

        if ($contact !== '') {
            $this->SetFont(config('pdf.font'), '', 8);
            $this->setX(15);
            $this->Cell($pageWidth - 30, 5, $contact, 0, 1, 'C');
        }

        $this->Line(15, 30, $pageWidth - 15, 30, ['width' => 0.2, 'color' => [180, 180, 180]]);

        if ($this->documentTitle !== '') {
            $this->SetFont(config('pdf.font'), 'B', 13);
            $this->setXY(15, 32);
            $this->Cell($pageWidth - 30, 7, $this->documentTitle, 0, 1, 'C');
        }
    }

    public function Footer(): void // @phpcs:ignore
    {
        $pageWidth = $this->getPageWidth();
        $this->setY(-14);
        $this->SetFont(config('pdf.font'), '', 8);
        $this->setTextColor(150, 150, 150);

        if ($this->facility->address) {
            $this->Cell(($pageWidth - 30) / 2, 5, $this->facility->address, 0, 0, 'R');
        } else {
            $this->Cell(($pageWidth - 30) / 2, 5, '', 0, 0, 'R');
        }

        $this->Cell(
            ($pageWidth - 30) / 2,
            5,
            'صفحة '.$this->getAliasNumPage().' / '.$this->getAliasNbPages(),
            0,
            0,
            'L'
        );

        $this->setTextColor(0, 0, 0);
    }

    /**
     * Paint the facility watermark centred on the current page. Call right
     * after AddPage().
     */
    public function drawWatermark(): void
    {
        $path = $this->facilityImagePath('watermark_path', (bool) $this->facility->use_watermark);

        if ($path === null) {
            return;
        }

        $width = $this->getPageWidth() * 0.6;
        $this->setAlpha(0.08);
        $this->Image(
            $path,
            ($this->getPageWidth() - $width) / 2,
            $this->getPageHeight() * 0.28,
            $width,
            0,
            '',
            '',
            '',
            false,
            300
        );
        $this->setAlpha(1);
    }

    /**
     * Print a single line of body text spanning the full content width.
     */
    public function paragraph(string $text, float $size = 10, string $style = '', string $align = 'R'): void
    {
        $this->bodyFont($size, $style);
        $this->moveTo($this->rightEdge(), $this->GetY());
        $this->Cell($this->contentWidth(), 6, $text, 0, 1, $align);
    }

    /**
     * Print a small, muted, centred line (e.g. a receipt number caption).
     */
    public function mutedCenter(string $text, float $size = 9): void
    {
        $this->bodyFont($size);
        $this->setTextColor(107, 114, 128);
        $this->moveTo($this->rightEdge(), $this->GetY());
        $this->Cell($this->contentWidth(), 6, $text, 0, 1, 'C');
        $this->resetTextColor();
    }

    /**
     * Print a small, centred warning line (e.g. "preliminary" notice).
     */
    public function warnCenter(string $text, float $size = 9): void
    {
        $this->bodyFont($size);
        $this->setTextColor(217, 119, 6);
        $this->moveTo($this->rightEdge(), $this->GetY());
        $this->Cell($this->contentWidth(), 6, $text, 0, 1, 'C');
        $this->resetTextColor();
    }

    /**
     * A three-column meta line: right / center / left, evenly split.
     *
     * Cell() places text with its ambient X as the box's RIGHT edge under
     * RTL mode, so each column is positioned by its own right edge rather
     * than its left edge.
     */
    public function metaRow(string $right, string $center, string $left, float $size = 9): void
    {
        $width = $this->contentWidth() / 3;
        $this->bodyFont($size);
        $rowHeight = max(
            $this->getStringHeight($width, $right),
            $this->getStringHeight($width, $center),
            $this->getStringHeight($width, $left),
            6
        );
        $this->breakIfNeeded($rowHeight);
        $y = $this->GetY();
        $edge = $this->rightEdge();

        $this->moveTo($edge, $y);
        $this->Cell($width, $rowHeight, $right, 0, 0, 'R');
        $this->moveTo($edge - $width, $y);
        $this->Cell($width, $rowHeight, $center, 0, 0, 'C');
        $this->moveTo($edge - (2 * $width), $y);
        $this->Cell($width, $rowHeight, $left, 0, 0, 'L');

        $this->setAbsY($y + $rowHeight);
    }

    /**
     * A label/value row (bold label on a ~30% right column, value on the
     * remaining left column), separated by a light bottom border.
     *
     * MultiCell() (unlike Cell()) takes its ambient X as the box's LEFT
     * edge even under RTL mode, so columns here are positioned by their
     * left edge.
     */
    public function kvRow(string $label, string $value): void
    {
        $labelWidth = $this->contentWidth() * 0.3;
        $valueWidth = $this->contentWidth() - $labelWidth;

        $this->bodyFont(10.5, 'B');
        $labelHeight = $this->getStringHeight($labelWidth, $label);
        $this->bodyFont(10.5);
        $valueHeight = $this->getStringHeight($valueWidth, $value);
        $rowHeight = max($labelHeight, $valueHeight, 8);
        $this->breakIfNeeded($rowHeight);
        $y = $this->GetY();

        $this->bodyFont(10.5, 'B');
        $this->moveTo($this->rightEdge() - $labelWidth, $y);
        $this->MultiCell($labelWidth, $rowHeight, $label, 0, 'R', false, 0, null, null, true, 0, false, true, 0, 'M');

        $this->bodyFont(10.5);
        $this->moveTo($this->leftMarginX(), $y);
        $this->MultiCell($valueWidth, $rowHeight, $value, 0, 'R', false, 0, null, null, true, 0, false, true, 0, 'M');

        $this->drawRowDivider($y + $rowHeight);
        $this->setAbsY($y + $rowHeight);
    }

    /**
     * A bold, shaded section header band spanning the full content width.
     */
    public function sectionTitle(string $title): void
    {
        $this->bodyFont(11, 'B');
        $rowHeight = max($this->getStringHeight($this->contentWidth(), $title), 7);
        $this->breakIfNeeded(2 + $rowHeight);
        $this->Ln(2);
        $y = $this->GetY();
        $this->setFillColor(243, 244, 246);
        $this->moveTo($this->rightEdge(), $y);
        $this->Cell($this->contentWidth(), $rowHeight, $title, 0, 1, 'R', true);
        $this->Ln(1);
    }

    /**
     * A shaded, bold table header row.
     *
     * @param  array<int, array{label: string, width: float}>  $columns
     */
    public function tableHeader(array $columns): void
    {
        $this->bodyFont(9, 'B');
        $rowHeight = 7;
        foreach ($columns as $column) {
            $rowHeight = max($rowHeight, $this->getStringHeight($column['width'], $column['label']));
        }
        $this->breakIfNeeded($rowHeight);
        $y = $this->GetY();
        $this->setFillColor(243, 244, 246);
        $x = $this->rightEdge();

        foreach ($columns as $column) {
            $this->moveTo($x, $y);
            $this->Cell($column['width'], $rowHeight, $column['label'], 0, 0, 'R', true);
            $x -= $column['width'];
        }

        $this->setAbsY($y + $rowHeight);
    }

    /**
     * A wrapping table row with a light bottom border, columns given
     * right-to-left (matching visual RTL order).
     *
     * @param  array<int, string>  $cells
     * @param  array<int, float>  $widths
     */
    public function tableRow(array $cells, array $widths, float $size = 9, string $align = 'R'): void
    {
        $this->bodyFont($size);

        $rowHeight = 7;
        foreach ($cells as $i => $text) {
            $rowHeight = max($rowHeight, $this->getStringHeight($widths[$i], (string) $text));
        }
        $this->breakIfNeeded($rowHeight);
        $y = $this->GetY();

        $x = $this->rightEdge();
        foreach ($cells as $i => $text) {
            $x -= $widths[$i];
            $this->moveTo($x, $y);
            $this->MultiCell($widths[$i], $rowHeight, (string) $text, 0, $align, false, 0, null, null, true, 0, false, true, 0, 'M');
        }

        $this->drawRowDivider($y + $rowHeight);
        $this->setAbsY($y + $rowHeight);
    }

    /**
     * A centred, muted "no records" row spanning the full table width.
     */
    public function tableEmptyRow(string $text): void
    {
        $this->bodyFont(9);
        $rowHeight = max($this->getStringHeight($this->contentWidth(), $text), 7);
        $this->breakIfNeeded($rowHeight);
        $y = $this->GetY();
        $this->setTextColor(107, 114, 128);
        $this->moveTo($this->rightEdge(), $y);
        $this->Cell($this->contentWidth(), $rowHeight, $text, 0, 0, 'C');
        $this->resetTextColor();
        $this->drawRowDivider($y + $rowHeight);
        $this->setAbsY($y + $rowHeight);
    }

    /**
     * A right-aligned block of label/value rows (55% of the content width,
     * floated to the left), used for invoice/statement totals.
     *
     * @param  array<int, array{label: string, value: string, bold?: bool, big?: bool}>  $rows
     */
    public function totalsBlock(array $rows): void
    {
        $this->Ln(3);

        $blockWidth = $this->contentWidth() * 0.55;
        $labelWidth = $blockWidth * 0.6;
        $valueWidth = $blockWidth - $labelWidth;
        $blockRightEdge = $this->leftMarginX() + $blockWidth;

        foreach ($rows as $row) {
            $bold = $row['bold'] ?? false;
            $size = ($row['big'] ?? false) ? 12 : 10;
            $this->bodyFont($size, $bold ? 'B' : '');

            $rowHeight = max(
                $this->getStringHeight($labelWidth, $row['label']),
                $this->getStringHeight($valueWidth, $row['value']),
                6
            );
            $this->breakIfNeeded($rowHeight);
            $y = $this->GetY();

            $this->moveTo($blockRightEdge, $y);
            $this->Cell($labelWidth, $rowHeight, $row['label'], 0, 0, 'R');
            $this->moveTo($blockRightEdge - $labelWidth, $y);
            $this->Cell($valueWidth, $rowHeight, $row['value'], 0, 0, 'R');
            $this->setAbsY($y + $rowHeight);
        }
    }

    /**
     * A bold, shaded amount callout (label right, value left).
     */
    public function amountBox(string $label, string $value): void
    {
        $this->bodyFont(13, 'B');
        $rowHeight = max(
            $this->getStringHeight($this->contentWidth(), $value),
            $this->getStringHeight($this->contentWidth(), $label),
            11
        );
        $this->breakIfNeeded(2 + $rowHeight);
        $this->Ln(2);
        $y = $this->GetY();
        $this->setFillColor(243, 244, 246);
        $this->moveTo($this->rightEdge(), $y);
        $this->Cell($this->contentWidth(), $rowHeight, $value, 0, 0, 'L', true);
        $this->moveTo($this->rightEdge(), $y);
        $this->Cell($this->contentWidth(), $rowHeight, $label, 0, 1, 'R', true);
    }

    /**
     * A shaded box with a small bold label followed by wrapped body text
     * (e.g. an amount spelled out in words).
     */
    public function wordsBlock(string $label, string $text): void
    {
        $this->bodyFont(8.5, 'B');
        $labelHeight = 5;
        $this->bodyFont(10);
        $textHeight = $this->getStringHeight($this->contentWidth(), $text);
        $boxHeight = $labelHeight + $textHeight + 2;
        $this->breakIfNeeded(2 + $boxHeight);
        $this->Ln(2);
        $y = $this->GetY();

        $this->setFillColor(249, 250, 251);
        $this->Rect($this->leftMarginX(), $y, $this->contentWidth(), $boxHeight, 'F');

        $this->bodyFont(8.5, 'B');
        $this->moveTo($this->rightEdge(), $y + 1);
        $this->Cell($this->contentWidth(), $labelHeight, $label, 0, 1, 'R');

        $this->bodyFont(10);
        $this->moveTo($this->leftMarginX(), $y + 1 + $labelHeight);
        $this->MultiCell($this->contentWidth(), $textHeight, $text, 0, 'R', false, 0, null, null, true, 0, false, true, 0, 'T');

        $this->setAbsY($y + $boxHeight + 2);
    }

    /**
     * A two-slot signature row (right/left), each with a top border line.
     */
    public function signatureRow(string $rightLabel, string $leftLabel): void
    {
        $width = $this->contentWidth() * 0.4;
        $this->bodyFont(9);
        $rowHeight = max(
            $this->getStringHeight($width, $rightLabel),
            $this->getStringHeight($width, $leftLabel),
            5
        );
        $this->breakIfNeeded(4 + 1 + $rowHeight);
        $this->Ln(4);
        $y = $this->GetY();

        $this->Line($this->rightEdge() - $width, $y, $this->rightEdge(), $y, ['width' => 0.2, 'color' => [156, 163, 175]]);
        $this->Line($this->leftMarginX(), $y, $this->leftMarginX() + $width, $y, ['width' => 0.2, 'color' => [156, 163, 175]]);

        $this->setTextColor(107, 114, 128);
        $this->moveTo($this->rightEdge(), $y + 1);
        $this->Cell($width, $rowHeight, $rightLabel, 0, 0, 'C');
        $this->moveTo($this->leftMarginX() + $width, $y + 1);
        $this->Cell($width, $rowHeight, $leftLabel, 0, 0, 'C');
        $this->resetTextColor();
    }

    /**
     * Vertical whitespace, in millimetres.
     */
    public function spacing(float $mm): void
    {
        $this->Ln($mm);
    }

    public function contentWidth(): float
    {
        return $this->getPageWidth() - $this->leftMarginX() - $this->getMargins()['right'];
    }

    public function rightEdge(): float
    {
        return $this->getPageWidth() - $this->getMargins()['right'];
    }

    public function leftMarginX(): float
    {
        return $this->getMargins()['left'];
    }

    private function bodyFont(float $size = 10, string $style = ''): void
    {
        $this->SetFont(config('pdf.font'), $style, $size);
    }

    private function resetTextColor(): void
    {
        $this->setTextColor(31, 41, 55);
    }

    private function drawRowDivider(float $y): void
    {
        $this->Line($this->leftMarginX(), $y, $this->rightEdge(), $y, ['width' => 0.2, 'color' => [240, 240, 240]]);
    }

    /**
     * Position the cursor at an absolute (non-RTL-mirrored) coordinate.
     * setX()/setY()/setXY() reinterpret X under RTL mode (it's measured
     * from the right edge), which corrupts precomputed layout coordinates;
     * this bypasses that via setAbsX()/setAbsY().
     */
    private function moveTo(float $x, float $y): void
    {
        $this->setAbsX($x);
        $this->setAbsY($y);
    }

    /**
     * Paint the facility stamp near the bottom-right of the current page.
     */
    public function drawStamp(): void
    {
        $path = $this->facilityImagePath('stamp_path', (bool) $this->facility->use_stamp);

        if ($path === null) {
            return;
        }

        $this->Image($path, 22, $this->getPageHeight() - 55, 30, 0, '', '', '', false, 300);
    }

    private function facilityImagePath(string $column, bool $enabled): ?string
    {
        $value = $this->facility->{$column};

        if (! $enabled || ! $value) {
            return null;
        }

        $disk = Storage::disk('public');

        return $disk->exists($value) ? $disk->path($value) : null;
    }
}
