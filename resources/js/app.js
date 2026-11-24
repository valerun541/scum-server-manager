import Alpine from 'alpinejs';

import { copyToClipboard } from './lib/clipboard.js';
import { serverForm } from './components/serverForm.js';
import { rconTerm } from './components/rconTerm.js';
import { mapView } from './components/mapView.js';
import { searchList } from './components/searchList.js';
import { cardForm } from './components/cardForm.js';
import { automationEditor } from './components/automationEditor.js';
import { playerActions } from './components/playerActions.js';
import { scumTableEditor } from './components/scumTableEditor.js';
import { configEditor } from './components/configEditor.js';
import { logPoller } from './components/logPoller.js';

window.Alpine = Alpine;
window.copyToClipboard = copyToClipboard;

/* ===== Глобальное состояние UI: модалка + сайдбар + Command Palette ===== */
Alpine.store('ui', {
    modalOpen: false,
    modalTitle: '…',
    modalBody: '<div class="text-sm text-[#8b949e]">Загрузка…</div>',
    sidebarOpen: false,
    sidebarCollapsed: localStorage.getItem('sidebar_collapsed') === 'true',
    cmdPaletteOpen: false,
    autoRefreshTimer: null,

    openModal(url, title, refreshSeconds) {
        this.modalTitle = title || '…';
        this.modalBody = '<div class="text-sm text-[#8b949e]">Загрузка…</div>';
        this.modalOpen = true;
        this.clearAutoRefresh();
        const sep = url.includes('?') ? '&' : '?';
        const load = () => fetch(url + sep + 'fragment=1', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.text())
            .then(html => { this.modalBody = html; })
            .catch(() => { this.modalBody = '<div class="text-sm text-[#f85149]">Ошибка загрузки</div>'; });
        load();
        if (refreshSeconds > 0) {
            this.autoRefreshTimer = setInterval(load, refreshSeconds * 1000);
        }
    },

    clearAutoRefresh() {
        if (this.autoRefreshTimer) {
            clearInterval(this.autoRefreshTimer);
            this.autoRefreshTimer = null;
        }
    },

    closeModal() {
        this.clearAutoRefresh();
        this.modalOpen = false;
    },

    toggleSidebar() {
        this.sidebarOpen = !this.sidebarOpen;
        document.body.style.overflow = this.sidebarOpen ? 'hidden' : '';
    },

    closeSidebar() {
        this.sidebarOpen = false;
        document.body.style.overflow = '';
    },

    toggleCollapse() {
        this.sidebarCollapsed = !this.sidebarCollapsed;
        localStorage.setItem('sidebar_collapsed', this.sidebarCollapsed ? 'true' : 'false');
    },

    openCmdPalette() {
        this.cmdPaletteOpen = true;
    },

    closeCmdPalette() {
        this.cmdPaletteOpen = false;
    },

    toggleCmdPalette() {
        this.cmdPaletteOpen = !this.cmdPaletteOpen;
    }
});

/* ===== Глобальные всплывающие Toast-уведомления ===== */
Alpine.store('toasts', {
    items: [],
    add(message, type = 'success', duration = 3500) {
        const id = Date.now() + Math.random();
        this.items.push({ id, message, type });
        if (duration > 0) {
            setTimeout(() => this.remove(id), duration);
        }
    },
    remove(id) {
        this.items = this.items.filter(t => t.id !== id);
    }
});

/* ===== Глобальный слушатель клавиш (Ctrl+K / Cmd+K / Escape) ===== */
window.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        Alpine.store('ui').toggleCmdPalette();
    }
    if (e.key === 'Escape' && Alpine.store('ui').cmdPaletteOpen) {
        Alpine.store('ui').closeCmdPalette();
    }
});

window.toast = (msg, type, duration) => Alpine.store('toasts').add(msg, type, duration);
window.openModal = (url, title, refreshSeconds) => Alpine.store('ui').openModal(url, title, refreshSeconds);

/* ===== Регистрация компонентов ===== */
Alpine.data('serverForm', serverForm);
Alpine.data('rconTerm', rconTerm);
Alpine.data('mapView', mapView);
Alpine.data('searchList', searchList);
Alpine.data('cardForm', cardForm);
Alpine.data('automationEditor', automationEditor);
Alpine.data('playerActions', playerActions);
Alpine.data('scumTableEditor', scumTableEditor);
Alpine.data('configEditor', configEditor);
Alpine.data('logPoller', logPoller);

Alpine.start();
