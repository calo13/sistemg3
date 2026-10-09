<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Page extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s.u';

    /** @var list<string> */
    protected $fillable = [
        'scenario_id',
        'process_id',
        'frame_id',
        'page_number',
        'loaded_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'scenario_id' => 'integer',
            'process_id' => 'integer',
            'frame_id' => 'integer',
            'page_number' => 'integer',
            'loaded_at' => 'immutable_datetime',
        ];
    }

    protected function present(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->frame_id !== null,
        );
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    public function frame(): BelongsTo
    {
        return $this->belongsTo(MemoryFrame::class, 'frame_id');
    }
}
