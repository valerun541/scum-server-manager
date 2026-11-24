export function logPoller(config) {
    return {
        lastId: config.initialLastId || 0,
        total: config.initialTotal || 0,
        newCount: 0,
        interval: null,
        running: false,
        url: config.url,
        mode: config.mode || 'id',
        ms: config.ms || 3000,
        q: '',

        init() {
            this.running = true;
            if (window.EventSource && this.mode !== 'system') {
                this._connectSse();
            } else {
                this.interval = setInterval(() => this.poll(), this.ms);
            }
        },

        _connectSse() {
            try {
                const channel = this.mode === 'chat' ? 'chat' : (this.mode === 'rcon' ? 'rcon' : 'events');
                let streamUrl = '/logs/stream?channel=' + channel + '&last_id=' + this.lastId;
                if (config.logType) {
                    streamUrl += '&type=' + encodeURIComponent(config.logType);
                }

                this.evtSource = new EventSource(streamUrl);
                this.evtSource.addEventListener('log', (e) => {
                    if (!this.running) return;
                    try {
                        const data = JSON.parse(e.data);
                        this._appendRows(data);
                    } catch (err) {}
                });

                this.evtSource.onerror = () => {
                    if (!this.interval) {
                        this.interval = setInterval(() => this.poll(), this.ms);
                    }
                };
            } catch (err) {
                if (!this.interval) {
                    this.interval = setInterval(() => this.poll(), this.ms);
                }
            }
        },

        destroy() {
            this.running = false;
            if (this.interval) clearInterval(this.interval);
            if (this.evtSource) {
                this.evtSource.close();
                this.evtSource = null;
            }
        },

        async poll() {
            if (!this.running) return;
            try {
                let url;
                if (this.mode === 'system') {
                    url = this.url + '?total=' + this.total;
                } else {
                    url = this.url + '?last_id=' + this.lastId;
                }
                const r = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!r.ok) return;
                const data = await r.json();

                if (this.mode === 'system') {
                    this._appendSystemLines(data);
                } else {
                    this._appendRows(data);
                }
            } catch (e) { /* silent */ }
        },

        _appendSystemLines(data) {
            if (!data.lines || data.lines.length === 0) return;
            this.total = data.total || this.total;
            this.newCount += data.lines.length;
            const container = this.$refs.logBody;
            if (!container) return;
            const wasAtBottom = container.scrollTop + container.clientHeight >= container.scrollHeight - 50;
            data.lines.forEach(line => {
                const div = document.createElement('div');
                div.className = 'whitespace-pre-wrap break-all px-2 py-0.5 rounded transition-colors hover:bg-[#161b22]/70 flex items-start gap-2 log-new-entry ' +
                    (line.level === 'error' ? 'text-[#f85149] bg-[#da3633]/5' :
                     line.level === 'warning' || line.level === 'warn' ? 'text-[#d29922] bg-[#d29922]/5' : 'text-[#c9d1d9]');
                
                let timeHtml = line.time ? '<span class="text-[#8b949e] opacity-70 shrink-0 select-none text-[11px]">[' + this.esc(line.time) + ']</span> ' : '';
                let badgeHtml = '';
                if (line.level === 'error') {
                    badgeHtml = '<span class="px-1 py-0.2 rounded text-[9px] font-bold bg-[#da3633]/20 text-[#f85149] border border-[#da3633]/30 shrink-0 uppercase select-none">ERR</span> ';
                } else if (line.level === 'warning' || line.level === 'warn') {
                    badgeHtml = '<span class="px-1 py-0.2 rounded text-[9px] font-bold bg-[#d29922]/20 text-[#d29922] border border-[#d29922]/30 shrink-0 uppercase select-none">WARN</span> ';
                }
                div.innerHTML = timeHtml + badgeHtml + '<span class="flex-1">' + this.esc(line.message) + '</span>';
                div.dataset.search = ((line.time || '') + ' ' + (line.level || '') + ' ' + (line.message || '')).toLowerCase();
                container.appendChild(div);
            });
            if (wasAtBottom) container.scrollTop = container.scrollHeight;
            this.applyFilter();
        },

        _appendRows(data) {
            if (!data.rows || data.rows.length === 0) return;
            this.lastId = Math.max(this.lastId, ...data.rows.map(r => r.id));
            this.newCount += data.rows.length;

            const container = this.$refs.logBody;
            if (!container) return;

            data.rows.forEach(row => {
                if (container.querySelector(`[data-id="${row.id}"]`)) return;
                const card = this._buildCard(row);
                if (card) {
                    card.dataset.id = row.id;
                    card.classList.add('log-new-entry');
                    container.insertBefore(card, container.firstChild);
                }
            });

            this.applyFilter();
        },

        _buildCard(row) {
            if (this.mode === 'chat') {
                return this._buildChatCard(row);
            }
            if (this.mode === 'rcon') {
                return this._buildRconCard(row);
            }
            if (this.mode === 'admin') {
                return this._buildAdminCard(row);
            }
            return this._buildEventCard(row);
        },

        _buildChatCard(row) {
            const time = row.created_at ? new Date(row.created_at).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '';
            const div = document.createElement('div');
            div.className = 'card p-3 sm:p-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 hover:border-[#484f58] transition-all group bg-[#0d1117]/80';
            const search = ((row.player_name || '') + ' ' + (row.message || '')).toLowerCase();
            const initials = ((row.player_name || '?').substring(0, 2)).toUpperCase();
            div.dataset.search = search;
            div.innerHTML =
                '<div class="flex items-start sm:items-center gap-3 min-w-0 flex-1">' +
                    '<div class="w-8 h-8 rounded-lg bg-gradient-to-br from-[#1f6feb]/20 to-[#bc4c9a]/20 border border-[#388bfd]/30 flex items-center justify-center text-xs font-bold text-[#58a6ff] shrink-0">' + this.esc(initials) + '</div>' +
                    '<div class="min-w-0 flex-1">' +
                        '<div class="flex items-center gap-2 flex-wrap">' +
                            '<span class="font-bold text-xs text-[#58a6ff] truncate">' + this.esc(row.player_name || 'Неизвестный') + '</span>' +
                            '<span class="text-[11px] text-[#8b949e] mono">[' + this.esc(time) + ']</span>' +
                        '</div>' +
                        '<div class="text-sm text-[#e6edf3] mt-0.5 break-words">' + this.esc(row.message || '') + '</div>' +
                    '</div>' +
                '</div>';
            if (this.q && !search.includes(this.q.trim().toLowerCase())) {
                div.style.display = 'none';
            }
            return div;
        },

        _buildRconCard(row) {
            const time = row.created_at ? new Date(row.created_at).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '';
            const div = document.createElement('div');
            div.className = 'p-3 sm:px-4 sm:py-3 hover:bg-[#161b22]/70 transition-colors flex flex-col md:grid md:grid-cols-12 gap-2 md:gap-3 md:items-center';
            const search = ((row.command || '') + ' ' + (row.response || '') + ' ' + (row.error || '')).toLowerCase();
            div.dataset.search = search;

            const statusHtml = row.error
                ? '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-[10px] font-bold border bg-[#da3633]/15 text-[#f85149] border-[#da3633]/30" title="' + this.esc(row.error || '') + '"><svg class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg><span>Ошибка</span></span>'
                : '<span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-[10px] font-bold border bg-[#238636]/15 text-[#3fb950] border-[#238636]/30"><svg class="w-2.5 h-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg><span>OK</span></span>';

            const respHtml = row.response
                ? '<div class="mono text-xs text-[#c9d1d9] bg-[#010409] border border-[#21262d] p-2 rounded-lg max-h-24 overflow-y-auto whitespace-pre-wrap break-words leading-relaxed select-text shadow-inner">' + this.esc(row.response) + '</div>'
                : (row.error ? '<div class="mono text-xs text-[#f85149] bg-[#da3633]/10 border border-[#da3633]/20 p-2 rounded-lg whitespace-pre-wrap break-words leading-relaxed">' + this.esc(row.error) + '</div>' : '<span class="text-xs text-[#8b949e] opacity-40">—</span>');

            div.innerHTML =
                '<div class="md:col-span-2 flex items-center justify-between md:justify-start gap-2">' +
                    '<span class="text-xs text-[#8b949e] font-mono whitespace-nowrap">' + this.esc(time) + '</span>' +
                    '<div class="md:hidden">' + statusHtml + '</div>' +
                '</div>' +
                '<div class="md:col-span-4 min-w-0">' +
                    '<div class="flex items-center gap-1.5 group/cmd">' +
                        '<span class="mono text-xs font-semibold text-[#58a6ff] bg-[#1f6feb]/10 border border-[#388bfd]/25 px-2.5 py-1 rounded-md break-all inline-block shadow-sm">' + this.esc(row.command || '') + '</span>' +
                        '<button onclick="window.copyToClipboard(\'' + this.esc(row.command || '').replace(/'/g, "\\'") + '\')" class="text-[#8b949e] hover:text-[#e6edf3] p-1 text-xs transition-colors shrink-0" title="Скопировать"><svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg></button>' +
                    '</div>' +
                '</div>' +
                '<div class="md:col-span-5 min-w-0">' + respHtml + '</div>' +
                '<div class="hidden md:block md:col-span-1 md:text-right whitespace-nowrap">' + statusHtml + '</div>';

            if (this.q && !search.includes(this.q.trim().toLowerCase())) {
                div.style.display = 'none';
            }
            return div;
        },

        _buildAdminCard(row) {
            const time = row.created_at ? new Date(row.created_at).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '';
            const parsed = this._parseLogLine('admin', row.message || '');
            const div = document.createElement('div');
            div.className = 'p-3 sm:px-4 sm:py-3 hover:bg-[#161b22]/70 transition-colors flex flex-col md:grid md:grid-cols-12 gap-2 md:gap-3 md:items-center';
            const search = ((parsed.player || '') + ' ' + (parsed.steamId || '') + ' ' + (parsed.details || '') + ' ' + (row.message || '')).toLowerCase();
            div.dataset.search = search;
            const initials = ((parsed.player || 'AD').substring(0, 2)).toUpperCase();

            let playerHtml = '<span class="text-xs text-[#8b949e]">—</span>';
            if (parsed.player) {
                playerHtml =
                    '<div class="flex items-center gap-2 min-w-0">' +
                        '<span class="w-7 h-7 rounded-lg bg-[#8957e5]/20 border border-[#8957e5]/30 text-[#a371f7] flex items-center justify-center text-xs font-bold shrink-0 shadow-sm">' + this.esc(initials) + '</span>' +
                        '<div class="min-w-0 flex-1">' +
                            '<div class="flex items-center gap-1.5 flex-wrap">' +
                                (parsed.steamId ? '<a href="/scumdb/player/' + encodeURIComponent(parsed.steamId) + '" class="font-bold text-xs text-[#e6edf3] hover:text-[#58a6ff] transition-colors truncate">' + this.esc(parsed.player) + '</a><span class="text-[10px] text-[#8b949e] font-mono">(' + this.esc(parsed.steamId) + ')</span>'
                                                : '<span class="font-bold text-xs text-[#e6edf3] truncate">' + this.esc(parsed.player) + '</span>') +
                            '</div>' +
                        '</div>' +
                    '</div>';
            }

            const badgeHtml = '<span class="inline-block px-2.5 py-1 rounded-md text-[11px] font-bold border ' + parsed.badgeClass + '">' + this.esc(parsed.badgeText || '⌨️ Команда') + '</span>';

            div.innerHTML =
                '<div class="md:col-span-2 flex items-center justify-between md:justify-start gap-2">' +
                    '<span class="text-xs text-[#8b949e] font-mono whitespace-nowrap">' + this.esc(time) + '</span>' +
                    '<div class="md:hidden">' + badgeHtml + '</div>' +
                '</div>' +
                '<div class="md:col-span-3 min-w-0">' + playerHtml + '</div>' +
                '<div class="hidden md:block md:col-span-2">' + badgeHtml + '</div>' +
                '<div class="md:col-span-5 min-w-0">' +
                    '<div class="flex items-start gap-1.5 group/cmd">' +
                        '<span class="mono text-xs text-[#e6edf3] bg-[#161b22] border border-[#30363d] px-2.5 py-1 rounded-md break-all inline-block shadow-inner select-text">' + this.esc(parsed.details || row.message) + '</span>' +
                        '<button onclick="window.copyToClipboard(\'' + this.esc(parsed.details || row.message).replace(/'/g, "\\'") + '\')" class="opacity-0 group-hover/cmd:opacity-100 text-[#8b949e] hover:text-[#e6edf3] p-1 text-xs transition-opacity shrink-0" title="Скопировать"><svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></svg></button>' +
                    '</div>' +
                '</div>';

            if (this.q && !search.includes(this.q.trim().toLowerCase())) {
                div.style.display = 'none';
            }
            return div;
        },

        _buildEventCard(row) {
            const time = row.created_at ? new Date(row.created_at).toLocaleString('ru-RU', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' }) : '';
            const parsed = this._parseLogLine(row.type || this.mode, row.message || '');
            const div = document.createElement('div');
            div.className = 'p-3 sm:px-4 sm:py-3 hover:bg-[#161b22]/70 transition-colors flex flex-col md:grid md:grid-cols-12 gap-2 md:gap-3 md:items-center';
            const search = ((parsed.player || '') + ' ' + (parsed.steamId || '') + ' ' + (parsed.target || '') + ' ' + (parsed.details || '') + ' ' + (row.message || '')).toLowerCase();
            div.dataset.search = search;
            const initials = ((parsed.player || '?').substring(0, 2)).toUpperCase();

            let playerHtml = '';
            if (parsed.player) {
                playerHtml =
                    '<div class="flex items-center gap-2 min-w-0">' +
                        '<span class="w-7 h-7 rounded-lg bg-gradient-to-br from-[#1f6feb]/20 to-[#388bfd]/20 border border-[#388bfd]/30 text-[#58a6ff] flex items-center justify-center text-xs font-bold shrink-0 shadow-sm">' + this.esc(initials) + '</span>' +
                        '<div class="min-w-0 flex-1">' +
                            '<div class="flex items-center gap-1.5 flex-wrap">' +
                                (parsed.steamId && parsed.steamId !== 'NPC' ? '<a href="/scumdb/player/' + encodeURIComponent(parsed.steamId) + '" class="font-bold text-xs text-[#e6edf3] hover:text-[#58a6ff] transition-colors truncate">' + this.esc(parsed.player) + '</a><span class="text-[10px] text-[#8b949e] font-mono">(' + this.esc(parsed.steamId) + ')</span>'
                                                                            : '<span class="font-bold text-xs text-[#e6edf3] truncate">' + this.esc(parsed.player) + '</span>') +
                            '</div>' +
                            (parsed.target ? '<div class="mt-0.5 flex items-center gap-1 text-[11px] text-[#8b949e]"><span>Жертва:</span><span class="font-semibold text-[#f85149] truncate">' + this.esc(parsed.target) + '</span></div>' : '') +
                        '</div>' +
                    '</div>';
            } else {
                playerHtml =
                    '<div class="flex items-center gap-2 text-xs text-[#8b949e]">' +
                        '<span class="w-6 h-6 rounded-md bg-[#21262d]/60 border border-[#30363d] flex items-center justify-center text-xs font-bold">' + this.esc((row.type || 'EV').substring(0, 2).toUpperCase()) + '</span>' +
                        '<span class="uppercase tracking-wider font-semibold">' + this.esc(row.type || 'LOG') + '</span>' +
                    '</div>';
            }

            const badgeHtml = '<span class="inline-block px-2.5 py-1 rounded-md text-[11px] font-bold border ' + parsed.badgeClass + '">' + this.esc(parsed.badgeText || (row.type || 'LOG')) + '</span>';

            let mapBtn = '';
            if (parsed.coords) {
                mapBtn = '<a href="/map?x=' + parsed.coords.x + '&y=' + parsed.coords.y + '" target="_blank" class="btn btn-xs bg-[#161b22] hover:bg-[#21262d] border-[#30363d] text-[#58a6ff] hover:text-[#79c0ff] inline-flex items-center gap-1 shadow-sm" title="Показать точку на интерактивной карте"><svg class="w-3 h-3 text-[#58a6ff]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="3 6 9 3 15 6 21 3 21 18 15 21 9 18 3 21"/><line x1="9" x2="9" y1="3" y2="18"/><line x1="15" x2="15" y1="6" y2="21"/></svg><span class="font-mono text-[10px]">[' + Math.round(parsed.coords.x) + ', ' + Math.round(parsed.coords.y) + ']</span></a>';
            } else {
                mapBtn = '<span class="hidden md:inline text-xs text-[#8b949e] opacity-40">—</span>';
            }

            let extraHtml = '';
            if (parsed.weapon || parsed.distance || parsed.ip) {
                extraHtml = '<div class="mt-1 flex items-center gap-1.5 flex-wrap">' +
                    (parsed.weapon ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-[#161b22] border border-[#30363d] text-[11px] text-[#8b949e] font-mono">Оружие: ' + this.esc(parsed.weapon) + '</span>' : '') +
                    (parsed.distance ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-[#161b22] border border-[#30363d] text-[11px] text-[#8b949e] font-mono">Дистанция: ' + this.esc(parsed.distance) + '</span>' : '') +
                    (parsed.ip ? '<span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-[#161b22] border border-[#30363d] text-[10px] text-[#8b949e] font-mono">IP: ' + this.esc(parsed.ip) + '</span>' : '') +
                '</div>';
            }

            div.innerHTML =
                '<div class="md:col-span-2 flex items-center justify-between md:justify-start gap-2">' +
                    '<span class="text-xs text-[#8b949e] font-mono whitespace-nowrap">' + this.esc(time) + '</span>' +
                    '<div class="md:hidden">' + badgeHtml + '</div>' +
                '</div>' +
                '<div class="hidden md:block md:col-span-2">' + badgeHtml + '</div>' +
                '<div class="md:col-span-3 min-w-0">' + playerHtml + '</div>' +
                '<div class="md:col-span-4 text-xs text-[#e6edf3] break-words">' +
                    '<div class="font-medium text-[#e6edf3] leading-relaxed">' + this.esc(parsed.details || row.message) + '</div>' +
                    extraHtml +
                '</div>' +
                '<div class="md:col-span-1 md:text-right flex items-center justify-between md:justify-end gap-2 pt-1 md:pt-0">' + mapBtn + '</div>';

            if (this.q && !search.includes(this.q.trim().toLowerCase())) {
                div.style.display = 'none';
            }
            return div;
        },

        _parseLogLine(type, msg) {
            let clean = (msg || '').replace(/^\d{4}\.\d{2}\.\d{2}-\d{2}\.\d{2}\.\d{2}:\s*/, '');
            let res = {
                badgeText: null,
                badgeClass: 'bg-[#21262d] text-[#c9d1d9] border-[#30363d]',
                player: null,
                steamId: null,
                target: null,
                targetSteamId: null,
                details: clean,
                weapon: null,
                distance: null,
                ip: null,
                coords: null,
            };

            // Kill
            const km = clean.match(/Died:\s*(.+?)\s*\((\d+)\),\s*Killer:\s*(.+?)\s*\(([^)]+)\)\s*Weapon:\s*([^S\n]+)(?:S:\[(.*)\])?/i);
            if (km) {
                res.target = km[1].trim();
                res.targetSteamId = km[2].trim();
                res.player = km[3].trim();
                res.steamId = km[4].trim();
                res.weapon = km[5].trim();
                res.badgeText = 'Убийство';
                res.badgeClass = 'bg-[#da3633]/15 text-[#f85149] border-[#da3633]/30';
                res.details = res.player + ' ликвидировал ' + res.target;
                if (km[6]) {
                    const lm = km[6].match(/VictimLoc:\s*([-\d.]+),\s*([-\d.]+)/i);
                    if (lm) res.coords = { x: parseFloat(lm[1]), y: parseFloat(lm[2]) };
                    const dm = km[6].match(/Distance:\s*([\d.]+)\s*m/i);
                    if (dm) res.distance = dm[1] + ' м';
                }
                return res;
            }

            // Login
            const lm = clean.match(/'([^\s]+)\s+(\d+):([^(]+)\(\d+\)'\s+(logged in|logged out)\s+at:\s*X=([-\d.]+)\s+Y=([-\d.]+)/i);
            if (lm) {
                res.ip = lm[1].trim();
                res.steamId = lm[2].trim();
                res.player = lm[3].trim();
                const isLogin = lm[4].toLowerCase() === 'logged in';
                res.badgeText = isLogin ? 'Вход' : 'Выход';
                res.badgeClass = isLogin ? 'bg-[#238636]/15 text-[#3fb950] border-[#238636]/30' : 'bg-[#da3633]/15 text-[#f85149] border-[#da3633]/30';
                res.coords = { x: parseFloat(lm[5]), y: parseFloat(lm[6]) };
                res.details = isLogin ? 'Подключение к серверу' : 'Отключение от сервера';
                return res;
            }

            // Quests
            const qm = clean.match(/\[LogQuestStatus\]\s*(.+?)\s*\(\d+,\s*(\d+)\)\s*(completed|abandoned|started)\s+quest\s+(.+)/i);
            if (qm) {
                res.player = qm[1].trim();
                res.steamId = qm[2].trim();
                const st = qm[3].toLowerCase();
                const qName = qm[4].replace(/[_\.]json/gi, ' ').trim();
                res.badgeText = st === 'completed' ? 'Квест выполнен' : (st === 'abandoned' ? 'Квест отменён' : 'Квест начат');
                res.badgeClass = st === 'completed' ? 'bg-[#238636]/15 text-[#3fb950] border-[#238636]/30' : (st === 'abandoned' ? 'bg-[#da3633]/15 text-[#f85149] border-[#da3633]/30' : 'bg-[#1f6feb]/15 text-[#58a6ff] border-[#388bfd]/30');
                res.details = qName;
                return res;
            }

            // Admin command
            const am = clean.match(/'(\d+):([^(]+)(?:\(\d+\))?'\s+Command:\s*'(.+)'/i);
            if (am) {
                res.steamId = am[1].trim();
                res.player = am[2].trim();
                res.details = am[3].trim();
                res.badgeText = '⌨️ Команда';
                if (/^(spawnitem|spawngenerateditem)/i.test(res.details)) {
                    res.badgeText = 'Спавн предмета'; res.badgeClass = 'bg-[#1f6feb]/15 text-[#58a6ff] border-[#388bfd]/30';
                } else if (/^(spawnvehicle|spawnzombie)/i.test(res.details)) {
                    res.badgeText = 'Спавн сущности'; res.badgeClass = 'bg-[#d29922]/15 text-[#d29922] border-[#d29922]/30';
                } else if (/^(teleport|tpto)/i.test(res.details)) {
                    res.badgeText = 'Телепорт'; res.badgeClass = 'bg-[#a371f7]/15 text-[#a371f7] border-[#a371f7]/30';
                } else if (/^(setimmortality|god)/i.test(res.details)) {
                    res.badgeText = 'Бессмертие'; res.badgeClass = 'bg-[#238636]/15 text-[#3fb950] border-[#238636]/30';
                }
                return res;
            }

            // Vehicle
            const vm = clean.match(/\[([^\]]+)\]\s*([^.]+)\.\s*VehicleId:\s*(\d+)\.\s*Owner:\s*([^.]+)\./i);
            if (vm) {
                const rawAction = vm[1].trim();
                const rawModel = vm[2].trim();
                const vehicleId = vm[3].trim();
                const rawOwner = vm[4].trim();

                const cleanModel = rawModel.replace(/(_ES|_C|BP_)/g, '').replace(/([A-Z])/g, ' $1').trim();
                res.player = cleanModel + ' #' + vehicleId;

                if (rawOwner !== 'N/A' && rawOwner !== '') {
                    const om = rawOwner.match(/(\d+)\s*\(([^)]+)\)/);
                    if (om) {
                        res.steamId = om[1];
                        res.player = om[2].trim();
                    } else {
                        res.player = rawOwner;
                    }
                }

                if (/Destroyed/i.test(rawAction)) {
                    res.badgeText = 'Уничтожен';
                    res.badgeClass = 'bg-[#da3633]/15 text-[#f85149] border-[#da3633]/30';
                    res.details = `Транспорт ${cleanModel} (#${vehicleId}) был уничтожен`;
                } else if (/Disappeared/i.test(rawAction)) {
                    res.badgeText = 'Исчез';
                    res.badgeClass = 'bg-[#d29922]/15 text-[#d29922] border-[#d29922]/30';
                    res.details = `Транспорт ${cleanModel} (#${vehicleId}) исчез с сервера`;
                } else if (/InactiveTimer/i.test(rawAction)) {
                    res.badgeText = '⏱️ Истёк таймер';
                    res.badgeClass = 'bg-[#30363d]/40 text-[#8b949e] border-[#30363d]';
                    res.details = `Сработал таймер неактивности для ${cleanModel} (#${vehicleId})`;
                } else if (/converted to item/i.test(rawAction)) {
                    res.badgeText = 'В предмет';
                    res.badgeClass = 'bg-[#1f6feb]/15 text-[#58a6ff] border-[#388bfd]/30';
                    res.details = `Транспорт ${cleanModel} (#${vehicleId}) упакован в предмет`;
                } else {
                    res.badgeText = rawAction;
                    res.details = `Транспорт ${cleanModel} (#${vehicleId}): ${rawAction}`;
                }

                const locM = clean.match(/Location:\s*X=([-\d.]+)\s+Y=([-\d.]+)/i);
                if (locM) {
                    res.coords = { x: parseFloat(locM[1]), y: parseFloat(locM[2]) };
                }
                return res;
            }

            // Generic category fallback
            const mapIcons = { login: 'Вход/Выход', vehicle: 'Транспорт', online: 'Онлайн', chest: 'Сундук', economy: 'Экономика', famepoints: 'Слава', gameplay: 'Геймплей', kill: 'Убийство', quests: 'Квесты', sentry: 'Турель' };
            res.badgeText = mapIcons[type] || 'Событие';
            return res;
        },

        applyFilter() {
            const q = this.q.trim().toLowerCase();
            const container = this.$refs.logBody;
            if (!container) return;
            container.querySelectorAll('[data-search]').forEach(el => {
                const searchStr = (el.getAttribute('data-search') || el.dataset?.search || el.textContent || '').toLowerCase();
                const ok = !q || searchStr.includes(q);
                el.style.display = ok ? '' : 'none';
            });
        },

        clearQuery() {
            this.q = '';
            this.applyFilter();
        },

        esc(s) {
            const d = document.createElement('div');
            d.textContent = s;
            return d.innerHTML;
        },

        scrollToBottom() {
            if (this.mode === 'system') {
                const c = this.$refs.logBody;
                if (c) c.scrollTop = c.scrollHeight;
            }
        },

        clearNew() {
            this.newCount = 0;
            this.$el.querySelectorAll('.log-new-entry').forEach(el => el.classList.remove('log-new-entry'));
        }
    };
}
