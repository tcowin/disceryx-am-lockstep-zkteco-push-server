<?php
    /**
     * Created by PhpStorm.
     * User: tcowin
     * Date: 12/10/15
     * Time: 10:32 AM
     *
     * @see https://en.wikipedia.org/wiki/Tz_database
     */

    namespace Blackfin\Utils;

    class Timezone
    {

        public static function timezoneToZoneInfoName($appTimezone)
        {
            switch($appTimezone){
                case 'Eastern':
                    $zoneInfoName = 'America/New_York';
                    break;
                case 'Central':
                    $zoneInfoName = 'America/Chicago';
                    break;
                case 'Mountain':
                    $zoneInfoName = 'America/Denver';
                    break;
                default:
                case 'Pacific':
                    $zoneInfoName = 'America/Los_Angeles';
                    break;
                case 'Alaska':
                    $zoneInfoName = 'America/Anchorage';
                    break;
                case 'Hawaii':
                    $zoneInfoName = 'Pacific/Honolulu';
                    break;
            }

            return $zoneInfoName;
        }

        protected static function secondsOffsetToHourMinuteFormat($offsetInSeconds)
        {
            $offsetHours = round(abs($offsetInSeconds)/3600);
            $offsetMinutes = round((abs($offsetInSeconds) - $offsetHours * 3600) / 60);
            $offsetString = ($offsetInSeconds < 0 ? '-' : '+')
                . ($offsetHours < 10 ? '0' : '') . $offsetHours
                . ':'
                . ($offsetMinutes < 10 ? '0' : '') . $offsetMinutes;

            return $offsetString;
        }

        public static function timezoneToOffset($appTimezone)
        {
            $zoneInfoName = self::timezoneToZoneinfoName($appTimezone);
            
            $dateTimeZone = new \DateTimeZone($zoneInfoName);

            $dateTimeInZone = new \DateTime("now", $dateTimeZone);

            $offsetInSeconds = $dateTimeZone->getOffset($dateTimeInZone);

            return self::secondsOffsetToHourMinuteFormat($offsetInSeconds);
        }

    }