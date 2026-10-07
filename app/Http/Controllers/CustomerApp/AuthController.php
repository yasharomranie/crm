<?php

namespace App\Http\Controllers\CustomerApp;

use App\Domain\CustomerApp\CustomerAuthException;
use App\Domain\CustomerApp\CustomerGoogleOAuthService;
use App\Domain\CustomerApp\OtpService;
use App\Http\Controllers\Controller;
use App\Models\CustomerAppGoogleSetting;
use App\Models\Guest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Three independent ways for a customer to reach their own account in
 * وب‌اپ مشتریان - SMS OTP (no password to remember), classic
 * mobile/email+password, and "ورود با گوگل" - all converging on the same
 * Guest record and the same 'customer' session guard (config/auth.php), so
 * a booking/wallet/ticket made from any one of them shows up no matter
 * which way the person signs back in later.
 */
class AuthController extends Controller
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly CustomerGoogleOAuthService $google,
    ) {}

    public function showLogin(Request $request): View
    {
        return view('customer-app-auth', [
            'otpRequestedFor' => $request->query('step') === 'otp' ? $request->query('mobile') : null,
            'googleConfigured' => CustomerAppGoogleSetting::current()->isConfigured(),
        ]);
    }

    public function requestOtp(Request $request): RedirectResponse
    {
        $data = $request->validate(['mobile' => ['required', 'string', 'max:32']]);

        $this->otp->requestOtp($data['mobile']);

        return redirect()->route('customer-app.auth.login', ['step' => 'otp', 'mobile' => $data['mobile']]);
    }

    public function verifyOtp(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'mobile' => ['required', 'string', 'max:32'],
            'code' => ['required', 'string'],
        ]);

        if (! $this->otp->verifyOtp($data['mobile'], $data['code'])) {
            throw ValidationException::withMessages(['code' => 'کد وارد شده نادرست یا منقضی شده است.']);
        }

        $guest = Guest::firstOrCreate(
            ['mobile' => $data['mobile']],
            ['name' => $data['mobile']],
        );

        if (! $guest->mobile_verified_at) {
            $guest->update(['mobile_verified_at' => now()]);
        }

        return $this->login($guest, $request);
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'string', 'max:32', 'unique:guests,mobile'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $guest = Guest::create([
            'name' => $data['name'],
            'mobile' => $data['mobile'],
            'password' => $data['password'],
        ]);

        return $this->login($guest, $request);
    }

    public function loginWithPassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $field = filter_var($data['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'mobile';

        if (! Auth::guard('customer')->attempt([$field => $data['login'], 'password' => $data['password']], remember: true)) {
            throw ValidationException::withMessages(['login' => 'شماره موبایل/ایمیل یا رمز عبور نادرست است.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('customer-app'));
    }

    public function redirectToGoogle(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put('customer_google_oauth_state', $state);

        try {
            return redirect($this->google->buildAuthorizationUrl($state));
        } catch (CustomerAuthException $exception) {
            return redirect()->route('customer-app.auth.login')->withErrors(['google' => $exception->getMessage()]);
        }
    }

    public function googleCallback(Request $request): RedirectResponse
    {
        $expectedState = $request->session()->pull('customer_google_oauth_state');
        $code = $request->query('code');

        if (! $code || ! $expectedState || $request->query('state') !== $expectedState) {
            return redirect()->route('customer-app.auth.login')->withErrors(['google' => 'ورود با گوگل ناموفق بود، دوباره تلاش کنید.']);
        }

        try {
            $profile = $this->google->resolveUserFromCode($code);
        } catch (CustomerAuthException $exception) {
            return redirect()->route('customer-app.auth.login')->withErrors(['google' => $exception->getMessage()]);
        }

        $guest = Guest::where('google_id', $profile['sub'])->first();

        if (! $guest && $profile['email']) {
            $guest = Guest::where('email', $profile['email'])->first();
        }

        if ($guest) {
            $guest->update(['google_id' => $profile['sub'], 'email' => $guest->email ?? $profile['email']]);
        } else {
            $guest = Guest::create([
                'name' => $profile['name'] ?? $profile['email'] ?? 'کاربر گوگل',
                'email' => $profile['email'],
                'google_id' => $profile['sub'],
            ]);
        }

        return $this->login($guest, $request);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('customer-app');
    }

    private function login(Guest $guest, Request $request): RedirectResponse
    {
        Auth::guard('customer')->login($guest, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('customer-app'));
    }
}
