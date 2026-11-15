<?php

    error_reporting(E_ALL);
    ini_set("display_errors", 1);

    $loader = require __DIR__ . '/../vendor/autoload.php';
    $loader->add('Blackfin\\AttendanceManager\\', __DIR__);

    /**
     *    Handle GET (commands) requests for ZK Tech Push API v 2.2(?) for Attendance Terminals - US-10
     */

    use Blackfin\AttendanceManager\AttendanceTerminalPeer;
    use Blackfin\AttendanceManager\TerminalCommandPeer;

    $response = "OK";

    //GET /iclock/getrequest?SN=6695145000008
    if(!empty($_GET['SN']) && !isset($_GET['INFO'])){
        $response = TerminalCommandPeer::composeCommandResponseForTerminal($_GET['SN']);
        AttendanceTerminalPeer::updateLastGetrequest($_GET['SN']);
        //$response = 'OK';//'C:ID2:CLEAR DATA';//"C:ID2:REBOOT";;//"C:ID2:CHECK";  //
    }
    //After the initial config request from the terminal has been recv'd and responded to, the clock(terminal)
    // will then send this getrequest with this info string
    //TODO determine what all of the different bits of information are here and if we should be persisting them in db
    //GET /iclock/getrequest?SN=6695145000008&INFO=Ver%206.8.0(build745),1,2,4,192.168.1.11,10,-1,-12,101
    elseif(!empty($_GET['SN']) && !empty($_GET['INFO'])){
        AttendanceTerminalPeer::updateInfo($_GET['SN'], $_GET['INFO']);
    }

    echo $response . "\r\n\r\n";
    if(strpos($response, 'C:') === 0){
        TerminalCommandPeer::markAsTransmitted($response);
    }
    exit;