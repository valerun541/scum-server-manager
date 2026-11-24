<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Services\Scum\SettingsService;
use App\Services\Security\AuditService;
use App\Services\Security\SessionManagerService;
use App\Services\Security\TotpService;
use App\Support\SecureValues;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(
        private SettingsService $settings,
        private TotpService $totp,
        private SessionManagerService $sessionManager,
        private AuditService $audit,
    ) {}

    public function loginForm()
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();
        $ip = $request->ip();
        $username = Str::lower(trim($credentials['username'] ?? ''));

        $throttleKey = 'auth_login:'.$username.'|'.$ip;
        $ipThrottleKey = 'auth_login_ip:'.$ip;

        $maxAttempts = (int) config('panel.auth.max_attempts', 5);
        $lockoutSeconds = (int) config('panel.auth.lockout_seconds', 7200);

        if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts) || RateLimiter::tooManyAttempts($ipThrottleKey, $maxAttempts)) {
            $seconds = max(
                RateLimiter::availableIn($throttleKey),
                RateLimiter::availableIn($ipThrottleKey)
            );

            $hours = intdiv($seconds, 3600);
            $minutes = intdiv($seconds % 3600, 60);
            if ($seconds % 60 > 0 && $hours === 0) {
                $minutes++;
            }

            $timeStr = $hours > 0 ? "{$hours} ч {$minutes} мин" : "{$minutes} мин";

            $this->audit->log('auth.throttle', 'auth', "Попытка входа заблокирована rate limiter на {$timeStr}", ['ip' => $ip], 'failed', $credentials['username'], $ip);

            return back()->withErrors([
                'password' => "Слишком много неудачных попыток входа. Доступ заблокирован на {$timeStr}. Пожалуйста, попробуйте позже.",
            ])->withInput($request->except('password'));
        }

        $config = $this->settings->getAppConfig();
        $webPanel = $config['webPanel'] ?? [];

        if (empty($webPanel['username']) || empty($webPanel['password'])) {
            return back()->withErrors([
                'password' => 'Учётная запись администратора не инициализирована. Запустите команду "php artisan panel:user" на сервере.',
            ])->withInput($request->except('password'));
        }

        $expectedUser = (string) $webPanel['username'];
        $expectedPass = (string) $webPanel['password'];

        if (hash_equals($credentials['username'], $expectedUser) && SecureValues::verifyPassword($credentials['password'], $expectedPass)) {
            RateLimiter::clear($throttleKey);
            RateLimiter::clear($ipThrottleKey);

            // Проверка включена ли 2FA
            if (! empty($webPanel['totp_enabled']) && ! empty($webPanel['totp_secret'])) {
                $request->session()->put('2fa_pending', true);
                $request->session()->put('2fa_pending_username', $expectedUser);

                $this->audit->log('auth.2fa.challenge', 'auth', 'Пароль подтверждён, запрошен код двухфакторной аутентификации', null, 'success', $expectedUser, $ip);

                return redirect()->route('login.2fa');
            }

            $request->session()->put('scum_auth', true);
            $request->session()->regenerate();
            $this->sessionManager->trackCurrentSession($request);

            $this->audit->log('auth.login', 'auth', 'Успешный вход в панель управления', null, 'success', $expectedUser, $ip);

            return redirect()->intended(route('servers.index'));
        }

        RateLimiter::hit($throttleKey, $lockoutSeconds);
        RateLimiter::hit($ipThrottleKey, $lockoutSeconds);

        $attempts = max(RateLimiter::attempts($throttleKey), RateLimiter::attempts($ipThrottleKey));
        $remaining = max(0, $maxAttempts - $attempts);

        $this->audit->log('auth.failed', 'auth', 'Неудачная попытка входа (неверный пароль)', ['remaining_attempts' => $remaining], 'failed', $credentials['username'], $ip);

        $msg = $remaining > 0
            ? "Неверное имя пользователя или пароль. Осталось попыток: {$remaining}."
            : 'Слишком много неудачных попыток входа. Доступ заблокирован на 2 часа.';

        return back()->withErrors(['password' => $msg])->withInput($request->except('password'));
    }

    public function form2fa(Request $request)
    {
        if (! $request->session()->get('2fa_pending')) {
            return redirect()->route('login');
        }

        return view('auth.2fa');
    }

    public function verify2fa(Request $request)
    {
        if (! $request->session()->get('2fa_pending')) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'code' => 'required|string',
        ], [
            'code.required' => 'Введите код подтверждения',
        ]);

        $config = $this->settings->getAppConfig();
        $webPanel = $config['webPanel'] ?? [];
        $secret = (string) ($webPanel['totp_secret'] ?? '');
        $code = trim($validated['code']);

        $isValid = false;

        // 1. Проверяем 6-значный TOTP код
        if (strlen($code) === 6 && ctype_digit($code) && ! empty($secret)) {
            $isValid = $this->totp->verify($secret, $code);
        }

        // 2. Либо проверяем резервный код восстановления (формат XXXX-XXXX)
        if (! $isValid && ! empty($webPanel['totp_recovery_codes'])) {
            $formattedCode = strtoupper($code);
            $codes = (array) $webPanel['totp_recovery_codes'];
            $key = array_search($formattedCode, $codes, true);
            if ($key !== false) {
                $isValid = true;
                // Сжигаем использованный резервный код
                unset($codes[$key]);
                $webPanel['totp_recovery_codes'] = array_values($codes);
                $this->settings->saveAppConfig(['webPanel' => $webPanel]);
            }
        }

        $username = (string) ($request->session()->get('2fa_pending_username') ?? 'admin');

        if (! $isValid) {
            $this->audit->log('auth.2fa.failed', 'auth', 'Неверный код 2FA при подтверждении входа', null, 'failed', $username, $request->ip());

            return back()->withErrors(['code' => 'Неверный код аутентификации. Проверьте время на устройстве или используйте резервный код.']);
        }

        $request->session()->forget(['2fa_pending', '2fa_pending_username']);
        $request->session()->put('scum_auth', true);
        $request->session()->regenerate();
        $this->sessionManager->trackCurrentSession($request);

        $this->audit->log('auth.2fa.success', 'auth', 'Успешная верификация двухфакторной аутентификации', null, 'success', $username, $request->ip());

        return redirect()->intended(route('servers.index'));
    }

    public function logout(Request $request)
    {
        $this->audit->log('auth.logout', 'auth', 'Выход из панели управления', null, 'success');

        $request->session()->forget(['scum_auth', '2fa_pending', '2fa_pending_username']);
        $request->session()->regenerate();

        return redirect()->route('login');
    }
}
