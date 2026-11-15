<?php

    namespace Blackfin\Database;

    use Blackfin\Utils\Logger;
    use Blackfin\Utils\StringUtil;

class Database {

    /**
     * Returns FALSE on failure. For successful SELECT, SHOW, DESCRIBE or EXPLAIN queries mysqli_query() will return a
     * mysqli_result object. For other successful queries mysqli_query() will return TRUE.

     * @param $query
     * @param bool|false $ignore_error
     * @return mixed
     */
    public static function processQuery($query, $ignore_error = false){

        $mysqli = MySQLAccessor::getInstance();
//echo "q: $query<br>";
        // A failed query returns false, as on PHP 7.4 (MySQLAccessor turns mysqli's exceptions off); the catch is for
        // a lost connection or anything else that still throws, so one bad query never ends the request.
        try {
            $result = $mysqli->query($query);
        } catch (\Throwable $e) {
            Logger::logMessage(__METHOD__, " MySQL ERROR " . $e->getMessage() . " Whole query: $query", LOG_ERR);
            return false;
        }

//        Logger::logMessage(__METHOD__, " MySQL $query", LOG_DEBUG);

        // Check result
        // This shows the actual query sent to MySQL, and the error. Useful for debugging.
        if (!$result && !$ignore_error) {
            $message  = 'Invalid query: ' . mysqli_error($mysqli) . "\n";
            $message .= 'Whole query: ' . $query;
            //echo $message; exit;
            Logger::logMessage(__METHOD__, " MySQL ERROR $message", LOG_ERR);
            //die($message);
        }
        return $result;
    }

    /**
     * Retrieve a single record from the specified table by either specifying the record id as an integer,
     * or an array of field names and values.
     *
     * @param $tablename
     * @param $mixed
     * @return null
     */
    public static function retrieveRecord($tablename, $mixed)
    {
        $return = null;
        $whereClause = '';
        if(is_integer($mixed)){
            $whereClause  = "`id` = $mixed";
        }
        elseif(is_array($mixed)){
            foreach($mixed as $key => $value){
                if($value === null || $value == 'null'){
                    $whereClause .= ($whereClause != '' ? ' AND ' : '') . "`$key` is null ";
                }
                else{
                    $whereClause .= ($whereClause != '' ? ' AND ' : '') . "`$key` = " . StringUtil::addQuotes($value);
                }
            }
        }
        $query = "select * from `$tablename` where " . $whereClause;

        $result = self::processQuery($query);

        if ($result) {
            $row = $result->fetch_array();
            if($row){
                $return = $row;
            }
            $result->close();
        }

        return $return;
    }

    /**
     * Retrieve all records from the specified query;
     *
     * @param $tablename
     * @param $mixed
     * @return array of associative arrays
     */
    public static function retrieveRecordsFromQuery($query)
    {
        $records = array();
        $result = self::processQuery($query);

        if ($result) {
            while($row = $result->fetch_array()){
                $records[] = $row;
            }
            $result->close();
        }

        return $records;
    }

    /**
     * Retrieve all records from the specified table by an array of field names and values.
     *
     * @param $tablename
     * @param $mixed
     * @return null
     */
    public static function retrieveRecords($tablename, $fields)
    {
        $whereClause = '';
        foreach($fields as $key => $value){
            if($value === null){
                $equalityClause = " IS NULL";
            }
            else{
                $equalityClause = ' = ' . StringUtil::addQuotes($value);
            }
            $whereClause .= ($whereClause != '' ? ' AND ' : '') . "`$key` {$equalityClause}";
        }
        $query = "select * from `$tablename` where " . $whereClause;

        return self::retrieveRecordsFromQuery($query);
    }

    public static function get_field_names($tablename){
        $col_names = array();
        $result = self::processQuery("show columns from `$tablename`");
        if(!$result){
            return $col_names;
        }
        while ($row = $result->fetch_array()){
            $col_names[] = $row[0];
        }
        $result->close();
        return $col_names;
    }

