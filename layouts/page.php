<?php
/**
 * Layout halaman umum: head + (navbar) + isi + (footer) + skrip.
 * Kunci $page opsional: navbar (mis. 'landing', 'public', 'employee-attendance'), footer ('landing', 'public').
 * Tanpa navbar/footer cocok untuk login, register, dan halaman penuh seperti learning.
 */
partial('head');
if (!empty($page['navbar'])) {
    partial('navbar/' . $page['navbar']);
}
?>
<?= $content ?>
<?php
if (!empty($page['footer'])) {
    partial('footer/' . $page['footer']);
}
?>

<?php partial('scripts'); ?>
</body>
</html>
