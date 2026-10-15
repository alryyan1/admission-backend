<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Operation extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'admission_id',
        'surgeon_id',
        'operation_number',
        'procedure_id',
        'price',
        'payment_method_id',
        'scheduled_at',
        'created_by',
        'entitlements_approved_at',
        'entitlements_approved_by',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'price' => 'decimal:2',
        'entitlements_approved_at' => 'datetime',
    ];

    protected $appends = ['is_paid', 'entitlements_approved'];

    protected static function booted(): void
    {
        static::created(function (Operation $operation) {
            $operation->forceFill([
                'operation_number' => (string) $operation->id,
            ])->saveQuietly();
        });
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function surgeon(): BelongsTo
    {
        return $this->belongsTo(Doctor::class, 'surgeon_id');
    }

    public function procedure(): BelongsTo
    {
        return $this->belongsTo(Procedure::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function teamMembers(): HasMany
    {
        return $this->hasMany(OperationTeamMember::class);
    }

    public function supplies(): HasMany
    {
        return $this->hasMany(OperationSupply::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function entitlementsApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entitlements_approved_by');
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(AdmissionDeposit::class);
    }

    /**
     * Derived from whether a deposit is linked to this operation, rather than stored
     * directly, so it can never drift out of sync with the deposit that backs it.
     */
    protected function isPaid(): Attribute
    {
        return Attribute::get(
            fn () => $this->relationLoaded('deposits') ? $this->deposits->isNotEmpty() : $this->deposits()->exists()
        );
    }

    protected function entitlementsApproved(): Attribute
    {
        return Attribute::get(fn () => $this->entitlements_approved_at !== null);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    /**
     * Once the team's entitlement distribution is approved, the roster and each
     * member's role/doctor/entitlement/payment method are locked; only an admin
     * can bypass this (after explicitly unapproving first).
     */
    public function assertEntitlementsEditable(User $user): void
    {
        if ($this->entitlements_approved_at !== null && $user->role !== 'admin') {
            throw ValidationException::withMessages([
                'entitlements_approved_at' => ['تم اعتماد توزيع الاستحقاقات لهذه العملية، ولا يمكن تعديله.'],
            ]);
        }
    }
}
