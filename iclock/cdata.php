<?php
    error_reporting(E_ALL);
    ini_set("display_errors", 1);

    $loader = require __DIR__ . '/../vendor/autoload.php';
    $loader->add('Blackfin\\AttendanceManager\\', __DIR__);

    use Blackfin\AttendanceManager\AttendanceEventRecorder;
    use Blackfin\AttendanceManager\AttendanceTerminalPeer;
    use Blackfin\AttendanceManager\StudentTerminalPeer;
    use Blackfin\AttendanceManager\OperationLogProcessor;
    use Blackfin\Utils\Logger;

/**
 * Handle incoming http requests for ZK Tech Push API v 2.2(?) for Attendance Terminals - US-10
 *
 * @author tom@cowin.us
 * @date 12/11/2015
 */

    $debug = true;

    //stock response should be an 'OK', if not overridden below
    $response = '';

    //Initial terminal config request
    //GET /iclock/cdata?SN=6695145000008&options=all&pushver=2.0.3.779&language=69
    if(isset($_GET['SN']) &&
       isset($_GET['options']) &&
       isset($_GET['pushver']) &&
       isset($_GET['language'])){

        if($debug){
            Logger::logMessage($_SERVER['SCRIPT_NAME'], "Initial terminal config request from {$_GET['SN']}", LOG_DEBUG);
        }
        $response = AttendanceTerminalPeer::composePushOptionsConfigResponse($_GET['SN'], $_GET['pushver']);
    }

    //Terminal request to sync clock
    //GET /iclock/cdata?SN=6695145000008&type=time
    elseif(isset($_GET['SN']) &&
           isset($_GET['type']) && $_GET['type'] == 'time'){

        if($debug){
            Logger::logMessage($_SERVER['SCRIPT_NAME'], "Terminal request to sync clock from {$_GET['SN']}", LOG_DEBUG);
        }
        date_default_timezone_set(AttendanceTerminalPeer::getTimezone($_GET['SN']));
        $response = "Time=" . date('c');
        //Time=YYYY-MM-DDThh:mm:ss±hh:mm For eg., server time is EASTERN 17:40:20 May 5th, 2011: “Time=2011-05-05T17:40:20-05:00”.
    }

    //TODO TESTING ONLY
    //Operation Log entries
    //POST /iclock/cdata?SN=12300001&table=TESTOPERLOG&Stamp=2008-02-25T12:08:21
    elseif(isset($_GET['SN']) &&
        isset($_GET['table']) &&
        isset($_GET['Stamp']) &&
        $_GET['table'] == 'TESTOPERLOG'){
        if($debug){
            Logger::logMessage($_SERVER['SCRIPT_NAME'], "Terminal request to log TEST entries from {$_GET['SN']}", LOG_DEBUG);
        }
        //$inputLines = file("php://input");//presumably should read them from stream regardless, as they'll remain there until read?

        $inputLines = array();
        $inputLines[0] = "OPLOG 4         2015-12-15 12:59:43             0       0       0";
        $inputLines[1] = "OPLOG 37                2015-12-15 13:00:08     1       0       0       0";
        $inputLines[2] = "USER PIN=8898944\tName=Test User\tPri=0\tPasswd=\tCard=\tGrp=1\tTZ=0000000000000000\tVerifyMode=0";
        //$inputLines[2] = "USER PIN=8898944  Name=Test User Pri=0   Passwd= Card=   Grp=1   TZ=0000000000000000 VerifyMode=0";
        $inputLines[3] = "OPLOG 6         2015-12-15 13:00:08     1       0       1       766";
        $inputLines[4] = "FP PIN=8898944\tFID=1\tSize=1152\tValid=1\tTMP=<template>";

        //have to issue OK right away or else terminal will not think its a successful transmission
        echo "OK\r\n\r\n";

        $processor = new OperationLogProcessor($_GET['SN'], //terminal serial no
            $_SERVER['REMOTE_ADDR'],
            $_GET['Stamp'], //datetime
            $inputLines); //post content

        $processor->processInput();
    }

    //Operation Log entries
    //POST /iclock/cdata?SN=12300001&table=OPERLOG&Stamp=2008-02-25T12:08:21
    elseif(isset($_GET['SN']) &&
        isset($_GET['table']) &&
        isset($_GET['Stamp']) &&
        $_GET['table'] == 'OPERLOG'){

        $inputLines = file("php://input");//presumably should read them from stream regardless, as they'll remain there until read?
        if($debug){
		if(is_array($inputLines)){
			$il = json_encode($inputLines);
		}
		else{
			$il = $inputLines;
		}
            Logger::logMessage($_SERVER['SCRIPT_NAME'], "Terminal request to log OPERLOG entries from {$_GET['SN']} $il ", LOG_DEBUG);
        }

        //have to issue OK right away or else terminal will not think its a successful transmission
        echo "OK\r\n\r\n";

        $processor = new OperationLogProcessor($_GET['SN'], //terminal serial no
                                               $_SERVER['REMOTE_ADDR'],
                                               $_GET['Stamp'], //datetime
                                               $inputLines); //post content

        $processor->processInput();
    }

    //Attendance Log Entries
    //POST /iclock/cdata?SN=12300001&table=ATTLOG&Stamp=2008-02-25T12:08:21
    //1	2015-12-02 13:45:23	0	129	0	255	0
    //16528	2017-07-13 11:01:37	255	130	0	0	0
    elseif(isset($_GET['SN']) &&
        isset($_GET['table']) &&
        $_GET['table'] == 'ATTLOG' &&
        isset($_GET['Stamp'])){

        $input = file("php://input");
        if($debug){
            Logger::logMessage($_SERVER['SCRIPT_NAME'], "Terminal request to log ATTLOG entries from {$_GET['SN']}: " . implode("; ", $input), LOG_DEBUG);
        }

        //have to issue OK right away or else terminal will not think its a successful transmission
        echo "OK\r\n\r\n";

        foreach($input as $line){

            $matched = preg_match("/(\d{1,10})\s+(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})\s+(\d{1,3})\s+(\d{1,4}).*/", $line, $matches);

            if($matched && count($matches) >= 4){
                $recorder = new AttendanceEventRecorder($matches[1],              //student id
                                                        $matches[2],              //datetime Y-m-d H:i:s
                                                        $matches[3],              //event type
                                                        $matches[4],              //verify mode
                                                        $_GET['SN'],              //terminal serial no
                                                        $_SERVER['REMOTE_ADDR']); //client ip
            }
            else{
                Logger::logMessage($_SERVER['SCRIPT_NAME'], "Error processing ATTLOG entry from {$_GET['SN']} $line", LOG_DEBUG);
            }
        }
    }
