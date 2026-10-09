<?php

namespace App\Models;

use App\Enums\SegmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Segment extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'scenario_id',
        'process_id',
        'segment_number',
        'name',
        'base',
        'size_bytes',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'scenario_id' => 'integer',
            'process_id' => 'integer',
            'segment_number' => 'integer',
            'base' => 'integer',
            'size_bytes' => 'integer',
            'status' => SegmentStatus::class,
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
}
