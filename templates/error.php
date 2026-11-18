<?php
/**
 * Shown when something unexpected goes wrong (HTTP 500).
 * Variables: $databaseProblem (bool), $debugMessage (string|null)
 */
require __DIR__ . '/header.php';
?>
<div class="container page-narrow">
    <?php empty_state(
        'Something went wrong',
        $databaseProblem
            ? 'CampusBridge could not connect to its database. If you are running it yourself, check that MySQL is started and that the database was imported (see the README).'
            : 'An unexpected error stopped this page from loading. Please try again in a moment.',
        '<a class="button button-primary" href="' . e(url('index.php')) . '">Go home</a>'
    ); ?>
    <?php if ($debugMessage): ?>
        <pre class="debug-message">Debug: <?= e($debugMessage) ?></pre>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/footer.php'; ?>
