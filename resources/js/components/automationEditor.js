import { copyToClipboard } from '../lib/clipboard.js';

export function automationEditor(initialTrigger = 'chat_command') {
    let parsedNodes = [];
    let parsedConns = [];

    const nodesEl = document.getElementById('wf-initial-nodes');
    const connsEl = document.getElementById('wf-initial-conns');
    if (nodesEl && nodesEl.textContent) {
        try {
            parsedNodes = JSON.parse(nodesEl.textContent.trim());
        } catch (e) {
            console.error('Failed to parse initial nodes:', e);
            parsedNodes = [];
        }
    }
    if (connsEl && connsEl.textContent) {
        try {
            parsedConns = JSON.parse(connsEl.textContent.trim());
        } catch (e) {
            console.error('Failed to parse initial connections:', e);
            parsedConns = [];
        }
    }

    function generateId() {
        return 'n_' + Math.random().toString(36).substring(2, 8);
    }

    // Построение линейного списка шагов из нод и соединений
    function buildStepsFromGraph(nodes, conns) {
        if (!Array.isArray(nodes) || nodes.length === 0) return [];
        
        const nodeMap = new Map();
        nodes.forEach(n => {
            if (n && n.id) {
                nodeMap.set(n.id, {
                    id: n.id,
                    type: n.type || 'rcon_command',
                    config: n.config ? JSON.parse(JSON.stringify(n.config)) : {}
                });
            }
        });

        // Находим цепочку начиная от trigger
        const ordered = [];
        const visited = new Set();
        let currentId = null;

        // Ищем первый узел от trigger
        const startConn = (conns || []).find(c => c.fromNodeId === 'trigger');
        if (startConn && nodeMap.has(startConn.toNodeId)) {
            currentId = startConn.toNodeId;
        }

        while (currentId && nodeMap.has(currentId) && !visited.has(currentId)) {
            visited.add(currentId);
            const node = nodeMap.get(currentId);
            ordered.push(node);

            // Ищем следующее соединение
            const nextConn = (conns || []).find(c => c.fromNodeId === currentId && (c.fromPort === 'main' || c.fromPort === 'true' || !c.fromPort));
            currentId = nextConn ? nextConn.toNodeId : null;
        }

        // Если остались узлы, не попавшие в линейную цепочку — добавляем их в конец
        nodeMap.forEach((node, id) => {
            if (!visited.has(id)) {
                ordered.push(node);
            }
        });

        return ordered;
    }

    const initialSteps = buildStepsFromGraph(parsedNodes, parsedConns);

    return {
        trigger: initialTrigger || 'chat_command',
        mode: 'visual', // 'visual' | 'expert'
        steps: initialSteps,
        rawNodesJson: JSON.stringify(parsedNodes, null, 2),
        rawConnectionsJson: JSON.stringify(parsedConns, null, 2),
        lastFocusedInput: null,
        addMenuOpen: false,
        testDrawerOpen: false,
        testSteamId: '76561198000000000',
        testPlayerName: 'PlayerOne',
        testMessage: '!starter',

        // Метаданные типов шагов
        stepMeta: {
            rcon_command: {
                title: 'RCON Команда',
                icon: 'zap',
                badge: 'badge-green',
                color: '#3fb950',
                desc: 'Выполнить команду на игровом сервере'
            },
            lolka_webhook: {
                title: 'Lolka Бот',
                icon: 'message-square',
                badge: 'badge-blue',
                color: '#58a6ff',
                desc: 'Отправить уведомление в Lolka через Webhook'
            },
            wargm_process: {
                title: 'Wargm Выдача',
                icon: 'shopping-cart',
                badge: 'badge-yellow',
                color: '#f0883e',
                desc: 'Автоматическая выдача покупок игрока с Wargm'
            },
            delay: {
                title: 'Задержка (Пауза)',
                icon: 'clock',
                badge: 'badge-yellow',
                color: '#d29922',
                desc: 'Подождать перед следующим действием'
            },
            condition: {
                title: 'Условие (Фильтр)',
                icon: 'share-2',
                badge: 'badge-purple',
                color: '#bc8cff',
                desc: 'Проверить условие перед продолжением'
            },
            log: {
                title: 'Запись в лог',
                icon: 'file-text',
                badge: 'badge-gray',
                color: '#8b949e',
                desc: 'Записать сообщение в лог-файл панели'
            },
            variable: {
                title: 'Переменная',
                icon: 'coins',
                badge: 'badge-blue',
                color: '#79c0ff',
                desc: 'Сохранить или изменить значение переменной'
            },
            http_request: {
                title: 'HTTP Запрос',
                icon: 'link',
                badge: 'badge-green',
                color: '#2ea043',
                desc: 'Отправить webhook / HTTP-запрос на сторонний API'
            },
        },

        init() {
            if (this.steps.length === 0 && parsedNodes.length > 0) {
                this.steps = parsedNodes.map(n => ({
                    id: n.id || generateId(),
                    type: n.type || 'rcon_command',
                    config: n.config || {}
                }));
            }
            this.syncToRawJson();

            // Отслеживание активного текстового поля для вставки переменных
            document.addEventListener('focusin', (e) => {
                if (e.target && (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') && !e.target.classList.contains('no-tag-insert')) {
                    this.lastFocusedInput = e.target;
                }
            });
        },

        selectTrigger(type) {
            this.trigger = type;
        },

        setMode(newMode) {
            if (newMode === 'visual') {
                this.syncFromRawJson();
            } else {
                this.syncToRawJson();
            }
            this.mode = newMode;
        },

        // Добавление шага
        addStep(type) {
            const id = generateId();
            let defaultConfig = {};

            switch (type) {
                case 'rcon_command':
                    defaultConfig = { command: 'SpawnItem BP_Weapon_AK47 1 Location "{{steamId}}"', retryCount: 0 };
                    break;
                case 'lolka_webhook':
                    defaultConfig = { webhookKey: 'chat', content: '{{playerName}}, действие выполнено!', username: '' };
                    break;
                case 'wargm_process':
                    defaultConfig = {};
                    break;
                case 'delay':
                    defaultConfig = { duration: 3, unit: 'seconds' };
                    break;
                case 'condition':
                    defaultConfig = { field: '{{message}}', operator: 'eq', value: '!starter' };
                    break;
                case 'log':
                    defaultConfig = { level: 'info', message: 'Действие выполнено для {{playerName}} ({{steamId}})' };
                    break;
                case 'variable':
                    defaultConfig = { scope: 'player', action: 'increment', name: 'count', value: '1' };
                    break;
                case 'http_request':
                    defaultConfig = { method: 'POST', url: '', body: '', responseVar: '' };
                    break;
                default:
                    defaultConfig = {};
            }

            this.steps.push({
                id: id,
                type: type,
                config: defaultConfig
            });

            this.addMenuOpen = false;
            this.syncToRawJson();
        },

        // Удаление шага
        removeStep(index) {
            this.steps.splice(index, 1);
            this.syncToRawJson();
        },

        // Перемещение шага вверх
        moveStepUp(index) {
            if (index <= 0) return;
            const temp = this.steps[index];
            this.steps[index] = this.steps[index - 1];
            this.steps[index - 1] = temp;
            this.syncToRawJson();
        },

        // Перемещение шага вниз
        moveStepDown(index) {
            if (index >= this.steps.length - 1) return;
            const temp = this.steps[index];
            this.steps[index] = this.steps[index + 1];
            this.steps[index + 1] = temp;
            this.syncToRawJson();
        },

        // Дублирование шага
        duplicateStep(index) {
            const original = this.steps[index];
            const clone = {
                id: generateId(),
                type: original.type,
                config: JSON.parse(JSON.stringify(original.config || {}))
            };
            this.steps.splice(index + 1, 0, clone);
            this.syncToRawJson();
        },

        // Вставка команды / сниппета в шаг RCON
        applyRconSnippet(stepIndex, snippet) {
            if (this.steps[stepIndex]) {
                this.steps[stepIndex].config.command = snippet;
                this.syncToRawJson();
            }
        },

        // Вставка тега переменной в последнее активное поле
        insertTag(tag) {
            const formatted = `{{${tag}}}`;
            const target = this.lastFocusedInput;

            if (target && typeof target.selectionStart === 'number') {
                const start = target.selectionStart;
                const end = target.selectionEnd;
                const val = target.value || '';
                target.value = val.substring(0, start) + formatted + val.substring(end);
                target.selectionStart = target.selectionEnd = start + formatted.length;
                target.dispatchEvent(new Event('input', { bubbles: true }));
                target.focus();
                if (window.toast) window.toast(`Вставлено: ${formatted}`, 'success', 1500);
            } else {
                copyToClipboard(formatted);
                if (window.toast) window.toast(`Скопировано: ${formatted}`, 'info', 1500);
            }
        },

        // Синхронизация steps -> raw JSON
        syncToRawJson() {
            const nodes = this.steps.map(s => ({
                id: s.id,
                type: s.type,
                config: s.config || {}
            }));

            const connections = [];
            for (let i = 0; i < this.steps.length; i++) {
                const prevId = i === 0 ? 'trigger' : this.steps[i - 1].id;
                const prevStep = i === 0 ? null : this.steps[i - 1];
                const fromPort = prevStep && prevStep.type === 'condition' ? 'true' : 'main';

                connections.push({
                    fromNodeId: prevId,
                    fromPort: fromPort,
                    toNodeId: this.steps[i].id,
                    toPort: 'main'
                });
            }

            this.rawNodesJson = JSON.stringify(nodes, null, 2);
            this.rawConnectionsJson = JSON.stringify(connections, null, 2);
        },

        // Синхронизация raw JSON -> steps
        syncFromRawJson() {
            try {
                const nodes = JSON.parse(this.rawNodesJson || '[]');
                const conns = JSON.parse(this.rawConnectionsJson || '[]');
                this.steps = buildStepsFromGraph(nodes, conns);
            } catch (e) {
                if (window.toast) window.toast('Ошибка в формате JSON узлов!', 'error');
            }
        },

        // Загрузка готового пресета
        loadPreset(presetKey) {
            const nameInput = document.querySelector('input[name="name"]');

            if (presetKey === 'starter_kit') {
                if (nameInput) nameInput.value = 'Стартовый набор (!starter)';
                this.trigger = 'chat_command';
                const trigInput = document.querySelector('input[name="trigger_chat_trigger"]');
                const aliasInput = document.querySelector('input[name="trigger_chat_aliases"]');
                const cdInput = document.querySelector('input[name="trigger_chat_cooldown"]');
                if (trigInput) trigInput.value = 'starter';
                if (aliasInput) aliasInput.value = 'st, start, kit';
                if (cdInput) cdInput.value = '3600';

                this.steps = [
                    { id: generateId(), type: 'rcon_command', config: { command: 'SpawnItem BP_Backpack_01 1 Location "{{steamId}}"', retryCount: 1 } },
                    { id: generateId(), type: 'rcon_command', config: { command: 'SpawnItem BP_Weapon_AK47 1 Location "{{steamId}}"' } },
                    { id: generateId(), type: 'lolka_webhook', config: { webhookKey: 'chat', content: '{{playerName}}, ваш стартовый набор успешно выдан!', username: 'SCUM Server' } },
                    { id: generateId(), type: 'log', config: { level: 'info', message: 'Выдан стартовый набор для {{playerName}} ({{steamId}})' } }
                ];
            } else if (presetKey === 'welcome_player') {
                if (nameInput) nameInput.value = 'Приветствие новичка (player_join)';
                this.trigger = 'event';
                const evSelect = document.querySelector('select[name="trigger_event_event"]');
                if (evSelect) evSelect.value = 'player_join';

                this.steps = [
                    { id: generateId(), type: 'delay', config: { duration: 3, unit: 'seconds' } },
                    { id: generateId(), type: 'rcon_command', config: { command: 'SendNotification 1 "Добро пожаловать, {{playerName}}! Напишите !starter для получения стартового набора." {{steamId}}' } },
                    { id: generateId(), type: 'lolka_webhook', config: { webhookKey: 'login', content: 'Игрок **{{playerName}}** подключился к серверу!', username: 'SCUM Server' } }
                ];
            } else if (presetKey === 'periodic_announcement') {
                if (nameInput) nameInput.value = 'Авто-анонс каждые 30 минут';
                this.trigger = 'schedule';
                const schedMin = document.querySelector('input[name="trigger_schedule_minutes"]');
                const schedInt = document.querySelector('input[name="trigger_schedule_interval"]');
                if (schedMin) schedMin.value = '30';
                if (schedInt) schedInt.checked = true;

                this.steps = [
                    { id: generateId(), type: 'rcon_command', config: { command: 'Announce "Внимание! Посетите наш Lolka для информации о правилах и ивентах!"' } },
                    { id: generateId(), type: 'log', config: { level: 'info', message: 'Авто-анонс отправлен в {{now.time}}' } }
                ];
            } else if (presetKey === 'wargm_delivery') {
                if (nameInput) nameInput.value = 'Авто-выдача покупок Wargm (!wargm)';
                this.trigger = 'chat_command';
                const trigInput = document.querySelector('input[name="trigger_chat_trigger"]');
                const aliasInput = document.querySelector('input[name="trigger_chat_aliases"]');
                const cdInput = document.querySelector('input[name="trigger_chat_cooldown"]');
                if (trigInput) trigInput.value = 'wargm';
                if (aliasInput) aliasInput.value = 'donate, shop, magazin';
                if (cdInput) cdInput.value = '30';

                this.steps = [
                    { id: generateId(), type: 'wargm_process', config: {} }
                ];
            } else if (presetKey === 'daily_reward') {
                if (nameInput) nameInput.value = 'Ежедневная награда (!daily)';
                this.trigger = 'chat_command';
                const trigInput = document.querySelector('input[name="trigger_chat_trigger"]');
                const aliasInput = document.querySelector('input[name="trigger_chat_aliases"]');
                const cdInput = document.querySelector('input[name="trigger_chat_cooldown"]');
                if (trigInput) trigInput.value = 'daily';
                if (aliasInput) aliasInput.value = 'bonus, day';
                if (cdInput) cdInput.value = '86400';

                this.steps = [
                    { id: generateId(), type: 'rcon_command', config: { command: 'ChangeCurrencyBalance Cash 500 {{steamId}}' } },
                    { id: generateId(), type: 'rcon_command', config: { command: 'ChangeFamePoints 25 {{steamId}}' } },
                    { id: generateId(), type: 'rcon_command', config: { command: 'SendNotification 1 "Вы получили ежедневный бонус: 500$ и 25 очков славы!" {{steamId}}' } },
                    { id: generateId(), type: 'log', config: { level: 'info', message: 'Ежедневный бонус выдан {{playerName}} ({{steamId}})' } }
                ];
            }

            this.syncToRawJson();
            if (window.toast) window.toast('Шаблон успешно применён!', 'success');
        },

        // Копирование кода / сниппета
        copyCode(btn) {
            const pre = btn.closest('div')?.querySelector('pre') || btn.parentElement?.parentElement?.querySelector('pre');
            if (pre) copyToClipboard(pre.innerText, btn);
        }
    };
}
