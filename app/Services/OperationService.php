<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\Operation;
use App\Models\User;

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
        $operation->update($data);

        return $operation;
    }
}
