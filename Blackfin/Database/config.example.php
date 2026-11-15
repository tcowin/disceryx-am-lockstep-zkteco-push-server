<?php
// Copy to config.php (not in git) and fill in.

// The app's database: MariaDB or MySQL 8 both work.
define('HOST', 'localhost');
define('USER', 'username');
define('PASSWORD', 'password');
define('DATABASE', 'database');

// The Lockstep app, whose `artisan fire_clock_event <id>` runs after each punch is stored (required: without it
// punches are stored, but the app's tardy and absence rules never run for them, and each one logs an ERROR line),
// and the PHP that runs that artisan, the app's PHP (default /usr/bin/php).
define('AM_APP_DIR', '/usr/share/nginx/vhosts/disceryx-am-lockstep');
define('PHP_CLI', '/usr/bin/php');
