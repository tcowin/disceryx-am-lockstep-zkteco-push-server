<?php
//GET /iclock/cdata?SN=6695145000008&options=all&pushver=2.0.3.636&language=69

    error_reporting(E_ALL);
    ini_set("display_errors", 1);

    $loader = require __DIR__ . '/../vendor/autoload.php';
    $loader->add('Blackfin\\AttendanceManager\\', __DIR__);

    use Blackfin\AttendanceManager\AttendanceEventRecorder;
    use Blackfin\AttendanceManager\AttendanceTerminalPeer;
    use Blackfin\Utils\Logger;

/**
 *    Handle Config data requests for ZK Tech Push API v 2.2(?) for Attendance Terminals - US-10
 */
    //stock response should be an 'OK', if not overridden below
    $response = 'OK';

    if(isset($_GET['SN']) &&
       isset($_GET['options']) &&
       isset($_GET['pushver']) &&
       isset($_GET['language'])){

        $response = AttendanceTerminalPeer::composePushOptionsConfigResponse($_GET['SN'], $_GET['pushver']);
    }
    elseif(isset($_GET['SN']) &&
           isset($_GET['type']) && $_GET['type'] == 'time'){

        date_default_timezone_set(AttendanceTerminalPeer::getTimezone($_GET['SN']));
        $response = "Time=" . date('c'); //"Y-m-d\TH:i:sO");
        //respond to sync time request
        /*
         * Time=YYYY-MM-DDThh:mm:ss±hh:mm
For example, if the server time is at EAST time 17:40:20 May 5th, 2011, the response is “Time=2011-05-05T17:40:20-05:00”.
         */
    }
    elseif(isset($_POST['SN']) &&
           isset($_POST['table']) &&
           isset($_POST['Stamp']) &&
           $_POST['table'] == 'ATTLOG'){
        //POST /iclock/cdata?SN=12300001&table=ATTLOG&Stamp=2008-02-25T12:08:21 (initial line)
        //1	2015-12-02 13:45:23	0	129	0	255	0 (second line, or content of post)
        $input = file_get_contents("php://input");
        Logger::logMessage($_SERVER['SCRIPT_NAME'], "POST {$_POST['SN']}, {$_POST['table']}, {$_POST['Stamp']} 2nd line: $input", LOG_DEBUG);
        $matched = preg_match("/(\d{1,8})\s+(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})\s+(\d{1,2})\s+(\d{1,4}).*/", $input, $matches);

        if(count($matches) >= 4){
            $recorder = new AttendanceEventRecorder($matches[1],              //student id
                                                    $matches[2],              //datetime
                                                    $matches[3],              //event type
                                                    $_POST['SN'],              //terminal serial no
                                                    $_SERVER['REMOTE_ADDR']); //client ip
        }
        else{
            //TODO log error
        }

        // $req_dump = print_r($_REQUEST, true);
        // $fp = file_put_contents('/Applications/MAMP/logs/request.log', $body, FILE_APPEND);
        // $server_dump = print_r($_SERVER, true);
        // $fp = file_put_contents('/Applications/MAMP/logs/request.log', $server_dump, FILE_APPEND);
    }
    elseif(isset($_POST['SN']) &&
        isset($_POST['table']) &&
        isset($_POST['Stamp']) &&
        $_POST['table'] == 'OPERLOG'){
        //POST /iclock/cdata?SN=12300001&table=OPERLOG&Stamp=2008-02-25T12:08:21 (initial line)
        //  OPLOG 5		2015-12-02 11:31:12		183	0	0 (subsequent lines, or content of post)
        /*
         * where lines will be like so:
OPLOG 5         2015-12-02 15:03:04             346     0       0
OPLOG 5         2015-12-02 15:03:04             347     0       0
OPLOG 4         2016-01-04 15:24:55             0       0       0
OPLOG 30                2016-01-04 15:26:39     2       0       0       0
USER PIN=2      Name=99 Pri=0   Passwd= Card=   Grp=1   TZ=0000000000000000     VerifyMode=0
OPLOG 6         2016-01-04 15:26:39     2       0       0       1110
FP PIN=2        FID=0   Size=1480       Valid=1 TMP=<template>
         */
        $inputLines = file("php://input");
        foreach($inputLines as $line){
            Logger::logMessage("Operation Log", "POST {$_POST['SN']} $line", LOG_INFO);
        }
    }
    elseif(isset($_GET['SN']) &&
        isset($_GET['table']) &&
        isset($_GET['Stamp']) &&
        $_GET['table'] == 'OPERLOG'){
        //POST /iclock/cdata?SN=12300001&table=OPERLOG&Stamp=2008-02-25T12:08:21 (initial line)
        //  OPLOG 5		2015-12-02 11:31:12		183	0	0 (subsequent lines, or content of post)
        $inputLines = file("php://input");
        foreach($inputLines as $line){
            Logger::logMessage("Operation Log", "GET {$_GET['SN']} $line", LOG_INFO);
        }
    }
    elseif(isset($_REQUEST['SN']) &&
        isset($_REQUEST['table']) &&
        isset($_REQUEST['Stamp']) &&
        $_REQUEST['table'] == 'OPERLOG'){
        //POST /iclock/cdata?SN=12300001&table=OPERLOG&Stamp=2008-02-25T12:08:21 (initial line)
        //  OPLOG 5		2015-12-02 11:31:12		183	0	0 (subsequent lines, or content of post)
        $inputLines = file("php://input");
        foreach($inputLines as $line){
            Logger::logMessage("Operation Log", "REQUEST {$_REQUEST['SN']} $line", LOG_INFO);
        }
    }
    //test case

