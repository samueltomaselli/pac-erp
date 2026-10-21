<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id', 'comment_id', 'uploaded_by', 'disk', 'path',
        'original_name', 'mime_type', 'size',
    ];

    protected $hidden = ['disk', 'path'];

    public function ticket(): BelongsTo { return $this->belongsTo(Ticket::class); }
    public function comment(): BelongsTo { return $this->belongsTo(TicketComment::class); }
    public function uploader(): BelongsTo { return $this->belongsTo(User::class, 'uploaded_by'); }
}
