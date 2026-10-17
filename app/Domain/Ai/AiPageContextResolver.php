<?php

namespace App\Domain\Ai;

use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Works out which panel page / module / record the user is looking at from
 * the URL the browser reports, so the floating assistant can default to that
 * context. The URL is never trusted beyond being a hint: it is matched
 * against the real route table, same-host only, and any record is re-fetched
 * through the resource's own query and re-authorized for the acting user.
 */
class AiPageContextResolver
{
    /**
     * @return array{module: string, page: string, page_label: string, record: ?Model, record_title: ?string, resource: ?class-string}|null
     */
    public function resolve(?string $url): ?array
    {
        $path = $this->localPath($url);

        if ($path === null) {
            return null;
        }

        try {
            $route = Route::getRoutes()->match(Request::create($path));
        } catch (\Throwable) {
            return null;
        }

        $routeName = (string) $route->getName();
        $panel = Filament::getCurrentOrDefaultPanel();

        if ($routeName === '' || ! $panel) {
            return null;
        }

        foreach ($panel->getResources() as $resourceClass) {
            /** @var class-string<resource> $resourceClass */
            $prefix = $resourceClass::getRouteBaseName($panel).'.';

            if (! str_starts_with($routeName, $prefix)) {
                continue;
            }

            return $this->resolveResourcePage($resourceClass, substr($routeName, strlen($prefix)), $route->parameter('record'));
        }

        foreach ($panel->getPages() as $pageClass) {
            /** @var class-string<Page> $pageClass */
            if ($pageClass::getRouteName($panel) === $routeName) {
                return [
                    'module' => $pageClass::getNavigationLabel(),
                    'page' => 'page',
                    'page_label' => 'صفحه',
                    'record' => null,
                    'record_title' => null,
                    'resource' => null,
                ];
            }
        }

        return null;
    }

    /**
     * @param  class-string<resource>  $resourceClass
     * @return array{module: string, page: string, page_label: string, record: ?Model, record_title: ?string, resource: ?class-string}
     */
    private function resolveResourcePage(string $resourceClass, string $page, mixed $recordKey): array
    {
        $record = null;

        if (in_array($page, ['view', 'edit'], true) && filled($recordKey)) {
            $record = $this->authorizedRecord($resourceClass, $page, $recordKey);
        }

        $titleAttribute = $resourceClass::getRecordTitleAttribute();

        return [
            'module' => $resourceClass::getPluralModelLabel(),
            'page' => $page,
            'page_label' => match ($page) {
                'create' => 'صفحهٔ ایجاد',
                'view' => 'صفحهٔ مشاهده',
                'edit' => 'صفحهٔ ویرایش',
                default => 'فهرست',
            },
            'record' => $record,
            'record_title' => $record && $titleAttribute ? (string) $record->getAttribute($titleAttribute) : null,
            'resource' => $resourceClass,
        ];
    }

    /**
     * @param  class-string<resource>  $resourceClass
     */
    private function authorizedRecord(string $resourceClass, string $page, mixed $recordKey): ?Model
    {
        try {
            $record = $resourceClass::getEloquentQuery()->find($recordKey);
        } catch (\Throwable) {
            return null;
        }

        if (! $record) {
            return null;
        }

        $allowed = $page === 'edit' ? $resourceClass::canEdit($record) : $resourceClass::canView($record);

        return $allowed ? $record : null;
    }

    private function localPath(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['path'])) {
            return null;
        }

        if (isset($parts['host']) && $parts['host'] !== request()->getHost()) {
            return null;
        }

        return $parts['path'];
    }
}
