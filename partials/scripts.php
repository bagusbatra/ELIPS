<?php foreach (elips_script_list($page) as $name): ?>
    <script src="<?= asset('js/' . $name . '.js') ?>"></script>
<?php endforeach; ?>
