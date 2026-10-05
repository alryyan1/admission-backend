<?php

namespace App\Support\Pdf\Documents;

use App\Services\PdfDocumentService;
use App\Support\Pdf\LetterheadPdf;

/**
 * The preliminary invoice for a single operation's price, styled as a
 * formal invoice: a bordered notice strip, a meta line, a ruled
 * single-line item table, and the amount spelled out in words — distinct
 * from the shared multi-line {@see PdfDocumentService::renderInvoice()}
 * used by the admission and final invoices. Draws directly with TCPDF's
 * Cell()/MultiCell()/Rect()/Line() primitives.
 */
class OperationInvoicePdf extends LetterheadPdf
{
    private const TITLE = 'فاتورة مبدئية';

    private const INK = [17, 24, 39];

    private const MUTED = [75, 85, 99];

    private const BORDER_DARK = [71, 85, 105];

    private const BORDER = [180, 180, 180];

    private const PANEL_FILL = [248, 250, 252];

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
        int $admissionId,
        string $issuedAt,
        string $itemName,
        string $price,
        string $priceWords,
    ): self {
        $this->preliminaryNotice();
        $this->Ln(3);
        $this->metaStrip($patientName, $admissionId, $issuedAt);
        $this->Ln(3);
        $this->itemsTable($itemName, $price);
        $this->Ln(2);
        $this->wordsPanel('المبلغ كتابة', $priceWords);

        return $this;
    }

    /**
     * A bordered strip flagging the invoice as preliminary — formal
     * phrasing in place of a bright warning banner.
     */
    private function preliminaryNotice(): void
    {
        $text = 'فاتورة مبدئية — غير نهائية، قابلة للتغيير';
        $height = 7;
        $this->breakIfNeeded($height);
        $y = $this->GetY();

        $this->setFillColor(...self::PANEL_FILL);
        $this->Rect($this->leftMarginX(), $y, $this->contentWidth(), $height, 'DF', [
            'width' => 0.3,
            'color' => self::BORDER_DARK,
        ]);

        $this->SetFont(config('pdf.font'), 'B', 9.5);
        $this->setTextColor(...self::MUTED);
        $this->setAbsXY($this->rightEdge(), $y);
        $this->Cell($this->contentWidth(), $height, $text, 0, 0, 'C');
        $this->setTextColor(...self::INK);

        $this->setAbsY($y + $height);
    }

    /**
     * A three-column meta line: patient / admission number / date, evenly
     * split. Cell() places text with its ambient X as the box's RIGHT edge
     * under RTL mode, so each column is positioned by its own right edge.
     */
    private function metaStrip(string $patientName, int $admissionId, string $issuedAt): void
    {
        $width = $this->contentWidth() / 3;
        $this->SetFont(config('pdf.font'), '', 9.5);
        $rowHeight = 6;
        $this->breakIfNeeded($rowHeight);
        $y = $this->GetY();
        $edge = $this->rightEdge();

        $this->setAbsXY($edge, $y);
        $this->Cell($width, $rowHeight, 'المريض: '.$patientName, 0, 0, 'R');
        $this->setAbsXY($edge - $width, $y);
        $this->Cell($width, $rowHeight, 'رقم التنويم: #'.$admissionId, 0, 0, 'C');
        $this->setAbsXY($edge - (2 * $width), $y);
        $this->Cell($width, $rowHeight, 'التاريخ: '.$issuedAt, 0, 0, 'L');

        $this->setAbsY($y + $rowHeight);
    }

    /**
     * A single-row ruled table (item / price), the item column on the
     * right per RTL reading order, matching the app's other table styles.
     */
    private function itemsTable(string $itemName, string $price): void
    {
        $nameWidth = $this->contentWidth() * 0.72;
        $priceWidth = $this->contentWidth() - $nameWidth;

        $this->SetFont(config('pdf.font'), 'B', 10);
        $headerHeight = 8;

        $this->SetFont(config('pdf.font'), '', 10.5);
        $dataHeight = max(
            $this->getStringHeight($nameWidth - 4, $itemName),
            $this->getStringHeight($priceWidth - 4, $price),
            8
        );

        $this->breakIfNeeded($headerHeight + $dataHeight);
        $y = $this->GetY();
        $divider = $this->leftMarginX() + $priceWidth;

        $this->setFillColor(...self::PANEL_FILL);
        $this->Rect($this->leftMarginX(), $y, $this->contentWidth(), $headerHeight, 'DF', [
            'width' => 0.3,
            'color' => self::BORDER_DARK,
        ]);
        $this->Rect($this->leftMarginX(), $y + $headerHeight, $this->contentWidth(), $dataHeight, 'D', [
            'width' => 0.3,
            'color' => self::BORDER_DARK,
        ]);
        $this->Line($divider, $y, $divider, $y + $headerHeight + $dataHeight, [
            'width' => 0.2,
            'color' => self::BORDER,
        ]);

        $this->SetFont(config('pdf.font'), 'B', 10);
        $this->setAbsXY($this->rightEdge() - 2, $y);
        $this->Cell($nameWidth - 4, $headerHeight, 'البند', 0, 0, 'R');
        $this->setAbsXY($divider, $y);
        $this->Cell($priceWidth - 2, $headerHeight, 'السعر', 0, 0, 'C');

        $this->SetFont(config('pdf.font'), '', 10.5);
        $this->setAbsXY($divider + 2, $y + $headerHeight);
        $this->MultiCell($nameWidth - 4, $dataHeight, $itemName, 0, 'R', false, 0, null, null, true, 0, false, true, 0, 'M');
        $this->setAbsXY($this->leftMarginX() + 2, $y + $headerHeight);
        $this->MultiCell($priceWidth - 4, $dataHeight, $price, 0, 'C', false, 0, null, null, true, 0, false, true, 0, 'M');

        $this->setAbsY($y + $headerHeight + $dataHeight);
    }

    /**
     * The amount spelled out in words, in a light bordered panel.
     */
    private function wordsPanel(string $label, string $text): void
    {
        $labelHeight = 5;
        $this->SetFont(config('pdf.font'), '', 10);
        $textHeight = $this->getStringHeight($this->contentWidth() - 8, $text);
        $boxHeight = $labelHeight + $textHeight + 4;
        $this->breakIfNeeded($boxHeight);
        $y = $this->GetY();

        $this->Rect($this->leftMarginX(), $y, $this->contentWidth(), $boxHeight, 'D', [
            'width' => 0.3,
            'color' => self::BORDER_DARK,
        ]);

        $this->SetFont(config('pdf.font'), 'B', 8.5);
        $this->setAbsXY($this->rightEdge() - 4, $y + 1.5);
        $this->Cell($this->contentWidth() - 8, $labelHeight, $label, 0, 1, 'R');

        $this->SetFont(config('pdf.font'), '', 10);
        $this->setAbsXY($this->leftMarginX() + 4, $y + 1.5 + $labelHeight);
        $this->MultiCell($this->contentWidth() - 8, $textHeight, $text, 0, 'R', false, 1, null, null, true, 0, false, true, 0, 'T');

        $this->setAbsY($y + $boxHeight);
    }
}