//GET /iclock/cdata?SN=6695145000008&table=ATTLOG&studentId=12345678906&datetime=2015-12-07%2016:08:21&eventTypeId=0
    elseif(isset($_GET['SN']) &&
           isset($_GET['table']) &&
           $_GET['table'] == 'ATTLOG' &&
           isset($_GET['studentId'])){

        //this case is for manual TESTING only...

        //GET /iclock/cdata?SN=12300001&table=ATTLOG&Stamp=2008-02-25T12:08:21
        //1	2015-12-02 13:45:23	0	129	0	255	0
        // $input = file_get_contents("php://input");
        //  $numMatches = preg_match("/(\d{1,8})\s+(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})\s+(\d{1,2})\s+(\d{1,4}).*/", $input, $matches);

        // if($numMatches >= 4){
        $recorder = new AttendanceEventRecorder($_GET['studentId'], //student id
                                                $_GET['datetime'], //datetime
                                                $_GET['eventTypeId'], //event type
                                                $_GET['SN'], //terminal serial no
                                                $_SERVER['REMOTE_ADDR']); //client ip
        // }
        // else{
        //TODO log error
        //  }

        // $req_dump = print_r($_REQUEST, true);
        // $fp = file_put_contents('/Applications/MAMP/logs/request.log', $body, FILE_APPEND);
        // $server_dump = print_r($_SERVER, true);
        // $fp = file_put_contents('/Applications/MAMP/logs/request.log', $server_dump, FILE_APPEND);
    }
    elseif(isset($_GET['SN']) &&
        isset($_GET['table']) &&
        $_GET['table'] == 'ATTLOG' &&
        isset($_GET['Stamp'])){

        //GET /iclock/cdata?SN=12300001&table=ATTLOG&Stamp=2008-02-25T12:08:21
        //1	2015-12-02 13:45:23	0	129	0	255	0
        //$input = file_get_contents("php://input");
        $input = file("php://input");

        //have to issue OK right away or else terminal will not think its a successful transmission
        echo $response . "\r\n\r\n";

        foreach($input as $line){

            $matched = preg_match("/(\d{1,8})\s+(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})\s+(\d{1,2})\s+(\d{1,4}).*/", $line, $matches);

            if($matched && count($matches) >= 4){
                $recorder = new AttendanceEventRecorder($matches[1],              //student id
                                                        $matches[2],              //datetime
                                                        $matches[3],              //event type
                                                        $_GET['SN'],              //terminal serial no
                                                        $_SERVER['REMOTE_ADDR']); //client ip
            }
            else{
                //TODO log error
                Logger::logMessage($_SERVER['SCRIPT_NAME'], "Error processing ATTLOG entry from {$_GET['SN']} $lines", LOG_DEBUG);
            }
        }
       //

        // $req_dump = print_r($_REQUEST, true);
        // $fp = file_put_contents('/Applications/MAMP/logs/request.log', $body, FILE_APPEND);
        // $server_dump = print_r($_SERVER, true);
        // $fp = file_put_contents('/Applications/MAMP/logs/request.log', $server_dump, FILE_APPEND);
    }
    else{
        $postvars = '';
        foreach($_GET as $attr => $value){
            $postvars .= "[$attr] => $value, ";
        }
        //$postvars = implode(',' , $_GET);
        $input = file_get_contents("php://input");
        Logger::logMessage($_SERVER['SCRIPT_NAME'], " $postvars 2nd line: $input", LOG_DEBUG);
    }

    echo $response . "\r\n\r\n";
    exit;
