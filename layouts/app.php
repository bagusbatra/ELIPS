<?php
/**
 * Layout dashboard: head + navbar + sidebar + isi halaman + skrip.
 * Kunci $page: navbar (default 'app'), sidebar (wajib), + kunci umum (lihat includes/layout.php).
 * Isi halaman ($content) cukup berupa <main class="dashboard-main">...</main> dan modal-modalnya.
 */
partial('head');
partial('navbar/' . ($page['navbar'] ?? 'app'));
?>

    <div class="dashboard-layout">
<?php partial('sidebar/' . $page['sidebar']); ?>
<?= $content ?>
    </div>

<?php partial('scripts'); ?>
</body>
</html>
