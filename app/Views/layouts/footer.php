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
/* ===== SIDEBAR MÓVIL (drawer) — override total ===== */
.sidebar-close-mobile{display:none;position:absolute;top:14px;right:14px;background:transparent;border:none;font-size:1.25rem;color:#333;cursor:pointer;padding:6px 10px;border-radius:8px;z-index:10}
.sidebar-close-mobile:hover{background:rgba(0,0,0,.08)}
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9998}
.sidebar-overlay.active{display:block}
@media (max-width:992px){
  .sidebar{
    display:block!important;
    visibility:visible!important;
    opacity:1!important;
    position:fixed!important;
    top:0!important;
    left:0!important;
    bottom:0!important;
    height:100%!important;
    width:280px!important;
    max-width:85vw!important;
    margin:0!important;
    transform:translateX(-105%)!important;
    transition:transform .3s ease!important;
    z-index:9999!important;
    overflow-y:auto!important;
    box-shadow:4px 0 20px rgba(0,0,0,.25);
  }
  .sidebar.sidebar-open{transform:translateX(0)!important}
  .sidebar .nav-item{display:flex!important;align-items:center!important}
  .sidebar .nav-item i{display:inline-block!important;width:24px!important;text-align:center!important;margin-right:12px!important}
  .sidebar .nav-item span{display:inline!important}
  .sidebar-close-mobile{display:block}
  .main-content{margin-left:0!important;width:100%!important}
  #toggleSidebar{display:inline-flex!important;align-items:center;justify-content:center}
  .search-box kbd{display:none}
  .user-info{display:none}
}
@media (max-width:576px){
  .sidebar{width:85vw!important}
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
        if (isOpen) sidebar.classList.remove('collapsed');
        return isOpen;
    }
    window.toggleSidebar = toggleSidebar;

    document.addEventListener('DOMContentLoaded', function () {
        var sb = document.getElementById('sidebar');
        if (sb && !sb.querySelector('.sidebar-close-mobile')) {
            var x = document.createElement('button');
            x.type = 'button';
            x.className = 'sidebar-close-mobile';
            x.setAttribute('aria-label', 'Cerrar menú');
            x.innerHTML = '<i class="fas fa-times"></i>';
            x.addEventListener('click', toggleSidebar);
            sb.insertBefore(x, sb.firstChild);
        }
        if (!document.getElementById('sidebarOverlay')) {
            var ov = document.createElement('div');
            ov.id = 'sidebarOverlay';
            ov.className = 'sidebar-overlay';
            ov.addEventListener('click', toggleSidebar);
            document.body.appendChild(ov);
        }
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('#toggleSidebar') : null;
        if (!btn || window.innerWidth > 992) return;
        e.preventDefault();
        e.stopImmediatePropagation();
        toggleSidebar();
    }, true);

    document.addEventListener('click', function (e) {
        if (window.innerWidth > 992) return;
        var sb = document.getElementById('sidebar');
        if (!sb || !sb.classList.contains('sidebar-open')) return;
        if (e.target.closest('.sidebar a')) toggleSidebar();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var sb = document.getElementById('sidebar');
        if (sb && sb.classList.contains('sidebar-open')) toggleSidebar();
    });

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
