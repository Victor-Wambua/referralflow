<?php

namespace App\Models;

use App\Security\ReferralAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Referral extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected function casts(): array
    {
        $dt = 'datetime';

        return [
            'reason_text' => 'encrypted',
            'clinical_note' => 'encrypted',
            'form_data' => 'encrypted:array',
            'terminal_note' => 'encrypted',
            'include_note' => 'boolean',
            'stale_confirmed' => 'boolean',
            'is_synthetic' => 'boolean',
            'sent_at' => $dt, 'received_at' => $dt, 'accepted_at' => $dt, 'scheduled_at' => $dt,
            'appointment_at' => $dt, 'attended_at' => $dt, 'completed_at' => $dt, 'declined_at' => $dt,
            'cancelled_at' => $dt, 'info_requested_at' => $dt, 'info_replied_at' => $dt,
            'last_escalated_at' => $dt, 'overdue_flagged_at' => $dt, 'purged_at' => $dt,
        ];
    }

    protected static function booted(): void
    {
        // The single visibility layer: every query is constrained by ReferralAccess.
        static::addGlobalScope('access', function (Builder $q) {
            ReferralAccess::constrain($q);
        });
    }

    public function newUniqueId(): string
    {
        return strtolower((string) \Illuminate\Support\Str::ulid());
    }

    /** Short display reference. Opaque: derived from the random tail of the id only. */
    public function displayRef(): string
    {
        return 'RF-'.strtoupper(substr($this->id, -8));
    }

    public function referringFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'referring_facility_id');
    }

    public function receivingFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'receiving_facility_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withoutGlobalScopes();
    }

    public function consent(): HasOne
    {
        return $this->hasOne(Consent::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ReferralEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id');
    }

    public function counterReferral(): HasOne
    {
        return $this->hasOne(CounterReferral::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->whereNull('purged_at')->orderBy('created_at');
    }

    public function formSchema(): BelongsTo
    {
        return $this->belongsTo(FormSchema::class, 'form_schema_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['completed', 'declined', 'cancelled'], true);
    }
}
