<?php namespace Blackfin\AttendanceManager;

use Blackfin\Database\Database;
use Blackfin\Utils\Logger;

/**
     * Created by PhpStorm.
     * User: tcowin
     * Date: 1/22/16
     * Time: 14:51 PM
     */
    class ZkTechVerifyModePeer
    {

        /**
         * @param $code   numeric code from table which indicates the auth/identity method used against the zk terminal
         *                which generated the entry
         * @see ZK Tech Push API doc Feb 2015 Table 2.3
         */
        public static function codeToIndex($code)
        {
            $fieldValues = array('code' => trim($code));
            //TODO should we count the same event received within 30(?) seconds as a dup?
            $rowId = Database::getTableRowId('zk_tech_verify_mode', $fieldValues, false);
            if(!$rowId){
                Logger::logMessage(__METHOD__, "Unable to resolve provided code $code from attendance log entry");
            }
            return $rowId;
        }

    }