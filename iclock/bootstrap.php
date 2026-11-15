<?php
/**
 * The start of the three PUSH endpoints (cdata.php, getrequest.php, devicecmd.php): error handling and the
 * autoloader. Returns the autoloader.
 */

use Blackfin\Utils\Logger;

error_reporting(E_ALL);
// PHP's messages go to lib/php-errors.log, never into the response: the clock reads every byte of it, and a notice
// ahead of "GET OPTION FROM" or "OK" is not what it expects. (The scripts used to turn display_errors on.)
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . '/lib/php-errors.log');

$loader = require __DIR__ . '/../vendor/autoload.php';
$loader->add('Blackfin\\AttendanceManager\\', __DIR__);

// A fatal error also goes in the server's own log, as one line, with the request it ended.
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
        $message = strtok($error['message'], "\n");
        Logger::logMessage(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : 'push server',
            "ERROR: fatal error {$message} in {$error['file']}:{$error['line']} handling "
            . (isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : ''), LOG_ERR);
    }
});

return $loader;
