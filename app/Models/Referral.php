<?php

namespace App\Models;

use App\Security\ReferralAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Referral extends Model
{
    use HasUlids;

    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'reason_text' => 'encrypted',
        'clinical_note' => 'encrypted',
        'form_data' => 'encrypted:array',
        'terminal_note' => 'encrypted',
        'include_note' => 'boolean',
        'stale_confirmed' => 'boolean',
        'is_synthetic' => 'boolean',
        'sent_at' => 'datetime', 'received_at' => 'datetime', 'accepted_at' => 'datetime', 'scheduled_at' => 'datetime',
        'appointment_at' => 'datetime', 'attended_at' => 'datetime', 'completed_at' => 'datetime', 'declined_at' => 'datetime',
        'cancelled_at' => 'datetime', 'info_requested_at' => 'datetime', 'info_replied_at' => 'datetime',
        'last_escalated_at' => 'datetime', 'overdue_flagged_at' => 'datetime', 'purged_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // The single visibility layer: every query is constrained by ReferralAccess.
        static::addGlobalScope('access', function (Builder $q) {
            ReferralAccess::constrain($q);
        });
    }

    public function newUniqueId(): string
    {
        return strtolower((string) Str::ulid());
    }

    /** Short display reference. Opaque: derived from the random tail of the id only. */
    public function displayRef(): string
    {
        return 'RF-'.strtoupper(substr($this->id, -8));
    }

    /** @return BelongsTo<Facility, $this> */
    public function referringFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'referring_facility_id');
    }

    /** @return BelongsTo<Facility, $this> */
    public function receivingFacility(): BelongsTo
    {
        return $this->belongsTo(Facility::class, 'receiving_facility_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<Patient, $this> */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withoutGlobalScopes();
    }

    /** @return HasOne<Consent, $this> */
    public function consent(): HasOne
    {
        return $this->hasOne(Consent::class);
    }

    /** @return HasMany<ReferralEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ReferralEvent::class)->orderBy('occurred_at')->orderBy('id');
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('id');
    }

    /** @return HasOne<CounterReferral, $this> */
    public function counterReferral(): HasOne
    {
        return $this->hasOne(CounterReferral::class);
    }

    /** @return HasMany<Attachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->whereNull('purged_at')->orderBy('created_at');
    }

    /** @return BelongsTo<FormSchema, $this> */
    public function formSchema(): BelongsTo
    {
        return $this->belongsTo(FormSchema::class, 'form_schema_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['completed', 'declined', 'cancelled'], true);
    }
}
