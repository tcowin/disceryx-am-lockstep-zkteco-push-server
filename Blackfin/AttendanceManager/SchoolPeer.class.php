<?php namespace Blackfin\AttendanceManager;

use Blackfin\Database\Database;
use Blackfin\Utils\Logger;
use Blackfin\Utils\Timezone;

    class SchoolPeer
    {

        public static function getTimezone($schoolId)
        {
            $sql = "select cos.value from configuration_option co
                             join configuration_option_setting cos on co.id = cos.option_id
                             where co.name = 'Timezone' and cos.school_id = " . (int) $schoolId . " and
                                   co.deleted_at IS NULL and cos.deleted_at IS NULL";
            $result = Database::processQuery($sql);
            $row = $result ? $result->fetch_array() : null;
            if(empty($row['value'])){
                // Timezone::timezoneToZoneInfoName() then uses Pacific
                Logger::logMessage(__METHOD__, "Missing option value when looking for timezone of school {$schoolId}", LOG_ERR);
                return null;
            }
            return $row['value'];
        }

        public static function getTimezoneZoneinfoName($schoolId)
        {
            return Timezone::timezoneToZoneInfoName(self::getTimezone($schoolId));
        }

        /**
         * Convert date from school timezone to server timezone(UTC)
         *
         * @param $schoolId
         * @param $date
         * @param string $format
         * @return string
         */
        public static function convertToServerDate($schoolId, $date, $format = 'Y-m-d H:i:s')
        {
            if($schoolId){
                //TODO see if want to use carbon here at some point http://carbon.nesbot.com/docs/ timezone UTC
                $schoolTimezone = self::getTimezoneZoneinfoName($schoolId);
                $serverTimeZone = 'UTC';
                try {
                    $dateTime = new \DateTime($date, new \DateTimeZone($schoolTimezone));
                    $dateTime->setTimezone(new \DateTimeZone($serverTimeZone));
                    return $dateTime->format($format);
                } catch (\Exception $e) {
                    Logger::logMessage(__METHOD__, "Error encountered converting time: " . $e->getMessage(), LOG_ERR);
                    return $date;
                }
            }
            else{
                return $date;
            }
        }

    }