<?php

namespace App\Http\Requests;

class ServerRequest extends ScumRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'ftp_protocol' => 'nullable|string|in:ftp,sftp',
            'ftp_host' => 'nullable|string|max:255',
            'ftp_port' => 'nullable|integer|min:1|max:65535',
            'ftp_username' => 'nullable|string|max:255',
            'ftp_password' => 'nullable|string|max:255',
            'ftp_log_path' => 'nullable|string|max:255',
            'ftp_scumdb_path' => 'nullable|string|max:255',
            'ftp_config_path' => 'nullable|string|max:255',
            'rcon_host' => 'nullable|string|max:255',
            'rcon_port' => 'nullable|integer|min:1|max:65535',
            'rcon_password' => 'nullable|string|max:255',
            'enabled' => 'nullable',

            // Lolka уведомления (в форме сервера)
            'lolka_enabled' => 'nullable',
            'lolka_webhooks' => 'nullable',
            'lolka_admin_log_webhook' => 'nullable|string|max:500',
            'lolka_chat_webhook' => 'nullable|string|max:500',
            'lolka_command_webhook' => 'nullable|string|max:500',
            'lolka_vehicle_webhook' => 'nullable|string|max:500',
            'lolka_login_webhook' => 'nullable|string|max:500',
            'lolka_server_status_webhook' => 'nullable|string|max:500',

            // Плагины (чекбоксы в форме сервера)
            'plugin_teleport' => 'nullable',
            'plugin_vip' => 'nullable',
            'plugin_saveHome' => 'nullable',
            'plugin_airdrop' => 'nullable',
            'plugin_rewards' => 'nullable',
            'plugin_packs' => 'nullable',
            'plugin_insurance' => 'nullable',
        ];
    }
}
