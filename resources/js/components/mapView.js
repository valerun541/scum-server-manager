import { copyToClipboard } from '../lib/clipboard.js';

export function mapView(cfg) {
    cfg = cfg || {};
    const ROW_LABELS = ['D', 'C', 'B', 'A', 'Z'];
    const COL_LABELS = ['4', '3', '2', '1', '0'];
    const KIND_COLORS = { player: 'text-[#58a6ff]', vehicle: 'text-[#d29922]', flag: 'text-[#a371f7]', chest: 'text-[#f0883e]', poi: 'text-[#f85149]' };

    function fmt(n) {
        n = Number(n) || 0;
        return n.toLocaleString('ru-RU');
    }
    function esc(v) {
        return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function row(label, value, cls) {
        if (value === null || value === undefined || value === '') return '';
        return '<div class="flex items-baseline justify-between gap-3"><span class="text-[#8b949e] shrink-0">' + esc(label) + '</span><span class="text-right ' + (cls || '') + ' font-medium">' + esc(value) + '</span></div>';
    }

    function stateBar(label, pct, color, sub) {
        pct = Math.max(0, Math.min(100, Math.round(Number(pct) || 0)));
        return '<div class="pt-0.5"><div class="flex items-center justify-between gap-3 mb-1"><span class="text-[#8b949e] shrink-0">' + esc(label) + '</span>'
            + '<span class="flex items-center gap-2 shrink-0"><span class="text-[#8b949e] text-[10px]">' + esc(sub || '') + '</span><span class="font-mono font-semibold" style="color:' + color + '">' + pct + '%</span></span></div>'
            + '<div class="h-1.5 rounded-full bg-[#21262d] overflow-hidden"><div class="h-full rounded-full transition-all" style="width:' + pct + '%;background:' + color + '"></div></div></div>';
    }

    return {
        cfg: cfg,
        layers: { players: true, vehicles: true, flags: true, zones: true, chests: true, labels: true, pois: false },
        theme: 'dark',
        coords: '—',
        tipVisible: false,
        tipPinned: false,
        tipName: '',
        tipKind: '',
        tipKindCls: '',
        tipHtml: '',
        tipFoot: '',
        overTip: false,
        lastTip: null,

        // Тактический боковой органайзер (Enterprise GIS Drawer)
        drawerOpen: false,
        drawerTab: 'sectors', // 'sectors' | 'vehicles' | 'flags' | 'chests' | 'players'
        drawerSearch: '',

        chestOpen: false,
        chestLoading: false,
        chestError: '',
        chestTitle: '',
        chestClass: '',
        chestItems: [],
        chestCoords: null,

        vb: null,
        aspect: 1,
        MIN_W: null,
        MAX_W: null,
        drag: null,
        pointers: {},
        pinch: null,
        lastTap: null,
        fullscreen: false,
        animId: null,

        toggleFullscreen() {
            const el = this.$refs.mapContainer || this.$root;
            if (!document.fullscreenElement && !document.webkitFullscreenElement) {
                if (el.requestFullscreen) {
                    el.requestFullscreen();
                } else if (el.webkitRequestFullscreen) {
                    el.webkitRequestFullscreen();
                }
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                }
            }
        },

        toggleDrawer() {
            this.drawerOpen = !this.drawerOpen;
            this.$nextTick(() => {
                this.updateAspect();
            });
        },

        setDrawerTab(tab) {
            this.drawerTab = tab;
            this.drawerOpen = true;
        },

        init() {
            if (!this.$refs.svg) return;
            document.addEventListener('fullscreenchange', () => {
                this.fullscreen = !!document.fullscreenElement;
                this.$nextTick(() => this.updateAspect());
            });
            document.addEventListener('webkitfullscreenchange', () => {
                this.fullscreen = !!document.webkitFullscreenElement;
                this.$nextTick(() => this.updateAspect());
            });
            window.addEventListener('resize', () => {
                this.updateAspect();
            });

            const saved = (() => { try { return localStorage.getItem('map-theme'); } catch (e) { return null; } })();
            if (saved) this.theme = saved;
            this.applyTheme();
            this.$watch('theme', () => this.applyTheme());

            this.MIN_W = cfg.size / 32;
            this.MAX_W = cfg.size * 2.5;

            // Инициализация адаптивного viewBox
            this.vb = { x: 0, y: 0, w: cfg.size, h: cfg.size };
            this.updateAspect(true);

            this.bindMarkers();
            this.resolveLabelCollisions();

            // Автофокус по URL
            this.$nextTick(() => {
                this.checkUrlTarget();
            });
        },

        updateAspect(isInitial = false) {
            const svg = this.$refs.svg;
            if (!svg) return;
            const r = svg.getBoundingClientRect();
            if (r.width > 50 && r.height > 50) {
                this.aspect = r.width / r.height;
            } else {
                this.aspect = 1;
            }

            if (isInitial) {
                if (this.aspect > 1) {
                    // Widescreen: карта острова занимает 100% высоты по центру, ширина плавно расширяется
                    this.vb.h = cfg.size;
                    this.vb.w = cfg.size * this.aspect;
                    this.vb.x = - (this.vb.w - cfg.size) / 2;
                    this.vb.y = 0;
                } else {
                    this.vb.w = cfg.size;
                    this.vb.h = cfg.size / this.aspect;
                    this.vb.x = 0;
                    this.vb.y = - (this.vb.h - cfg.size) / 2;
                }
            } else {
                // Поддерживаем текущий масштаб и соотношение сторон
                this.vb.h = this.vb.w / this.aspect;
            }
            this.clampVb();
        },

        checkUrlTarget() {
            const params = new URLSearchParams(window.location.search);
            const xStr = params.get('x');
            const yStr = params.get('y');
            const steamId = params.get('steam_id');
            const name = params.get('name');

            if (xStr !== null && yStr !== null) {
                const worldX = parseFloat(xStr);
                const worldY = parseFloat(yStr);
                if (!isNaN(worldX) && !isNaN(worldY)) {
                    const span = this.cfg.span || (this.cfg.maxX - this.cfg.minX);
                    const size = this.cfg.size || 4096;
                    const svgX = size - ((worldX - this.cfg.minX) / span) * size;
                    const svgY = ((this.cfg.maxY - worldY) / span) * size;

                    this.animateTo(svgX, svgY, size / 6);

                    setTimeout(() => {
                        let targetMarker = null;
                        if (steamId) {
                            const markers = this.$refs.svg.querySelectorAll('.marker-player');
                            for (let m of markers) {
                                try {
                                    const info = JSON.parse(m.getAttribute('data-info') || '{}');
                                    if (info.steamId && String(info.steamId) === String(steamId)) {
                                        targetMarker = m;
                                        break;
                                    }
                                } catch (e) {}
                            }
                        }
                        if (!targetMarker && name) {
                            const markers = this.$refs.svg.querySelectorAll('.marker-player');
                            for (let m of markers) {
                                if (m.getAttribute('data-name') === name) {
                                    targetMarker = m;
                                    break;
                                }
                            }
                        }
                        if (!targetMarker) {
                            const allMarkers = this.$refs.svg.querySelectorAll('.marker');
                            let minDist = 150;
                            for (let m of allMarkers) {
                                const tr = m.getAttribute('transform');
                                if (tr && tr.startsWith('translate(')) {
                                    const parts = tr.replace('translate(', '').replace(')', '').split(/[\s,]+/);
                                    if (parts.length >= 2) {
                                        const mx = parseFloat(parts[0]);
                                        const my = parseFloat(parts[1]);
                                        const dist = Math.hypot(mx - svgX, my - svgY);
                                        if (dist < minDist) {
                                            minDist = dist;
                                            targetMarker = m;
                                        }
                                    }
                                }
                            }
                        }

                        if (targetMarker) {
                            this.tipPinned = true;
                            this.renderTip(targetMarker);
                            this.tipVisible = true;
                            this.$nextTick(() => {
                                const svg = this.$refs.svg;
                                const tip = this.$refs.tip;
                                if (svg && tip) {
                                    const r = svg.getBoundingClientRect();
                                    const screenX = r.left + (svgX - this.vb.x) * (r.width / this.vb.w);
                                    const screenY = r.top + (svgY - this.vb.y) * (r.height / this.vb.h);
                                    this.positionTip({ clientX: screenX, clientY: screenY });
                                }
                            });
                        }
                    }, 350);
                }
            }
        },

        animateTo(cx, cy, targetW, duration = 300) {
            if (this.animId) cancelAnimationFrame(this.animId);
            const startX = this.vb.x;
            const startY = this.vb.y;
            const startW = this.vb.w;
            const startH = this.vb.h;

            const finalW = Math.max(this.MIN_W, Math.min(this.MAX_W, targetW));
            const finalH = finalW / this.aspect;
            const finalX = cx - finalW / 2;
            const finalY = cy - finalH / 2;

            const startTime = performance.now();
            const step = (now) => {
                const elapsed = now - startTime;
                const progress = Math.min(1, elapsed / duration);
                // Ease out cubic
                const ease = 1 - Math.pow(1 - progress, 3);

                this.vb.w = startW + (finalW - startW) * ease;
                this.vb.h = startH + (finalH - startH) * ease;
                this.vb.x = startX + (finalX - startX) * ease;
                this.vb.y = startY + (finalY - startY) * ease;

                this.clampVb();

                if (progress < 1) {
                    this.animId = requestAnimationFrame(step);
                } else {
                    this.animId = null;
                }
            };
            this.animId = requestAnimationFrame(step);
        },

        flyToSector(sec) {
            sec = String(sec).toUpperCase().trim();
            if (sec.length < 2) return;
            const rowChar = sec.charAt(0);
            const colChar = sec.charAt(1);
            const ri = ROW_LABELS.indexOf(rowChar);
            const ci = COL_LABELS.indexOf(colChar);
            if (ri < 0 || ci < 0) return;
            const step = (this.cfg.size || 4096) / 5;
            const targetX = (ci + 0.5) * step;
            const targetY = (ri + 0.5) * step;
            this.animateTo(targetX, targetY, step * 1.6);
        },

        flyToEntity(kind, idOrName) {
            const svg = this.$refs.svg;
            if (!svg) return;
            const selector = kind ? `.marker-${kind}` : '.marker';
            const markers = svg.querySelectorAll(selector);
            let target = null;
            for (let m of markers) {
                if (m.getAttribute('data-name') === idOrName) {
                    target = m;
                    break;
                }
                try {
                    const info = JSON.parse(m.getAttribute('data-info') || '{}');
                    if (String(info.entityId) === String(idOrName) || String(info.steamId) === String(idOrName) || String(info.baseId) === String(idOrName)) {
                        target = m;
                        break;
                    }
                } catch (e) {}
            }

            if (target) {
                const tr = target.getAttribute('transform');
                if (tr && tr.startsWith('translate(')) {
                    const parts = tr.replace('translate(', '').replace(')', '').split(/[\s,]+/);
                    if (parts.length >= 2) {
                        const tx = parseFloat(parts[0]);
                        const ty = parseFloat(parts[1]);
                        this.animateTo(tx, ty, (this.cfg.size || 4096) / 5);
                        setTimeout(() => {
                            this.tipPinned = true;
                            this.renderTip(target);
                            this.tipVisible = true;
                            const r = svg.getBoundingClientRect();
                            const screenX = r.left + (tx - this.vb.x) * (r.width / this.vb.w);
                            const screenY = r.top + (ty - this.vb.y) * (r.height / this.vb.h);
                            this.positionTip({ clientX: screenX, clientY: screenY });
                        }, 320);
                    }
                }
            }
        },

        applyTheme() {
            const img = this.$refs.mapImage;
            if (img) img.setAttribute('href', (this.cfg.imageUrl || '/map/image') + '?theme=' + encodeURIComponent(this.theme));
            try { localStorage.setItem('map-theme', this.theme); } catch (e) {}
        },

        layerStyle(key) {
            return this.layers[key] ? '' : 'display:none';
        },

        fmt: fmt,

        applyVb() {
            const svg = this.$refs.svg;
            if (svg) svg.setAttribute('viewBox', this.vb.x + ' ' + this.vb.y + ' ' + this.vb.w + ' ' + this.vb.h);
        },

        clampVb() {
            this.vb.w = Math.max(this.MIN_W, Math.min(this.MAX_W, this.vb.w));
            this.vb.h = this.vb.w / this.aspect;
            // Разрешаем свободное панорамирование вокруг острова с тактическим запасом
            const marginX = Math.max(cfg.size * 0.4, this.vb.w * 0.4);
            const marginY = Math.max(cfg.size * 0.4, this.vb.h * 0.4);
            this.vb.x = Math.max(-marginX, Math.min(cfg.size + marginX - this.vb.w, this.vb.x));
            this.vb.y = Math.max(-marginY, Math.min(cfg.size + marginY - this.vb.h, this.vb.y));
            this.applyVb();
        },

        zoomAt(sx, sy, factor) {
            const newW = Math.max(this.MIN_W, Math.min(this.MAX_W, this.vb.w * factor));
            const newH = newW / this.aspect;
            const f = newW / this.vb.w;
            this.vb.x = sx - (sx - this.vb.x) * f;
            this.vb.y = sy - (sy - this.vb.y) * f;
            this.vb.w = newW;
            this.vb.h = newH;
            this.clampVb();
        },

        zoomIn() { this.zoomAt(this.vb.x + this.vb.w / 2, this.vb.y + this.vb.h / 2, 0.72); },
        zoomOut() { this.zoomAt(this.vb.x + this.vb.w / 2, this.vb.y + this.vb.h / 2, 1.38); },
        zoomReset() {
            this.updateAspect(true);
        },

        wheel(e) {
            e.preventDefault();
            const svg = this.$refs.svg;
            const r = svg.getBoundingClientRect();
            const sx = this.vb.x + (e.clientX - r.left) * (this.vb.w / r.width);
            const sy = this.vb.y + (e.clientY - r.top) * (this.vb.h / r.height);
            this.zoomAt(sx, sy, e.deltaY < 0 ? 0.72 : 1.38);
        },

        pointerList() {
            return Object.values(this.pointers);
        },

        pointerDist() {
            const p = this.pointerList();
            if (p.length < 2) return 0;
            return Math.hypot(p[0].x - p[1].x, p[0].y - p[1].y);
        },

        pointerMid() {
            const p = this.pointerList();
            if (p.length < 2) return null;
            return { x: (p[0].x + p[1].x) / 2, y: (p[0].y + p[1].y) / 2 };
        },

        panStart(e) {
            if (e.target && e.target.closest && e.target.closest('.marker')) return;
            this.pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
            try { this.$refs.svg.setPointerCapture(e.pointerId); } catch (err) {}
            this.$refs.svg.classList.add('panning');
            if (this.pointerList().length === 2) {
                const mid = this.pointerMid();
                this.pinch = { dist: this.pointerDist(), lastDist: this.pointerDist(), lastMid: mid };
                this.drag = null;
            } else {
                this.drag = { x: e.clientX, y: e.clientY, moved: false };
            }
        },

        panMove(e) {
            if (this.pointers[e.pointerId]) this.pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
            if (this.pinch && this.pointerList().length >= 2) {
                const mid = this.pointerMid();
                const dist = this.pointerDist();
                const svg = this.$refs.svg;
                const r = svg.getBoundingClientRect();

                if (dist > 0 && this.pinch.lastDist > 0 && Math.abs(dist - this.pinch.lastDist) > 1) {
                    const sx = this.vb.x + (mid.x - r.left) * (this.vb.w / r.width);
                    const sy = this.vb.y + (mid.y - r.top) * (this.vb.h / r.height);
                    const factor = this.pinch.lastDist / dist;
                    this.zoomAt(sx, sy, factor);
                    this.pinch.lastDist = dist;
                }

                if (this.pinch.lastMid) {
                    const dx = mid.x - this.pinch.lastMid.x;
                    const dy = mid.y - this.pinch.lastMid.y;
                    if (Math.abs(dx) > 0.5 || Math.abs(dy) > 0.5) {
                        const u = this.vb.w / r.width;
                        this.vb.x -= dx * u;
                        this.vb.y -= dy * u;
                        this.clampVb();
                    }
                }
                this.pinch.lastMid = mid;
                return;
            }
            if (!this.drag) return;
            const dx = e.clientX - this.drag.x;
            const dy = e.clientY - this.drag.y;
            if (Math.abs(dx) > 2 || Math.abs(dy) > 2) this.drag.moved = true;
            if (!this.drag.moved) return;
            this.lastTap = null;
            const u = this.vb.w / this.$refs.svg.clientWidth;
            this.vb.x -= dx * u;
            this.vb.y -= dy * u;
            this.clampVb();
            this.drag.x = e.clientX; this.drag.y = e.clientY;
        },

        panEnd(e) {
            delete this.pointers[e.pointerId];
            try { this.$refs.svg.releasePointerCapture(e.pointerId); } catch (err) {}
            if (this.pinch && this.pointerList().length < 2) {
                this.pinch = null;
                const remaining = this.pointerList();
                if (remaining.length === 1) {
                    this.drag = { x: remaining[0].x, y: remaining[0].y, moved: false };
                }
            }
            const wasTap = this.drag && !this.drag.moved;
            this.drag = null;
            if (this.$refs.svg) this.$refs.svg.classList.remove('panning');
            if (wasTap && e) {
                this.updateCoordsAt(e);
                this.handleTap(e);
            }
        },

        setCoords(sx, sy) {
            if (sx < 0 || sy < 0 || sx > cfg.size || sy > cfg.size) { this.coords = '—'; return; }
            const wx = cfg.maxX - (sx / cfg.size) * cfg.span;
            const wy = cfg.minY + (sy / cfg.size) * cfg.span;
            const step = cfg.size / 5;
            const ci = Math.max(0, Math.min(4, Math.floor(sx / step)));
            const ri = Math.max(0, Math.min(4, Math.floor(sy / step)));
            const sec = ROW_LABELS[ri] + COL_LABELS[ci];
            this.coords = 'X ' + fmt(Math.round(wx)) + '  Y ' + fmt(Math.round(wy)) + '  ·  ' + sec;
        },

        updateCoordsAt(e) {
            const svg = this.$refs.svg;
            const r = svg.getBoundingClientRect();
            const sx = this.vb.x + (e.clientX - r.left) * (this.vb.w / r.width);
            const sy = this.vb.y + (e.clientY - r.top) * (this.vb.h / r.height);
            this.setCoords(sx, sy);
        },

        handleTap(e) {
            const now = Date.now();
            const svg = this.$refs.svg;
            const r = svg.getBoundingClientRect();
            const sx = this.vb.x + (e.clientX - r.left) * (this.vb.w / r.width);
            const sy = this.vb.y + (e.clientY - r.top) * (this.vb.h / r.height);
            if (this.lastTap && (now - this.lastTap.t) < 350
                && Math.hypot(e.clientX - this.lastTap.x, e.clientY - this.lastTap.y) < 48) {
                this.zoomAt(sx, sy, 0.55);
                this.lastTap = null;
                return;
            }
            this.lastTap = { t: now, x: e.clientX, y: e.clientY };
        },

        move(e) {
            const svg = this.$refs.svg;
            const r = svg.getBoundingClientRect();
            const sx = this.vb.x + (e.clientX - r.left) * (this.vb.w / r.width);
            const sy = this.vb.y + (e.clientY - r.top) * (this.vb.h / r.height);
            this.setCoords(sx, sy);
        },

        clearCoords() {
            this.coords = '—';
        },

        copyCoord(e, x, y, z) {
            e.stopPropagation();
            e.preventDefault();
            const text = x + ' ' + y + (z !== null && z !== undefined ? ' ' + z : '');
            copyToClipboard(text, e.currentTarget);
        },

        coordRow(x, y, z) {
            const zTxt = (z !== null && z !== undefined) ? '  Z ' + fmt(z) : '';
            return '<div class="flex items-center justify-between gap-3 mt-1 pt-1.5 border-t border-[#21262d]">'
                + '<span class="text-[#8b949e] shrink-0">Координаты</span>'
                + '<button type="button" data-copy="' + x + ' ' + y + (z !== null && z !== undefined ? ' ' + z : '') + '" '
                + 'class="coord-copy pointer-events-auto font-mono text-[11px] text-[#58a6ff] bg-[#0d1117] border border-[#30363d] rounded px-2 py-0.5 hover:border-[#58a6ff] transition-colors" '
                + 'style="cursor: copy !important; pointer-events: auto !important;">X ' + fmt(x) + '  Y ' + fmt(y) + zTxt + '  ⧉</button>'
                + '</div>';
        },

        renderTip(g) {
            const kind = g.getAttribute('data-kind');
            this.tipName = g.getAttribute('data-name') || '';
            const kindTitles = { player: 'Игрок', vehicle: 'Транспорт', flag: 'База / Флаг', chest: 'Сундук' };
            this.tipKind = kindTitles[kind] || 'Объект';
            this.tipKindCls = KIND_COLORS[kind] || 'text-[#8b949e]';
            this.tipFoot = 'ESC / клик — закрыть · координаты копируются';

            let info = {};
            try { info = JSON.parse(g.getAttribute('data-info') || '{}'); } catch (e) {}

            let html = '';
            if (kind === 'player') {
                if (info.lastLogin) {
                    const onlineSec = Math.max(0, Math.floor(Date.now() / 1000) - info.lastLogin);
                    let ot = '';
                    if (onlineSec > 60) {
                        const oh = Math.floor(onlineSec / 3600);
                        const om = Math.floor((onlineSec % 3600) / 60);
                        ot = (oh > 0 ? oh + ' ч ' : '') + om + ' мин';
                    } else ot = 'только что';
                    html += row('В сети', ot, 'text-[#3fb950]');
                }
                html += row('Сектор', info.sector, 'text-[#58a6ff]');
                html += row('SteamID', info.steamId, 'text-[#e6edf3] break-all');
                html += row('Деньги', fmt(info.money) + ' $', 'text-[#3fb950]');
                html += row('🪙 Золото', fmt(info.gold), 'text-[#d29922]');
                if (info.steamId) {
                    html += '<div class="flex items-center gap-2 mt-2 pt-2 border-t border-[#21262d]">'
                        + '<a href="/scumdb/player/' + encodeURIComponent(info.steamId) + '" class="flex-1 text-center py-1 rounded bg-[#21262d] hover:bg-[#30363d] text-xs font-semibold text-[#58a6ff] transition-colors">Профиль игрока</a>'
                        + '<button type="button" onclick="window.openModal(\'/players/whois/' + encodeURIComponent(info.steamId) + '\', \'Whois: ' + esc(this.tipName) + '\')" class="px-2.5 py-1 rounded bg-[#21262d] hover:bg-[#30363d] text-xs text-[#c9d1d9] transition-colors">Whois</button>'
                        + '</div>';
                }
            } else if (kind === 'vehicle') {
                const vColor = info.broken ? '#f85149' : '#3fb950';
                html += stateBar('Состояние', info.health, vColor, info.state);
                html += row('Сектор', info.sector, 'text-[#d29922]');
                if (info.entityId) html += row('ID', fmt(info.entityId), 'text-[#e6edf3]');
                if (info.alias) html += row('Псевдоним', info.alias, 'text-[#e6edf3]');
                if (info.auto) html += row('Спавн', 'автоматический', 'text-[#8b949e]');
                if (info.forbidden) html += row('Запретная зона', info.forbidden + ' с', 'text-[#f85149]');
                if (info.last) html += row('Последний доступ', info.last, 'text-[#e6edf3]');
            } else if (kind === 'flag') {
                const fColor = String(info.state || '').indexOf('осп') === 0 ? '#f0883e' : '#a371f7';
                html += stateBar('Состояние', info.health, fColor, info.state);
                html += row('Сектор', info.sector, 'text-[#a371f7]');
                html += row('Владелец', info.owner, 'text-[#e6edf3]');
                if (info.ownerSteamId) html += row('SteamID', info.ownerSteamId, 'text-[#8b949e] break-all');
                if (info.squadName && Number(info.isOwnedByPlayer) === 0) html += row('Отряд', info.squadName, 'text-[#58a6ff]');
                if (info.elementOwner && info.elementOwnerSteamId && info.baseOwnerSteamId && info.elementOwnerSteamId !== info.baseOwnerSteamId) {
                    html += row('Флаг поставил', info.elementOwner, 'text-[#d29922]');
                    if (info.elementOwnerSteamId) html += row('SteamID флага', info.elementOwnerSteamId, 'text-[#8b949e] break-all');
                }
                if (info.overtaker) html += row('Оспаривает', info.overtaker + (info.overtakerSteamId ? ' (' + info.overtakerSteamId + ')' : ''), 'text-[#f0883e]');
                if (info.overtakeEnd) {
                    const sec = info.overtakeEnd - Math.floor(Date.now() / 1000);
                    let t = '';
                    if (sec > 0) {
                        const dd = Math.floor(sec / 86400);
                        const hh = Math.floor((sec % 86400) / 3600);
                        const mm = Math.floor((sec % 3600) / 60);
                        if (dd > 0) t = dd + ' д ' + hh + ' ч';
                        else if (hh > 0) t = hh + ' ч ' + mm + ' мин';
                        else t = mm + ' мин';
                        t += ' осталось';
                    } else t = 'завершено';
                    html += row('Оспаривание до', t, 'text-[#f0883e]');
                }
                if (info.baseId) html += row('База', '#' + fmt(info.baseId), 'text-[#8b949e]');
                html += row('ID флага', info.elementId ? fmt(info.elementId) : '', 'text-[#e6edf3]');
            } else if (kind === 'chest') {
                html += row('Владелец', info.owner || '—', 'text-[#e6edf3]');
                if (info.ownerSteamId) html += row('Владелец SteamID', info.ownerSteamId, 'text-[#8b949e] break-all');
                html += row('Сектор', info.sector, 'text-[#f0883e]');
                html += row('Перехватов', Math.max(0, info.ownerChanges), 'text-[#f85149]');
                if (info.claimedAt) html += row('Установлен', info.claimedAt, 'text-[#8b949e]');
                if (info.entityId) html += row('ID', fmt(info.entityId), 'text-[#e6edf3]');
                if (info.entityId) {
                    html += '<button type="button" data-chest="' + info.entityId + '" class="pointer-events-auto w-full mt-1.5 pt-1.5 border-t border-[#21262d] text-left font-mono text-[11px] text-[#58a6ff] hover:text-[#e6edf3] transition-colors">Содержимое сундука</button>';
                }
            }

            if (info.x !== undefined && info.y !== undefined) {
                html += this.coordRow(info.x, info.y, info.z);
            }
            this.tipHtml = html;
            this.lastTip = g;
        },

        showTip(g, e) {
            this.renderTip(g);
            this.tipVisible = true;
            this.positionTip(e);
        },

        positionTip(e) {
            const svg = this.$refs.svg;
            const tip = this.$refs.tip;
            if (!tip || !tip.offsetParent) { this.$nextTick(() => this.positionTip(e)); return; }
            const r = svg.getBoundingClientRect();
            const x = e.clientX - r.left;
            const y = e.clientY - r.top;
            const tw = tip.offsetWidth, th = tip.offsetHeight;
            let px = x + 18;
            let py = y + 16;
            if (px + tw > r.width) px = x - tw - 14;
            if (px < 4) px = 4;
            if (py + th > r.height) py = y - th - 12;
            if (py < 4) py = 4;
            tip.style.left = px + 'px';
            tip.style.top = py + 'px';
        },

        resolveLabelCollisions() {
            const svg = this.$refs.svg;
            if (!svg) return;
            const labels = Array.from(svg.querySelectorAll('.marker .label'));
            if (labels.length < 2) return;
            const rects = [];
            labels.forEach(l => {
                let b;
                try { b = l.getBBox(); } catch (e) { return; }
                if (!b.width || !b.height) return;
                const g = l.closest('.marker');
                let dx = 0, dy = 0;
                const tr = g && g.transform && g.transform.baseVal && g.transform.baseVal.consolidate
                    ? g.transform.baseVal.consolidate() : null;
                if (tr) { dx = tr.matrix.e; dy = tr.matrix.f; }
                rects.push({ el: l, x: b.x + dx, y: b.y + dy, w: b.width, h: b.height });
            });
            const kept = [];
            for (let i = rects.length - 1; i >= 0; i--) {
                const r = rects[i];
                const collides = kept.some(k => r.x < k.x + k.w && r.x + r.w > k.x && r.y < k.y + k.h && r.y + r.h > k.y);
                if (collides) {
                    r.el.classList.add('label-hidden');
                } else {
                    kept.push(r);
                }
            }
        },

        bindMarkers() {
            const svg = this.$refs.svg;
            const onEnter = (g) => (e) => {
                if (this.tipPinned) return;
                this.showTip(g, e);
            };
            const onMove = (g) => (e) => {
                if (this.tipPinned || this.overTip) return;
                this.positionTip(e);
            };
            const onLeave = (g) => () => {
                if (this.tipPinned || this.overTip) return;
                this.tipVisible = false;
            };
            const onClick = (g) => (e) => {
                e.stopPropagation();
                this.tipPinned = true;
                this.showTip(g, e);
            };
            svg.querySelectorAll('.marker').forEach(g => {
                g.addEventListener('mouseenter', onEnter(g));
                g.addEventListener('mousemove', onMove(g));
                g.addEventListener('mouseleave', onLeave(g));
                g.addEventListener('click', onClick(g));
            });
        },

        svgClick(e) {
            if (e.target && e.target.closest && e.target.closest('.marker')) return;
            if (e.target && e.target.closest && e.target.closest('.coord-copy')) return;
            this.tipPinned = false;
            this.tipVisible = false;
        },

        tipEnter() { this.overTip = true; },
        tipLeave() {
            this.overTip = false;
            if (!this.tipPinned) this.tipVisible = false;
        },

        onTipCopy(e) {
            const btn = e.target.closest('.coord-copy');
            if (btn) {
                e.stopPropagation();
                copyToClipboard(btn.getAttribute('data-copy'), btn);
                return;
            }
            const chestBtn = e.target.closest('[data-chest]');
            if (chestBtn) {
                e.stopPropagation();
                this.openChest(chestBtn.getAttribute('data-chest'));
            }
        },

        openChest(entityId) {
            this.chestOpen = true;
            this.chestLoading = true;
            this.chestError = '';
            this.chestItems = [];
            this.chestTitle = 'Сундук #' + entityId;
            this.chestClass = '';
            this.chestCoords = null;
            fetch('/map/chest/' + encodeURIComponent(entityId), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(data => {
                    this.chestLoading = false;
                    if (!data || !data.ok) {
                        this.chestError = (data && data.error) || 'Не удалось загрузить содержимое';
                        return;
                    }
                    this.chestTitle = 'Сундук #' + data.entityId;
                    this.chestClass = data.entityClass || '';
                    this.chestCoords = data.location || null;
                    (data.items || []).forEach(i => {
                        const c = (i.components || []).find(x => x.name === 'Count');
                        i.count = c && c.value !== null ? c.value : null;
                        i.components = (i.components || []).filter(x => x.name !== 'Count' && x.value !== null);
                    });
                    this.chestItems = data.items || [];
                })
                .catch(() => {
                    this.chestLoading = false;
                    this.chestError = 'Ошибка сети';
                });
        },

        closeChest() {
            this.chestOpen = false;
            this.chestLoading = false;
            this.chestError = '';
            this.chestItems = [];
            this.chestCoords = null;
        },
    };
}
