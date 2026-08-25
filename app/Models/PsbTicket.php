<?php

namespace App\Models;

use App\Enums\PsbTicketStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $ticket_number
 * @property int $prospect_id
 * @property int|null $user_id
 * @property Carbon $scheduled_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $cancelled_at
 * @property string|null $progress_note
 * @property string|null $finish_note
 * @property string|null $cancel_reason
 * @property string $status
 */
#[Fillable(['ticket_number', 'prospect_id', 'user_id', 'scheduled_at', 'started_at', 'finished_at', 'cancelled_at', 'progress_note', 'finish_note', 'cancel_reason', 'payment_method', 'check_pppoe_account', 'check_customer_active', 'check_vlan_tr069', 'isolated_at', 'isolated_profile', 'status'])]
class PsbTicket extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PsbTicketStatus::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'isolated_at' => 'datetime',
            'check_pppoe_account' => 'boolean',
            'check_customer_active' => 'boolean',
            'check_vlan_tr069' => 'boolean',
        ];
    }

    /**
     * Generate the next ticket number, e.g. PSB-20260824-0001.
     */
    public static function generateNumber(): string
    {
        $prefix = 'PSB-'.now()->format('Ymd');

        $count = static::query()
            ->where('ticket_number', 'like', "{$prefix}-%")
            ->count() + 1;

        return sprintf('%s-%04d', $prefix, $count);
    }

    /**
     * Whether the ticket can still be worked on.
     */
    public function isOpen(): bool
    {
        return in_array($this->status, [PsbTicketStatus::Scheduled, PsbTicketStatus::InProgress], true);
    }

    /**
     * The prospect this ticket was created from.
     */
    public function prospect(): BelongsTo
    {
        return $this->belongsTo(Prospect::class);
    }

    /**
     * The technician assigned to this installation.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The activation invoice issued when the ticket was closed.
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * The recurring monthly bills of this customer.
     */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    /**
     * The FTTH pelanggan record born from this ticket.
     */
    public function pelanggan(): HasOne
    {
        return $this->hasOne(Pelanggan::class);
    }

    /**
     * The billing-side customer record of this ticket, if converted.
     */
    public function billingCustomer(): HasOne
    {
        return $this->hasOne(Customer::class);
    }

    /**
     * Best WhatsApp contact for every customer communication:
     * the managed pelanggan record wins over the raw prospect data,
     * so corrections made in customer management take effect.
     */
    public function contactWhatsapp(): ?string
    {
        $whatsapp = $this->pelanggan?->whatsapp
            ?? $this->prospect?->whatsapp;

        return $whatsapp !== null && $whatsapp !== '' ? $whatsapp : null;
    }

    /**
     * Display name of the customer behind this ticket: the managed
     * pelanggan record wins over the raw prospect data.
     */
    public function customerName(): ?string
    {
        return $this->pelanggan?->name
            ?? $this->prospect?->name;
    }

    /**
     * Display area code of the customer behind this ticket.
     */
    public function customerAreaCode(): ?string
    {
        return $this->pelanggan?->areaCode?->code
            ?? $this->prospect?->areaCode?->code;
    }

    /**
     * Display package name of the customer behind this ticket.
     */
    public function customerPackageName(): ?string
    {
        return $this->pelanggan?->package?->name
            ?? $this->prospect?->package?->name;
    }

    /**
     * The monthly price that recurring bills must charge: the CURRENT
     * package of the pelanggan record (upgrades propagate), falling back
     * to the prospect's original package.
     */
    public function billableMonthlyPrice(): int
    {
        return (int) ($this->pelanggan?->package?->price
            ?? $this->prospect?->package?->price
            ?? 0);
    }
}
