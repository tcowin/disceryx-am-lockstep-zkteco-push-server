<?php namespace Blackfin\AttendanceManager;

    use Blackfin\Database\Database;
    use Blackfin\Utils\Logger;

    class StudentTerminalPeer
    {
        /**
         * @param $studentId
         */
        public static function retrieveByStudentId($studentId)
        {
            return Database::retrieveRecords('student_terminal', array('student_id' => $studentId));
        }

        /**
         * @param $studentId
         */
        public static function retrieveByStudentTerminal($studentId, $terminalId)
        {
            return Database::retrieveRecord('student_terminal', array('student_id' => $studentId, 'terminal_id' => $terminalId));
        }

        /**
         * @param $terminalId
         */
        public static function retrieveByTerminal($terminalId)
        {
            return Database::retrieveRecord('student_terminal', array('terminal_id' => $terminalId));
        }

        public static function verifyRecord($fields)
        {
            $return = 0;
            if(!empty($fields['terminalSerialNumber'])){
                $terminal = AttendanceTerminalPeer::retrieveBySerialNumber($fields['terminalSerialNumber']);
                if($terminal){
                    $student = StudentPeer::retrieveByStudentSchool($fields['student_id'], $terminal['school_id']);
                    if($student){
                        $dateNow = date('Y-m-d H:i:s');
                        $fields['updated_at'] = $dateNow;
                        unset($fields['terminalSerialNumber']);
                        $st = self::retrieveByStudentTerminal($student['id'], $terminal['id']);
                        if(!$st){
                            $fields['student_id'] = $student['id'];
                            $fields['terminal_id'] = $terminal['id'];
                            $fields['created_at'] = $dateNow;
                            return Database::insertRecord('student_terminal', $fields);
                        }
                        else{
                            unset($fields['student_id']);
                            Database::updateRecord('student_terminal', $st['id'], $fields);
                        }
                        $return = 1;
                    }
                    else{
                        //log error
                        Logger::logMessage($_SERVER['SCRIPT_NAME'], "Error unable to get student by student id and school", LOG_DEBUG);
                    }

                }
                else{
                    //log error
                    Logger::logMessage($_SERVER['SCRIPT_NAME'], "Error unable to get terminal by serial number", LOG_DEBUG);
                }
            }
            else{
                //log error
                Logger::logMessage($_SERVER['SCRIPT_NAME'], "Error did not get terminalSerialNumber", LOG_DEBUG);
            }

            return $return;
        }

    }