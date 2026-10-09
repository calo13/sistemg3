<?php

namespace App\Models;

use App\Enums\SimulationEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SimulationEvent extends Model
{
    protected $dateFormat = 'Y-m-d H:i:s.u';

    /** @var list<string> */
    protected $fillable = [
        'scenario_id',
        'user_id',
        'process_id',
        'type',
        'description',
        'metadata',
        'occurred_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'scenario_id' => 'integer',
            'user_id' => 'integer',
            'process_id' => 'integer',
            'type' => SimulationEventType::class,
            'metadata' => 'array',
            'occurred_at' => 'immutable_datetime',
        ];
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
