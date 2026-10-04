<?php

use App\Http\Controllers\Api\AccountantController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AdmissionController;
use App\Http\Controllers\Api\AdmissionDepositController;
use App\Http\Controllers\Api\AdmissionInvoiceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\BedController;
use App\Http\Controllers\Api\ChartOpeningServiceSettingController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\DoctorOrderController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\FacilitySettingController;
use App\Http\Controllers\Api\FloorController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\NursingAssignmentController;
use App\Http\Controllers\Api\OperationController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\PaymentMethodController;
use App\Http\Controllers\Api\PdfController;
use App\Http\Controllers\Api\ProcedureCategoryController;
use App\Http\Controllers\Api\ProcedureController;
use App\Http\Controllers\Api\InsuranceCompanyController;
use App\Http\Controllers\Api\RequestedServiceController;
use App\Http\Controllers\Api\RevenueCalculatorController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\RoomTypeController;
use App\Http\Controllers\Api\ServiceCategoryController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\SpecialistController;
use App\Http\Controllers\Api\StatisticsController;
use App\Http\Controllers\Api\TeamRoleController;
use App\Http\Controllers\Api\TreatmentDoseController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VitalSignController;
use App\Http\Controllers\Api\WardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::apiResource('floors', FloorController::class)->only(['index', 'show']);
    Route::apiResource('floors', FloorController::class)->only(['store', 'update', 'destroy'])->middleware('role:admin');

    Route::apiResource('wards', WardController::class)->only(['index', 'show']);
    Route::apiResource('wards', WardController::class)->only(['store', 'update', 'destroy'])->middleware('role:admin');

    Route::apiResource('room-types', RoomTypeController::class)->only(['index']);
    Route::apiResource('room-types', RoomTypeController::class)->only(['store', 'update', 'destroy'])->middleware('role:admin');

    Route::apiResource('rooms', RoomController::class)->only(['index', 'show']);
    Route::apiResource('rooms', RoomController::class)->only(['store', 'update', 'destroy'])->middleware('role:admin');

    Route::apiResource('beds', BedController::class)->only(['index', 'show']);
    Route::apiResource('beds', BedController::class)->only(['store', 'update', 'destroy'])->middleware('role:admin');

    Route::get('/patients/search-jawda', [PatientController::class, 'searchJawda']);
    Route::post('/patients/import-jawda', [PatientController::class, 'importJawda'])->middleware('role:admin,admission_clerk');
    Route::apiResource('patients', PatientController::class)->only(['index', 'show']);
    Route::apiResource('patients', PatientController::class)->only(['store'])->middleware('role:admin,admission_clerk');
    Route::patch('/patients/{patient}', [PatientController::class, 'update'])->middleware('role:admin,admission_clerk');

    Route::apiResource('doctors', DoctorController::class)->only(['index']);
    Route::apiResource('doctors', DoctorController::class)->only(['store', 'update', 'destroy'])->middleware('role:admin');

    Route::apiResource('team-roles', TeamRoleController::class)->only(['index']);
    Route::apiResource('team-roles', TeamRoleController::class)->only(['store', 'update', 'destroy'])->middleware('role:admin');

    Route::apiResource('specialists', SpecialistController::class)->only(['index']);
    Route::apiResource('specialists', SpecialistController::class)->only(['store', 'update', 'destroy'])->middleware('role:admin');

    Route::apiResource('insurance-companies', InsuranceCompanyController::class)->only(['index']);
    Route::apiResource('insurance-companies', InsuranceCompanyController::class)->only(['store', 'update', 'destroy'])->middleware('role:admin');

    Route::apiResource('users', UserController::class)->only(['index', 'store', 'update', 'destroy'])->middleware('role:admin');

    Route::apiResource('procedure-categories', ProcedureCategoryController::class)->only(['index']);
    Route::apiResource('procedure-categories', ProcedureCategoryController::class)->only(['store', 'update', 'destroy'])->middleware('role:admin');

    Route::apiResource('procedures', ProcedureController::class)->only(['index']);
    Route::apiResource('procedures', ProcedureController::class)->only(['store', 'update', 'destroy'])->middleware('role:admin');

    Route::apiResource('service-categories', ServiceCategoryController::class)->only(['index']);
    Route::apiResource('service-categories', ServiceCategoryController::class)->only(['store', 'update', 'destroy'])->middleware('role:admin');

    Route::apiResource('services', ServiceController::class)->only(['index']);
    Route::apiResource('services', ServiceController::class)->only(['store', 'update', 'destroy'])->middleware('role:admin');

    Route::apiResource('payment-methods', PaymentMethodController::class)->only(['index']);
    Route::apiResource('payment-methods', PaymentMethodController::class)->only(['store', 'update', 'destroy'])->middleware('role:admin');

    Route::apiResource('expenses', ExpenseController::class)->middleware('role:admin,cashier');

    Route::middleware('role:admin')->group(function () {
        Route::get('/settings/chart-opening-service', [ChartOpeningServiceSettingController::class, 'show']);
        Route::put('/settings/chart-opening-service', [ChartOpeningServiceSettingController::class, 'update']);

        Route::get('/settings/facility', [FacilitySettingController::class, 'show']);
        Route::post('/settings/facility', [FacilitySettingController::class, 'update']);
        Route::get('/settings/facility/logo', [FacilitySettingController::class, 'logo']);
        Route::get('/settings/facility/stamp', [FacilitySettingController::class, 'stamp']);
        Route::get('/settings/facility/watermark', [FacilitySettingController::class, 'watermark']);
    });

    Route::apiResource('admissions', AdmissionController::class)->only(['index', 'show']);
    Route::post('/admissions', [AdmissionController::class, 'store'])->middleware('role:admin,admission_clerk');
    Route::patch('/admissions/{admission}', [AdmissionController::class, 'update'])->middleware('role:admin,admission_clerk,doctor');
    Route::patch('/admissions/{admission}/discharge', [AdmissionController::class, 'discharge'])->middleware('role:admin,doctor');
    Route::patch('/admissions/{admission}/cancel', [AdmissionController::class, 'cancel'])->middleware('role:admin,admission_clerk');

    Route::get('/admissions/{admission}/vitals', [VitalSignController::class, 'index']);
    Route::post('/admissions/{admission}/vitals', [VitalSignController::class, 'store'])->middleware('role:admin,nurse');

    Route::get('/admissions/{admission}/orders', [DoctorOrderController::class, 'index']);
    Route::post('/admissions/{admission}/orders', [DoctorOrderController::class, 'store'])->middleware('role:admin,doctor');
    Route::post('/orders/{order}/doses', [TreatmentDoseController::class, 'store'])->middleware('role:admin,nurse');

    Route::apiResource('nursing-assignments', NursingAssignmentController::class)->only(['index']);
    Route::apiResource('nursing-assignments', NursingAssignmentController::class)->only(['store', 'destroy'])->middleware('role:admin');

    Route::get('/admissions/{admission}/deposits', [AdmissionDepositController::class, 'index']);
    Route::post('/admissions/{admission}/deposits', [AdmissionDepositController::class, 'store'])->middleware('role:admin,cashier');
    Route::delete('/admissions/{admission}/deposits/{deposit}', [AdmissionDepositController::class, 'destroy'])->middleware('role:admin,cashier');

    Route::get('/admissions/{admission}/services', [RequestedServiceController::class, 'index']);
    Route::post('/admissions/{admission}/services', [RequestedServiceController::class, 'store'])->middleware('role:admin,nurse,doctor');
    Route::post('/admissions/{admission}/services/accommodation-fee', [RequestedServiceController::class, 'accommodationFee'])->middleware('role:admin,nurse,doctor');
    Route::patch('/admissions/{admission}/services/{requestedService}', [RequestedServiceController::class, 'update'])->middleware('role:admin,nurse,doctor');
    Route::delete('/admissions/{admission}/services/{requestedService}', [RequestedServiceController::class, 'destroy'])->middleware('role:admin,nurse,doctor');

    Route::get('/admissions/{admission}/operations', [OperationController::class, 'index']);
    Route::post('/admissions/{admission}/operations', [OperationController::class, 'store'])->middleware('role:admin,doctor');

    Route::get('/operations', [OperationController::class, 'all']);
    Route::get('/operations/{operation}', [OperationController::class, 'show']);
    Route::patch('/operations/{operation}', [OperationController::class, 'update'])->middleware('role:admin,doctor');
    Route::post('/operations/{operation}/team-members', [OperationController::class, 'addTeamMember'])->middleware('role:admin,doctor');
    Route::delete('/operations/{operation}/team-members/{teamMember}', [OperationController::class, 'removeTeamMember'])->middleware('role:admin,doctor');
    Route::post('/operations/{operation}/supplies', [OperationController::class, 'addSupply'])->middleware('role:admin,doctor,nurse');
    Route::delete('/operations/{operation}/supplies/{supply}', [OperationController::class, 'removeSupply'])->middleware('role:admin,doctor,nurse');

    Route::patch('/accountant/team-members/{teamMember}/entitlement', [AccountantController::class, 'updateEntitlement'])->middleware('role:admin,cashier');

    Route::get('/admissions/{admission}/invoice', [AdmissionInvoiceController::class, 'show']);

    Route::get('/admissions/{admission}/invoices', [InvoiceController::class, 'index']);
    Route::post('/admissions/{admission}/invoices', [InvoiceController::class, 'store'])->middleware('role:admin,cashier');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::patch('/invoices/{invoice}/pay', [InvoiceController::class, 'markPaid'])->middleware('role:admin,cashier');

    Route::get('/admissions/{admission}/deposits/{deposit}/receipt.pdf', [PdfController::class, 'depositReceipt']);
    Route::get('/admissions/{admission}/invoice.pdf', [PdfController::class, 'admissionInvoice']);
    Route::get('/admissions/{admission}/account-statement.pdf', [PdfController::class, 'accountStatement']);
    Route::get('/admissions/{admission}/summary.pdf', [PdfController::class, 'admissionSummary']);
    Route::get('/invoices/{invoice}/invoice.pdf', [PdfController::class, 'finalInvoice']);
    Route::get('/operations/{operation}/invoice.pdf', [PdfController::class, 'operationInvoice']);
    Route::get('/operations/{operation}/team.pdf', [PdfController::class, 'operationTeam']);

    Route::get('/reports/revenue-calculator', [RevenueCalculatorController::class, 'show']);
    Route::get('/reports/revenue-calculator.pdf', [PdfController::class, 'revenueCalculator']);

    Route::get('/statistics/occupancy', [StatisticsController::class, 'occupancy']);
    Route::get('/statistics/admissions', [StatisticsController::class, 'admissions']);
    Route::get('/statistics/financials', [StatisticsController::class, 'financials']);
    Route::get('/statistics/doctors-services', [StatisticsController::class, 'doctorsAndServices']);
    Route::get('/statistics/operations', [StatisticsController::class, 'operations']);

    Route::get('/sessions', [SessionController::class, 'index'])->middleware('role:admin');
    Route::delete('/sessions/{token}', [SessionController::class, 'destroy'])->middleware('role:admin');
    Route::delete('/users/{user}/sessions', [SessionController::class, 'destroyForUser'])->middleware('role:admin');

    Route::middleware('role:admin')->group(function () {
        Route::get('/activity-logs', [ActivityLogController::class, 'index']);
        Route::get('/activity-logs/subject-types', [ActivityLogController::class, 'subjectTypes']);
        Route::get('/activity-logs/causers', [ActivityLogController::class, 'causers']);

        Route::get('/backups', [BackupController::class, 'index']);
        Route::post('/backups', [BackupController::class, 'store']);
        Route::get('/backups/{filename}/download', [BackupController::class, 'download'])->where('filename', '.*');
        Route::delete('/backups/{filename}', [BackupController::class, 'destroy'])->where('filename', '.*');
    });
});
