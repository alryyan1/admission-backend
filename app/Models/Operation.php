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

    protected $appends = ['is_paid', 'amount_paid', 'remaining_amount', 'entitlements_approved'];

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
     * Sum of every deposit linked to this operation, derived rather than stored so it
     * can never drift out of sync with the deposits that back it.
     */
    protected function amountPaid(): Attribute
    {
        return Attribute::get(
            fn () => round((float) ($this->relationLoaded('deposits') ? $this->deposits->sum('amount') : $this->deposits()->sum('amount')), 2)
        );
    }

    /**
     * Outstanding balance on the operation's price, floored at zero.
     */
    protected function remainingAmount(): Attribute
    {
        return Attribute::get(function () {
            if ($this->price === null) {
                return null;
            }

            return max(round((float) $this->price - $this->amount_paid, 2), 0.0);
        });
    }

    /**
     * Whether the operation's price has been paid in full (as opposed to partially paid).
     */
    protected function isPaid(): Attribute
    {
        return Attribute::get(function () {
            if ($this->price === null) {
                return false;
            }

            return $this->amount_paid >= round((float) $this->price, 2);
        });
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
