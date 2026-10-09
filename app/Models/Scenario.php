<?php

namespace App\Models;

use App\Enums\ScenarioStatus;
use App\Enums\SimulationMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Scenario extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'name',
        'description',
        'mode',
        'status',
        'is_demo',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'mode' => SimulationMode::class,
            'status' => ScenarioStatus::class,
            'is_demo' => 'boolean',
            'created_by' => 'integer',
        ];
    }

    public function configuration(): HasOne
    {
        return $this->hasOne(MemoryConfiguration::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function processes(): HasMany
    {
        return $this->hasMany(Process::class);
    }

    public function frames(): HasMany
    {
        return $this->hasMany(MemoryFrame::class);
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
