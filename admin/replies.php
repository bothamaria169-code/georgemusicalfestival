<?php
/** The Replies inbox was removed: competition questions now arrive through Support. */
require __DIR__ . '/_layout.php';
require_admin('admin');
redirect('admin/support.php');
