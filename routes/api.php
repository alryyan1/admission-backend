<?php

use App\Http\Controllers\Api\AdmissionController;
use App\Http\Controllers\Api\AdmissionDepositController;
use App\Http\Controllers\Api\AdmissionInvoiceController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BedController;
use App\Http\Controllers\Api\DoctorController;
use App\Http\Controllers\Api\DoctorOrderController;
use App\Http\Controllers\Api\FloorController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\NursingAssignmentController;
use App\Http\Controllers\Api\OperationController;
use App\Http\Controllers\Api\PatientController;
use App\Http\Controllers\Api\ProcedureCategoryController;
use App\Http\Controllers\Api\ProcedureController;
use App\Http\Controllers\Api\RequestedServiceController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\StatisticsController;
use App\Http\Controllers\Api\TreatmentDoseController;
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

    Route::apiResource('rooms', RoomController::class)->only(['index', 'show']);
    Route::apiResource('rooms', RoomController::class)->only(['store', 'update', 'destroy'])->middleware('role:admin');

    Route::apiResource('beds', BedController::class)->only(['index', 'show']);
    Route::apiResource('beds', BedController::class)->only(['store', 'update', 'destroy'])->middleware('role:admin');

    Route::get('/patients/search-jawda', [PatientController::class, 'searchJawda']);
    Route::post('/patients/import-jawda', [PatientController::class, 'importJawda'])->middleware('role:admin,admission_clerk');
    Route::apiResource('patients', PatientController::class)->only(['index', 'show']);
    Route::apiResource('patients', PatientController::class)->only(['store'])->middleware('role:admin,admission_clerk');
    Route::patch('/patients/{patient}', [PatientController::class, 'update'])->middleware('role:admin,admission_clerk');

    Route::get('/doctors', [DoctorController::class, 'index']);

    Route::apiResource('procedure-categories', ProcedureCategoryController::class)->only(['index']);
    Route::apiResource('procedure-categories', ProcedureCategoryController::class)->only(['store', 'update', 'destroy'])->middleware('role:admin');

    Route::apiResource('procedures', ProcedureController::class)->only(['index']);
    Route::apiResource('procedures', ProcedureController::class)->only(['store', 'update', 'destroy'])->middleware('role:admin');

    Route::apiResource('admissions', AdmissionController::class)->only(['index', 'show']);
    Route::post('/admissions', [AdmissionController::class, 'store'])->middleware('role:admin,admission_clerk');
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

    Route::get('/admissions/{admission}/services', [RequestedServiceController::class, 'index']);
    Route::post('/admissions/{admission}/services', [RequestedServiceController::class, 'store'])->middleware('role:admin,nurse,doctor');

    Route::get('/admissions/{admission}/operations', [OperationController::class, 'index']);
    Route::post('/admissions/{admission}/operations', [OperationController::class, 'store'])->middleware('role:admin,doctor');

    Route::get('/operations', [OperationController::class, 'all']);
    Route::get('/operations/{operation}', [OperationController::class, 'show']);
    Route::patch('/operations/{operation}', [OperationController::class, 'update'])->middleware('role:admin,doctor');
    Route::patch('/operations/{operation}/prepare', [OperationController::class, 'prepare'])->middleware('role:admin,doctor,nurse');
    Route::patch('/operations/{operation}/start', [OperationController::class, 'start'])->middleware('role:admin,doctor');
    Route::patch('/operations/{operation}/complete', [OperationController::class, 'complete'])->middleware('role:admin,doctor');
    Route::patch('/operations/{operation}/cancel', [OperationController::class, 'cancel'])->middleware('role:admin,doctor');
    Route::post('/operations/{operation}/team-members', [OperationController::class, 'addTeamMember'])->middleware('role:admin,doctor');
    Route::delete('/operations/{operation}/team-members/{teamMember}', [OperationController::class, 'removeTeamMember'])->middleware('role:admin,doctor');
    Route::post('/operations/{operation}/supplies', [OperationController::class, 'addSupply'])->middleware('role:admin,doctor,nurse');
    Route::delete('/operations/{operation}/supplies/{supply}', [OperationController::class, 'removeSupply'])->middleware('role:admin,doctor,nurse');

    Route::get('/admissions/{admission}/invoice', [AdmissionInvoiceController::class, 'show']);

    Route::get('/admissions/{admission}/invoices', [InvoiceController::class, 'index']);
    Route::post('/admissions/{admission}/invoices', [InvoiceController::class, 'store'])->middleware('role:admin,cashier');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::patch('/invoices/{invoice}/pay', [InvoiceController::class, 'markPaid'])->middleware('role:admin,cashier');

    Route::get('/statistics/occupancy', [StatisticsController::class, 'occupancy']);
    Route::get('/statistics/admissions', [StatisticsController::class, 'admissions']);
    Route::get('/statistics/financials', [StatisticsController::class, 'financials']);
    Route::get('/statistics/doctors-services', [StatisticsController::class, 'doctorsAndServices']);
    Route::get('/statistics/operations', [StatisticsController::class, 'operations']);

    Route::get('/sessions', [SessionController::class, 'index'])->middleware('role:admin');
    Route::delete('/sessions/{token}', [SessionController::class, 'destroy'])->middleware('role:admin');
    Route::delete('/users/{user}/sessions', [SessionController::class, 'destroyForUser'])->middleware('role:admin');
});
