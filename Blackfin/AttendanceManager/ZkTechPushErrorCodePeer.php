<?php

namespace Blackfin\AttendanceManager;

use Blackfin\Database\Database;
use Blackfin\Utils\Logger;

/**
     * Created by PhpStorm.
     * User: tcowin
     * Date: 1/22/16
     * Time: 14:51 PM
     */
    class ZkTechPushErrorCodePeer
    {

        /**
         * @param $code   numeric code from table which indicates the Push API error code recv'd from the zk terminal
         *                which is responding to a command issued to it
         * @see ZK Tech Push API doc ? (from Jared Gaines email 1/7/16)
         */
        public static function codeToIndex($code)
        {
            $fieldValues = array('code' => trim($code));
            $rowId = Database::getTableRowId('zk_tech_push_error_code', $fieldValues, false);
            if(!$rowId){
                Logger::logMessage(__METHOD__, "Unable to resolve provided code $code from zk terminal command response");
            }
            return $rowId;
        }

    }