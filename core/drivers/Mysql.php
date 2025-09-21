<?php
namespace Zero\Drivers;

use PDO;
use PDOException;

/**
 * MySQL Database Driver Class
 * 
 * This class handles MySQL database connections using PDO.
 * It provides a robust and secure way to connect to MySQL databases
 * with proper error handling and connection configuration.
 */
class MysqlDriver {

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
     * @param array $config Database configuration array containing host, database, username, and password
     * @throws PDOException If connection cannot be established
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->createConnection();
    }

    /**
     * Creates a new PDO connection to MySQL database
     * 
     * Establishes connection with the following settings:
     * - UTF-8 charset
     * - Error mode set to exceptions
     * - Prepared statements (not emulated)
     * - Associative fetch mode by default
     * - Persistent connections enabled
     * 
     * @return PDO Returns the PDO connection instance
     * @throws PDOException If connection fails
     */
    public function createConnection() {
        try {
            // Create new PDO instance with connection parameters
            $charset = $this->config['charset'] ?? 'utf8mb4';
            $collation = $this->config['collation'] ?? null;

            $this->connection = new PDO(
                'mysql:host=' . $this->config['host'] .
                ';dbname=' . $this->config['database'] .
                ';charset=' . $charset,
                $this->config['username'],
                $this->config['password']
            );

            // Configure PDO connection attributes
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->connection->setAttribute(PDO::ATTR_PERSISTENT, true);

            if ($collation !== null) {
                $escapedCharset = str_replace("'", "''", $charset);
                $escapedCollation = str_replace("'", "''", $collation);
                $this->connection->exec(sprintf("SET NAMES '%s' COLLATE '%s'", $escapedCharset, $escapedCollation));
            }

        } catch (PDOException $e) {
            throw new PDOException("Failed to connect to MySQL: " . $e->getMessage(), $e->getCode());
        }

        return $this->connection;
    }
}
