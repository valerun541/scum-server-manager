export function serverForm(servers, pluginsConfig, lolkaConfig) {
    const defaults = {
        name: '',
        ftp_protocol: 'ftp',
        ftp_host: '',
        ftp_port: '21',
        ftp_username: '',
        ftp_password: '',
        ftp_log_path: 'SCUM/Saved/SaveFiles/Logs',
        ftp_scumdb_path: 'SCUM/Saved/SaveFiles/SCUM.db',
        ftp_config_path: 'SCUM/Saved/Config/WindowsServer',
        rcon_host: '',
        rcon_port: '28015',
        rcon_password: '',
        plugin_teleport: true,
        plugin_vip: true,
        plugin_saveHome: true,
        plugin_airdrop: true,
        plugin_rewards: true,
        plugin_packs: true,
        plugin_insurance: true,
        lolka_webhooks: [],
        enabled: true,
    };

    const keyLabels = {
        commandWebhook: 'Выполненные команды',
        adminLogWebhook: 'Логи администраторов',
        chatWebhook: 'Чат сервера',
        vehicleWebhook: 'Транспорт и машины',
        loginWebhook: 'Вход и выход игроков',
        serverStatusWebhook: 'Статус сервера',
    };

    function parseWebhooks(cfg) {
        if (!cfg) return [];
        if (Array.isArray(cfg.webhooks) && cfg.webhooks.length > 0) {
            return JSON.parse(JSON.stringify(cfg.webhooks));
        }
        const list = [];
        for (const [k, lbl] of Object.entries(keyLabels)) {
            if (cfg[k] && typeof cfg[k] === 'string' && cfg[k].trim()) {
                list.push({
                    name: lbl,
                    key: k,
                    url: cfg[k].trim()
                });
            }
        }
        return list;
    }

    function resetForm() {
        const f = { ...defaults };
        ['teleport', 'vip', 'saveHome', 'airdrop', 'rewards', 'packs', 'insurance'].forEach(k => {
            f['plugin_' + k] = (pluginsConfig && pluginsConfig[k]) ? pluginsConfig[k].enabled !== false : true;
        });
        f.lolka_webhooks = parseWebhooks(lolkaConfig);
        return f;
    }

    return {
        servers: servers || [],
        open: false,
        action: '',
        title: 'Добавить сервер',
        submitLabel: 'Сохранить сервер',
        currentTab: 'general',
        viewMode: localStorage.getItem('scum_servers_view') || 'cards',
        search: '',
        showFtpPassword: false,
        showRconPassword: false,
        copiedText: null,
        showAddWebhookForm: false,
        editingWebhookIndex: null,
        editingId: null,
        ftpTesting: false,
        ftpTestResult: null,
        rconTesting: false,
        rconTestResult: null,
        webhookForm: { name: '', key: '', url: '' },
        form: resetForm(),

        init() {
            this.storeUrl = window.SERVER_STORE_URL || '/servers';
        },

        setViewMode(mode) {
            this.viewMode = mode;
            try {
                localStorage.setItem('scum_servers_view', mode);
            } catch (e) {}
        },

        setTab(tab) {
            this.currentTab = tab;
        },

        onProtocolChange() {
            if (this.form.ftp_protocol === 'sftp') {
                if (!this.form.ftp_port || this.form.ftp_port === '21' || this.form.ftp_port === 21) {
                    this.form.ftp_port = '22';
                }
            } else if (this.form.ftp_protocol === 'ftp') {
                if (!this.form.ftp_port || this.form.ftp_port === '22' || this.form.ftp_port === 22) {
                    this.form.ftp_port = '21';
                }
            }
        },

        openAddWebhook() {
            this.editingWebhookIndex = null;
            this.webhookForm = { name: '', key: '', url: '' };
            this.showAddWebhookForm = true;
        },

        openEditWebhook(index) {
            this.editingWebhookIndex = index;
            const item = this.form.lolka_webhooks[index] || {};
            this.webhookForm = {
                name: item.name || '',
                key: item.key || '',
                url: item.url || ''
            };
            this.showAddWebhookForm = true;
        },

        selectKeyPreset(key, name) {
            this.webhookForm.key = key;
            if (!this.webhookForm.name) {
                this.webhookForm.name = name;
            }
        },

        saveWebhook() {
            const url = (this.webhookForm.url || '').trim();
            if (!url) {
                if (Alpine.store('toasts')) Alpine.store('toasts').add('Укажите ссылку на Webhook', 'error');
                return;
            }
            let name = (this.webhookForm.name || '').trim();
            let key = (this.webhookForm.key || '').trim();
            if (!key) {
                key = name ? name.toLowerCase().replace(/[^a-z0-9_]/g, '_') : 'webhook_' + Date.now();
            }
            if (!name) {
                name = keyLabels[key] || key;
            }

            const item = { name, key, url };

            if (!Array.isArray(this.form.lolka_webhooks)) {
                this.form.lolka_webhooks = [];
            }

            if (this.editingWebhookIndex !== null && this.editingWebhookIndex >= 0) {
                this.form.lolka_webhooks[this.editingWebhookIndex] = item;
            } else {
                this.form.lolka_webhooks.push(item);
            }

            this.showAddWebhookForm = false;
            this.editingWebhookIndex = null;
            this.webhookForm = { name: '', key: '', url: '' };
            if (Alpine.store('toasts')) {
                Alpine.store('toasts').add('Вебхук сохранён', 'success');
            }
        },

        removeWebhook(index) {
            if (confirm('Удалить этот вебхук?')) {
                this.form.lolka_webhooks.splice(index, 1);
                if (this.editingWebhookIndex === index) {
                    this.showAddWebhookForm = false;
                    this.editingWebhookIndex = null;
                }
            }
        },

        cancelWebhookForm() {
            this.showAddWebhookForm = false;
            this.editingWebhookIndex = null;
            this.webhookForm = { name: '', key: '', url: '' };
        },

        copy(text) {
            if (!text) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text);
            } else {
                const ta = document.createElement('textarea');
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            }
            this.copiedText = text;
            setTimeout(() => {
                if (this.copiedText === text) this.copiedText = null;
            }, 2000);
            if (Alpine.store('toasts')) {
                Alpine.store('toasts').add('Скопировано в буфер: ' + text, 'info');
            }
        },

        openCreate() {
            this.form = resetForm();
            this.editingId = null;
            this.ftpTestResult = null;
            this.rconTestResult = null;
            this.action = this.storeUrl;
            this.title = 'Добавить сервер';
            this.submitLabel = 'Создать и подключить';
            this.currentTab = 'general';
            this.showFtpPassword = false;
            this.showRconPassword = false;
            this.showAddWebhookForm = false;
            this.open = true;
        },

        openEdit(id) {
            const s = (this.servers || []).find(x => String(x.id) === String(id));
            if (!s) return;
            this.editingId = id;
            this.ftpTestResult = null;
            this.rconTestResult = null;
            const f = resetForm();
            f.name = s.name || '';
            f.ftp_protocol = s.ftp_protocol || 'ftp';
            f.ftp_host = s.ftp_host || '';
            f.ftp_port = s.ftp_port || '21';
            f.ftp_username = s.ftp_username || '';
            f.ftp_password = s.ftp_password || '';
            f.ftp_log_path = s.ftp_log_path || 'SCUM/Saved/SaveFiles/Logs';
            f.ftp_scumdb_path = s.ftp_scumdb_path || 'SCUM/Saved/SaveFiles/SCUM.db';
            f.ftp_config_path = s.ftp_config_path || 'SCUM/Saved/Config/WindowsServer';
            f.rcon_host = s.rcon_host || '';
            f.rcon_port = s.rcon_port || '28015';
            f.rcon_password = s.rcon_password || '';
            f.enabled = !!(+s.enabled);
            this.form = f;
            this.action = (window.SERVER_UPDATE_BASE || '/servers/') + id;
            this.title = 'Настройки сервера: ' + (s.name || 'Без имени');
            this.submitLabel = 'Сохранить изменения';
            this.currentTab = 'general';
            this.showFtpPassword = false;
            this.showRconPassword = false;
            this.showAddWebhookForm = false;
            this.open = true;
        },

        async testFtp() {
            if (!this.form.ftp_host) {
                this.ftpTestResult = { success: false, message: 'Укажите хост FTP' };
                return;
            }
            this.ftpTesting = true;
            this.ftpTestResult = null;
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const res = await fetch('/servers/test-ftp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        ftp_protocol: this.form.ftp_protocol,
                        ftp_host: this.form.ftp_host,
                        ftp_port: this.form.ftp_port,
                        ftp_username: this.form.ftp_username,
                        ftp_password: this.form.ftp_password,
                        server_id: this.editingId || null,
                    })
                });
                const data = await res.json();
                this.ftpTestResult = data;
            } catch (e) {
                this.ftpTestResult = { success: false, message: 'Ошибка сети при проверке FTP' };
            } finally {
                this.ftpTesting = false;
            }
        },

        async testRcon() {
            if (!this.form.rcon_host || !this.form.rcon_port) {
                this.rconTestResult = { success: false, message: 'Укажите хост и порт RCON' };
                return;
            }
            this.rconTesting = true;
            this.rconTestResult = null;
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                const res = await fetch('/servers/test-rcon', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token || '',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        rcon_host: this.form.rcon_host,
                        rcon_port: this.form.rcon_port,
                        rcon_password: this.form.rcon_password,
                        server_id: this.editingId || null,
                    })
                });
                const data = await res.json();
                this.rconTestResult = data;
            } catch (e) {
                this.rconTestResult = { success: false, message: 'Ошибка сети при проверке RCON' };
            } finally {
                this.rconTesting = false;
            }
        },

        close() {
            this.open = false;
        },

        matchesSearch(server) {
            if (!this.search || !this.search.trim()) return true;
            const q = this.search.toLowerCase().trim();
            const name = (server.name || '').toLowerCase();
            const ftpHost = (server.ftp_host || '').toLowerCase();
            const rconHost = (server.rcon_host || '').toLowerCase();
            const id = String(server.id || '');
            return name.includes(q) || ftpHost.includes(q) || rconHost.includes(q) || id.includes(q);
        }
    };
}
