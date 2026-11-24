export function copyToClipboard(text, btn) {
    const done = ok => {
        if (btn) {
            const old = btn.innerHTML;
            btn.innerHTML = ok ? '<span class="text-xs font-semibold text-white">Скопировано</span>' : '<span class="text-xs font-semibold text-white">Ошибка</span>';
            btn.classList.add('!bg-[#238636]', '!border-[#2ea043]');
            setTimeout(() => {
                btn.innerHTML = old;
                btn.classList.remove('!bg-[#238636]', '!border-[#2ea043]');
            }, 1500);
        }
        if (window.Alpine && window.Alpine.store('toasts')) {
            const preview = String(text).length > 40 ? String(text).substring(0, 37) + '…' : text;
            window.Alpine.store('toasts').add(ok ? 'Скопировано: ' + preview : 'Не удалось скопировать', ok ? 'success' : 'error', 2500);
        }
    };
    const fallback = () => {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(true); } catch (e) { done(false); }
        document.body.removeChild(ta);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => done(true), fallback);
    } else fallback();
}
