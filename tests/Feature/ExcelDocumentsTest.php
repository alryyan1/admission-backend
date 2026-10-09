<?php

namespace Tests\Feature;

use App\Models\Admission;
use App\Models\Operation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExcelDocumentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_operations_report_xlsx(): void
    {
        $user = User::factory()->create();
        $admission = Admission::factory()->create();
        $operation = Operation::factory()->for($admission)->create(['price' => 50000]);
        $operation->teamMembers()->create(['name' => 'جراح', 'entitlement_amount' => 20000]);

        $response = $this->actingAs($user, 'sanctum')->get('/api/operations/report.xlsx');

        $response->assertOk();
        $this->assertSame(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $response->headers->get('Content-Type'),
        );
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
    }

    public function test_operations_report_xlsx_requires_authentication(): void
    {
        $this->getJson('/api/operations/report.xlsx')->assertUnauthorized();
    }
}
