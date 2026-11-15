<?php namespace Blackfin\AttendanceManager;

use Blackfin\Database\Database;
use Blackfin\Utils\Logger;

/**
     * Created by PhpStorm.
     * User: tcowin
     * Date: 12/4/15
     * Time: 8:51 AM
     */
    class AttendanceEventPeer
    {
        /* these methods moved to AttendanceEventRecorder

        public static function determineSchoolAndClassFromTerminalStudent($terminalSerialNumber, $customerStudentId)
        {
            $returnParams = array();
            $terminal = AttendanceTerminalPeer::retrieveBySerialNumber($terminalSerialNumber);
            if($terminal){
                $returnParams['schoolId'] = $terminal['school_id'];

                $student = StudentPeer::retrieveByStudentSchool($customerStudentId, $terminal['school_id']);
                if($student && !empty($student['id'])){
                    //actual student object id as opposed to representative assigned ID
                    $returnParams['studentId'] = $student['id'];

                    //get class(es) that this student is enrolled in - see if terminal used is linked to the class?
                    $studentClasses = StudentClassPeer::retrieveByStudentId($returnParams['studentId']);
                    if(count($studentClasses) < 1){
                        //record as unmapped event
                    }
                    elseif(count($studentClasses) == 1){
                        //TODO should we verify that terminal is linked to specified class?
                        $returnParams['studentClassId'] = $studentClasses[0]['id'];
                        if(!isset($returnParams['schoolId'])){
                            $returnParams['schoolId'] = $studentClasses[0]['school_id'];
                        }
                        $returnParams['classId'] = $studentClasses[0]['class_id'];
                    }
                    else{
                        foreach($studentClasses as $studentClass){
                            //TODO write this
                            //$classTerminals = AttendanceTerminalPeer::retrieveByClassId($studentClass['class_id']);
                            //if we only have one terminal match - assume that is the class, o/w compare time to class
                            //times

                        }
                    }
                }
                else{
                    //record student as an unknown student?
                    Logger::logMessage(__METHOD__, "Unable to identify student with ID $customerStudentId in school {$terminal['school_id']}", LOG_DEBUG);
                }
            }
            else{
                //record terminal as an unattached terminal
                Logger::logMessage(__METHOD__, "Unable to identify terminal $terminalSerialNumber", LOG_DEBUG);
            }

            return $returnParams;
        }

        /**
         * @param $zkTechEventType
         * @see ZK Tech Push API doc Feb 2015 Table 2.4
         *
        public static function convertZkTechToAppEventType($zkTechEventType)
        {
            if(in_array($zkTechEventType, array(0, 2, 4))){
                return 1; //Attendance_Event_Type::IN
            }
            elseif(in_array($zkTechEventType, array(1, 3, 5))){
                return 2; //Attendance_Event_Type::OUT
            }
            return 1;//?
        }

        public static function persistEventRecord($studentClassId, $attendanceEventTypeId, $dateTimeString, $terminalIpAddress)
        {
            //TODO convert datetime to UTC from school's TZ b4 insert
            $fieldValues = array('student_class_id' => $studentClassId,
                                 'event_type_id' => $attendanceEventTypeId,
                                 'event_date' => $dateTimeString,
                                 'client_ip_address' => $terminalIpAddress);
            //TODO should we count the same event received within 30 seconds as a dup?
            $rowId = Database::getTableRowId('attendance_event', $fieldValues, false);
            if(!$rowId){
                $fieldValues['created_at'] = "NOW()";
                $rowId = Database::insertRecord('attendance_event', $fieldValues);
            }
            else{
                Logger::logMessage(__METHOD__, "Discarding a duplicate entry for StudentClass $studentClassId at $dateTimeString from $terminalIpAddress", LOG_DEBUG);
            }
            return $rowId;
        }

        public static function recordEvent($terminalSerialNumber, $studentId, $dateTimeString, $zkTechEventType, $terminalIpAddress)
        {
            $contextParams = self::determineSchoolAndClassFromTerminalStudent($terminalSerialNumber, $terminalIpAddress, $studentId);
            $attendanceEventTypeId = self::convertZkTechToAppEventType($zkTechEventType);
            if(isset($contextParams['studentClassId'])){
                self::persistEventRecord($contextParams['studentClassId'],
                                         $attendanceEventTypeId,
                                         $dateTimeString,
                                         $terminalIpAddress);

            }
            else{
//if we're unable to definitively determine class or school or verify student (or even id terminal as it hasn't been mapped to a school yet)
                self::persistUnmappedEventRecord($terminalSerialNumber,
                                                 $terminalIpAddress,
                                                 $contextParams['schoolId'],
                                                 $contextParams['classId'],
                                                 $contextParams['studentId'],
                                                 $attendanceEventTypeId,
                                                 $dateTimeString);
            }

        }

        */
    }