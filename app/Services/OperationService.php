<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\Operation;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class OperationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function schedule(Admission $admission, array $data, User $user): Operation
    {
        return $admission->operations()->create([
            ...$data,
            'created_by' => $user->id,
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

    public function start(Operation $operation): Operation
    {
        if ($operation->status !== 'scheduled') {
            throw ValidationException::withMessages([
                'status' => ['لا يمكن بدء عملية غير مجدولة.'],
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
