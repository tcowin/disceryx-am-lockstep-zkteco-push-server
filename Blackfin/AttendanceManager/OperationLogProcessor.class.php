<?php namespace Blackfin\AttendanceManager;

use Blackfin\Database\Database;
use Blackfin\Utils\Logger;
use Blackfin\Utils\StringUtil;
use Blackfin\Utils\Timezone;

/**
 * Created by PhpStorm.
 * User: tcowin
 * Date: 12/4/15
 * Time: 8:51 AM
 */
class OperationLogProcessor
{
    protected $dateTimeString;
    protected $serverDateString;
    protected $terminalSerialNumber;
    protected $terminalIpAddress;
    protected $schoolId;
    protected $terminal;
    protected $postContent;

    public function __construct($terminalSerialNumber, $terminalIpAddress, $dateTimeString, $postContent)
    {
        $this->terminalSerialNumber = $terminalSerialNumber;
        $this->terminalIpAddress = $terminalIpAddress;
        $this->dateTimeString = $dateTimeString;
        $this->postContent = $postContent;

    }

    public function processInput()
    {
        /* where lines will be like so:
OPLOG 5         2015-12-02 15:03:04             346     0       0
OPLOG 5         2015-12-02 15:03:04             347     0       0
OPLOG 4         2016-01-04 15:24:55             0       0       0
OPLOG 30                2016-01-04 15:26:39     2       0       0       0
USER PIN=2      Name=99 Pri=0   Passwd= Card=   Grp=1   TZ=0000000000000000     VerifyMode=0
OPLOG 6         2016-01-04 15:26:39     2       0       0       1110
FP PIN=2        FID=0   Size=1480       Valid=1 TMP=<template>
        */
        if(AttendanceTerminalPeer::containsNewLogEntries($this->terminalSerialNumber, $this->dateTimeString)){
            foreach($this->postContent as $line){
                $logEntryElements = preg_split('/\s+/', $line);
                switch($logEntryElements[0]){
                    default:
                    case 'OPLOG':
                        Logger::logMessage("Operation Log", "{$this->terminalSerialNumber} $line", LOG_INFO);
                        break;
                    case 'USER':
                        //TODO the need for split on spaces may just be an artifact of testing
                        //$keyValuePairs = StringUtil::apiResponseToArray(substr($line, 5), 'tabs_or_spaces');
                        $keyValuePairs = StringUtil::apiResponseToArray(substr($line, 5));
                        $out = var_export($keyValuePairs, true);
                        Logger::logMessage("Operation Log USER", "Processing {$line} got $out", LOG_INFO);
                        //TODO if user is created at terminal, should we create a new student in school? -- NO - items below commented out 1/22/16
                        //$matched = preg_match("/USER\s+PIN=(\d{1,10})\s+Name=([0-9A-Za-z '-]+)\s+Pri=.*/", $line, $matches);
                        if(isset($keyValuePairs['PIN']) && isset($keyValuePairs['Name']) && isset($keyValuePairs['Passwd'])){
                           // students should be created in the system and not at the terminal
                           // StudentPeer::verifyStudentRecord($keyValuePairs['PIN'], $keyValuePairs['Name'], $keyValuePairs['Passwd'], $this->terminalSerialNumber);//?
                        }
                        Logger::logMessage("Operation Log USER", "{$this->terminalSerialNumber} $line", LOG_INFO);
                        break;
                    case 'FP':
                        //TODO if user is created at terminal, should we create a new student in school?
                        $matched = preg_match("#^FP\s+PIN=(\d{1,15})\s+FID=([0-9]+)\s+Size=([0-9]+)\s+Valid=1\s+TMP=([0-9A-Za-z/\+]+={0,2})$#", $line, $matches);
                                         //TODO the need for split on spaces may just be an artifact of testing
                        //$keyValuePairs = StringUtil::apiResponseToArray(substr($line, 3), 'tabs_or_spaces');
                        //$keyValuePairs = StringUtil::apiResponseToArray(substr($line, 3));
                        //var_dump($keyValuePairs);exit;
                        if($matched && count($matches) >= 4){
                            $studentId = $matches[1];
                            $templateId = $matches[2];
                            $size = $matches[3];
                            $base64file = $matches[4];
                       // if(isset($keyValuePairs['PIN']) && isset($keyValuePairs['FID']) && isset($keyValuePairs['Size']) && isset($keyValuePairs['TMP'])){
                           // StudentPeer::storeFingerprint($keyValuePairs['PIN'], $keyValuePairs['FID'], $keyValuePairs['Size'], $keyValuePairs['TMP'], $this->terminalSerialNumber);
                            StudentPeer::storeFingerprint($studentId, $templateId, $size, $base64file, $this->terminalSerialNumber);
                        }
                        else{
                            Logger::logMessage("Operation Log FP", "Encountered error trying to parse [{$line}] from {$this->terminalSerialNumber}", LOG_ERR);
                        }
                        Logger::logMessage("Operation Log FP", "{$this->terminalSerialNumber} $line", LOG_INFO);
                        break;
                }
            }
            //update last operlog entry timestamp
            $terminal = AttendanceTerminalPeer::retrieveBySerialNumber($this->terminalSerialNumber);
            if(!$terminal){
                return;   // containsNewLogEntries() has logged it
            }
            $this->serverDateString = SchoolPeer::convertToServerDate($terminal['school_id'], $this->dateTimeString);

            //store last_operlog_entry in timeclocks reported time so that later reconnection
            //has _exact_ time string, and possible UTC tz conversion/restoration doesn't cause problem tbc 10/18/17
            AttendanceTerminalPeer::updateTerminal($terminal['id'], array('last_operlog_entry' => $this->dateTimeString));
        }
    }
}