<?php

namespace App\Support\Pdf\Documents;

use App\Support\ArabicNumber;
use App\Support\Pdf\LetterheadPdf;

/**
 * The receipt for a single admission deposit, styled as a formal payment
 * voucher (سند قبض): sharp-cornered bordered boxes, solid rule lines, and a
 * signature line — distinct from the other documents' shared LetterheadPdf
 * row styles. Draws directly with TCPDF's Cell()/MultiCell()/Rect()/Line()
 * primitives.
 */
class DepositReceiptPdf extends LetterheadPdf
{
    private const TITLE = 'إيصال استلام ';

    private const INK = [17, 24, 39];

    private const MUTED = [75, 85, 99];

    private const BORDER = [180, 180, 180];

    private const BORDER_DARK = [71, 85, 105];

    private const PANEL_FILL = [248, 250, 252];

    public function __construct()
    {
        parent::__construct('L', 'A5');

        $this->setDocumentTitle(self::TITLE);
        $this->setTitle(self::TITLE);
        $this->AddPage();
        $this->drawWatermark();
        $this->setTextColor(...self::INK);
    }

    public function render(
        int $receiptNumber,
        string $patientName,
        int $admissionId,
        string $paidAt,
        string $amount,
        string $amountWords,
        string $paymentMethod,
        string $reason,
    ): self {
        $this->voucherHeader($receiptNumber, $paidAt);
        $this->Ln(2);
        $this->row('الاسم', $patientName, size: 10.5, boldValue: true);
        $this->Ln(1);
        $this->amountBlock($amount, $amountWords);
        $this->Ln(1.5);
        $this->row('رقم التنويم', '#'.$admissionId);
        $this->row('طريقة الدفع', $paymentMethod);
        // $this->noteRow('وذلك عن', $reason);
        $this->Ln(3);
        $this->signatures();

        return $this;
    }

    /**
     * A plain bordered strip identifying the voucher: number on the right,
     * date on the left.
     */
    private function voucherHeader(int $receiptNumber, string $paidAt): void
    {
        $height = 8;
        $this->breakIfNeeded($height);
        $y = $this->GetY();

        $this->setFillColor(...self::PANEL_FILL);
        $this->Rect($this->leftMarginX(), $y, $this->contentWidth(), $height, 'DF', [
            'width' => 0.3,
            'color' => self::BORDER_DARK,
        ]);

        $this->SetFont(config('pdf.font'), 'B', 10);
        $this->setAbsXY($this->rightEdge() - 4, $y);
        $this->Cell($this->contentWidth() / 2 - 4, $height, 'رقم السند: #'.$receiptNumber, 0, 0, 'R');

        $this->SetFont(config('pdf.font'), '', 9.5);
        $this->setAbsXY($this->leftMarginX() + $this->contentWidth() / 2, $y);
        $this->Cell($this->contentWidth() / 2 - 4, $height, 'التاريخ: '.$paidAt, 0, 0, 'L');

        $this->setAbsY($y + $height);
    }

    /**
     * A formal label/value line: bold label sized to fit its own text (not
     * a fixed column) so it sits close to the value, with a solid thin rule
     * beneath. MultiCell() takes its ambient X as the box's LEFT edge even
     * under RTL mode, so columns are positioned by their left edge.
     */
    private function row(string $label, string $value, float $size = 10, bool $boldValue = false): void
    {
        $this->SetFont(config('pdf.font'), 'B', $size);
        $labelWidth = $this->GetStringWidth($label) + 3;
        $valueWidth = $this->contentWidth() - $labelWidth;

        $labelHeight = $this->getStringHeight($labelWidth, $label);
        $this->SetFont(config('pdf.font'), $boldValue ? 'B' : '', $size);
        $valueHeight = $this->getStringHeight($valueWidth, $value);
        $rowHeight = max($labelHeight, $valueHeight, 6);
        $this->breakIfNeeded($rowHeight + 1.5);
        $y = $this->GetY();

        $this->SetFont(config('pdf.font'), 'B', $size);
        $this->setAbsXY($this->rightEdge() - $labelWidth, $y);
        $this->MultiCell($labelWidth, $rowHeight, $label, 0, 'R', false, 0, null, null, true, 0, false, true, 0, 'M');

        $this->SetFont(config('pdf.font'), $boldValue ? 'B' : '', $size);
        $this->setAbsXY($this->leftMarginX(), $y);
        $this->MultiCell($valueWidth, $rowHeight, $value, 0, 'R', false, 0, null, null, true, 0, false, true, 0, 'M');

        $this->Line($this->leftMarginX(), $y + $rowHeight, $this->rightEdge(), $y + $rowHeight, [
            'width' => 0.2,
            'color' => self::BORDER,
        ]);
        $this->setAbsY($y + $rowHeight + 1.5);
    }

