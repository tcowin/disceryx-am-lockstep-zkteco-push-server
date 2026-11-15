<?php namespace Blackfin\AttendanceManager;

    use Blackfin\Database\Database;
    use Blackfin\Utils\Logger;

    class StudentPeer
    {
        /**
         * @param $studentId
         */
        public static function retrieveById($id)
        {
            return Database::retrieveRecord('student', array('id' => $id));
        }

        /**
         * Changed to include deleted_at -> NULL 1/23/18 -- given that schools could
         * have duplicate student ids, probably shouldn't use this method -- should
         * qualify with School id using method below
         *
         * @param $studentId
         */
        public static function retrieveByStudentId($schoolStudentId)
        {
            return Database::retrieveRecord('student', array('student_id' => $schoolStudentId, 'deleted_at' => NULL));
        }

        /**
         * Changed to include deleted_at -> NULL 1/23/18; it was finding the wrong, deleted student record.
         *
         * @param $studentId
         */
        public static function retrieveByStudentSchool($schoolStudentId, $schoolId)
        {
            return Database::retrieveRecord('student', array('student_id' => $schoolStudentId, 'school_id' => $schoolId, 'deleted_at' => NULL));
        }

        public static function updateStudent($studentId, $name, $password, $source)
        {
            $student = self::retrieveById($studentId);
            if($student){
                if(!empty($student['person_id'])){
                    PersonPeer::updatePerson($student['person_id'], $name);
                }
                else{
                    $returnValue = PersonPeer::createNew($name, $source);
                    if($returnValue){
                        Database::updateRecord('student', $student['id'], array('person_id' => $returnValue));
                    }
                }
                if($student['terminal_password'] != $password){
                    Database::updateRecord('student', $student['id'], array('terminal_password' => $password));
                }
            }
        }

        public static function createNew($studentName, $schoolStudentId, $password, $schoolId, $source = '')
        {
            $personId = PersonPeer::createNew($studentName, $source);

            $dateNow = date('Y-m-d H:i:s');
            $fields = array('person_id' => $personId,
                            'student_id' => $schoolStudentId,
                            'terminal_password' => $password,
                            'school_id' => $schoolId,
                            'active' => 1,
                            'deletion_candidate' => 0,
                            'creation_source' => $source,
                            'updated_at' => $dateNow,
                            'created_at' => $dateNow);
            return Database::insertRecord('student', $fields);
        }

        public static function verifyStudentRecord($schoolStudentId, $studentName, $password, $terminalSerialNumber)
        {
            $schoolId = AttendanceTerminalPeer::getSchoolId($terminalSerialNumber);

            if($schoolId){
                $student = self::retrieveByStudentSchool($schoolStudentId, $schoolId);
                if(!$student){
                    self::createNew($studentName, $schoolStudentId, $password, $schoolId, "Created by Operation Log entry from terminal {$terminalSerialNumber}");
                    Logger::logMessage(__METHOD__, "Persisting record for user[{$schoolStudentId}] from school[{$schoolId}] indicated by Operation Log entry", LOG_DEBUG);
                }
                else{
                    self::updateStudent($student['id'], $studentName, $password, "Created by Operation Log entry from terminal {$terminalSerialNumber}");
                    Logger::logMessage(__METHOD__, "User[{$schoolStudentId}] from school[{$schoolId}] indicated by Operation Log entry already exists", LOG_DEBUG);
                }
            }
        }

        public static function storeFingerprint($schoolStudentId, $fid, $size, $base64FileContent, $terminalSerialNumber)
        {
            $schoolId = AttendanceTerminalPeer::getSchoolId($terminalSerialNumber);
            if($schoolId){
                $student = self::retrieveByStudentSchool($schoolStudentId, $schoolId);
                if($student){
                    StudentFingerprintPeer::verifyRecord($student['id'], $fid, $size, $base64FileContent, $terminalSerialNumber);
                }
                else{
                    Logger::logMessage(__METHOD__, "Unable to find student record for use with student id[{$schoolStudentId}] from terminal[{$terminalSerialNumber}]", LOG_ERR);
                }
            }

        }
    }