<?php
namespace Zero\Drivers;

use PDO;
use PDOException;

/**
 * SQLite3 Database Driver Class
 * 
 * This class handles SQLite3 database connections using PDO.
 * It provides functionality to create and connect to SQLite databases,
 * including automatic creation of database directories if they don't exist.
 */
class Sqlite3Driver {

    /**
     * @var PDO The PDO connection instance
     */
    public $connection;

    /**
     * @var array Database configuration parameters
     */
    public $config;

    /**
     * Constructor
     * 
     * @param array $config Database configuration array containing database path
     * @throws PDOException If connection cannot be established
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->createConnection();
    }

    /**
     * Creates a new PDO connection to SQLite database
     * 
     * Creates the database directory if it doesn't exist and establishes
     * connection with the following settings:
     * - Error mode set to exceptions
     * - Prepared statements (not emulated)
     * - Associative fetch mode by default
     * - Persistent connections enabled
     * 
     * @return PDO Returns the PDO connection instance
     * @throws PDOException If connection fails or if unable to create database directory
     */
    public function createConnection() {
        try {
            // Create database directory if it doesn't exist
            $folder = dirname($this->config['database']);
            if (!file_exists($folder)) {
                if (!mkdir($folder, 0777, true)) {
                    throw new PDOException("Failed to create database directory: " . $folder);
                }
            }
            
            // Create new PDO instance for SQLite
            $this->connection = new PDO('sqlite:' . $this->config['database']);

            // Configure PDO connection attributes
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->connection->setAttribute(PDO::ATTR_PERSISTENT, true);

        } catch (PDOException $e) {
            throw new PDOException("Failed to connect to SQLite database: " . $e->getMessage(), $e->getCode());
        }

        return $this->connection;
    }
}
