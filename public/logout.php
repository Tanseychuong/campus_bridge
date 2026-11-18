<?php
/**
 * Signs the user out. Only accepts POST (with a CSRF token), so another
 * website cannot sign you out just by linking to this page.
 */
require __DIR__ . '/../src/bootstrap.php';

require_post_with_csrf();
log_out();
flash('You have signed out.', 'info');
redirect(url('index.php'));
