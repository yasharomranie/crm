<?php

namespace App\Http\Controllers\Seo;

use App\Domain\Seo\GoogleOAuthService;
use App\Enums\SeoConnectionStatus;
use App\Exceptions\SeoGoogleException;
use App\Filament\Resources\SeoProjects\SeoProjectResource;
use App\Http\Controllers\Controller;
use App\Models\SeoGoogleConnection;
use App\Models\SeoProject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class GoogleOAuthController extends Controller
{
    private const SESSION_STATE_KEY = 'seo_google_oauth_state';

    private const SCOPES = [
        'https://www.googleapis.com/auth/webmasters.readonly',
        SeoGoogleConnection::ANALYTICS_SCOPE,
    ];

    public function redirect(Request $request, SeoProject $project, GoogleOAuthService $oauth): RedirectResponse
    {
        Gate::authorize('update', $project);

        // The project id rides inside state itself (not just the session)
        // so the callback - which Google redirects to with no route
        // parameter of its own - knows which project this consent belongs
        // to; the random suffix plus the session round-trip is what
        // actually guards against CSRF (an attacker can forge the id but
        // not the matching session value).
        $state = $project->id.'.'.Str::random(32);

        try {
            $url = $oauth->buildAuthorizationUrl(self::SCOPES, $state);
        } catch (SeoGoogleException $exception) {
            return $this->redirectToProject($project, $exception->getMessage());
        }

        $request->session()->put(self::SESSION_STATE_KEY, $state);

        return redirect()->away($url);
    }

    public function callback(Request $request, GoogleOAuthService $oauth): RedirectResponse
    {
        $expectedState = $request->session()->pull(self::SESSION_STATE_KEY);
        $state = (string) $request->query('state');

        if (blank($expectedState) || $state !== $expectedState) {
            abort(419, 'وضعیت اتصال گوگل نامعتبر است - لطفاً دوباره تلاش کنید.');
        }

        [$projectId] = explode('.', $state, 2);
        $project = SeoProject::findOrFail((int) $projectId);
        Gate::authorize('update', $project);

        if ($request->query('error')) {
            return $this->redirectToProject($project, 'دسترسی به حساب گوگل رد شد: '.$request->query('error'));
        }

        try {
            $tokens = $oauth->exchangeCodeForToken((string) $request->query('code'));

            $project->googleConnection()->updateOrCreate([], [
                'access_token' => $tokens['access_token'],
                'refresh_token' => $tokens['refresh_token'] ?? $project->googleConnection?->refresh_token,
                'token_expires_at' => now()->addSeconds($tokens['expires_in']),
                'granted_scopes' => explode(' ', $tokens['scope'] ?? ''),
                'status' => SeoConnectionStatus::Connected,
                'last_sync_error' => null,
            ]);
        } catch (SeoGoogleException $exception) {
            return $this->redirectToProject($project, $exception->getMessage());
        }

        return $this->redirectToProject($project, null, 'حساب گوگل با موفقیت وصل شد. حالا ملک Search Console مورد نظر را انتخاب کنید.');
    }

    private function redirectToProject(SeoProject $project, ?string $error, ?string $success = null): RedirectResponse
    {
        $url = SeoProjectResource::getUrl('edit', ['record' => $project]);

        return redirect($url)->with(array_filter([
            'error' => $error,
            'success' => $success,
        ]));
    }
}
