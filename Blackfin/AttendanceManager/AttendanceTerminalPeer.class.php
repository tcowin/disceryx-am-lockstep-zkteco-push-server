<?php namespace Blackfin\AttendanceManager;

use Blackfin\Database\Database;
use Blackfin\Utils\Logger;
use Blackfin\Utils\Timezone;

define('SERVER_VERSION', '0.1 2015-12-11');

class AttendanceTerminalPeer {

    /**
     * @param $terminalSerialNumber
     */
    public static function retrieveBySerialNumber($terminalSerialNumber)
    {
        //TODO if this doesn't find the terminal, probably should add it here, and
        //TODO msg admins of a new Terminal that needs to be associated with a school
        return Database::retrieveRecord('attendance_terminal', array('serial_number' => $terminalSerialNumber,
                                                                     'deleted_at' => null));
    }

    public static function getSchoolId($terminalSerialNumber)
    {
        $returnValue = null;
        $terminal = self::retrieveBySerialNumber($terminalSerialNumber);

        if($terminal){
            if(!empty($terminal['school_id'])){
                $returnValue = $terminal['school_id'];
            }
            else{
                //TODO perhaps this ought to trigger a notification to admins
                Logger::logMessage(__METHOD__, "Unable to find school id from terminal[{$terminalSerialNumber}]", LOG_ERR);
            }
        }

        return $returnValue;
    }

    /**
     * @param $terminalSerialNumber
     */
    public static function retrieveByClassId($classId)
    {
        $terminals = array();
        $sql = "select at.* from attendance_terminal at
                            join class_terminal ct on at.id = ct.terminal_id
                            where ct.class_id = " . (int) $classId . " AND at.deleted_at IS NULL AND ct.deleted_at IS NULL";
        $result = Database::processQuery($sql);
        if($result){
            while($row = $result->fetch_array()){
                $terminals[] = $row;
            }
            $result->close();
        }

        return $terminals;
    }

    public static function updateTerminal($id, $fields)
    {
        Database::updateRecord('attendance_terminal', $id, $fields);
    }

    /**
     * Get Zoneinfo formatted Timezone based upon terminal/school
     *
     * @param $terminalSerialNumber
     */
        public static function getTimezone($terminalSerialNumber)
        {
            $schoolTimezone = 'America/Los_Angeles';
            $terminal = self::retrieveBySerialNumber($terminalSerialNumber);
            if(!empty($terminal['school_id'])){
                $schoolTimezone = Timezone::timezoneToZoneInfoName(SchoolPeer::getTimezone($terminal['school_id']));
            }

            return $schoolTimezone;
        }

        /**
         * When terminal requests its initial Push options config, respond with context sensitive config settings
         *
         */
        public static function composePushOptionsConfigResponse($terminalSerialNumber, $apiVersion)
        {
            //standard defaults if we can't find terminal/school/configured timezone
            $tzOffset = '-08:00';

            //should default be now or some time ago to capture any stored/pending entries?
            $lastAttlogEntryTimestamp = $lastOperlogEntryTimestamp = date('Y-m-d\TH:i:s', strtotime('one month ago'));

            $serverVer = SERVER_VERSION;

            $terminal = self::retrieveBySerialNumber($terminalSerialNumber);

            if($terminal){
                Logger::logMessage(__METHOD__, "Responding to get config request from known terminal {$terminalSerialNumber}", LOG_DEBUG);
                if(!empty($terminal['school_id'])){
                    $timezone = SchoolPeer::getTimezone($terminal['school_id']);
                    $tzOffset = Timezone::timezoneToOffset($timezone);
                    if(!preg_match('/[+-][0-1][0-9]:[0][0]/', $tzOffset)){
                        $tzOffset = '-08:00';
                    }
                    if($terminal['last_attlog_entry']){
                        $lastAttlogEntryTimestamp = str_replace(' ', 'T', $terminal['last_attlog_entry']);
                    }
                    if($terminal['last_operlog_entry']){
                        $lastOperlogEntryTimestamp = str_replace(' ', 'T', $terminal['last_operlog_entry']);
                    }
                }
                else{
                    Logger::logMessage(__METHOD__, "Unable to identify school from terminal {$terminalSerialNumber} requesting config", LOG_ERR);
                }

                if(!empty($terminal['id']) && (empty($terminal['api_version']) || (!empty($terminal['api_version']) && $apiVersion != $terminal['api_version']))){
                    self::updateTerminal($terminal['id'], array('api_version' => $apiVersion));
                }
            }
            else{
                Logger::logMessage(__METHOD__, "Unable to identify terminal {$terminalSerialNumber} requesting config", LOG_ERR);
            }


            // 1) recognize incoming requests
            // 2) record/verify incoming requests
            // 3) compose appropriate response
//TODO possibly put std/default config options in table, store variations per terminal?
            $response = "GET OPTION FROM:{$terminalSerialNumber}
ErrorDelay=60
Delay=60
TransTimes=0
TransInterval=2
TransFlag=AttLog\tOpLog\tEnrollUser\tChgUser\tEnrollFP\tChgFP\tFPImag
Realtime=1
Encrypt=0
TimeZone={$tzOffset}
Timeout=60
SyncTime=3600
ServerVer={$serverVer}
ATTLOGStamp={$lastAttlogEntryTimestamp}
OPERLOGStamp={$lastOperlogEntryTimestamp}";

            Logger::logMessage(__METHOD__, "Sending init response {$response} back to terminal {$terminalSerialNumber}", LOG_DEBUG);

            return $response;
        }

