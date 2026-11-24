export function rconTerm(boot, sendUrl, jobBaseUrl) {
    boot = boot || { connected: false, history: [] };

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    return {
        lines: [],
        cmd: '',
        busy: false,
        connected: !!boot.connected,
        history: [],
        histIdx: -1,
        clock: '',

        // Быстрые команды & поиск
        activeTab: 'all',
        cmdSearch: '',

        // Модальное окно спавна
        spawnerOpen: false,
        spawnerType: 'item',
        spawnerItem: '',
        spawnerAmount: 1,
        spawnerTarget: '',

        // Модальное окно просмотра ответа сервера
        responseModalOpen: false,
        modalTitle: '',
        modalContent: '',

        showModal(title, content) {
            this.modalTitle = title || 'Ответ сервера';
            this.modalContent = content || '';
            this.responseModalOpen = true;
        },

        closeModal() {
            this.responseModalOpen = false;
        },

        copyText(text) {
            if (!text) return;
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).catch(() => {});
            } else {
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); } catch(e) {}
                document.body.removeChild(ta);
            }
        },

        reRun(cmd) {
            if (!cmd) return;
            this.cmd = cmd;
            this.submit();
        },

        copyTerminal() {
            const text = this.lines.map(l => l.kind === 'prompt' ? `scum$ ${l.cmd}` : (l.text || '')).join('\n');
            this.copyText(text);
        },

        getSpawnerCommand() {
            if (!this.spawnerItem) return '';
            const tgt = (this.spawnerTarget || '').trim();
            if (this.spawnerType === 'item') {
                const amt = Math.max(1, parseInt(this.spawnerAmount) || 1);
                return tgt ? `SpawnItem ${this.spawnerItem} ${amt} Location "${tgt}"` : `SpawnItem ${this.spawnerItem} ${amt}`;
            }
            if (this.spawnerType === 'vehicle') {
                return tgt ? `SpawnVehicle ${this.spawnerItem} Location "${tgt}"` : `SpawnVehicle ${this.spawnerItem}`;
            }
            if (this.spawnerType === 'npc') {
                const amt = Math.max(1, parseInt(this.spawnerAmount) || 1);
                const isArmed = this.spawnerItem.startsWith('BP_Guard') || this.spawnerItem.startsWith('BP_Drifter');
                const cmdName = isArmed ? 'SpawnArmedNPC' : 'SpawnAnimal';
                return tgt ? `${cmdName} ${this.spawnerItem} ${amt} Location "${tgt}"` : `${cmdName} ${this.spawnerItem} ${amt}`;
            }
            if (this.spawnerType === 'marker') {
                return tgt ? `${this.spawnerItem} ${tgt}` : this.spawnerItem;
            }
            return '';
        },

        insertSpawnerCmd() {
            const cmd = this.getSpawnerCommand();
            if (cmd) {
                this.insert(cmd);
                this.spawnerOpen = false;
            }
        },

        executeSpawnerCmd() {
            const cmd = this.getSpawnerCommand();
            if (cmd) {
                this.cmd = cmd;
                this.spawnerOpen = false;
                this.submit();
            }
        },

        init() {
            const tick = () => { this.clock = new Date().toLocaleTimeString('ru-RU'); };
            tick();
            setInterval(tick, 1000);
            this.lines.push({ kind: 'line', text: 'RCON-консоль SCUM · команды выполняются через защищённый демон очереди', color: '#58a6ff' });
            this.lines.push({ kind: 'line', text: 'Введите "ListCommands" для списка всех команд или команду, например "ListPlayers".', color: '#8b949e' });
            this.lines.push({ kind: 'line', text: '', color: '#e6edf3' });
            if (boot.history && boot.history.length) {
                this.lines.push({ kind: 'line', text: 'Последние команды:', color: '#58a6ff' });
                boot.history.slice(-8).forEach(entry => {
                    this.lines.push({ kind: 'prompt', cmd: entry.cmd || '(пусто)' });
                    if (entry.error) this.lines.push({ kind: 'line', text: '[ERROR] ' + entry.error, color: '#f85149' });
                    else if (entry.response !== null && entry.response !== undefined) this.lines.push({ kind: 'line', text: entry.response || '(пустой ответ)', color: '#3fb950' });
                    else this.lines.push({ kind: 'line', text: '…', color: '#8b949e' });
                });
            }
            if (!boot.connected) {
                this.lines.push({ kind: 'line', text: '[WARN] Демон RCON не запущен — команды будут стоять в очереди без выполнения.', color: '#d29922' });
            }
            this.$nextTick(() => this.focus());
        },

        focus() {
            if (this.$refs.input && !this.$refs.input.disabled) this.$refs.input.focus();
        },

        insert(cmd) {
            this.cmd = cmd;
            this.$nextTick(() => this.focus());
        },

        clear() {
            this.lines = [];
        },

        async submit() {
            if (this.busy) return;
            const cmd = this.cmd.trim();
            if (!cmd) return;
            this.lines.push({ kind: 'prompt', cmd: cmd });
            this.history = this.history.filter(h => h !== cmd);
            this.history.push(cmd);
            if (this.history.length > 100) this.history.shift();
            this.histIdx = this.history.length;
            this.cmd = '';
            const lower = cmd.toLowerCase();
            if (lower === 'clear') { this.clear(); return; }
            if (lower === 'help') {
                this.lines.push({ kind: 'line', text: '  Команды идут в RCON SCUM. Встроенные команды консоли: clear, help.', color: '#8b949e' });
                this.lines.push({ kind: 'line', text: '  Попробуйте: ListPlayers, ListSpawnedVehicles, Whois <SteamID>, ListCommands', color: '#3fb950' });
                return;
            }
            this.busy = true;
            try {
                const resp = await fetch(sendUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    body: JSON.stringify({ command: cmd }),
                });
                const data = await resp.json();
                if (!resp.ok || !data.success) {
                    this.lines.push({ kind: 'line', text: '[ERROR] ' + (data.error || 'Ошибка отправки'), color: '#f85149' });
                } else {
                    const waitIdx = this.lines.length;
                    this.lines.push({ kind: 'line', text: '⏳ Выполняется…', color: '#8b949e' });
                    const jobId = data.job_id;
                    const started = Date.now();
                    let done = false;
                    let pollDelay = 80;
                    while (Date.now() - started < 30000) {
                        await new Promise(r => setTimeout(r, pollDelay));
                        if (pollDelay < 150) pollDelay = 150;
                        else if (pollDelay < 250) pollDelay = 250;
                        else pollDelay = 350;
                        const st = await fetch(jobBaseUrl + jobId, { headers: { 'Accept': 'application/json' } });
                        const sj = await st.json();
                        if (sj.status === 'done') {
                            this.lines.splice(waitIdx, 1);
                            this.lines.push({ kind: 'resp', text: sj.response || '(пустой ответ)' });
                            done = true;
                            break;
                        }
                        if (sj.status === 'error') {
                            this.lines.splice(waitIdx, 1);
                            this.lines.push({ kind: 'line', text: '[ERROR] ' + (sj.error || 'Ошибка выполнения'), color: '#f85149' });
                            done = true;
                            break;
                        }
                    }
                    if (!done) {
                        this.lines[waitIdx] = { kind: 'line', text: '⏳ Таймаут — команда отправлена, ответа нет. Проверьте логи.', color: '#d29922' };
                    }
                }
            } catch (e) {
                this.lines.push({ kind: 'line', text: '[ERROR] Сетевая ошибка: ' + e.message, color: '#f85149' });
            } finally {
                this.busy = false;
                this.$nextTick(() => this.focus());
            }
        },

        nav(dir) {
            if (dir < 0) {
                if (this.histIdx > 0) {
                    this.histIdx--;
                    this.cmd = this.history[this.histIdx] || '';
                }
            } else {
                if (this.histIdx < this.history.length - 1) {
                    this.histIdx++;
                    this.cmd = this.history[this.histIdx] || '';
                } else {
                    this.histIdx = this.history.length;
                    this.cmd = '';
                }
            }
        },

        renderLine(line) {
            if (line.kind === 'prompt') {
                return '<div class="flex gap-2 items-start"><span style="color:#f0883e;font-weight:600;white-space:nowrap">scum$</span><span style="color:#e6edf3;white-space:pre-wrap;word-break:break-all">' + escapeHtml(line.cmd) + '</span></div>';
            }
            if (line.kind === 'resp') {
                return '<div class="whitespace-pre-wrap break-all" style="color:#3fb950;background:rgba(63,185,80,.07);border-left:2px solid #3fb950;padding:5px 10px;margin:2px 0 6px 18px;border-radius:0 6px 6px 0">' + escapeHtml(line.text) + '</div>';
            }
            return '<div class="whitespace-pre-wrap break-all" style="color:' + (line.color || '#e6edf3') + '">' + escapeHtml(line.text) + '</div>';
        },
    };
}
