<?php namespace Blackfin\AttendanceManager;

    use Blackfin\Database\Database;

    class StudentFingerprintPeer
    {
        /**
         * @param $studentId
         */
        public static function retrieveByStudentId($studentId)
        {
            return Database::retrieveRecords('student_fingerprint', array('student_id' => $studentId, 'deleted_at' => NULL));
        }

        /**
         * @param $studentId
         */
        public static function retrieveByStudentTemplate($studentId, $fid)
        {
            return Database::retrieveRecord('student_fingerprint', array('student_id' => $studentId, 'fid' => $fid, 'deleted_at' => NULL));
        }

        public static function verifyRecord($studentId, $fid, $size, $base64FileContent, $terminalSerialNumber)
        {
            $fp = self::retrieveByStudentTemplate($studentId, $fid);
            if(!$fp){
                $dateNow = date('Y-m-d H:i:s');
                $fields = array('student_id' => $studentId,
                                'fid' => $fid,
                                'size' => $size,
                                'content' => $base64FileContent,
                                'created_at' => $dateNow);
                $terminal = AttendanceTerminalPeer::retrieveBySerialNumber($terminalSerialNumber);
                if($terminal){
                    $fields['originating_terminal_id'] = $terminal['id'];
                }
                return Database::insertRecord('student_fingerprint', $fields);
            }
            else{
                $fieldsToUpdate = array();
                if($fp['size'] != $size){
                    $fieldsToUpdate['size'] = $size;
                }
                if($fp['content'] != $base64FileContent){
                    $fieldsToUpdate['content'] = $base64FileContent;
                }
                if(!empty($fieldsToUpdate)){
                    $terminal = AttendanceTerminalPeer::retrieveBySerialNumber($terminalSerialNumber);
                    if($terminal){
                        $fields['originating_terminal_id'] = $terminal['id'];
                    }
                    Database::updateRecord('student_fingerprint', $fp['id'], $fieldsToUpdate);
                }
            }
        }

    }