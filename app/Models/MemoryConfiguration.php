<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemoryConfiguration extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'scenario_id',
        'ram_size_bytes',
        'page_size_bytes',
        'secondary_storage_bytes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'scenario_id' => 'integer',
            'ram_size_bytes' => 'integer',
            'page_size_bytes' => 'integer',
            'secondary_storage_bytes' => 'integer',
        ];
    }

    protected function frameCount(): Attribute
    {
        return Attribute::make(
            get: function (): ?int {
                $ramSize = $this->ram_size_bytes;
                $pageSize = $this->page_size_bytes;

                if (! is_int($ramSize) || ! is_int($pageSize) || $ramSize <= 0 || $pageSize <= 0) {
                    return null;
                }

                if ($pageSize > $ramSize || $ramSize % $pageSize !== 0) {
                    return null;
                }

                return intdiv($ramSize, $pageSize);
            },
        );
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(Scenario::class);
    }
}
