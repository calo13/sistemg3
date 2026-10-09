<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MemoryFrame extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'scenario_id',
        'frame_number',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'scenario_id' => 'integer',
            'frame_number' => 'integer',
        ];
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }

    public function page(): HasOne
    {
        return $this->hasOne(Page::class, 'frame_id');
    }
}
