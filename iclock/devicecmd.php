<?php

    $loader = require __DIR__ . '/bootstrap.php';   // error handling (nothing printed to the clock), autoloader

    use Blackfin\AttendanceManager\TerminalCommandPeer;
    use Blackfin\Utils\Logger;

/**
 *    Handle Config data requests for ZK Tech Push API v 2.2(?) for Attendance Terminals - US-10
 */
    $response = 'OK';
    $debug = true;

    //for testing only
    //http://terminals.blackfinwebware.com/iclock/devicecmd?SN=6618151400025&ID=35&Return=0&CMD=DATA&test=1
    if(!empty($_GET['SN']) && !empty($_GET['ID']) && !empty($_GET['test'])){
       // $input = file_get_contents("php://input");

        $input = "ID={$_GET['ID']}&Return={$_GET['Return']}&CMD={$_GET['CMD']}&test=1";

        //have to issue OK right away or else terminal will not think its a successful transmission
        echo "OK\r\n\r\n";

        $response = TerminalCommandPeer::recordCommandResult($_GET['SN'], $input);
        if($debug){
            Logger::logMessage($_SERVER['SCRIPT_NAME'], "(test) Terminal command result code from {$_GET['SN']} $input", LOG_DEBUG);
        }

        echo $response . "\r\n\r\n";
        exit;
    }
/*POST /iclock/devicecmd?SN=%s
    // ID=%d&Return=%d&CMD=%s
    if(!empty($_GET['SN']) && !empty($_GET['ID']) && !empty($_GET['FILENAME']) && !empty($_GET['CMD']) && $_GET['Content']){
        $input = file_get_contents("php://input");
        $contents = $_GET['Content'];
        $localFilename = $_GET['FILENAME'] . '_' . $_GET['SN'];
        file_put_contents($localFilename, $contents);

        //have to issue OK right away or else terminal will not think its a successful transmission
        echo "OK\r\n\r\n";

        $response = TerminalCommandPeer::recordFileCommandResult($_GET['SN'], $input);
        if($debug){
            Logger::logMessage($_SERVER['SCRIPT_NAME'], "Terminal command result code from {$_GET['SN']} $input", LOG_DEBUG);
        }
    }
    //POST /iclock/devicecmd?SN=%s
    // ID=%d&Return=%d&CMD=%s
    else */
    if(!empty($_GET['SN'])){
        $input = file_get_contents("php://input");

        //have to issue OK right away or else terminal will not think its a successful transmission
        echo "OK\r\n\r\n";

        if(strpos($input, 'CMD=GetFile') !== FALSE){
            $response = TerminalCommandPeer::recordFileCommandResult($_GET['SN'], $input);
        }
        else{
            $response = TerminalCommandPeer::recordCommandResult($_GET['SN'], $input);
        }

        if($debug){
            Logger::logMessage($_SERVER['SCRIPT_NAME'], "Terminal command result code from {$_GET['SN']} $input", LOG_DEBUG);
        }
    }

    echo $response . "\r\n\r\n";
    exit;