<?php namespace Blackfin\AttendanceManager;

    use Blackfin\Database\Database;
    use Blackfin\Utils\Logger;
    use Blackfin\Utils\StringUtil;

    class TerminalCommandPeer
    {
        /**
         * @param $studentId
         */
        public static function retrieveByTerminal($terminalId)
        {
            return Database::retrieveRecords('terminal_command', array('terminal_id' => $terminalId, 'deleted_at' => NULL));
        }

        /**
         * From SDK doc: Note, we suggest no more than 200 commands or less 40KB in one message due to device limitation;
         * if using GPRS mode, the size should be less than 10Kbyte. When the device completes executing all the commands,
         * it will send out separated request to update the server the execution results.
         * i.e., TODO make sure number of requests to a specific terminal are well below these limits
         *
         * @param $terminalId
         */
        public static function retrieveReadyForTerminal($terminalId)
        {
            return Database::retrieveRecords('terminal_command', array('terminal_id' => $terminalId,
                                                                       'transmitted_at' => null,
                                                                       'confirmed_at' => null,
                                                                       'deleted_at' => NULL));
        }

        public static function retrieveByCommandId($commandId)
        {
            return Database::retrieveRecord('terminal_command', array('id' => $commandId));
        }

        public static function updateCommandResult($commandId, $returnCode, $returnData)
        {
            $fields = array('result_code' => $returnCode,
                            'result_data' => $returnData,
                            'confirmed_at' => date('Y-m-d H:i:s'));

            Database::updateRecord('terminal_command', $commandId, $fields);
        }

        protected static function apiResponseToArray($response)
        {
            $elements = array();
            $pairs = explode('&', $response);
            foreach($pairs as $pair){
                $keyValue = explode('=', $pair);
                $elements[$keyValue[0]] = $keyValue[1];
            }

            return $elements;
        }

        /**
         * Store results from command sent to terminal into database
         * the ZK Push Error Codes, in the Return field
         *
         * 0 – Success
        -1 – Parameter error, Database Not initialized;
        -2 – Wrong operation;
        -3 – Access error, Flash I/O Error;
        -4 – No matched data found;
        -5 – No more space;
        -6 – Data is not valid;
        -9 – The size of the fingerprint template does not match the specified “Size”.
        -10 – The user specified by “PIN” does not exist.
        -11 – Invalid fingerprint template format
        -12 – Invalid fingerprint template
        -22 – Unknown command
         *
         * if CMD is ENROLL_FP
         * Result code：
        0 – Success
        2 – The corresponding user’s fingerprint has already existed.
        6 – Cancel registration
        5 – The registered fingerprint has already existed in the fingerprint database. (Duplicate fingerprint)
        4 – Registration failed, normally due to the bad fingerprint quality, or the 3 fingerprints mismatch.
         *
         *
         *
         * @param $terminalSerialNumber
         * @param $input
         */
        public static function recordCommandResult($terminalSerialNumber, $input)
        {
            //$matched = preg_match("/ID=([\d]{1,10})&Return=([\d-]{1,5})&CMD=(.*)$/", $input, $matches);
            $lines = preg_split ('/$\R?^/m', $input);
            foreach($lines as $line){
                $elements = StringUtil::apiResponseToArray($line, '&');
                if(isset($elements['ID']) && isset($elements['Return']) && isset($elements['CMD'])){
                    Logger::logMessage(__METHOD__, "Returned command response[{$line}] broken down to " . implode(", ", $elements), LOG_ERR);
                    $command = self::retrieveByCommandId($elements['ID']);
                    if($command){
                        $returnCodeIndex = ZkTechPushErrorCodePeer::codeToIndex($elements['Return']);
                        if(!$returnCodeIndex){
                            Logger::logMessage(__METHOD__, "Unable to retrieve Push Error Code Index for code {$elements['Return']}", LOG_ERR);
                        }
                        self::updateCommandResult($command['id'], $returnCodeIndex, $elements['CMD']);
                    }
                    if($elements['Return'] != 0){
                        Logger::logMessage(__METHOD__, "Command[{$elements['ID']}] sent to terminal received non successful return code of {$elements['Return']} with info of {$elements['CMD']}", LOG_ERR);
                    }
                }
                else{
                    Logger::logMessage(__METHOD__, "Non-parseable command response[{$line}] recvd from terminal[{$terminalSerialNumber}]", LOG_ERR);
                }
            }
        }

        /**
         *
        ID=920
        SN=6695145000008
        FILENAME=ssruser.dat
        CMD=GetFile
        Return=1800
        Content=<the file: the clock users' PINs, names, passwords and cards>
         *
         * @param $terminalSerialNumber
         * @param $input
         */
        public static function recordFileCommandResult($terminalSerialNumber, $input)
        {
            $pos = strpos($input, 'Content=');
            $fileContents = substr($input, 8 + $pos);
            $lines = preg_split ('/$\R?^/m', $input);
            $elements = array();
            $line = '';
            foreach($lines as $line){
                $keyValue = explode('=', $line, 2);
                if('Content' == $keyValue[0]){
                    break;
                }
                if(count($keyValue) == 2){
                    $elements[$keyValue[0]] = $keyValue[1];
                }
            }
            if(isset($elements['ID']) && isset($elements['Return']) && isset($elements['CMD'])){
                Logger::logMessage(__METHOD__, "Returned command response[{$line}] broken down to " . implode(", ", $elements), LOG_ERR);
                $command = self::retrieveByCommandId($elements['ID']);
                if($command){
                   //$path = '/home/tcowin/webapps/terminals2';
                    // basename: the name comes from the request
                    $localFilename = basename(isset($elements['FILENAME']) ? $elements['FILENAME'] : 'file') . '_' . basename($terminalSerialNumber);
                    if(file_put_contents($localFilename, $fileContents) === false){
                        Logger::logMessage(__METHOD__, "Unable to save {$localFilename} from terminal[{$terminalSerialNumber}]", LOG_ERR);
                    }
                    self::updateCommandResult($command['id'], $elements['Return'], $elements['CMD']);
                }
                if($elements['Return'] != 0){
                    Logger::logMessage(__METHOD__, "Command[{$elements['ID']}] sent to terminal received non successful return code of {$elements['Return']} with info of {$elements['CMD']}", LOG_ERR);
                }
            }
            else{
                Logger::logMessage(__METHOD__, "Non-parseable command response[{$line}] recvd from terminal[{$terminalSerialNumber}]", LOG_ERR);
            }
        }

        /**
         * C:[ID]:[Command]
         * C:[ID]:[Command]
         *
         * @param $terminalSerialNumber
         * @return string
         */
        public static function composeCommandResponseForTerminal($terminalSerialNumber)
        {
            $terminal = AttendanceTerminalPeer::retrieveBySerialNumber($terminalSerialNumber);
            if(!$terminal){
                return 'OK';   // an unknown clock has nothing queued
            }
            $commands = self::retrieveReadyForTerminal($terminal['id']);
            if(!empty($commands)){
                $response = '';
                foreach($commands as $command){
                    $response .= ($response == '' ? '' : "\n") . "C:{$command['id']}:{$command['command']}";
                }
            }
            else{
                $response = 'OK';
            }

            return $response;
        }

        public static function markCommandAsTransmitted($commandId)
        {
            $fields = array('transmitted_at' => date('Y-m-d H:i:s'));

            return Database::updateRecord('terminal_command', $commandId, $fields);
        }

        public static function markAsTransmitted($commandList)
        {
            $commands = explode("\n", $commandList);
            foreach($commands as $command){
                $elements = explode(":", $command);
                if(count($elements) >= 3){
                    self::markCommandAsTransmitted($elements[1]);
                }
            }
        }

        public static function createNew($terminalId, $command)
        {
            $dateNow = date('Y-m-d H:i:s');
            $fields = array('terminal_id' => $terminalId,
                            'command' => $command,
                            'created_at' => $dateNow);

            return Database::insertRecord('terminal_command', $fields);
        }

        /**
         * Compose server request to terminal to add/modify user
         *
         * UPDATE USERINFO PIN=%d\tName=%s\tPri=%d\tPasswd=%s\tCard=%s\tGrp=%d\tTZ=%d\tVerifyMode=%d
         *
         * @param $studentId
         */
        public static function composeUpdateStudent($studentId, $password = null)
        {
            $requestText = null;
            $student = StudentPeer::retrieveById($studentId);
            $person = PersonPeer::retrieveById($student['person_id']);
            if($student){
                $requestText = sprintf("DATA UPDATE USERINFO\tPIN=%d\tCard=%d",
                                        $student['student_id'], $student['student_id']);
                if($person && (!empty($person['first_name']) || !empty($person['last_name']))) {
                    $requestText .= "\tName={$person['first_name']} {$person['last_name']}";
                }
                if($password){
                    $requestText .= "\tPasswd={$password}";
                }
            }

            return $requestText;
        }

        /**
         * Compose server request to terminal to delete user
         *
         * DELETE USERINFO PIN=%d
         *
         * @param $studentId
         */
        public static function composeDeleteStudent($studentId)
        {
            $requestText = null;
            $student = StudentPeer::retrieveById($studentId);
            if($student){
                $requestText = sprintf("DATA DELETE USERINFO PIN=%d",
                    $student['student_id']);
            }

            return $requestText;
        }

        /**
         * Compose server request to terminal to add/modify fingerprint
         *
         * UPDATE FINGERTMP PIN=%d\tFID=%d\tSize=%d\tValid=%d\tTMP=%s
         *
         * @param $terminalId
         * @param $studentId
         */
        public static function composeUpdateFingerprint($studentId, $fid = 0)
        {
            $requestText = null;
            $student = StudentPeer::retrieveById($studentId);
            if($student){
                $studentFingerprint = StudentFingerprintPeer::retrieveByStudentTemplate($studentId, $fid);
                if($studentFingerprint){
                    $requestText = sprintf("DATA UPDATE FINGERTMP PIN=%d\tFID=%d\tSize=%d\tValid=%d\tTMP=%s",
                                           $student['student_id'],
                                           $fid,
                                           $studentFingerprint['size'],
                                           1,
                                           $studentFingerprint['content']);
                }
            }

            return $requestText;
        }

        /**
         * Compose server request to terminal to delete fingerprint
         *
         * DELETE FINGERTMP PIN=%d\tFID=%d
         *
         * @param $terminalId
         * @param $studentId
         */
        public static function composeDeleteFingerprint($studentId, $fid = null)
        {
            $requestText = null;
            $student = StudentPeer::retrieveById($studentId);
            if($student){
                $studentFingerprint = StudentFingerprintPeer::retrieveByStudentTemplate($studentId, $fid);
                if($studentFingerprint){
                    if($fid){
                        $fidClause = "\tFID={$fid}";
                    }
                    else{
                        $fidClause = '';
                    }
                    $requestText = sprintf("DATA DELETE FINGERTMP PIN=%d%s",
                                           $student['student_id'],
                                           $fidClause);
                }
            }

            return $requestText;
        }

        /**
         * Send a command to a terminal to either UpdateUser, DeleteUser, UpdateFingerprint, or DeleteFingerprint
         * by specifying terminalId, commandType(one of the previous listed commands) and an array of args for
         * that command. The command will be enqueued and sent to the specific terminal when that terminal
         * checks in for server commands.
         *
         * @param $terminalId
         * @param $commandType
         * @param $args
         */
        public static function enQueueCommand($terminalId, $commandType, $args)
        {
            $composeCommand = "compose{$commandType}";
            if(method_exists(__NAMESPACE__ .'\TerminalCommandPeer', $composeCommand)){
                $request = call_user_func_array(array(__NAMESPACE__ .'\TerminalCommandPeer', $composeCommand), $args);
                if($request){
                    self::createNew($terminalId, $request);
                }
            }
        }

    }