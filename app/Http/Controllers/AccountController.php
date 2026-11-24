<?php

namespace App\Http\Controllers;

use App\Services\Scum\SettingsService;
use App\Services\Security\AuditService;
use App\Services\Security\IpWhitelistService;
use App\Services\Security\SessionManagerService;
use App\Services\Security\TotpService;
use App\Support\SecureValues;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(
        private IpWhitelistService $whitelistService,
        private SettingsService $settings,
        private TotpService $totp,
        private SessionManagerService $sessionManager,
        private AuditService $audit,
    ) {}

    public function index(Request $request)
    {
        $this->sessionManager->trackCurrentSession($request);

        $config = $this->settings->getAppConfig();
        $webPanel = $config['webPanel'] ?? [];
        $username = $webPanel['username'] ?? 'admin';
        $totpEnabled = ! empty($webPanel['totp_enabled']) && ! empty($webPanel['totp_secret']);

        $enabled = $this->whitelistService->isEnabled();
        $entries = $this->whitelistService->getAllowedEntries();
        $currentIp = $request->ip();
        $isCurrentIpAllowed = $this->whitelistService->isAllowed($currentIp);

        $activeSessions = $this->sessionManager->getActiveSessions($request->session()->getId());

        return view('account.settings', [
            'username' => $username,
            'enabled' => $enabled,
            'entries' => $entries,
            'currentIp' => $currentIp,
            'isCurrentIpAllowed' => $isCurrentIpAllowed,
            'totpEnabled' => $totpEnabled,
            'recoveryCodes' => $webPanel['totp_recovery_codes'] ?? [],
            'activeSessions' => $activeSessions,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|min:3|max:64',
            'current_password' => 'required|string',
            'new_password' => 'nullable|string|min:4|confirmed',
        ], [
            'username.required' => 'Укажите имя пользователя',
            'current_password.required' => 'Введите текущий пароль для подтверждения',
            'new_password.min' => 'Новый пароль должен содержать минимум 4 символа',
            'new_password.confirmed' => 'Новые пароли не совпадают',
        ]);

        $config = $this->settings->getAppConfig();
        $webPanel = $config['webPanel'] ?? [];
        $expectedPass = (string) ($webPanel['password'] ?? '');

        if (! SecureValues::verifyPassword($validated['current_password'], $expectedPass)) {
            $this->audit->log('security.profile.failed', 'security', 'Неудачная попытка смены реквизитов администратора (неверный текущий пароль)', null, 'failed');

            return back()->withErrors(['current_password' => 'Неверный текущий пароль']);
        }

        $newUsername = trim($validated['username']);
        $newPassword = ! empty($validated['new_password'])
            ? SecureValues::hashPassword($validated['new_password'])
            : $expectedPass;

        $webPanel['username'] = $newUsername;
        $webPanel['password'] = $newPassword;

        $this->settings->saveAppConfig(['webPanel' => $webPanel]);

        $this->audit->log('security.profile.update', 'security', 'Обновление учетных данных администратора', [
            'new_username' => $newUsername,
            'password_changed' => ! empty($validated['new_password']),
        ], 'success');

        return back()->with('success', 'Учетные данные администратора успешно сохранены.');
    }

    public function get2faSetup(Request $request): JsonResponse
    {
        $config = $this->settings->getAppConfig();
        $username = $config['webPanel']['username'] ?? 'admin';

        $secret = $this->totp->generateSecret();
        $request->session()->put('2fa_pending_secret', $secret);

        $uri = $this->totp->getOtpAuthUri($username, $secret);
        $qr = $this->totp->generateQrDataUri($uri);

        return response()->json([
            'secret' => $secret,
            'qr' => $qr,
            'uri' => $uri,
        ]);
    }

    public function enable2fa(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ], [
            'code.required' => 'Введите 6-значный код из приложения аутентификатора',
            'code.size' => 'Код должен состоять из 6 цифр',
        ]);

        $secret = (string) $request->session()->get('2fa_pending_secret');
        if (empty($secret)) {
            return back()->with('error', 'Сессия настройки 2FA истекла. Начните сначала.');
        }

        if (! $this->totp->verify($secret, $validated['code'])) {
            $this->audit->log('security.2fa.failed', 'security', 'Неверный код подтверждения при включении 2FA', null, 'failed');

            return back()->withErrors(['code' => 'Неверный 6-значный код. Проверьте время на телефоне и повторите ввод.']);
        }

        $recoveryCodes = $this->totp->generateRecoveryCodes();

        $config = $this->settings->getAppConfig();
        $webPanel = $config['webPanel'] ?? [];
        $webPanel['totp_enabled'] = true;
        $webPanel['totp_secret'] = $secret;
        $webPanel['totp_recovery_codes'] = $recoveryCodes;

        $this->settings->saveAppConfig(['webPanel' => $webPanel]);
        $request->session()->forget('2fa_pending_secret');

        $this->audit->log('security.2fa.enable', 'security', 'Двухфакторная аутентификация (2FA TOTP) успешно включена');

        return back()->with('success', 'Двухфакторная аутентификация (2FA) успешно активирована!');
    }

    public function disable2fa(Request $request)
    {
        $validated = $request->validate([
            'password' => 'required|string',
        ], [
            'password.required' => 'Введите текущий пароль для отключения 2FA',
        ]);

        $config = $this->settings->getAppConfig();
        $webPanel = $config['webPanel'] ?? [];
        $expectedPass = (string) ($webPanel['password'] ?? '');

        if (! SecureValues::verifyPassword($validated['password'], $expectedPass)) {
            $this->audit->log('security.2fa.disable_failed', 'security', 'Неверный пароль при попытке отключения 2FA', null, 'failed');

            return back()->withErrors(['password' => 'Неверный пароль администратора']);
        }

        $webPanel['totp_enabled'] = false;
        unset($webPanel['totp_secret'], $webPanel['totp_recovery_codes']);

        $this->settings->saveAppConfig(['webPanel' => $webPanel]);

        $this->audit->log('security.2fa.disable', 'security', 'Двухфакторная аутентификация (2FA TOTP) отключена');

        return back()->with('success', 'Двухфакторная аутентификация отключена.');
    }

    public function revokeOtherSessions(Request $request)
    {
        $count = $this->sessionManager->revokeOtherSessions($request->session()->getId());

        $this->audit->log('security.sessions.revoke', 'security', "Завершение других активных сессий (отозвано: {$count})", [
            'revoked_count' => $count,
        ]);

        return back()->with('success', "Успешно завершено других активных сессий: {$count}");
    }

    public function toggleWhitelist(Request $request)
    {
        $validated = $request->validate([
            'enabled' => 'required|boolean',
        ]);

        $enabled = (bool) $validated['enabled'];
        $currentIp = $request->ip();

        $res = $this->whitelistService->setEnabled($enabled, $currentIp);

        $this->audit->log('security.whitelist.toggle', 'security', ($enabled ? 'Включение' : 'Выключение').' белого списка IP', [
            'enabled' => $enabled,
            'result' => $res['ok'] ? 'ok' : 'error',
        ], $res['ok'] ? 'success' : 'failed');

        return back()->with(
            $res['ok'] ? 'success' : 'error',
            $res['message']
        );
    }

    public function addWhitelist(Request $request)
    {
        $validated = $request->validate([
            'ip' => 'required|string|max:64',
            'label' => 'nullable|string|max:120',
        ]);

        $res = $this->whitelistService->addEntry($validated['ip'], $validated['label'] ?? '');

        $this->audit->log('security.whitelist.add', 'security', "Добавление IP в белый список: {$validated['ip']}", [
            'ip' => $validated['ip'],
            'label' => $validated['label'] ?? '',
        ], $res['ok'] ? 'success' : 'failed');

        return back()->with(
            $res['ok'] ? 'success' : 'error',
            $res['message']
        );
    }

    public function quickAddWhitelist(Request $request)
    {
        $currentIp = $request->ip();
        $res = $this->whitelistService->addEntry($currentIp, 'Текущий IP (добавлен из браузера)');

        $this->audit->log('security.whitelist.quick_add', 'security', "Быстрое добавление текущего IP в белый список: {$currentIp}", [
            'ip' => $currentIp,
        ], $res['ok'] ? 'success' : 'failed');

        return back()->with(
            $res['ok'] ? 'success' : 'error',
            $res['message']
        );
    }

    public function deleteWhitelist(Request $request)
    {
        $validated = $request->validate([
            'ip' => 'required|string',
        ]);

        $res = $this->whitelistService->removeEntry($validated['ip']);

        $this->audit->log('security.whitelist.delete', 'security', "Удаление IP из белого списка: {$validated['ip']}", [
            'ip' => $validated['ip'],
        ], $res['ok'] ? 'success' : 'failed');

        return back()->with(
            $res['ok'] ? 'success' : 'error',
            $res['message']
        );
    }
}
