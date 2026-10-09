<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\Operation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

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

    /**
     * The surgeon/date/search filters shared by the operations listing, PDF report, and Excel export.
     */
    public function filteredQuery(Request $request): Builder
    {
        $query = Operation::query();

        if ($request->filled('surgeon_id')) {
            $query->where('surgeon_id', $request->integer('surgeon_id'));
        }

        if ($request->filled('date')) {
            $query->whereDate('scheduled_at', $request->date('date'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date('date_to'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('procedure', fn ($p) => $p->where('name_ar', 'like', "%{$search}%")->orWhere('name_en', 'like', "%{$search}%"))
                    ->orWhereHas('admission.patient', fn ($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        return $query;
    }
}
