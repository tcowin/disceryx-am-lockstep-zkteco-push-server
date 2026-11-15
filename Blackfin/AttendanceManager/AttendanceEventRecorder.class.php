<?php namespace Blackfin\AttendanceManager;

use Blackfin\Database\Database;
use Blackfin\Utils\Logger;
use Blackfin\Utils\Timezone;

/**
     * Created by PhpStorm.
     * User: tcowin
     * Date: 12/4/15
     * Time: 8:51 AM
     */
    class AttendanceEventRecorder
    {
        protected $schoolStudentId;
        protected $dateTimeString;
        protected $serverDateString;
        protected $zkTechEventType;
        protected $zkTechVerifyMode;
        protected $terminalSerialNumber;
        protected $terminalIpAddress;
        protected $schoolId;
        protected $studentId;
        protected $classId;
        protected $studentClassId;
        protected $attendanceEventTypeId = 1;
        protected $terminal;

        // a punch dated further ahead than this means the clock's date or time is wrong (see recordEvent)
        const MAX_FUTURE_SECONDS = 28800;   // 8 hours
        public function __construct($schoolStudentId, $dateTimeString, $zkTechEventType, $zkTechVerifyMode, $terminalSerialNumber, $terminalIpAddress)
        {
            //TODO possibly include and record method of provided authentication (i.e., fingerprint, badge or passcode) ? phase 2 ?
            $this->schoolStudentId = $schoolStudentId;
            $this->dateTimeString = $dateTimeString;
            $this->zkTechEventType = $zkTechEventType;
            $this->zkTechVerifyMode = $zkTechVerifyMode;
            $this->terminalSerialNumber = $terminalSerialNumber;
            $this->terminalIpAddress = $terminalIpAddress;

            $this->recordEvent();
        }

        protected function determineClosestTemporally($studentClasses)
        {
            foreach($studentClasses as $studentClass){
                $proximity[$studentClass['id']] = SchoolClassPeer::determineTemporalProximity($studentClass['class_id'], $this->dateTimeString, $this->attendanceEventTypeId);
            }
            //var_dump($proximity);exit;

            return array_search(min($proximity), $proximity);
        }

        protected function isNewRecord()
        {
            $answer = true;
            // with no school there is no timezone: keep the clock's own time, so the punch can still be stored as
            // unresolved (event_date is NOT NULL; it used to be lost)
            $this->serverDateString = $this->dateTimeString;
            $this->terminal = AttendanceTerminalPeer::retrieveBySerialNumber($this->terminalSerialNumber);
            if($this->terminal){
                if(!empty($this->terminal['school_id'])){
                    $this->schoolId = $this->terminal['school_id'];
                    $this->serverDateString = SchoolPeer::convertToServerDate($this->schoolId, $this->dateTimeString);

                    //store last_attlog_entry in timeclocks reported time so that later reconnection
                    //has exact time string, and possible UTC tz conversion/restoration doesn't cause problem tbc 10/18/17
                    if(!empty($this->terminal['last_attlog_entry'])){
                       // $recordTime = new \DateTime($this->serverDateString);
                        $recordTime = new \DateTime($this->dateTimeString);
                        $lastRecordTime = new \DateTime($this->terminal['last_attlog_entry']);
                        if($lastRecordTime >= $recordTime){
                            $answer = false;
                        }
                    }
                }
                else{
                    Logger::logMessage(__METHOD__, "Unable to identify school from terminal {$this->terminalSerialNumber}", LOG_ERR);
                }
            }
            else{
                Logger::logMessage(__METHOD__, "Unable to identify terminal {$this->terminalSerialNumber}", LOG_ERR);
                //TODO if we can't id terminal, but can id student and (assume) class, should we record event?
            }

            return $answer;
        }

        protected function determineStudentAndClass()
        {
            $student = StudentPeer::retrieveByStudentSchool($this->schoolStudentId, $this->schoolId);
            if($student && !empty($student['id'])){
                //actual student object id as opposed to representative assigned ID
                $this->studentId = $student['id'];

                //get class(es) that this student is enrolled in - see if terminal used is linked to the class?
                //TODO - if student is not enrolled in class(withdrawn or completed), should we still record event?
               // $studentClasses = StudentClassPeer::retrieveEnrolledByStudentId($this->studentId);

                $localTimezone = SchoolPeer::getTimezoneZoneinfoName($this->schoolId);
                Logger::logMessage(__METHOD__, "timezone: {$localTimezone}", LOG_ERR);
                $studentClasses = StudentClassPeer::retrieveCurrentlyEnrolledByStudentId($this->studentId, $localTimezone);
                if(count($studentClasses) == 1){
                    //student only enrolled in one class - use that and record event
                    //TODO should we verify that terminal is linked to specified class?
                    $this->studentClassId = $studentClasses[0]['id'];
                    if(!$this->schoolId){
                        $this->schoolId = $studentClasses[0]['school_id'];
                    }
                    $this->classId = $studentClasses[0]['class_id'];
                }
                elseif(count($studentClasses) > 1){
                    //if more than one class, try to determine if terminal used is tied uniquely to one of the classes, if so - use that class
                    $foundMatches = array();
                    foreach($studentClasses as $studentClass){
                        $classTerminals = AttendanceTerminalPeer::retrieveByClassId($studentClass['class_id']);
                        foreach($classTerminals as $classTerminal){
                            if($this->terminal['serial_number'] == $classTerminal['serial_number']){
                                $foundMatches[] = $studentClass;
                            }
                        }
                        //if we only have one terminal match - assume that is the class, o/w compare time to class
                        //times
                    }
                   // $foundMatches = array_unique($foundMatches);

                    if(count($foundMatches) == 1){
                        $this->studentClassId = $foundMatches[0]['id'];
                        $this->classId = $foundMatches[0]['class_id'];
                    }
                    elseif(count($foundMatches) > 1){
                        //else if terminal being used for more than one of this students classes, try to determine which class time is closer to the event time
                        //should be rare that we get this far?
                        $this->studentClassId = $this->determineClosestTemporally($foundMatches);
                    }
                    else{
                        //log condition
                        Logger::logMessage(__METHOD__, "Unable to find any classes that student with ID {$this->schoolStudentId} in school {$this->terminal['school_id']} that map to terminal {$this->terminalSerialNumber}", LOG_ERR);
                    }
                }
                else{
                    //record as Unresolved event
                    Logger::logMessage(__METHOD__, "Unable to find any classes that student with ID {$this->schoolStudentId} in school {$this->terminal['school_id']} is enrolled in", LOG_DEBUG);
                }
            }
            else{
                //record student as an unknown student?
                Logger::logMessage(__METHOD__, "Unable to identify student with ID {$this->schoolStudentId} in school {$this->terminal['school_id']}", LOG_DEBUG);
            }

        }

        /**
         * @param $zkTechEventType
         * @see ZK Tech Push API doc Feb 2015 Table 2.4
         */
        protected function convertZkTechToAppEventType()
        {
            //defaults to 1 if neither of these matches
            if(in_array($this->zkTechEventType, array(0, 2, 4))){
                $this->attendanceEventTypeId = 1; //Attendance_Event_Type::IN
            }
            elseif(in_array($this->zkTechEventType, array(1, 3, 5))){
                $this->attendanceEventTypeId = 2; //Attendance_Event_Type::OUT
            }
        }

        protected function fireClockEvent($rowId)
        {
            // The app and the PHP that runs its artisan: AM_APP_DIR and PHP_CLI in config.php, for the new host;
            // without them, what the old host used.
            $app_dir = defined('AM_APP_DIR') ? AM_APP_DIR : '/usr/local/vhosts/beta';
            if(!defined('AM_APP_DIR') && isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] == 'terminals.blackfin.biz'){
                $app_dir = '/home/rlehr/apps/laravelam';
            }
            $php = defined('PHP_CLI') ? PHP_CLI : '/bin/php74';
            if(!is_file($app_dir . '/artisan')){
                Logger::logMessage(__METHOD__, "Error encountered firing clock event for attendance event[{$rowId}]: no artisan in {$app_dir} (set AM_APP_DIR in config.php)", LOG_ERR);
                return;
            }
            $output = array();
            exec('cd ' . escapeshellarg($app_dir) . ' && ' . escapeshellarg($php) . ' artisan fire_clock_event ' . (int) $rowId, $output, $exitCode);
            if(!empty($output) || $exitCode !== 0){
                Logger::logMessage(__METHOD__, "Error encountered firing clock event for attendance event[{$rowId}] (exit {$exitCode}): " . implode("", $output), LOG_ERR);
            }
            else{
                Logger::logMessage(__METHOD__, "Firing clock event for attendance event[{$rowId}]", LOG_DEBUG);
            }
        }

        protected function persistEventRecord()
        {
            $fieldValues = array('student_class_id' => $this->studentClassId,
                                 'event_type_id' => $this->attendanceEventTypeId,
                                 'event_date' => $this->serverDateString,
                                 'client_ip_address' => $this->terminalIpAddress,
                                 'verify_mode' => ZkTechVerifyModePeer::codeToIndex($this->zkTechVerifyMode));
            //TODO should we count the same event received within 30(?) seconds as a dup?
            // the same punch already stored? (updated_at used to be in this lookup as the string 'NOW()': it never
            // matched on MariaDB, and MySQL 8 refuses the query, which lost every punch)
            $rowId = Database::getTableRowId('attendance_event', $fieldValues, false);
            if(!$rowId){
                $fieldValues['updated_at'] = "NOW()";
                $fieldValues['created_at'] = "NOW()";
                $rowId = Database::insertRecord('attendance_event', $fieldValues);
                if($rowId){
                   // AttendanceTerminalPeer::updateTerminal($this->terminal['id'], array('last_attlog_entry' => $this->serverDateString));
                    //store last_attlog_entry in timeclocks reported time so that later reconnection
                    //has exact time string, and possible UTC tz conversion/restoration doesn't cause problem tbc 10/18/17
                    AttendanceTerminalPeer::updateTerminal($this->terminal['id'], array('last_attlog_entry' => $this->dateTimeString));
                    sleep(1);
                    $this->fireClockEvent($rowId);//getting errors on this periodically -- Trying to get property of non-object from app, which I believe to be a race condition, so adding a one second delay (above) here.
                }
            }
            else{
                Logger::logMessage(__METHOD__, "Discarding a duplicate entry for StudentClass {$this->studentClassId} at {$this->dateTimeString} from {$this->terminalIpAddress}", LOG_DEBUG);
            }
            return $rowId;
        }

        /**
         * Since we're unable to map this definitively to a student/class for whatever reason, record as unresolved
         * so that either the instructor/school admin or Disceryx tech/sysadmin can id and move this record.
         *
         * @return \Blackfin\Database\unknown_type|int
         */
        protected function persistUnresolvedEventRecord()
        {
            $fieldValues = array('event_date' => $this->serverDateString,
                                 'school_student_id' => $this->schoolStudentId,
                                 'terminal_serial_number' => $this->terminalSerialNumber,
                                 'terminal_ip_address' => $this->terminalIpAddress,
                                 'verify_mode' => ZkTechVerifyModePeer::codeToIndex($this->zkTechVerifyMode));
            if(!empty($this->attendanceEventTypeId)){
                $fieldValues['event_type_id'] = $this->attendanceEventTypeId;
            }
            if(!empty($this->schoolId)){
                $fieldValues['school_id'] = $this->schoolId;
            }
            if(!empty($this->studentClassId)){
                $fieldValues['student_class_id'] = $this->studentClassId;
            }
            if(!empty($this->studentId)){
                $fieldValues['student_id'] = $this->studentId;
            }
            if(!empty($this->classId)){
                $fieldValues['class_id'] = $this->classId;
            }

            $rowId = Database::getTableRowId('unresolved_attendance_event', $fieldValues, false);
            if(!$rowId){
                $fieldValues['updated_at'] = "NOW()";
                $fieldValues['created_at'] = "NOW()";
                $rowId = Database::insertRecord('unresolved_attendance_event', $fieldValues);
                if($rowId && $this->terminal){
                    //AttendanceTerminalPeer::updateTerminal($this->terminal['id'], array('last_attlog_entry' => $this->serverDateString));
                    //store last_attlog_entry in timeclocks reported time so that later reconnection
                    //has exact time string, and possible UTC tz conversion/restoration doesn't cause problem tbc 10/18/17
                    AttendanceTerminalPeer::updateTerminal($this->terminal['id'], array('last_attlog_entry' => $this->dateTimeString));
                }
            }
            else{
                Logger::logMessage(__METHOD__, "Discarding a duplicate UNRESOLVED entry for StudentId {$this->schoolStudentId} at {$this->dateTimeString} from terminal {$this->terminalSerialNumber} at {$this->terminalIpAddress}", LOG_WARNING);
            }
            return $rowId;
        }

        /**
         * How far ahead of now the punch is dated, in seconds (the clock's time in the school's timezone, or as UTC
         * when the clock has no school, which never makes a US local time look ahead); null if it can't be read.
         */
        protected function secondsInFuture()
        {
            $timestamp = strtotime($this->serverDateString . ' UTC');
            return $timestamp === false ? null : $timestamp - time();
        }

        public function recordEvent()
        {
            $this->convertZkTechToAppEventType();
            $isNew = $this->isNewRecord();
            // Recording a punch dated far ahead would move the clock's last_attlog_entry ahead, and every real punch
            // from that clock would then be ignored until that date: log it and leave it out.
            $ahead = $this->secondsInFuture();
            if($ahead !== null && $ahead > self::MAX_FUTURE_SECONDS){
                Logger::logMessage(__METHOD__, "ERROR: ignoring a punch from terminal {$this->terminalSerialNumber} for student ID {$this->schoolStudentId} dated {$this->dateTimeString}, more than 8 hours ahead; check the clock's date and time", LOG_ERR);
                return;
            }
            if($isNew){
                if($this->terminal && $this->schoolId && $this->schoolStudentId){
                    $this->determineStudentAndClass();
                }

                if(!empty($this->studentClassId)){
                    $this->persistEventRecord();
                }
                else{
                    //if we're unable to definitively determine class or school or verify student (or even id terminal as it hasn't been mapped to a school yet)
                    $this->persistUnresolvedEventRecord();
                }
            }
        }

    }
