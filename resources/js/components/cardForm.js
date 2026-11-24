/**
 * Интерактивный визуальный конструктор карточек и наград WARGM
 */
export function cardForm(initialCards = []) {
    return {
        activeTab: 'cards',
        showModal: false,
        modalMode: 'create', // 'create' | 'edit'
        searchQuery: '',
        selectedPreset: '',
        cards: initialCards || [],
        cardsMap: (initialCards || []).reduce((acc, c) => {
            acc[c.id] = c;
            return acc;
        }, {}),

        // Список поддерживаемых навыков SCUM с русскими названиями
        availableSkills: [
            { id: 'ALL', name: 'Все навыки (ALL)' },
            { id: 'Archery', name: 'Стрельба из лука (Archery)' },
            { id: 'Aviation', name: 'Пилотирование (Aviation)' },
            { id: 'Awareness', name: 'Внимательность (Awareness)' },
            { id: 'Brawling', name: 'Рукопашный бой (Brawling)' },
            { id: 'Camouflage', name: 'Маскировка (Camouflage)' },
            { id: 'Cooking', name: 'Кулинария (Cooking)' },
            { id: 'Demolition', name: 'Подрывное дело (Demolition)' },
            { id: 'Driving', name: 'Вождение (Driving)' },
            { id: 'Endurance', name: 'Выносливость (Endurance)' },
            { id: 'Engineering', name: 'Инженерия (Engineering)' },
            { id: 'Farming', name: 'Фермерство (Farming)' },
            { id: 'Handgun', name: 'Пистолеты (Handgun)' },
            { id: 'Medical', name: 'Медицина (Medical)' },
            { id: 'Motorcycling', name: 'Мотоциклы (Motorcycling)' },
            { id: 'Resistance', name: 'Сопротивление (Resistance)' },
            { id: 'Rifles', name: 'Винтовки (Rifles)' },
            { id: 'Running', name: 'Бег (Running)' },
            { id: 'Sniping', name: 'Снайпинг (Sniping)' },
            { id: 'Stealth', name: 'Скрытность (Stealth)' },
            { id: 'Survival', name: 'Выживание (Survival)' },
            { id: 'Tactics', name: 'Тактика (Tactics)' },
            { id: 'Thievery', name: 'Воровство (Thievery)' },
            { id: '"Melee Weapons"', name: 'Холодное оружие (Melee)' },
        ],

        // Популярные виды транспорта
        vehicleSuggestions: [
            { name: 'BP_Dirtbike', label: 'Кроссовый мотоцикл (Dirtbike)' },
            { name: 'BP_Wolfswagen', label: 'Внедорожник (Wolfswagen)' },
            { name: 'BP_Laika', label: 'Автомобиль (Laika)' },
            { name: 'BP_Cruiser', label: 'Круизер (Cruiser)' },
            { name: 'BP_Kinglet_Duster', label: 'Самолёт (Kinglet Duster)' },
            { name: 'BP_Dinghy', label: 'Надувная лодка (Dinghy)' },
        ],

        // Готовые комплексные наборы (пресеты)
        presets: [
            {
                id: 'starter',
                name: 'Набор новичка',
                items: [
                    { type: 'item', data: { itemName: 'Military_Backpack_01', amount: 1 } },
                    { type: 'item', data: { itemName: 'MRE_Cheeseburger', amount: 3 } },
                    { type: 'item', data: { itemName: 'Emergency_Bandage', amount: 4 } },
                    { type: 'money', data: { amount: 5000 } },
                    { type: 'fame', data: { amount: 100 } },
                ]
            },
            {
                id: 'weapon_pack',
                name: 'Оружейный пак: AK-47 + Патроны',
                items: [
                    { type: 'item', data: { itemName: 'Weapon_AK47', amount: 1 } },
                    { type: 'item', data: { itemName: 'Cal_7_62x39mm_Ammobox_Closed', amount: 4 } },
                    { type: 'item', data: { itemName: 'Bulletproof_Vest_01', amount: 1 } },
                ]
            },
            {
                id: 'sniper_pack',
                name: 'Снайперский комплект Barrett .50',
                items: [
                    { type: 'item', data: { itemName: 'Weapon_Barret_M82A1', amount: 1 } },
                    { type: 'item', data: { itemName: 'Cal_50_AE_Ammobox_Closed', amount: 5 } },
                    { type: 'item', data: { itemName: 'Night_Vision_Goggles', amount: 1 } },
                    { type: 'skill', data: { skillName: 'Sniping', level: 3 } },
                ]
            },
            {
                id: 'vehicle_pack',
                name: 'Транспортный набор: Wolfswagen + Канистра',
                items: [
                    { type: 'vehicle', data: { vehicleName: 'BP_Wolfswagen' } },
                    { type: 'item', data: { itemName: 'Canister10L', amount: 2 } },
                    { type: 'item', data: { itemName: 'Car_Repair_Kit', amount: 2 } },
                ]
            },
            {
                id: 'vip_starter',
                name: 'VIP Премиум (30 дней + Бонусы)',
                items: [
                    { type: 'vip', data: { days: 30 } },
                    { type: 'gold', data: { amount: 20 } },
                    { type: 'money', data: { amount: 25000 } },
                    { type: 'fame', data: { amount: 500 } },
                ]
            },
            {
                id: 'full_skills',
                name: 'Полная прокачка всех навыков',
                items: [
                    { type: 'skill', data: { skillName: 'ALL', level: 4 } },
                    { type: 'attributes', data: { strength: 5, stamina: 5, dexterity: 5, intellect: 5 } },
                ]
            },
            {
                id: 'insurance_pack',
                name: 'Страховка транспорта (30 дней)',
                items: [
                    { type: 'insurance', data: { claims: 1, days: 30 } },
                ]
            }
        ],

        // Форма создания / редактирования карточки
        form: {
            id: null,
            name: '',
            shopId: '',
            enabled: true,
            items: [],
            itemsJson: '[]',
            jsonMode: false,
            rawJson: '',
        },

        init() {
            if (window.location.hash) {
                const tab = window.location.hash.replace('#', '');
                if (['cards', 'deliveries', 'settings', 'check', 'import'].includes(tab)) {
                    this.activeTab = tab;
                }
            }
        },

        setTab(tab) {
            this.activeTab = tab;
            window.location.hash = tab;
        },

        filterCards() {
            const q = (this.searchQuery || '').trim().toLowerCase();
            const elements = document.querySelectorAll('[data-card-item]');
            elements.forEach(el => {
                const searchTxt = (el.getAttribute('data-search') || '').toLowerCase();
                const ok = !q || searchTxt.includes(q);
                el.style.display = ok ? '' : 'none';
            });
        },

        clearSearch() {
            this.searchQuery = '';
            this.filterCards();
        },

        async loadItemInfo(item) {
            if (!item || !item.data) return;
            if (item.type === 'item' && item.data.itemName && !item.data._info) {
                try {
                    const q = encodeURIComponent(item.data.itemName.trim());
                    const res = await fetch(`/catalog-api/items?q=${q}&limit=5`, { headers: { 'Accept': 'application/json' } });
                    const json = await res.json();
                    if (json.ok && json.data && json.data.length > 0) {
                        const found = json.data.find(r => r.id.toLowerCase() === item.data.itemName.toLowerCase()) || json.data[0];
                        item.data._info = found;
                    }
                } catch(e) {}
            } else if (item.type === 'vehicle' && item.data.vehicleName && !item.data._info) {
                try {
                    const q = encodeURIComponent(item.data.vehicleName.trim());
                    const res = await fetch(`/catalog-api/vehicles?q=${q}`, { headers: { 'Accept': 'application/json' } });
                    const json = await res.json();
                    if (json.ok && json.data && json.data.length > 0) {
                        const found = json.data.find(v => v.id.toLowerCase() === item.data.vehicleName.toLowerCase()) || json.data[0];
                        item.data._info = found;
                    }
                } catch(e) {}
            }
        },

        openCreateModal() {
            this.modalMode = 'create';
            this.form.id = null;
            this.form.name = '';
            this.form.shopId = '';
            this.form.enabled = true;
            this.form.items = [];
            this.form.jsonMode = false;
            this.form.rawJson = '[]';
            this.syncJson();
            this.showModal = true;
        },

        editCard(cardId) {
            const card = this.cardsMap[cardId];
            if (card) {
                this.loadCard(card);
            }
        },

        loadCard(card) {
            this.modalMode = 'edit';
            this.form.id = card.id;
            this.form.name = card.name || '';
            this.form.shopId = card.shopItemId || '';
            this.form.enabled = !!card.enabled;
            
            this.form.items = (card.items || []).map((it, idx) => {
                const row = {
                    _uid: 'it_' + Date.now() + '_' + idx + '_' + Math.random().toString(36).substr(2, 4),
                    type: it.type || 'item',
                    data: JSON.parse(JSON.stringify(it.data || {}))
                };
                this.loadItemInfo(row);
                return row;
            });

            this.form.jsonMode = false;
            this.syncJson();
            this.showModal = true;
        },

        closeModal() {
            this.showModal = false;
        },

        applyPreset(presetId) {
            const found = this.presets.find(p => p.id === presetId);
            if (!found) return;

            if (this.modalMode === 'create' && (!this.form.name || this.form.name === '')) {
                this.form.name = found.name;
            }

            const newItems = found.items.map((it, idx) => {
                const row = {
                    _uid: 'it_' + Date.now() + '_' + idx + '_' + Math.random().toString(36).substr(2, 4),
                    type: it.type,
                    data: JSON.parse(JSON.stringify(it.data || {}))
                };
                this.loadItemInfo(row);
                return row;
            });

            this.form.items = [...this.form.items, ...newItems];
            this.syncJson();
            this.selectedPreset = '';
        },

        addItem(type) {
            const uid = 'it_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6);
            let defaultData = {};

            switch (type) {
                case 'item':
                    defaultData = { itemName: '', amount: 1 };
                    break;
                case 'vehicle':
                    defaultData = { vehicleName: '' };
                    break;
                case 'money':
                    defaultData = { amount: 1000 };
                    break;
                case 'gold':
                    defaultData = { amount: 5 };
                    break;
                case 'fame':
                    defaultData = { amount: 100 };
                    break;
                case 'skill':
                    defaultData = { skillName: 'Rifles', level: 3 };
                    break;
                case 'attributes':
                    defaultData = { strength: 5, stamina: 5, dexterity: 5, intellect: 5 };
                    break;
                case 'vip':
                    defaultData = { days: 30 };
                    break;
                case 'cargo_drop':
                    defaultData = {};
                    break;
                case 'insurance':
                    defaultData = { claims: 1, days: 30 };
                    break;
            }

            this.form.items.push({
                _uid: uid,
                type: type,
                data: defaultData
            });

            this.syncJson();
        },

        removeItem(index) {
            this.form.items.splice(index, 1);
            this.syncJson();
        },

        moveItem(index, direction) {
            const target = index + direction;
            if (target < 0 || target >= this.form.items.length) return;
            const temp = this.form.items[index];
            this.form.items[index] = this.form.items[target];
            this.form.items[target] = temp;
            this.syncJson();
        },

        duplicateItem(index) {
            const orig = this.form.items[index];
            if (!orig) return;
            const copy = {
                _uid: 'it_' + Date.now() + '_' + Math.random().toString(36).substr(2, 6),
                type: orig.type,
                data: JSON.parse(JSON.stringify(orig.data || {}))
            };
            if (orig.data && orig.data._info) {
                copy.data._info = orig.data._info;
            }
            this.form.items.splice(index + 1, 0, copy);
            this.syncJson();
        },

        syncJson() {
            const clean = this.form.items.map(it => {
                const dataCopy = { ...(it.data || {}) };
                delete dataCopy._info;
                return {
                    type: it.type,
                    data: dataCopy
                };
            });
            this.form.itemsJson = JSON.stringify(clean);
            this.form.rawJson = JSON.stringify(clean, null, 2);
        },

        toggleJsonMode() {
            if (!this.form.jsonMode) {
                this.syncJson();
                this.form.jsonMode = true;
            } else {
                try {
                    const parsed = JSON.parse(this.form.rawJson);
                    if (Array.isArray(parsed)) {
                        this.form.items = parsed.map((it, idx) => {
                            const row = {
                                _uid: 'it_' + Date.now() + '_' + idx + '_' + Math.random().toString(36).substr(2, 4),
                                type: it.type || 'item',
                                data: it.data || {}
                            };
                            this.loadItemInfo(row);
                            return row;
                        });
                        this.syncJson();
                        this.form.jsonMode = false;
                    } else {
                        alert('JSON должен быть массивом объектов наград');
                    }
                } catch (e) {
                    alert('Ошибка парсинга JSON: ' + e.message);
                }
            }
        },

        handleRawJsonInput() {
            try {
                const parsed = JSON.parse(this.form.rawJson);
                if (Array.isArray(parsed)) {
                    this.form.itemsJson = JSON.stringify(parsed);
                }
            } catch (e) {
                // Ignore while typing in JSON mode
            }
        },

        typeIcon(type) {
            const icons = {
                item: '<svg class="w-4 h-4 text-[#f0883e]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="14.5 17.5 3 6 3 3 6 3 17.5 14.5"/><line x1="13" y1="19" x2="19" y2="13"/><line x1="16" y1="16" x2="20" y2="20"/><line x1="19" y1="21" x2="21" y2="19"/></svg>',
                vehicle: '<svg class="w-4 h-4 text-[#58a6ff]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 17h2c.6 0 1-.4 1-1v-3c0-.9-.7-1.7-1.5-1.9C18.7 10.6 16 10 16 10s-1.3-1.4-2.2-2.3c-.5-.4-1.1-.7-1.8-.7H5c-.6 0-1.1.4-1.4.9l-1.4 2.9A3.7 3.7 0 0 0 2 12v4c0 .6.4 1 1 1h2"/><circle cx="7" cy="17" r="2"/><path d="M9 17h6"/><circle cx="17" cy="17" r="2"/></svg>',
                skill: '<svg class="w-4 h-4 text-[#d2a8ff]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
                attributes: '<svg class="w-4 h-4 text-[#3fb950]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6.5 6.5 11 11"/><path d="m21 21-1-1"/><path d="m3 3 1 1"/><path d="m18 22 4-4"/><path d="m2 6 4-4"/><path d="m3 10 7-7"/><path d="m14 21 7-7"/></svg>',
                money: '<svg class="w-4 h-4 text-[#3fb950]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
                gold: '<svg class="w-4 h-4 text-[#e3b341]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="8" r="6"/><path d="M18.09 10.37A6 6 0 1 1 10.34 18"/><path d="M7 6h1v4"/><path d="m16.71 13.88.7.71-2.82 2.82"/></svg>',
                fame: '<svg class="w-4 h-4 text-[#f0883e]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>',
                cargo_drop: '<svg class="w-4 h-4 text-[#bc8cff]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>',
                vip: '<svg class="w-4 h-4 text-[#bc8cff]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m2 4 3 12h14l3-12-6 7-4-7-4 7-6-7zm3 16h14"/></svg>',
                insurance: '<svg class="w-4 h-4 text-[#58a6ff]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
            };
            return icons[type] || '<svg class="w-4 h-4 text-[#8b949e]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/></svg>';
        },

        typeLabel(type) {
            const labels = {
                item: 'Предмет',
                vehicle: 'Транспорт',
                skill: 'Навык',
                attributes: 'Атрибуты',
                money: 'Деньги ($)',
                gold: 'Золото',
                fame: 'Слава',
                cargo_drop: 'Аирдроп',
                vip: 'VIP статус',
                insurance: 'Страховка ТС',
            };
            return labels[type] || type;
        },

        typeBadge(type) {
            const badges = {
                item: 'bg-[#1f6feb]/15 text-[#58a6ff] border-[#388bfd]/30',
                vehicle: 'bg-[#d29922]/15 text-[#d29922] border-[#d29922]/30',
                skill: 'bg-[#a371f7]/15 text-[#a371f7] border-[#a371f7]/30',
                attributes: 'bg-[#d29922]/15 text-[#d29922] border-[#d29922]/30',
                money: 'bg-[#238636]/15 text-[#3fb950] border-[#238636]/30',
                gold: 'bg-[#f0883e]/15 text-[#f0883e] border-[#f0883e]/30',
                fame: 'bg-[#da3633]/15 text-[#f85149] border-[#da3633]/30',
                cargo_drop: 'bg-[#21262d] text-[#c9d1d9] border-[#30363d]',
                vip: 'bg-[#bc4c9a]/15 text-[#d2a8ff] border-[#bc4c9a]/30',
                insurance: 'bg-[#238636]/15 text-[#3fb950] border-[#2ea043]/30',
            };
            return badges[type] || 'bg-[#21262d] text-[#8b949e] border-[#30363d]';
        },

        copyToClipboard(text) {
            if (window.copyToClipboard) {
                window.copyToClipboard(text);
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(text);
            }
        }
    };
}
