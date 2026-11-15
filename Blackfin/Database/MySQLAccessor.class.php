<?php
    namespace Blackfin\Database;

    use Blackfin\Utils\Logger;

    include_once("config.php");

    class MySQLAccessor extends \MySQLi {

    private static $instance = null; //The single instance

    // Constructor
    public function __construct($host, $user, $password, $database) {
        parent::__construct($host, $user, $password, $database);
    }

    /**
    * Get an instance of the Database
    * @return Instance
    */
    public static function getInstance() {
      if(self::$instance == null) { // If no instance then make one
          // Failed queries return false and are logged by Database::processQuery, on PHP 7.4 and 8 alike (since PHP
          // 8.1 mysqli throws by default, which ended the request after the clock had been told OK).
          mysqli_report(MYSQLI_REPORT_OFF);
          self::$instance = @new self(HOST, USER, PASSWORD, DATABASE);
          if(self::$instance->connect_errno){
              Logger::logMessage(__METHOD__, " MySQL ERROR cannot connect to the database: " . self::$instance->connect_error, LOG_ERR);
          }
          else{
              self::$instance->set_charset('utf8mb4');
          }
      }
      return self::$instance;
    }

}
