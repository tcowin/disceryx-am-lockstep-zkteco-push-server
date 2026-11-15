<?php namespace Blackfin\AttendanceManager;

use Blackfin\Database\Database;

    class SchoolClassPeer
    {
        /**
         * @param $studentId
         */
        public static function determineTemporalProximity($classId, $dateTimeString, $attendanceEventTypeId)
        {
            $scheduleElements = self::getScheduleElements($classId);
            $timestamp = strtotime($dateTimeString);
            $day = strtolower(date('l', $timestamp));
            $eventTime = \DateTime::createFromFormat('H:i:s', date('H:i:s', $timestamp));
            $proximities = array();
            foreach($scheduleElements as $schedule){
                if($schedule[$day]){
                    //schedule element is for this day
                    $startTime = \DateTime::createFromFormat('H:i:s', $schedule['start_time']);
                    $endTime = \DateTime::createFromFormat('H:i:s', $schedule['end_time']);
                    $thirtyMinutePeriod = new \DateInterval("PT30M");
                    if($attendanceEventTypeId == 1 &&
                        date_sub($startTime, $thirtyMinutePeriod) <= $eventTime &&
                        date_add($startTime, $thirtyMinutePeriod) >= $eventTime){
                        $proximities[$schedule['id']] = -1;
                    }
                    elseif($attendanceEventTypeId == 2 &&
                        date_sub($endTime, $thirtyMinutePeriod) <= $eventTime &&
                        date_add($endTime, $thirtyMinutePeriod) >= $eventTime){
                        $proximities[$schedule['id']] = -1;
                    }
                    elseif($startTime <= $eventTime && $eventTime <= $endTime){
                        $proximities[$schedule['id']] = 0;
                    }
                    elseif($startTime > $eventTime){
                        $proximities[$schedule['id']] = $startTime->getTimestamp() - $eventTime->getTimestamp();
                    }
                    elseif($endTime < $eventTime){
                        $proximities[$schedule['id']] = $eventTime->getTimestamp() - $endTime->getTimestamp();
                    }
                }
                else{
                    //this schedule element is not on the same day as the event, so proximity should be set? arbitrarily high?
                    $proximities[$schedule['id']] = 24 * 60 * 60;
                }
            }
            //var_dump($proximities);
            //TODO if no schedules found? return 0?
            return min($proximities);
        }

        public static function getScheduleElements($classId)
        {
            $sql = "select se.* from schedule_element se
                             join class_schedule cs on se.class_schedule_id = cs.id
                             join school_class sc on cs.id = sc.schedule_id
                             where se.deleted_at IS NULL AND cs.deleted_at IS NULL AND sc.deleted_at IS NULL AND
                                   sc.id = " . (int) $classId;

            return Database::retrieveRecordsFromQuery($sql);
        }

    }