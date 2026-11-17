<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Doctor;
use App\Models\GuardianRelationship;
use App\Models\InsuranceCompany;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PatientControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admission_clerk_can_create_a_patient_linked_to_an_insurance_company(): void
    {
        $clerk = User::factory()->create(['role' => 'admission_clerk']);
        $insuranceCompany = InsuranceCompany::factory()->create(['name' => 'التعاونية']);

        $response = $this->actingAs($clerk, 'sanctum')->postJson('/api/patients', [
            'name' => 'أحمد',
            'insurance_company_id' => $insuranceCompany->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('insurance_company_id', $insuranceCompany->id)
            ->assertJsonPath('insurance_company.name', 'التعاونية');
        $this->assertDatabaseHas('patients', ['name' => 'أحمد', 'insurance_company_id' => $insuranceCompany->id]);
    }

    public function test_patient_can_be_created_without_an_insurance_company(): void
    {
        $clerk = User::factory()->create(['role' => 'admission_clerk']);

        $response = $this->actingAs($clerk, 'sanctum')->postJson('/api/patients', [
            'name' => 'أحمد',
        ]);

        $response->assertCreated()->assertJsonPath('insurance_company_id', null);
    }

    public function test_creating_a_patient_rejects_an_unknown_insurance_company(): void
    {
        $clerk = User::factory()->create(['role' => 'admission_clerk']);

        $response = $this->actingAs($clerk, 'sanctum')->postJson('/api/patients', [
            'name' => 'أحمد',
            'insurance_company_id' => 999999,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['insurance_company_id']);
        $this->assertDatabaseCount('patients', 0);
    }

    public function test_admission_clerk_can_create_a_patient_linked_to_a_company_and_guardian_relationship(): void
    {
        $clerk = User::factory()->create(['role' => 'admission_clerk']);
        $company = Company::factory()->create(['name' => 'شركة سابك']);
        $relationship = GuardianRelationship::factory()->create();

        $response = $this->actingAs($clerk, 'sanctum')->postJson('/api/patients', [
            'name' => 'أحمد',
            'company_id' => $company->id,
            'guardian_relationship_id' => $relationship->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('company_id', $company->id)
            ->assertJsonPath('company.name', 'شركة سابك')
            ->assertJsonPath('guardian_relationship_id', $relationship->id)
            ->assertJsonPath('guardian_relationship.name', $relationship->name);
        $this->assertDatabaseHas('patients', [
            'name' => 'أحمد',
            'company_id' => $company->id,
            'guardian_relationship_id' => $relationship->id,
        ]);
    }

    public function test_creating_a_patient_rejects_an_unknown_company(): void
    {
        $clerk = User::factory()->create(['role' => 'admission_clerk']);

        $response = $this->actingAs($clerk, 'sanctum')->postJson('/api/patients', [
            'name' => 'أحمد',
            'company_id' => 999999,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['company_id']);
        $this->assertDatabaseCount('patients', 0);
    }

    public function test_creating_a_patient_rejects_an_unknown_guardian_relationship(): void
    {
        $clerk = User::factory()->create(['role' => 'admission_clerk']);

        $response = $this->actingAs($clerk, 'sanctum')->postJson('/api/patients', [
            'name' => 'أحمد',
            'guardian_relationship_id' => 999999,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['guardian_relationship_id']);
        $this->assertDatabaseCount('patients', 0);
    }

    public function test_admission_clerk_can_create_a_patient_with_a_relative_contact(): void
    {
        $clerk = User::factory()->create(['role' => 'admission_clerk']);

        $response = $this->actingAs($clerk, 'sanctum')->postJson('/api/patients', [
            'name' => 'أحمد',
            'emergency_contact_name' => 'محمد',
            'emergency_contact_relationship' => 'أخ',
            'emergency_contact_phone' => '0501234567',
            'emergency_contact_address' => 'الرياض',
        ]);

        $response->assertCreated()->assertJsonPath('emergency_contact_name', 'محمد');
        $this->assertDatabaseHas('patients', [
            'name' => 'أحمد',
            'emergency_contact_name' => 'محمد',
            'emergency_contact_relationship' => 'أخ',
            'emergency_contact_phone' => '0501234567',
            'emergency_contact_address' => 'الرياض',
        ]);
    }

    public function test_admission_clerk_can_link_and_unlink_a_patient_insurance_company(): void
    {
        $clerk = User::factory()->create(['role' => 'admission_clerk']);
        $patient = Patient::factory()->create(['insurance_company_id' => null]);
        $insuranceCompany = InsuranceCompany::factory()->create();

        $linkResponse = $this->actingAs($clerk, 'sanctum')->patchJson("/api/patients/{$patient->id}", [
            'insurance_company_id' => $insuranceCompany->id,
        ]);

        $linkResponse->assertOk()
            ->assertJsonPath('insurance_company.id', $insuranceCompany->id);

        $unlinkResponse = $this->actingAs($clerk, 'sanctum')->patchJson("/api/patients/{$patient->id}", [
            'insurance_company_id' => null,
        ]);

        $unlinkResponse->assertOk()->assertJsonPath('insurance_company_id', null);
        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'insurance_company_id' => null]);
    }

    public function test_updating_a_patient_rejects_an_unknown_insurance_company(): void
    {
        $clerk = User::factory()->create(['role' => 'admission_clerk']);
        $patient = Patient::factory()->create(['insurance_company_id' => null]);

        $response = $this->actingAs($clerk, 'sanctum')->patchJson("/api/patients/{$patient->id}", [
            'insurance_company_id' => 999999,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['insurance_company_id']);
    }

    public function test_deleting_an_insurance_company_unlinks_its_patients_without_deleting_them(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $insuranceCompany = InsuranceCompany::factory()->create();
        $patient = Patient::factory()->create(['insurance_company_id' => $insuranceCompany->id]);

        $this->actingAs($admin, 'sanctum')->deleteJson("/api/insurance-companies/{$insuranceCompany->id}")
            ->assertNoContent();

        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'insurance_company_id' => null]);
    }

    public function test_admission_clerk_can_store_an_insurance_card_number_with_the_company(): void
    {
        $clerk = User::factory()->create(['role' => 'admission_clerk']);
        $insuranceCompany = InsuranceCompany::factory()->create();

        $response = $this->actingAs($clerk, 'sanctum')->postJson('/api/patients', [
            'name' => 'أحمد',
            'insurance_company_id' => $insuranceCompany->id,
            'insurance_card_number' => 'CARD-123456',
        ]);

        $response->assertCreated()->assertJsonPath('insurance_card_number', 'CARD-123456');
        $this->assertDatabaseHas('patients', ['name' => 'أحمد', 'insurance_card_number' => 'CARD-123456']);
    }

    public function test_admission_clerk_can_update_an_insurance_card_number(): void
    {
        $clerk = User::factory()->create(['role' => 'admission_clerk']);
        $patient = Patient::factory()->create(['insurance_card_number' => 'OLD-1']);

        $response = $this->actingAs($clerk, 'sanctum')->patchJson("/api/patients/{$patient->id}", [
            'insurance_card_number' => 'NEW-2',
        ]);

        $response->assertOk()->assertJsonPath('insurance_card_number', 'NEW-2');
    }

    public function test_insurance_card_number_cannot_exceed_one_hundred_characters(): void
    {
        $clerk = User::factory()->create(['role' => 'admission_clerk']);

        $response = $this->actingAs($clerk, 'sanctum')->postJson('/api/patients', [
            'name' => 'أحمد',
            'insurance_card_number' => str_repeat('9', 101),
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['insurance_card_number']);
    }

    public function test_patient_index_includes_the_insurance_company_name(): void
    {
        $user = User::factory()->create();
        $insuranceCompany = InsuranceCompany::factory()->create(['name' => 'بوبا']);
        Patient::factory()->create(['insurance_company_id' => $insuranceCompany->id]);

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/patients');

        $response->assertOk()->assertJsonPath('data.0.insurance_company.name', 'بوبا');
    }

    public function test_admission_clerk_can_store_admitting_and_referring_doctors_with_a_patient(): void
    {
        $clerk = User::factory()->create(['role' => 'admission_clerk']);
        $admittingDoctor = Doctor::factory()->create();
        $referringDoctor = Doctor::factory()->create();

        $response = $this->actingAs($clerk, 'sanctum')->postJson('/api/patients', [
            'name' => 'أحمد',
            'admitting_doctor_id' => $admittingDoctor->id,
            'referred_by_doctor_id' => $referringDoctor->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('admitting_doctor.id', $admittingDoctor->id)
            ->assertJsonPath('referred_by_doctor.id', $referringDoctor->id);
    }

    public function test_admission_clerk_can_set_and_clear_a_patients_doctors(): void
    {
        $clerk = User::factory()->create(['role' => 'admission_clerk']);
        $patient = Patient::factory()->create(['admitting_doctor_id' => null, 'referred_by_doctor_id' => null]);
        $doctor = Doctor::factory()->create();

        $setResponse = $this->actingAs($clerk, 'sanctum')->patchJson("/api/patients/{$patient->id}", [
            'admitting_doctor_id' => $doctor->id,
            'referred_by_doctor_id' => $doctor->id,
        ]);

        $setResponse->assertOk()
            ->assertJsonPath('admitting_doctor.id', $doctor->id)
            ->assertJsonPath('referred_by_doctor.id', $doctor->id);

        $clearResponse = $this->actingAs($clerk, 'sanctum')->patchJson("/api/patients/{$patient->id}", [
            'admitting_doctor_id' => null,
        ]);

        $clearResponse->assertOk()->assertJsonPath('admitting_doctor_id', null);
        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'referred_by_doctor_id' => $doctor->id]);
    }

    public function test_updating_a_patient_rejects_an_unknown_doctor(): void
    {
        $clerk = User::factory()->create(['role' => 'admission_clerk']);
        $patient = Patient::factory()->create();

        $response = $this->actingAs($clerk, 'sanctum')->patchJson("/api/patients/{$patient->id}", [
            'admitting_doctor_id' => 999999,
            'referred_by_doctor_id' => 999999,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['admitting_doctor_id', 'referred_by_doctor_id']);
    }

    public function test_creating_a_patient_links_it_to_a_newly_registered_jawda_medical_patient(): void
    {
        Http::fake([
            '*/public/admission-patients' => Http::response(['data' => ['id' => 777]], 201),
        ]);
        $clerk = User::factory()->create(['role' => 'admission_clerk']);
        $admittingDoctor = Doctor::factory()->create(['name' => 'د. أحمد الطيب']);

        $response = $this->actingAs($clerk, 'sanctum')->postJson('/api/patients', [
            'name' => 'أحمد',
            'gender' => 'male',
            'admitting_doctor_id' => $admittingDoctor->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('patients', [
            'name' => 'أحمد',
            'jawda_patient_id' => 777,
            'is_local_only' => false,
        ]);

        Http::assertSent(fn ($request) => $request['doctor_name'] === 'د. أحمد الطيب');
    }

    public function test_creating_a_patient_stays_local_only_when_jawda_medical_registration_fails(): void
    {
        Http::fake([
            '*/public/admission-patients' => Http::response(['message' => 'لا توجد وردية مفتوحة حالياً.'], 400),
        ]);
        $clerk = User::factory()->create(['role' => 'admission_clerk']);

        $response = $this->actingAs($clerk, 'sanctum')->postJson('/api/patients', [
            'name' => 'أحمد',
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('patients', [
            'name' => 'أحمد',
            'jawda_patient_id' => null,
            'is_local_only' => true,
        ]);
    }

    public function test_admission_clerk_can_retry_linking_a_local_only_patient_to_jawda_medical(): void
    {
        Http::fake([
            '*/public/admission-patients' => Http::response(['data' => ['id' => 999]], 201),
        ]);
        $clerk = User::factory()->create(['role' => 'admission_clerk']);
        $patient = Patient::factory()->create(['jawda_patient_id' => null, 'is_local_only' => true]);

        $response = $this->actingAs($clerk, 'sanctum')->postJson("/api/patients/{$patient->id}/link-jawda-medical");

        $response->assertOk()
            ->assertJsonPath('jawda_patient_id', 999)
            ->assertJsonPath('is_local_only', false);
        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'jawda_patient_id' => 999,
            'is_local_only' => false,
        ]);
    }

    public function test_retrying_the_link_reports_an_error_when_jawda_medical_registration_fails(): void
    {
        Http::fake([
            '*/public/admission-patients' => Http::response(['message' => 'لا توجد وردية مفتوحة حالياً.'], 400),
        ]);
        $clerk = User::factory()->create(['role' => 'admission_clerk']);
        $patient = Patient::factory()->create(['jawda_patient_id' => null, 'is_local_only' => true]);

        $response = $this->actingAs($clerk, 'sanctum')->postJson("/api/patients/{$patient->id}/link-jawda-medical");

        $response->assertStatus(502);
        $this->assertDatabaseHas('patients', [
            'id' => $patient->id,
            'jawda_patient_id' => null,
            'is_local_only' => true,
        ]);
    }

    public function test_retrying_the_link_on_an_already_linked_patient_is_a_no_op(): void
    {
        $clerk = User::factory()->create(['role' => 'admission_clerk']);
        $patient = Patient::factory()->create(['jawda_patient_id' => 123, 'is_local_only' => false]);

        $response = $this->actingAs($clerk, 'sanctum')->postJson("/api/patients/{$patient->id}/link-jawda-medical");

        $response->assertOk()->assertJsonPath('jawda_patient_id', 123);
        Http::assertNothingSent();
    }
}
