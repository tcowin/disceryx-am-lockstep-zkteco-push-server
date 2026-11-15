<?php namespace Blackfin\AttendanceManager;

    use Blackfin\Database\Database;

    class PersonPeer
    {
        public static function createNew($name, $source)
        {
            $name = trim($name);
            $dateNow = date('Y-m-d H:i:s');
            if(strpos($name, ' ') !== false){
                list($firstName, $lastName) = explode(' ', $name);
            }
            else{
                $firstName = $name;
                $lastName = '';
            }
            $fields = array('first_name' => $firstName,
                            'last_name' => $lastName,
                            'security_level' => 1,
                            'deletion_candidate' => 0,
                            'creation_source' => $source,
                            'updated_at' => $dateNow,
                            'created_at' => $dateNow);
            return Database::insertRecord('person', $fields);
        }

        public static function updatePerson($personId, $name)
        {
            if($personId && $name){
                $name = trim($name);
                if(strpos($name, ' ') !== false){
                    list($firstName, $lastName) = explode(' ', $name);
                }
                else{
                    $firstName = $name;
                    $lastName = '';
                }
                $fields = array('first_name' => $firstName,
                                'last_name' => $lastName);
                return Database::updateRecord('person', $personId, $fields);
            }
        }
    }