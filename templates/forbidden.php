<?php
/** Shown when a signed-in user tries to open a page their role cannot use (HTTP 403). */
require __DIR__ . '/header.php';
?>
<div class="container page-narrow">
    <?php empty_state(
        'You don’t have access to this page',
        'This area is only for organization representatives or administrators. If you think this is a mistake, contact a CampusBridge administrator.',
        '<a class="button button-primary" href="' . e(url('dashboard.php')) . '">Go to my dashboard</a>'
    ); ?>
</div>
<?php require __DIR__ . '/footer.php'; ?>
