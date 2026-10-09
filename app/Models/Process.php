<?php

namespace App\Models;

use App\Enums\ProcessStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Process extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'scenario_id',
        'name',
        'size_bytes',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'scenario_id' => 'integer',
            'size_bytes' => 'integer',
            'status' => ProcessStatus::class,
        ];
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function segments(): HasMany
    {
        return $this->hasMany(Segment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(SimulationEvent::class);
    }
}
