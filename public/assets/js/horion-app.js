document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('toggleSidebar');
    const sidebar = document.getElementById('sidebar');
    if (toggleBtn && sidebar) {
        if (localStorage.getItem('sidebarCollapsed') === 'true') sidebar.classList.add('collapsed');
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        });
    }
    const themeBtn = document.getElementById('toggleTheme');
    const saved = localStorage.getItem('theme') || 'light';
    document.body.className = 'theme-' + saved;
    if (themeBtn) {
        themeBtn.querySelector('i').className = saved === 'light' ? 'fas fa-moon' : 'fas fa-sun';
        themeBtn.addEventListener('click', () => {
            const next = document.body.classList.contains('theme-light') ? 'dark' : 'light';
            document.body.className = 'theme-' + next;
            localStorage.setItem('theme', next);
            themeBtn.querySelector('i').className = next === 'light' ? 'fas fa-moon' : 'fas fa-sun';
        });
    }
    document.querySelectorAll('.modal-overlay').forEach(ov => {
        ov.addEventListener('click', e => { if (e.target === ov) ov.classList.remove('active'); });
    });
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
    });
});
window.openModal = id => { const m = document.getElementById(id); if (m) m.classList.add('active'); };
window.closeModal = id => { const m = document.getElementById(id); if (m) m.classList.remove('active'); };
window.showToast = (msg, type) => {
    type = type || 'success';
    const colors = { success: 'var(--success)', error: 'var(--danger)', warning: 'var(--warning)', info: 'var(--info)' };
    const icons = { success: 'fa-check-circle', error: 'fa-times-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
    const t = document.createElement('div');
    t.className = 'toast';
    t.style.borderLeftColor = colors[type];
    t.innerHTML = '<i class="fas ' + icons[type] + '" style="color:' + colors[type] + ';font-size:1.2rem"></i><span style="flex:1;font-weight:500">' + msg + '</span>';
    const c = document.getElementById('toastContainer');
    if (c) c.appendChild(t);
    setTimeout(() => t.remove(), 5000);
};
window.confirmDelete = msg => confirm(msg || '¿Está seguro de eliminar este registro?');