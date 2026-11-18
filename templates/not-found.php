<?php
/** Shown when a page or item does not exist (HTTP 404). */
http_response_code(404);
$pageTitle = 'Page not found';
require __DIR__ . '/header.php';
?>
<div class="container page-narrow">
    <?php empty_state(
        'We couldn’t find that page',
        'The link may be broken, or the item may have been removed. Try searching instead.',
        '<a class="button button-primary" href="' . e(url('explore.php')) . '">Search CampusBridge</a> '
        . '<a class="button button-secondary" href="' . e(url('index.php')) . '">Go home</a>'
    ); ?>
</div>
<?php require __DIR__ . '/footer.php'; ?>