    /**
     * Receive a getrequest from the terminal upon startup that consists of this
     * Ver%206.8.0(build745),1,2,4,192.168.1.11,10,-1,-12,101
     */
    public static function updateInfo($terminalSerialNumber, $info)
    {
        //TODO determine what all of the different bits of information are here and if we should be persisting them in the database
        //Ver%206.8.0(build745),1,2,4,192.168.1.11,10,-1,-12,101
        //set firmware version into db so that we have that recorded

        $terminal = self::retrieveBySerialNumber($terminalSerialNumber);

        if($terminal){
            $infoElements = explode(',', $info);
            $firmware = str_replace('Ver%', '', $infoElements[0]);
            $ipAddress = isset($infoElements[4]) ? $infoElements[4] : '';
            $fieldValues = array();
            if(filter_var($ipAddress, FILTER_VALIDATE_IP) !== FALSE && $ipAddress != $terminal['ipv4_address']){
                $fieldValues['ipv4_address'] = $ipAddress;
            }
            if(empty($terminal['firmware_version']) || $firmware != $terminal['firmware_version']){
                //self::updateTerminal($terminal['id'], array('firmware_version' => $firmware));
                $fieldValues['firmware_version'] = $firmware;
            }
            $datetime = new \DateTime();
            $fieldValues['last_getrequest'] = $datetime->format('Y-m-d H:i:s');
            if(!empty($fieldValues)){
                self::updateTerminal($terminal['id'], $fieldValues);
            }
        }
        else{
            Logger::logMessage(__METHOD__, "Unable to identify terminal from {$terminalSerialNumber}", LOG_ERR);
        }
    }

        /**
         * Receive a getrequest from the terminal upon startup that consists of this
         * Ver%206.8.0(build745),1,2,4,192.168.1.11,10,-1,-12,101
         */
        public static function updateLastGetrequest($terminalSerialNumber)
        {
            $terminal = self::retrieveBySerialNumber($terminalSerialNumber);

            if($terminal){
                $datetime = new \DateTime();
                $fieldValues['last_getrequest'] = $datetime->format('Y-m-d H:i:s');
                if(!empty($fieldValues)){
                    self::updateTerminal($terminal['id'], $fieldValues);
                }
            }
            else{
                Logger::logMessage(__METHOD__, "Unable to identify terminal from {$terminalSerialNumber}", LOG_ERR);
            }
        }

        /**
         * @param $terminalSerialNumber
         * @param $dateString
         */
        public static function containsNewLogEntries($terminalSerialNumber, $dateTimeString)
        {
            $answer = true;
            $terminal = self::retrieveBySerialNumber($terminalSerialNumber);

            if($terminal){
                if(!empty($terminal['school_id'])){
                   // $serverDateString = SchoolPeer::convertToServerDate($terminal['school_id'], $dateTimeString);
                    if(!empty($terminal['last_operlog_entry'])){
                       // $recordTime = new \DateTime($serverDateString);
                        //store last_attlog_entry in timeclocks reported time so that later reconnection
                        //has exact time string, and possible UTC tz conversion/restoration doesn't cause problem tbc 10/18/17
                        $recordTime = new \DateTime($dateTimeString);
                        // the last operation log entry (this compared with the last punch, last_attlog_entry, so a
                        // clock's users and fingerprints were skipped whenever its last punch was newer)
                        $lastRecordTime = new \DateTime($terminal['last_operlog_entry']);
                        if($lastRecordTime >= $recordTime){
                            $answer = false;
                        }
                    }
                }
                else{
                    Logger::logMessage(__METHOD__, "Unable to identify school from terminal {$terminalSerialNumber}", LOG_ERR);
                }
            }
            else{
                Logger::logMessage(__METHOD__, "Unable to identify terminal from {$terminalSerialNumber}", LOG_ERR);
            }

            return $answer;
        }

    }