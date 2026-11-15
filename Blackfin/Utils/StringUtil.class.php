<?php

    /**
     * Created by PhpStorm.
     * User: tcowin
     * Date: 12/3/15
     * Time: 6:24 PM
     */

    namespace Blackfin\Utils;

class StringUtil{

    public static function toMysqlDate($string){
        if(!$string){
            return '';
        }
        return date('Y-m-d', strtotime($string));
    }

    public static function toMysqlDatetime($string){
        if(!$string){
            return '';
        }
        return date('Y-m-d H:i:s', strtotime($string));
    }

    public static function to_mixed_case($string){
        return ucwords(strtolower($string));
    }

    public static function is_caps($str)
    {
        if (strtoupper($str) == $str)
            return(TRUE);
        else
            return(FALSE);
    }

    public static function apiResponseToArray($response, $primarySeparator = "\t")
    {
        $elements = array();

        if($primarySeparator == 'tabs_or_spaces'){
            $pairs = preg_split('/\s+/', $response);
        }
        else{
            $pairs = explode($primarySeparator, $response);
        }

        foreach($pairs as $pair){
            $keyValue = explode('=', $pair);
            if(count($keyValue) >= 2){
                $elements[$keyValue[0]] = $keyValue[1];
            }
        }

        return $elements;
    }

    public static function addQuotes($var)
    {
        if(is_array($var)){
            foreach($var as $element){
                $element = "'" . addslashes($element) . "'";
            }
            return $var;
        }
        else{
            return "'" . addslashes((string) $var) . "'";   // null is '', as before (PHP 8.1 deprecates passing null)
        }
    }

    public static function capitalize_last_name($name) {

        $suffixes = array('Ph.D.', 'JR.', 'Jr.', 'SR.', 'Sr.', 'II', 'III', 'IV', 'V');
        $suffixes_to_capitalize = array('JR.', 'SR.');

        $suffix = '';
        $name_elements = explode(' ', $name);   // split() is gone since PHP 7
        $num_elements = count($name_elements);
        if($num_elements > 1){
            if(in_array($name_elements[$num_elements - 1], $suffixes)){
                if(!in_array($name_elements[$num_elements - 1], $suffixes_to_capitalize)){
                    $suffix = array_pop($name_elements);
                }
            }
            $name = implode(' ', $name_elements);
        }


        $name = strtolower($name);
        if(stripos($name, "'s ") === FALSE){
            $name = join("'", array_map('ucwords', explode("'", $name)));
        }
        $name = join("-", array_map('ucwords', explode("-", $name)));
        //$name = join("Mac", array_map('ucwords', explode("Mac", $name)));
        $name = join("Mc", array_map('ucwords', explode("Mc", $name)));
        return $name . ($suffix != '' ? ' ' . $suffix : '');
    }

    public static function mc_addQuotes($str, $last_name = false){
        if(self::is_caps($str)){
            if($last_name){
                $str = self::capitalize_last_name($str);
            }
            else{
                $str = self::to_mixed_case($str);
            }
        }
        return self::addQuotes($str);
    }
}
