<?php
// Copy to config.php (not in git) and fill in.

// The app's database: MariaDB or MySQL 8 both work.
define('HOST', 'localhost');
define('USER', 'username');
define('PASSWORD', 'password');
define('DATABASE', 'database');

// The Lockstep app, whose `artisan fire_clock_event <id>` runs after each punch is stored, and the PHP that runs
// it. Without these: /usr/local/vhosts/beta and /bin/php74, as on the old host.
//define('AM_APP_DIR', '/usr/share/nginx/vhosts/disceryx-am-lockstep');
//define('PHP_CLI', '/usr/bin/php');
