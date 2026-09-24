<?php
require __DIR__ . '/includes/bootstrap.php';

unset($_SESSION['entrant_id'], $_SESSION['login_entrant_id']);
session_regenerate_id(true);
flash('info', 'You have been signed out.');
redirect('flyfortwo/');
