<?php

namespace {
    /**
     * Stub for DB class
     */
    class Database
    {

        public static function fetch($query, $bind=null, $params=null, $debug=false) {}
        public static function select($query, $bind=null, $params=null, $debug=false) {}
        public static function first($query, $bind=null, $params=null, $debug=false) {}
        public static function create($query, $bind=null, $params=null, $debug=false) {}
        public static function update($query, $bind=null, $params=null, $debug=false) {}
        public static function delete($query, $bind=null, $params=null, $debug=false) {}
        public static function query($query, $bind=null, $params=null, $debug=false) {}
        public static function escape($string) {}
        public static function connection($type) {}

    }

    class DB extends Database {}
}