    public static function get_highest_current_precedence($tablename){
        $query = "select max(precedence) from `$tablename`";
        $result = self::processQuery($query);
        if(!$result){
            return 0;
        }
        $row = $result->fetch_array();
        if(empty($row['max(precedence)'])){
            $returnValue = 0;
        }
        else {
            $returnValue = $row['max(precedence)'];
        }
        $result->close();
        return $returnValue;
    }

    /**
     * @return int|false the new row's id, or false if the insert failed (it was -1, which callers took for success:
     *                   a lost punch moved the clock's high-water mark and fired a clock event for id -1)
     */
    public static function insertRecord($tablename, $values, $addQuotes = true)
    {
        $returnValue = false;
        $column_names = "(";
        $column_values = "(";
        foreach($values as $key => $value){
            $column_names .= $key.",";
            if($value === null){
                $column_values .= "NULL,";
            }
            else{
                $column_values .= ($addQuotes && $value != "NOW()" ? StringUtil::addQuotes($value) : $value).",";
            }
        }
        $column_names[strlen($column_names) - 1] = ')';
        $column_values[strlen($column_values) - 1] = ')';

        $query  = "INSERT INTO `$tablename` $column_names VALUES $column_values";
        //tbc TODO comment or set to HIGH debug level
        Logger::logMessage(__METHOD__, " query: $query", LOG_DEBUG);
        $result = self::processQuery($query);

        if ($result) {
            $mysqli = MySQLAccessor::getInstance();
            $returnValue = $mysqli->insert_id;
        }
        return $returnValue;
    }
/*
    public static function old_update_record($tablename, $record_id, $column_name, $column_value, $string = false)
    {
        $query  = "UPDATE `$tablename` SET $column_name = " . ($string ? '\'' : '') . $column_value . ($string ? '\'' : '' ) . " where id = $record_id";

        return self::processQuery($query);
    }

    public static function update_record($tablename, $record_id, $column_name, $column_value, $string = false)
    {
        $mysqli = MySQLAccessor::getInstance();

        $query  = "UPDATE `$tablename` SET $column_name = " . ($string ? '\'' : '') . $mysqli->real_escape_string($column_value) . ($string ? '\'' : '' ) . " where id = $record_id";

        self::processQuery($query);

        $field_names = self::get_field_names($tablename);
        if(in_array('updated_at', $field_names)) {
            $query  = "UPDATE `$tablename` SET updated_at = NOW() where id = $record_id";
            self::processQuery($query);
        }
    }
*/
    public static function updateRecord($tablename, $record_id, $fields, $string = true)
    {
        $set_clause = '';
        foreach($fields as $key => $value){
            //$set_clause .= ($set_clause != '' ? ', ' : 'SET ') . "`$key` = " . (($string && $value != "NOW()") ? StringUtil::addQuotes(mysql_real_escape_string($value)) : mysql_real_escape_string($value));
            // escaped once (addQuotes escapes; this used to escape twice, storing O'Brien as O\'Brien); null is NULL
            if($value === null){
                $sqlValue = 'NULL';
            }
            elseif($string && $value != "NOW()"){
                $sqlValue = StringUtil::addQuotes($value);
            }
            else{
                $sqlValue = addslashes((string) $value);
            }
            $set_clause .= ($set_clause != '' ? ', ' : 'SET ') . "`$key` = " . $sqlValue;
        }
        if($set_clause == ''){
            return;
        }
        if(stripos($set_clause, 'updated_at') === false && in_array('updated_at', self::get_field_names($tablename))) {
            $set_clause .= ", updated_at = NOW()";
        }
        $query  = "UPDATE `$tablename` $set_clause where id = $record_id";
        //Logger::logMessage(__METHOD__, "query: $query", LOG_DEBUG);

        self::processQuery($query);
    }

