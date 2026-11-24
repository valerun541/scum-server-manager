export function scumTableEditor(cfg) {
    return {
        table: cfg.table,
        cols: cfg.cols,
        rows: cfg.rows,
        hasPk: cfg.hasPk,
        hasRowId: cfg.hasRowId,
        updateUrl: cfg.updateUrl,
        insertUrl: cfg.insertUrl,
        deleteUrl: cfg.deleteUrl,
        csrf: cfg.csrf,
        open: '',
        values: {},
        id: {},
        busy: false,
        notice: null,

        isPk(c) { return (c.pk || 0) > 0; },
        isBlob(c) { return /BLOB/i.test(c.type || ''); },
        isNum(c) { return /INT|REAL|FLOA|DOUB|NUM|BOOL/i.test(c.type || ''); },
        isReadonly(c) { return !!c.readonly; },

        rowId(row) {
            const id = {};
            for (const c of this.cols) {
                if (this.isPk(c)) id[c.name] = row[c.name];
            }
            if (!this.hasPk && this.hasRowId && row.__rowid__ !== undefined) {
                return { __rowid__: row.__rowid__ };
            }
            return id;
        },

        openEdit(row) {
            this.id = this.rowId(row);
            this.values = {};
            for (const c of this.cols) {
                this.values[c.name] = row[c.name] === null || row[c.name] === undefined ? '' : row[c.name];
            }
            this.open = 'edit';
            this.notice = null;
        },
        openAdd() {
            this.id = {};
            this.values = {};
            for (const c of this.cols) this.values[c.name] = '';
            this.open = 'add';
            this.notice = null;
        },
        close() {
            this.open = '';
            this.notice = null;
        },
        title() {
            return this.open === 'edit' ? 'Редактировать строку' : 'Добавить строку';
        },
        submit() {
            if (this.busy) return;
            const cols = {};
            for (const c of this.cols) {
                if (this.isReadonly(c)) continue;
                const v = this.values[c.name];
                cols[c.name] = v === null || v === undefined ? '' : String(v);
            }
            const url = this.open === 'edit' ? this.updateUrl : this.insertUrl;
            this.busy = true;
            this.notice = null;
            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.csrf,
                },
                body: JSON.stringify({ table: this.table, _id: this.id, cols }),
            })
                .then((r) => r.json())
                .then((d) => {
                    this.notice = { ok: !!d.success, text: d.message || (d.success ? 'Сохранено' : 'Ошибка') };
                    if (d.success) {
                        setTimeout(() => location.reload(), 900);
                    }
                })
                .catch(() => { this.notice = { ok: false, text: 'Ошибка запроса' }; })
                .finally(() => { this.busy = false; });
        },
        remove(row) {
            if (this.busy) return;
            const id = this.rowId(row);
            if (!confirm('Удалить эту строку?\nЭто действие изменит SCUM.db на сервере.')) return;
            this.busy = true;
            fetch(this.deleteUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': this.csrf,
                },
                body: JSON.stringify({ table: this.table, _id: id }),
            })
                .then((r) => r.json())
                .then((d) => {
                    if (d.success) {
                        location.reload();
                    } else {
                        alert(d.message || 'Ошибка удаления');
                        this.busy = false;
                    }
                })
                .catch(() => { alert('Ошибка запроса'); this.busy = false; });
        },
        fieldHint(c) {
            if (this.isBlob(c)) return 'BLOB (двоичные данные) — редактирование недоступно';
            if (this.isNum(c)) return 'Число · пустое поле = NULL';
            return 'Пустое поле = NULL';
        },
    };
}