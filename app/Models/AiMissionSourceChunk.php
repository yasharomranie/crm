<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One searchable piece of a source file. Its citation ("«file»، بخش N") is
 * what a team member writes as the source of a finding.
 */
class AiMissionSourceChunk extends Model
{
    use HasFactory;

    protected $fillable = ['ai_mission_source_id', 'position', 'heading', 'content'];

    public function source(): BelongsTo
    {
        return $this->belongsTo(AiMissionSource::class, 'ai_mission_source_id');
    }

    public function citation(): string
    {
        return '«'.str($this->source->title)->limit(80, '')->toString().'»، بخش '.$this->position.(filled($this->heading) ? ' ('.str($this->heading)->limit(80, '')->toString().')' : '');
    }
}
