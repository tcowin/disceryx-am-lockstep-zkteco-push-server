<?php namespace Blackfin\AttendanceManager;

use Blackfin\Database\Database;

    class StudentClassPeer
    {
        //todo qualify everything here by school
        /**
         * @param $studentId
         */
        public static function retrieveByStudentId($studentId)
        {
            return Database::retrieveRecords('student_class', array('student_id' => $studentId, 'deleted_at' => NULL));
        }

        /**
         * @param $studentId
         */
        public static function retrieveEnrolledByStudentId($studentId)
        {
           // return Database::retrieveRecords('student_class', array('student_id' => $studentId, 'student_class_status_id' => 1));
            return Database::retrieveRecords('student_class', array('student_id' => $studentId, 'deleted_at' => NULL));
        }

        /**
         * @param $studentId
         */
        public static function retrieveCurrentlyEnrolledByStudentId($studentId, $localTimezone)
        {
            // return Database::retrieveRecords('student_class', array('student_id' => $studentId, 'student_class_status_id' => 1));
            // today in the school's timezone, worked out here: CONVERT_TZ with a zone name needs the database's time
            // zone tables, and without them it is NULL and no class is found
            try {
                $localToday = (new \DateTime('now', new \DateTimeZone($localTimezone)))->format('Y-m-d');
            } catch (\Exception $e) {
                $localToday = (new \DateTime('now', new \DateTimeZone('America/Los_Angeles')))->format('Y-m-d');
            }
            $studentId = (int) $studentId;
            $sql = "select sc.* from student_class sc 
                                join school_class c on sc.class_id = c.id
                                join enrollment_event ee on ee.id = (SELECT id FROM enrollment_event where student_class_id = sc.id order by created_at desc limit 1) 
                                                      where sc.student_id = $studentId AND 
                                                            sc.deleted_at IS NULL AND
                                                            c.deleted_at IS NULL AND 
                                                            c.active = 1 AND 
                                                            ee.event_type_id IN (1, 2) AND
                                                            '{$localToday}' BETWEEN c.start_date AND c.end_date";
           // DATE(CONVERT_TZ(NOW(), 'UTC', '{$localTimezone}'))
                //DATE(NOW())
            return Database::retrieveRecordsFromQuery($sql);
            //return Database::retrieveRecords('student_class', array('student_id' => $studentId));
        }
    }