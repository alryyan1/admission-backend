<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\Operation;
use App\Models\Procedure;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function schedule(Admission $admission, array $data, User $user): Operation
    {
        return DB::transaction(function () use ($admission, $data, $user) {
            $operation = $admission->operations()->create([
                ...$data,
                'created_by' => $user->id,
            ]);

            $this->addOperationRequestedService($admission, $operation);

            return $operation;
        });
    }

    private function addOperationRequestedService(Admission $admission, Operation $operation): void
    {
        $procedure = Procedure::find($operation->procedure_id);

        if (! $procedure) {
            return;
        }

        $service = Service::firstOrCreate(
            ['name_ar' => $procedure->name_ar],
            ['name_en' => $procedure->name_en, 'price' => 0, 'is_active' => true],
        );

        $admission->requestedServices()->create([
            'name' => $service->name_ar,
            'quantity' => 1,
            'unit_price' => $service->price,
            'is_auto_added' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Operation $operation, array $data): Operation
    {
        if ($operation->status !== 'scheduled') {
            throw ValidationException::withMessages([
                'status' => ['لا يمكن تعديل عملية بعد بدئها.'],
            ]);
        }

        $operation->update($data);

        return $operation;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function prepare(Operation $operation, array $data): Operation
    {
        if ($operation->status !== 'scheduled') {
            throw ValidationException::withMessages([
                'status' => ['لا يمكن تجهيز عملية بعد بدئها.'],
            ]);
        }

        $operation->update([
            ...$data,
            'prepared_at' => now(),
            'prepared_by' => auth()->id(),
        ]);

        return $operation;
    }

    public function start(Operation $operation): Operation
    {
        if ($operation->status !== 'scheduled') {
            throw ValidationException::withMessages([
                'status' => ['لا يمكن بدء عملية غير مجدولة.'],
            ]);
        }

        if (! $operation->isPrepared()) {
            throw ValidationException::withMessages([
                'status' => ['يجب إكمال تجهيز المريض قبل بدء العملية.'],
            ]);
        }

        $operation->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        return $operation;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function complete(Operation $operation, array $data): Operation
    {
        if ($operation->status !== 'in_progress') {
            throw ValidationException::withMessages([
                'status' => ['لا يمكن إنهاء عملية غير جارية.'],
            ]);
        }

        $operation->update([
            'status' => 'completed',
            'ended_at' => now(),
            'notes' => $data['notes'] ?? $operation->notes,
            'findings' => $data['findings'] ?? null,
            'complications' => $data['complications'] ?? null,
            'blood_loss_ml' => $data['blood_loss_ml'] ?? null,
            'outcome' => $data['outcome'] ?? null,
            'report_notes' => $data['report_notes'] ?? null,
        ]);

        return $operation;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function cancel(Operation $operation, array $data): Operation
    {
        if (! in_array($operation->status, ['scheduled', 'in_progress'], true)) {
            throw ValidationException::withMessages([
                'status' => ['لا يمكن إلغاء عملية منتهية أو ملغاة بالفعل.'],
            ]);
        }

        $operation->update([
            'status' => 'cancelled',
            'cancellation_reason' => $data['cancellation_reason'] ?? null,
            'cancelled_at' => now(),
        ]);

        return $operation;
    }
}
