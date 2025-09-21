<?php
namespace Zero\Drivers;

use PDO;
use PDOException;

/**
 * PostgreSQL Database Driver Class
 * 
 * This class handles PostgreSQL database connections using PDO.
 * It provides a robust and secure way to connect to PostgreSQL databases
 * with proper error handling and connection configuration.
 */
class PgsqlDriver {

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
     * @param array $config Database configuration array containing host, port, database, username, and password
     * @throws PDOException If connection cannot be established
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->createConnection();
    }

    /**
     * Creates a new PDO connection to PostgreSQL database
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
            // Build DSN string with optional port configuration
            $charset = $this->config['charset'] ?? 'UTF8';
            $dsn = sprintf(
                "pgsql:host=%s;dbname=%s;options='--client_encoding=%s'",
                $this->config['host'],
                $this->config['database'],
                $charset
            );

            // Add port if specified
            if (!empty($this->config['port'])) {
                $dsn .= ';port=' . $this->config['port'];
            }

            // Create new PDO instance with connection parameters
            $this->connection = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password']
            );

            // Configure PDO connection attributes
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $this->connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->connection->setAttribute(PDO::ATTR_PERSISTENT, true);

        } catch (PDOException $e) {
            throw new PDOException("Failed to connect to PostgreSQL: " . $e->getMessage(), $e->getCode());
        }

        return $this->connection;
    }
}
