export function searchList() {
    return {
        q: '',
        match(el) {
            const query = this.q.trim().toLowerCase();
            return !query || (el.textContent || '').toLowerCase().includes(query);
        },
        visible(container) {
            if (!container) return 0;
            return [...container.querySelectorAll('tr, a')].filter(el => el.offsetParent !== null).length;
        },
    };
}
