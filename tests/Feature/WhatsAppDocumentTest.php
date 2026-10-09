<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Operation;
use App\Models\Patient;
use App\Models\Procedure;
use App\Models\User;
use App\Models\WhatsAppRecipient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uploads_the_team_pdf_and_sends_it_to_every_recipient(): void
    {
        config([
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.access_token' => 'test-token',
            'services.whatsapp.operation_team_template.name' => 'operation_team_pdf',
            'services.whatsapp.operation_team_template.language' => 'ar',
            'services.whatsapp.default_country_code' => '20',
        ]);

        Http::fake([
            'graph.facebook.com/*/media' => Http::response(['id' => 'media-123'], 200),
            'graph.facebook.com/*/messages' => Http::response(['messages' => [['id' => 'wamid.test']]], 200),
        ]);

        $user = User::factory()->create();
        $admission = Admission::factory()->create();
        $procedure = Procedure::factory()->create(['name_ar' => 'استئصال الزائدة']);
        $operation = Operation::factory()->for($admission)->create(['procedure_id' => $procedure->id, 'price' => 50000]);

        WhatsAppRecipient::factory()->create(['phone' => '01011111111']);
        WhatsAppRecipient::factory()->create(['phone' => '01022222222']);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/operations/{$operation->id}/team.pdf/whatsapp");

        $response->assertOk();
        $this->assertCount(2, $response->json('results'));
        $this->assertTrue(collect($response->json('results'))->every(fn ($r) => $r['sent'] === true));

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/media'));
        Http::assertSentCount(3); // 1 media upload + 2 template sends
    }

    public function test_it_fails_when_no_recipients_are_configured(): void
    {
        config(['services.whatsapp.phone_number_id' => '1234567890', 'services.whatsapp.access_token' => 'test-token']);

        $user = User::factory()->create();
        $operation = Operation::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/operations/{$operation->id}/team.pdf/whatsapp")
            ->assertUnprocessable();
    }

    public function test_it_fails_when_whatsapp_is_not_configured(): void
    {
        config(['services.whatsapp.phone_number_id' => null, 'services.whatsapp.access_token' => null]);

        $user = User::factory()->create();
        $operation = Operation::factory()->create();
        WhatsAppRecipient::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/operations/{$operation->id}/team.pdf/whatsapp")
            ->assertUnprocessable();
    }

    public function test_it_sends_the_operation_invoice_to_the_patient(): void
    {
        config([
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.access_token' => 'test-token',
            'services.whatsapp.operation_invoice_template.name' => 'operation_invoice_pdf',
            'services.whatsapp.operation_invoice_template.language' => 'ar',
            'services.whatsapp.default_country_code' => '20',
        ]);

        Http::fake([
            'graph.facebook.com/*/media' => Http::response(['id' => 'media-123'], 200),
            'graph.facebook.com/*/messages' => Http::response(['messages' => [['id' => 'wamid.test']]], 200),
        ]);

        $user = User::factory()->create();
        $patient = Patient::factory()->create(['phone' => '01012345678']);
        $admission = Admission::factory()->create(['patient_id' => $patient->id]);
        $procedure = Procedure::factory()->create(['name_ar' => 'استئصال الزائدة']);
        $operation = Operation::factory()->for($admission)->create(['procedure_id' => $procedure->id, 'price' => 50000]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/operations/{$operation->id}/invoice.pdf/whatsapp");

        $response->assertOk()->assertJson(['sent' => true]);

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/media'));
        Http::assertSent(fn ($request) => ($request['to'] ?? null) === '201012345678');
        Http::assertSentCount(2); // 1 media upload + 1 template send
    }

    public function test_it_fails_to_send_the_operation_invoice_without_a_price(): void
    {
        config(['services.whatsapp.phone_number_id' => '1234567890', 'services.whatsapp.access_token' => 'test-token']);

        $user = User::factory()->create();
        $operation = Operation::factory()->create(['price' => null]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/operations/{$operation->id}/invoice.pdf/whatsapp")
            ->assertUnprocessable();
    }

    public function test_it_fails_to_send_the_operation_invoice_when_the_patient_has_no_phone(): void
    {
        config(['services.whatsapp.phone_number_id' => '1234567890', 'services.whatsapp.access_token' => 'test-token']);

        $user = User::factory()->create();
        $patient = Patient::factory()->create(['phone' => null]);
        $admission = Admission::factory()->create(['patient_id' => $patient->id]);
        $operation = Operation::factory()->for($admission)->create(['price' => 50000]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/operations/{$operation->id}/invoice.pdf/whatsapp")
            ->assertUnprocessable();
    }
}
