<?php

namespace App\Models\Concerns;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * Give a model the ability to carry universal tags (the shared `taggables`
 * polymorphic pivot from Phase 1 / Universal Taxonomy).
 *
 * Any model that should be tag-able (form submissions, tasks, documents,
 * and later CRM/booking entities) uses this trait instead of a bespoke
 * relation, so the schema never has to change to support a new module.
 */
trait HasTags
{
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function attachTag(Tag $tag): void
    {
        $this->tags()->syncWithoutDetaching([
            $tag->id => ['created_at' => now()],
        ]);
    }

    public function detachTag(Tag $tag): void
    {
        $this->tags()->detach($tag->id);
    }
}
