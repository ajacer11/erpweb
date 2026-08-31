<?php $version = (int)($_SESSION['version'] ?? 1); ?>
<script>
    dash = "dashboard<?= $version ?>.php";
    loadModule(dash);
</script>