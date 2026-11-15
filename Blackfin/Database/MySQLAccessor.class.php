<?php
    namespace Blackfin\Database;

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
          self::$instance = new self(HOST, USER, PASSWORD, DATABASE);
          self::$instance->set_charset('utf8mb4');
      }
      return self::$instance;
    }

}
