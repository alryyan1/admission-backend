<?php

namespace App\Services;

use App\Support\Pdf\LetterheadPdf;
use Illuminate\Http\Response;

class PdfRenderer
{
    /**
     * Start a new letterhead PDF: creates the document, adds the first page,
     * and paints the watermark. Callers draw content directly on the
     * returned instance before passing it to toResponse().
     */
    public function make(string $title, string $orientation = 'P', string $format = 'A4'): LetterheadPdf
    {
        $pdf = new LetterheadPdf($orientation, $format);
        $pdf->setDocumentTitle($title);
        $pdf->setTitle($title);
        $pdf->AddPage();
        $pdf->drawWatermark();
        $pdf->setTextColor(31, 41, 55);

        return $pdf;
    }

    /**
     * Stamp (if requested) and wrap a finished PDF into an inline
     * (browser-previewable) HTTP response.
     */
    public function toResponse(LetterheadPdf $pdf, string $filename, bool $withStamp = true): Response
    {
        if ($withStamp) {
            $pdf->drawStamp();
        }

        return new Response($pdf->Output('document.pdf', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'Cache-Control' => 'no-store, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }
}
