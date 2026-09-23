<?php

namespace App\Models;

use App\Enums\ProposalTemplateType;
use Database\Factories\ProposalTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProposalTemplate extends Model
{
    /** @use HasFactory<ProposalTemplateFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'type',
        'content',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProposalTemplateType::class,
        ];
    }
}
