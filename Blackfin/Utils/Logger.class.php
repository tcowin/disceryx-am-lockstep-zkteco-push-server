<?php

    /**
     * Created by PhpStorm.
     * User: tcowin
     * Date: 12/7/15
     * Time: 4:47 PM
     */

    namespace Blackfin\Utils;

    class Logger
    {

        public static function logMessageUsingSyslog($source, $output, $logLevel = LOG_ERR)
        {
            $syslog_facility = LOG_LOCAL0;
            $log_options = LOG_PID;
            if (!openlog($source, $log_options, $syslog_facility)){
                echo "Fatal error opening syslog facility $syslog_facility !!\n";
                return;
            }

            if(is_array($output)){
                foreach($output as $output_line){
                    syslog($logLevel, "$output_line");
                }
            }
            else {
                syslog($logLevel, "$output");
            }

            closelog();
        }

        public static function logMessageUsingFile($source, $output, $logLevel = LOG_ERR)
        {
            //$filename = dirname(__FILE__) . "../../../zk_push_api_server.log";
            $filename = dirname(__FILE__) . "../../../lib/zk_push_api_server.log";
            $datetime = date("M d H:i:s");
            $hostname = gethostname();
            if(is_array($output)){
                foreach($output as $output_line){
                    $logLine = sprintf("%s %s %s %s: %s\n", $datetime, $hostname, $source, $logLevel, $output_line);
                    file_put_contents($filename, $logLine, FILE_APPEND);
                }
            }
            else {
                $logLine = sprintf("%s %s %s %s: %s\n", $datetime, $hostname, $source, $logLevel, $output);
                file_put_contents($filename, $logLine, FILE_APPEND);
            }
        }

        public static function logMessage($source, $output, $logLevel = LOG_ERR)
        {
            $hostname = gethostname();
            //if(stripos($hostname, 'webfaction.com') === FALSE){
           //     self::logMessageUsingSyslog($source, $output, $logLevel);
           // }
            //else{
                self::logMessageUsingFile($source, $output, $logLevel);
           // }
        }

    }
