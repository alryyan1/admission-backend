<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Operation;
use App\Models\WhatsAppRecipient;
use App\Services\PdfDocumentService;
use App\Services\WhatsAppService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response as HttpResponse;

class WhatsAppDocumentController extends Controller
{
    /**
     * Renders the operation-team PDF, uploads it once to the Cloud API's Media
     * endpoint, then sends the operation_team_template document-header template
     * to every configured recipient, reusing the same media id for each send.
     */
    public function operationTeam(Operation $operation, WhatsAppService $whatsApp, PdfDocumentService $documents): JsonResponse
    {
        abort_unless($whatsApp->isConfigured(), HttpResponse::HTTP_UNPROCESSABLE_ENTITY, 'خدمة واتساب غير مُفعّلة.');

        $recipients = WhatsAppRecipient::query()->get();
        abort_if($recipients->isEmpty(), HttpResponse::HTTP_UNPROCESSABLE_ENTITY, 'لم يتم تحديد أي أرقام استلام في إعدادات واتساب.');

        $filename = 'team-'.($operation->operation_number ?? $operation->id).'.pdf';
        $pdfContent = $documents->operationTeam($operation)->getContent();

        $mediaId = $whatsApp->uploadMedia($pdfContent, $filename);

        $templateName = (string) config('services.whatsapp.operation_team_template.name');
        $languageCode = (string) config('services.whatsapp.operation_team_template.language');
        $procedureName = $operation->procedure?->name_ar ?? ('#'.$operation->id);

        $results = $recipients->map(function (WhatsAppRecipient $recipient) use ($whatsApp, $templateName, $languageCode, $procedureName, $mediaId, $filename) {
            try {
                $whatsApp->sendTemplate(
                    toPhone: $recipient->phone,
                    templateName: $templateName,
                    languageCode: $languageCode,
                    bodyParameters: [
                        ['type' => 'text', 'text' => $procedureName],
                    ],
                    headerParameters: [
                        ['type' => 'document', 'document' => ['id' => $mediaId, 'filename' => $filename]],
                    ],
                );

                return ['phone' => $recipient->phone, 'label' => $recipient->label, 'sent' => true];
            } catch (\Throwable $exception) {
                return ['phone' => $recipient->phone, 'label' => $recipient->label, 'sent' => false, 'message' => $exception->getMessage()];
            }
        });

        return response()->json(['results' => $results]);
    }

    /**
     * Renders the operation's preliminary invoice PDF and sends it to the
     * patient's own WhatsApp number as a document-header template.
     */
    public function operationInvoice(Operation $operation, WhatsAppService $whatsApp, PdfDocumentService $documents): JsonResponse
    {
        abort_unless($whatsApp->isConfigured(), HttpResponse::HTTP_UNPROCESSABLE_ENTITY, 'خدمة واتساب غير مُفعّلة.');
        abort_if($operation->price === null, HttpResponse::HTTP_UNPROCESSABLE_ENTITY, 'حدّد سعر العملية أولاً.');

        $operation->loadMissing('procedure', 'admission.patient');
        $phone = $operation->admission->patient?->phone;
        abort_if(blank($phone), HttpResponse::HTTP_UNPROCESSABLE_ENTITY, 'لا يوجد رقم واتساب مسجل للمريض.');

        $filename = 'invoice-'.($operation->operation_number ?? $operation->id).'.pdf';
        $pdfContent = $documents->operationInvoice($operation)->getContent();

        $mediaId = $whatsApp->uploadMedia($pdfContent, $filename);

        try {
            $whatsApp->sendTemplate(
                toPhone: $phone,
                templateName: (string) config('services.whatsapp.operation_invoice_template.name'),
                languageCode: (string) config('services.whatsapp.operation_invoice_template.language'),
                bodyParameters: [
                    ['type' => 'text', 'text' => $operation->procedure?->name_ar ?? ('#'.$operation->id)],
                ],
                headerParameters: [
                    ['type' => 'document', 'document' => ['id' => $mediaId, 'filename' => $filename]],
                ],
            );

            return response()->json(['sent' => true, 'message' => 'تم إرسال الفاتورة إلى المريض عبر واتساب.']);
        } catch (\Throwable $exception) {
            return response()->json(['sent' => false, 'message' => 'تعذر إرسال الفاتورة: '.$exception->getMessage()]);
        }
    }
}