    /**
     * A label on its own line followed by wrapped free text — for the
     * comment, which (unlike the other facts) can run to multiple lines.
     */
    private function noteRow(string $label, string $text): void
    {
        $this->SetFont(config('pdf.font'), 'B', 9.5);
        $this->breakIfNeeded(4.5);
        $this->setAbsXY($this->rightEdge(), $this->GetY());
        $this->Cell($this->contentWidth(), 4.5, $label.':', 0, 1, 'R');

        $this->SetFont(config('pdf.font'), '', 9.5);
        $this->setTextColor(...self::MUTED);
        $textHeight = $this->getStringHeight($this->contentWidth(), $text);
        $this->breakIfNeeded($textHeight);
        $this->setAbsXY($this->leftMarginX(), $this->GetY());
        $this->MultiCell($this->contentWidth(), $textHeight, $text, 0, 'R', false, 1, null, null, true, 0, false, true, 0, 'T');
        $this->setTextColor(...self::INK);
    }

    /**
     * The classic Arabic accounting-voucher amount phrasing: "مبلغ وقدره"
     * (an amount of) the figure, then the amount spelled out in words (the
     * "فقط ... لا غير" / only-no-more phrasing is already part of
     * {@see ArabicNumber::amountToWords()}) — in a single
     * bordered box.
     */
    private function amountBlock(string $amount, string $words): void
    {
        $label = 'مبلغ وقدره';
        $figureWidth = $this->contentWidth() - 53;

        $this->SetFont(config('pdf.font'), 'B', 14);
        $amountHeight = 7;
        $this->SetFont(config('pdf.font'), '', 9.5);
        $wordsHeight = $this->getStringHeight($this->contentWidth() - 8, $words);
        $boxHeight = $amountHeight + $wordsHeight + 5;
        $this->breakIfNeeded($boxHeight);
        $y = $this->GetY();

        $this->setFillColor(...self::PANEL_FILL);
        $this->Rect($this->leftMarginX(), $y, $this->contentWidth(), $boxHeight, 'DF', [
            'width' => 0.3,
            'color' => self::BORDER_DARK,
        ]);

        $this->SetFont(config('pdf.font'), 'B', 10);
        $this->setAbsXY($this->rightEdge() - 4, $y + 2);
        $this->Cell(45, $amountHeight, $label.':', 0, 0, 'R');

        $this->SetFont(config('pdf.font'), 'B', 14);
        $this->setAbsXY($this->leftMarginX() + 4 + $figureWidth, $y + 2);
        $this->Cell($figureWidth, $amountHeight, $amount, 0, 0, 'L');

        $this->SetFont(config('pdf.font'), '', 9.5);
        $this->setTextColor(...self::MUTED);
        $this->setAbsXY($this->leftMarginX() + 4, $y + 3 + $amountHeight);
        $this->MultiCell($this->contentWidth() - 8, $wordsHeight, $words, 0, 'R', false, 1, null, null, true, 0, false, true, 0, 'T');
        $this->setTextColor(...self::INK);

        $this->setAbsY($y + $boxHeight);
    }

    /**
     * Two signature slots (recipient / accountant), each a short rule with
     * a caption beneath it.
     */
    private function signatures(): void
    {
        $width = $this->contentWidth() * 0.35;
        $rowHeight = 5;
        $this->breakIfNeeded($rowHeight);
        $y = $this->GetY();

        $this->Line($this->rightEdge() - $width, $y, $this->rightEdge(), $y, ['width' => 0.3, 'color' => self::BORDER_DARK]);
        $this->Line($this->leftMarginX(), $y, $this->leftMarginX() + $width, $y, ['width' => 0.3, 'color' => self::BORDER_DARK]);

        $this->SetFont(config('pdf.font'), '', 9);
        $this->setTextColor(...self::MUTED);
        $this->setAbsXY($this->rightEdge(), $y + 1.5);
        $this->Cell($width, $rowHeight, 'توقيع المستلم', 0, 0, 'C');
        $this->setAbsXY($this->leftMarginX() + $width, $y + 1.5);
        $this->Cell($width, $rowHeight, 'توقيع المحاسب', 0, 0, 'C');
        $this->setTextColor(...self::INK);

        $this->setAbsY($y + 1.5 + $rowHeight);
    }
}
