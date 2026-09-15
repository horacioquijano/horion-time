        </div> <!-- Fin content-wrapper -->
    </main>
</div> <!-- Fin app-container -->
<div class="modal-overlay" id="globalModal"><div class="modal-content"></div></div>
<div class="toast-container" id="toastContainer"></div>
<?php $basePath = $basePath ?? rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); ?>
<script src="<?= $basePath ?>/assets/js/horion-app.js"></script>
<?php if (isset($pageScripts)): ?><script><?= $pageScripts ?></script><?php endif; ?>
</body>
</html>