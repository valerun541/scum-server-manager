export function playerActions(steamId, actionUrl, csrfToken, vehiclesUrl = '') {
    const titles = {
        give_money: 'Выдать деньги',
        give_gold: 'Выдать золото',
        give_fame: 'Выдать славу',
        spawn_item: 'Выдать предмет',
        chest_full: 'Выдать ящик с предметами',
        spawn_vehicle: 'Заспавнить ТС',
        spawn_npc: 'Заспавнить NPC / существо',
        starter_pack: 'Стартовый набор',
        teleport_to_vehicle: 'Телепорт к транспорту',
        knockout: 'Нокаут',
        announce: 'Объявление',
        notify: 'Уведомление игроку',
        chat: 'Сообщение в чат',
        teleport_to: 'Телепорт по координатам',
    };
    return {
        steamId,
        actionUrl,
        csrfToken,
        vehiclesUrl,
        open: '',
        amount: 500,
        item: '',
        count: 1,
        chestItem: 'Improvised_Metal_Chest',
        vehicle: '',
        seconds: 10,
        message: '',
        color: 'Yellow',
        type: 1,
        x: '',
        y: '',
        z: '',
        busy: false,
        notice: null,
        vehicleList: [],
        vehiclesLoading: false,

        openAction(a) {
            this.open = a;
            this.notice = null;
            this.item = '';
            this.vehicle = '';
            this.count = 1;
            this.amount = ['give_money', 'give_gold', 'give_fame'].includes(a) ? 500 : 1;
            if (a === 'teleport_to_vehicle') this.loadVehicles();
        },
        close() {
            this.open = '';
            this.notice = null;
        },
        title() {
            return titles[this.open] || 'Действие';
        },
        loadVehicles() {
            if (!this.vehiclesUrl) return;
            this.vehiclesLoading = true;
            this.vehicleList = [];
            fetch(this.vehiclesUrl, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then((r) => r.json())
                .then((d) => {
                    this.vehicleList = d.vehicles || [];
                    if (this.vehicleList.length === 0) {
                        this.notice = { ok: false, text: 'У игрока не найден транспорт' };
                    }
                })
                .catch(() => {
                    this.notice = { ok: false, text: 'Не удалось загрузить транспорт' };
                })
                .finally(() => {
                    this.vehiclesLoading = false;
                });
        },
        teleportToVehicle(v) {
            this.run('teleport_to_vehicle', { x: v.x, y: v.y, z: v.z });
        },
        run(action, params = {}) {
            if (this.busy) return;
            this.busy = true;
            this.notice = { ok: null, text: 'Выполняется…' };
            const body = { steam_id: this.steamId, action, ...params };
            fetch(this.actionUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.csrfToken,
                },
                body: JSON.stringify(body),
            })
                .then((r) => r.json())
                .then((d) => {
                    this.notice = { ok: !!d.success, text: d.message || (d.success ? 'Выполнено' : 'Ошибка') };
                    if (this.open && d.success) this.open = '';
                })
                .catch(() => {
                    this.notice = { ok: false, text: 'Ошибка запроса' };
                })
                .finally(() => {
                    this.busy = false;
                });
        },
        submitModal() {
            const p = {};
            if (this.open === 'spawn_item') { p.item = this.item; p.amount = this.amount; }
            else if (this.open === 'chest_full') { p.chest_item = this.chestItem; p.item = this.item; p.count = this.count; }
            else if (this.open === 'spawn_vehicle') { p.vehicle = this.vehicle; }
            else if (this.open === 'spawn_npc') { p.npc = this.item; p.amount = this.amount; }
            else if (this.open === 'knockout') { p.seconds = this.seconds; }
            else if (this.open === 'announce') { p.message = this.message; }
            else if (this.open === 'notify') { p.message = this.message; p.type = this.type; }
            else if (this.open === 'chat') { p.message = this.message; p.color = this.color; }
            else if (this.open === 'teleport_to') { p.x = this.x; p.y = this.y; p.z = this.z; }
            else { p.amount = this.amount; }
            this.run(this.open, p);
        },
    };
}