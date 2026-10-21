<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketComment extends Model
{
    use HasFactory;

    protected $fillable = ['ticket_id', 'user_id', 'body'];
    protected $touches = ['ticket'];

    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }
    public function author(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function attachments(): HasMany { return $this->hasMany(TicketAttachment::class, 'comment_id'); }
}
