<?php

namespace App\Http\Controllers;

use App\Enums\GuideEventType;
use App\Enums\GuideReferenceStatus;
use App\Models\GuideEvent;
use App\Models\GuideObject;
use App\Models\GuideSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * What a visitor's phone needs from the server for the smart guide: the list
 * of recognisable objects (with their photo fingerprints, information and
 * videos) and a place to report anonymous outcomes. Never receives a picture.
 */
class GuideController extends Controller
{
    /** Staff may open the guide while it is still switched off, to test it. */
    private function canTest(Request $request): bool
    {
        return (bool) $request->user()?->can('guide-objects.view_any');
    }

    public function data(Request $request): JsonResponse
    {
        $setting = GuideSetting::current();

        abort_unless($setting->is_enabled || $this->canTest($request), 404);

        $objects = GuideObject::query()
            ->recognisable()
            ->with([
                'zone',
                'videos' => fn ($videos) => $videos->where('is_published', true),
                'referenceImages' => fn ($images) => $images->where('status', GuideReferenceStatus::Processed->value),
            ])
            ->orderBy('title_fa')
            ->get();

        return response()
            ->json([
                'enabled' => $setting->is_enabled,
                'settings' => [
                    'similarity_threshold' => $setting->similarity_threshold,
                    'min_margin' => $setting->min_margin,
                    'frame_interval_ms' => $setting->frame_interval_ms,
                ],
                'objects' => $objects->map(fn (GuideObject $object) => [
                    'id' => $object->id,
                    'slug' => $object->slug,
                    'title' => $object->title_fa,
                    'title_en' => $object->title_en,
                    'description' => $object->description,
                    'category' => $object->category->value,
                    'zone' => $object->zone?->name,
                    'cover_url' => $object->coverUrl(),
                    'videos' => $object->videos->map(fn ($video) => [
                        'id' => $video->id,
                        'title' => $video->title,
                        'url' => $video->playUrl(),
                        'poster_url' => $video->posterUrl(),
                        'duration_seconds' => $video->duration_seconds,
                        'language' => $video->language,
                    ])->filter(fn (array $video) => filled($video['url']))->values(),
                    'embeddings' => $object->referenceImages->pluck('embedding')->filter()->values(),
                ])->values(),
            ])
            ->header('Cache-Control', 'no-cache');
    }

    public function event(Request $request): Response
    {
        $setting = GuideSetting::current();

        abort_unless($setting->is_enabled || $this->canTest($request), 404);

        $data = $request->validate([
            'type' => ['required', Rule::enum(GuideEventType::class)],
            'object_id' => ['nullable', 'integer', 'exists:guide_objects,id'],
            'confidence' => ['nullable', 'numeric', 'between:0,1'],
            'latency_ms' => ['nullable', 'integer', 'between:0,120000'],
        ]);

        GuideEvent::create([
            'type' => $data['type'],
            'guide_object_id' => $data['object_id'] ?? null,
            'confidence' => $data['confidence'] ?? null,
            'latency_ms' => $data['latency_ms'] ?? null,
        ]);

        return response()->noContent();
    }
}