    public static function createStandardRow($tablename, $specific_values, $include_stock_rows = true, $addQuotes = true){
        if($include_stock_rows){
            $field_names = self::get_field_names($tablename);
            if(in_array('active', $field_names)) $specific_values['active'] = 1;
            if(in_array('precedence', $field_names) && !isset($specific_values['precedence'])) $specific_values['precedence'] = 1 + self::get_highest_current_precedence($tablename);
            if(in_array('updated_at', $field_names)) $specific_values['updated_at'] = "NOW()";
            if(in_array('created_at', $field_names)) $specific_values['created_at'] = "NOW()";
        }
        return self::insertRecord($tablename, $specific_values, $addQuotes);
    }

    public static function fetchAssoc($tablename, $fields){
        $rowId = self::getTableRowId($tablename, $fields, false);
        if($rowId){
            return self::retrieveRecord($tablename, $rowId);
        }
    }

    /**
     * Will retrieve (or possibly create) a record from the specified table with the values as
     * specified within the array of field values. This arrary should consist of at least one pair
     * formed as: 'fieldname' => fieldvalue. If insert_row is true(default), the row will be inserted into the table
     * if it does not exist.
     * 
     * @param $tablename
     * @param $fields
     * @param $insert_row
     * @param $addQuotes
     * @return unknown_type
     */
    public static function getTableRowId($tablename, $fields, $insert_row = true, $addQuotes = true){
        $where_clause = '';
        foreach($fields as $key => $value){
            // null is IS NULL: "= ''" never matches and MySQL 8 refuses it for date columns
            $condition = $value === null ? 'IS NULL' : '= ' . ($addQuotes ? StringUtil::addQuotes($value) : $value);
            $where_clause .= ($where_clause != '' ? ' AND ' : '') . "`$key` $condition";
        }
        $query = "select id from `$tablename` where " . $where_clause;
        $result = self::processQuery($query);
        if(!$result){
            return null;
        }
        $row = $result->fetch_array();
        $result->close();
        if(empty($row['id'])){
            if($insert_row){
                return self::createStandardRow($tablename, $fields, true, $addQuotes);
            }
        }
        else {
            return $row['id'];
        }
        return null;
    }

    public static function removeTableRow($tablename, $fields, $addQuotes = true, $ignore_error = false){
        $where_clause = '';
        foreach($fields as $key => $value){
            $where_clause .= ($where_clause != '' ? ' AND ' : '') . "`$key` = " . ($addQuotes ? StringUtil::addQuotes($value) : $value);
        }
        $query = "delete from `$tablename` where " . $where_clause;
        self::processQuery($query, $ignore_error);
    }

    // (the first three are required: defaults before a required parameter meant nothing, and PHP 8 says so)
    public static function retrieve_field_value($tablename, $fieldname_select, $value, $fieldname_to_retrieve, $exact_match = true, $addQuotes = true)
    {
        $return = null;
        if($exact_match) {
            $query  = "select $fieldname_to_retrieve from `$tablename` where $fieldname_select = ".($addQuotes ? '\'' : '') . $value . ($addQuotes ? '\'' : '');
        }
        else {
            $query  = "select $fieldname_to_retrieve from `$tablename` where $fieldname_select like '%$value%'";
        }

        $result = self::processQuery($query);

        if ($result) {
            $row = $result->fetch_array();
            if($row){
                $return = $row[$fieldname_to_retrieve];
            }
            $result->close();
        }
        return $return;
    }

    public static function retrieve_field_value_ext($tablename, $fields, $fieldname_to_retrieve, $addQuotes = true)
    {
        $return = null;
        $where_clause = '';
        foreach($fields as $key => $value){
            $where_clause .= ($where_clause != '' ? ' AND ' : 'WHERE ') . "`$key` = " . ($addQuotes ? StringUtil::addQuotes($value) : $value);
        }

        $query  = "select $fieldname_to_retrieve from `$tablename` $where_clause";

        $result = self::processQuery($query);

        if ($result) {
            $row = $result->fetch_array();
            if($row){
                $return = $row[$fieldname_to_retrieve];
            }
            $result->close();
        }
        return $return;
    }

}
