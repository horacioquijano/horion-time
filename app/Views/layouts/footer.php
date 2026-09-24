 </div> <!-- Fin content-wrapper -->
    </main>
</div> <!-- Fin app-container -->
<div class="modal-overlay" id="globalModal"><div class="modal-content"></div></div>
<div class="toast-container" id="toastContainer"></div>
<?php $basePath = $basePath ?? rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); ?>
<script src="<?= $basePath ?>/assets/js/horion-app.js"></script>
<?php if (isset($pageScripts)): ?><script><?= $pageScripts ?></script><?php endif; ?>

<!-- =====================================================
     SIDEBAR MÓVIL (drawer) — CSS + JS autocontenido
     ===================================================== -->
<style>
.sidebar-close-mobile{display:none;position:absolute;top:14px;right:14px;background:transparent;border:none;font-size:1.25rem;color:var(--text-dark,#333);cursor:pointer;padding:6px 10px;border-radius:8px;z-index:10}
.sidebar-close-mobile:hover{background:rgba(0,0,0,.08)}
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:998}
.sidebar-overlay.active{display:block}
@media (max-width:992px){
  .sidebar{position:fixed!important;top:0;left:0;height:100vh;transform:translateX(-100%);transition:transform .3s ease;z-index:999!important;box-shadow:4px 0 20px rgba(0,0,0,.2);overflow-y:auto}
  .sidebar.sidebar-open{transform:translateX(0)}
  .sidebar-close-mobile{display:block}
  .sidebar.sidebar-open .nav-item span{display:inline!important}
  .main-content{margin-left:0!important;width:100%!important}
  #toggleSidebar{display:inline-flex!important;align-items:center;justify-content:center}
  .search-box kbd{display:none}
  .user-info{display:none}
}
@media (max-width:576px){
  .sidebar{width:85vw!important;max-width:320px}
  .search-box{display:none!important}
  .top-header select{max-width:150px!important;font-size:.78rem!important}
}
</style>

<script>
(function () {
    function toggleSidebar() {
        var sidebar = document.getElementById('sidebar');
        var overlay = document.getElementById('sidebarOverlay');
        if (!sidebar) return;
        var isOpen = sidebar.classList.toggle('sidebar-open');
        if (overlay) overlay.classList.toggle('active', isOpen);
        document.body.style.overflow = isOpen ? 'hidden' : '';
        if (isOpen) sidebar.classList.remove('collapsed'); // garantiza iconos + texto visibles
        return isOpen;
    }
    window.toggleSidebar = toggleSidebar;

    document.addEventListener('DOMContentLoaded', function () {
        var sb = document.getElementById('sidebar');

        // Inyecta botón ✕ si no existe en el sidebar
        if (sb && !sb.querySelector('.sidebar-close-mobile')) {
            var x = document.createElement('button');
            x.type = 'button';
            x.className = 'sidebar-close-mobile';
            x.setAttribute('aria-label', 'Cerrar menú');
            x.innerHTML = '<i class="fas fa-times"></i>';
            x.addEventListener('click', toggleSidebar);
            sb.insertBefore(x, sb.firstChild);
        }

        // Inyecta overlay oscuro si no existe
        if (!document.getElementById('sidebarOverlay')) {
            var ov = document.createElement('div');
            ov.id = 'sidebarOverlay';
            ov.className = 'sidebar-overlay';
            ov.addEventListener('click', toggleSidebar);
            document.body.appendChild(ov);
        }
    });

    // Botón ☰ del header: en móvil abre/cierra el drawer (en desktop NO toca el comportamiento original)
    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('#toggleSidebar') : null;
        if (!btn || window.innerWidth > 992) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        toggleSidebar();
    }, true);

    // Cierra al navegar por un enlace del menú (solo móvil)
    document.addEventListener('click', function (e) {
        if (window.innerWidth > 992) return;
        var sb = document.getElementById('sidebar');
        if (!sb || !sb.classList.contains('sidebar-open')) return;
        if (e.target.closest('.sidebar a')) toggleSidebar();
    });

    // Cierra con tecla Escape
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var sb = document.getElementById('sidebar');
        if (sb && sb.classList.contains('sidebar-open')) toggleSidebar();
    });

    // Si vuelve a tamaño desktop, limpia estados
    window.addEventListener('resize', function () {
        if (window.innerWidth > 992) {
            var sb = document.getElementById('sidebar');
            var ov = document.getElementById('sidebarOverlay');
            if (sb) sb.classList.remove('sidebar-open');
            if (ov) ov.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
})();
</script>
</body>
</html>
