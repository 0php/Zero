<?php
namespace Zero\Lib;

use PDO;

class Database {
    public static function fetch($query, $bind=null, $params=null, $debug=false) {
        $db = new DatabaseConnection();
        return $db->fetch($query, $bind, $params, $debug);
    }

    public function select($query, $bind=null, $params=null, $debug=false) {
        return self::fetch($query, $bind, $params, $debug);
    }

    public static function first($query, $bind=null, $params=null, $debug=false) {
        $db = new DatabaseConnection();
        return $db->first($query, $bind, $params, $debug);
    }

    public static function create($query, $bind=null, $params=null, $debug=false) {
        $db = new DatabaseConnection();
        return $db->create($query, $bind, $params, $debug);
    }

    public static function update($query, $bind=null, $params=null, $debug=false) {
        $db = new DatabaseConnection();
        return $db->update($query, $bind, $params, $debug);
    }

    public static function delete($query, $bind=null, $params=null, $debug=false) {
        $db = new DatabaseConnection();
        return $db->delete($query, $bind, $params, $debug);
    }

    public static function query($query, $bind=null, $params=null, $debug=false) {
        $db = new DatabaseConnection();
        return $db->query($query, $bind, $params, $debug);
    }

    public static function escape($string) {
        $db = new DatabaseConnection();
        return $db->escape($string);
    }

    public static function write() {
        $db = new DatabaseConnection();
        return $db->setConnector('write');
    }

    public static function read() {
        $db = new DatabaseConnection();
        return $db->setConnector('read');
    }

}



class DatabaseConnection {

    public $connection;

    public $connector;

    public $driver;

    public function __construct() {
        $this->driver = config("database.".config('database.connection'));
        $this->connect();
    }

    public function connect() {
        $driverClass = ucfirst($this->driver['driver']) . 'Driver';
        $class = "Zero\\Drivers\\{$driverClass}";

        if (class_exists($class)) {
            $driver = new $class($this->driver);
            $this->connection = $driver->createConnection();
        } else {
            throw new \Exception("Driver class $class not found");
        }
    }
    

    public function setConnector($connector) {
        $this->connector = $connector;
        return $this;
    }


    // Check is write or write
    public function isWrite($query) {
        $query_ex = explode(" ", trim(strtolower($query)),2);
        if (in_array($query_ex,array("alter","create","delete","drop","insert","truncate","update")))
            return true;
        return false;
    }

    public function escape($string) {
        return $this->connection->quote($string);
    }

    public function query($query, $bind = null, $params = array(), $state = 'fetch', $debug=false) {
        $db    = $this->connection;
        $stmt  = null;

        $stmt = $db->prepare($query);

        if (is_string($bind) && $bind !== '' && is_array($params) && count($params) > 0) {
            $splitedBind = str_split($bind);

            foreach ($splitedBind as $key => $value) {
                $dataType = match ($value) {
                    'i', 'b', 'd' => PDO::PARAM_INT,
                    default => PDO::PARAM_STR,
                };

                $stmt->bindValue($key + 1, $params[$key] ?? null, $dataType);
            }
        } elseif (is_array($bind) && empty($params)) {
            foreach ($bind as $key => $value) {
                if (is_numeric($key)) {
                    $stmt->bindValue((int) $key + 1, $value);
                } else {
                    $stmt->bindValue($key, $value);
                }
            }
        } elseif (is_array($params) && count($params) > 0) {
            foreach (array_values($params) as $index => $value) {
                $stmt->bindValue($index + 1, $value);
            }
        }

        $stmt->execute();

        if ($state == 'fetch') {
            $stmt = $stmt->fetchAll() ?? [];
        } elseif($state == 'first') {
            $stmt = $stmt->fetch();
        } elseif($state == 'create') {
            $stmt = $db->lastInsertId();
        } elseif($state == 'update') {
            $stmt = $stmt->rowCount();
        } elseif($state == 'delete') {
            $stmt = $stmt->rowCount();
        }
        return $stmt;
    }

    public function fetch($query, $bind=null, $params=null, $debug=false) {
        return $this->query($query, $bind, $params, 'fetch', $debug);
    }

    public function first($query, $bind=null, $params=null, $debug=false) {
        return $this->query($query, $bind, $params, 'first', $debug);
    }

    public function create($query, $bind=null, $params=null, $debug=false) {

        return $this->query($query, $bind, $params, 'create', $debug);
    }

    public function update($query, $bind=null, $params=null, $debug=false) {
        return $this->query($query, $bind, $params, 'update', $debug);
    }

    public function delete($query, $bind=null, $params=null, $debug=false) {
        return $this->query($query, $bind, $params, 'delete', $debug);
    }

}