/*    //GET /iclock/cdata?SN=6695145000008&table=ATTLOG&studentId=111111&datetime=2015-12-07%2016:08:21&eventTypeId=0
    //this case is for MANUAL, INTERACTIVE TESTING only...
    elseif(isset($_GET['SN']) &&
        isset($_GET['table']) &&
        $_GET['table'] == 'ATTLOG' &&
        isset($_GET['studentId'])){

        $recorder = new AttendanceEventRecorder($_GET['studentId'], //student id
            $_GET['datetime'], //datetime
            $_GET['eventTypeId'], //event type
            $_GET['SN'], //terminal serial no
            $_SERVER['REMOTE_ADDR']); //client ip
    }
[SN] => <serial>, [table] => USERINFO, [Stamp] => (null),  2nd line: USER PIN=1001	Name=Test User	Pri=0	Passwd=	Card=	Grp=0	TZ=0000000000000000	VerifyMode=0
USER PIN=1002	Name=Other User	Pri=0	Passwd=	Card=	Grp=0	TZ=0000000000000000	VerifyMode=129*/

    elseif(isset($_GET['SN']) &&
        isset($_GET['table']) &&
        $_GET['table'] == 'USERINFO' &&
        isset($_GET['Stamp'])){

        $input = file("php://input");

        if($debug){
            Logger::logMessage($_SERVER['SCRIPT_NAME'], " USERINFO $postvars 2nd line: $input", LOG_DEBUG);
        }

        //have to issue OK right away or else terminal will not think its a successful transmission
        echo "OK\r\n\r\n";

        foreach($input as $line){
            $matched = preg_match("/USER\sPIN=(\d{1,10})\s+Name=([a-zA-Z '.-]*)\s+Pri=0\s+Passwd=([a-zA-Z0-9 !@#.-]*)\s+Card=(\d*)\s+Grp=\d*\s+TZ=\d{0,20}\s+VerifyMode=(\d{0,4})/", $line, $matches);

            $fields = array();
            if($matched && count($matches) >= 6){
                  $fields['student_id'] = $matches[1];                 //student id
                  $fields['name'] = $matches[2];              //name
                  $fields['password'] = $matches[3];              //passwd
                  $fields['card'] = $matches[4];              //card
                  $fields['verify_mode'] = $matches[5];              //verify mode
                  $fields['terminalSerialNumber'] = $_GET['SN'];    //terminal serial no
                  StudentTerminalPeer::verifyRecord($fields);

                //id, terminal_id, student_id, name, verify_mode, created_at
            }
            else{
                Logger::logMessage($_SERVER['SCRIPT_NAME'], "Error processing USERINFO entry from {$_GET['SN']} $line", LOG_DEBUG);
            }
        }
    }

    else{
        $postvars = '';
        foreach($_GET as $attr => $value){
            $postvars .= "[$attr] => $value, ";
        }
        //$postvars = implode(',' , $_GET);
        $input = file_get_contents("php://input");
        Logger::logMessage($_SERVER['SCRIPT_NAME'], "final catch all $postvars 2nd line: $input", LOG_DEBUG);
    }

    echo $response . "\r\n\r\n";
    exit;
