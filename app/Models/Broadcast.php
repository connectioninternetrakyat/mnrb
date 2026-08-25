<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int|null $area_code_id
 * @property int|null $message_template_id
 * @property string $message
 * @property int $recipients
 * @property int $sent_count
 * @property int $failed_count
 */
#[Fillable(['user_id', 'area_code_id', 'message_template_id', 'message', 'recipients', 'sent_count', 'failed_count', 'sent_at'])]
class Broadcast extends Model
{
    /**
     * The operator who launched this broadcast.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The targeted area (null = all areas).
     */
    public function areaCode(): BelongsTo
    {
        return $this->belongsTo(AreaCode::class);
    }

    /**
     * The template used to compose the message, if any.
     */
    public function messageTemplate(): BelongsTo
    {
        return $this->belongsTo(MessageTemplate::class);
    }
}
