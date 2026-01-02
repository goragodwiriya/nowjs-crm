<?php

namespace Kotchasan\Connection;

use Kotchasan\Exception\DatabaseException;
use Kotchasan\Execution\PDOStatement;
use Kotchasan\Execution\StatementInterface;

/**
 * Class MySQLDriver
 *
 * MySQL-specific driver implementation.
 *
 * @package Kotchasan\Connection
 */
class MySQLDriver implements DriverInterface
{
    /**
     * The PDO instance.
     *
     * @var \PDO|null
     */
    protected $pdo = null;

    /**
     * The last error message.
     *
     * @var string|null
     */
    protected ?string $lastError = null;

    /**
     * {@inheritdoc}
     */
    public function connect(array $config): bool
    {
        $dsn = $this->buildDsn($config);

        try {
            $options = [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES '.($config['charset'] ?? 'utf8mb4')
            ];

            $this->pdo = new \PDO(
                $dsn,
                $config['username'] ?? '',
                $config['password'] ?? '',
                $options
            );

            return true;
        } catch (\PDOException $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Builds the DSN string for MySQL.
     *
     * @param array $config The configuration array.
     * @return string The DSN string.
     */
    protected function buildDsn(array $config): string
    {
        $dsn = 'mysql:';

        // Add host
        if (isset($config['host'])) {
            $dsn .= 'host='.$config['host'];
        } else {
            $dsn .= 'host=localhost';
        }

        // Add port if specified
        if (isset($config['port'])) {
            $dsn .= ';port='.$config['port'];
        }

        // Add database name
        if (isset($config['database'])) {
            $dsn .= ';dbname='.$config['database'];
        }

        // Add unix_socket if specified
        if (isset($config['unix_socket'])) {
            $dsn .= ';unix_socket='.$config['unix_socket'];
        }

        // Add charset
        if (isset($config['charset'])) {
            $dsn .= ';charset='.$config['charset'];
        } else {
            $dsn .= ';charset=utf8mb4';
        }

        return $dsn;
    }

    /**
     * {@inheritdoc}
     */
    public function disconnect(): bool
    {
        $this->pdo = null;

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function isConnected(): bool
    {
        return $this->pdo !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function prepare(string $query): StatementInterface
    {
        if (!$this->isConnected()) {
            throw new DatabaseException("Cannot prepare statement: Not connected to database.");
        }

        try {
            $pdoStatement = $this->pdo->prepare($query);

            return new PDOStatement($pdoStatement);
        } catch (\PDOException $e) {
            $this->lastError = $e->getMessage();
            throw new DatabaseException("Failed to prepare statement: ".$e->getMessage(), 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function beginTransaction(): bool
    {
        if (!$this->isConnected()) {
            throw new DatabaseException("Cannot begin transaction: Not connected to database.");
        }

        try {
            return $this->pdo->beginTransaction();
        } catch (\PDOException $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function commit(): bool
    {
        if (!$this->isConnected()) {
            throw new DatabaseException("Cannot commit transaction: Not connected to database.");
        }

        try {
            return $this->pdo->commit();
        } catch (\PDOException $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function rollback(): bool
    {
        if (!$this->isConnected()) {
            throw new DatabaseException("Cannot rollback transaction: Not connected to database.");
        }

        try {
            return $this->pdo->rollBack();
        } catch (\PDOException $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function inTransaction(): bool
    {
        if (!$this->isConnected()) {
            return false;
        }

        return $this->pdo->inTransaction();
    }

    /**
     * {@inheritdoc}
     */
    public function lastInsertId(?string $name = null): string
    {
        if (!$this->isConnected()) {
            throw new DatabaseException("Cannot get last insert ID: Not connected to database.");
        }

        return $this->pdo->lastInsertId($name);
    }

    /**
     * {@inheritdoc}
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * {@inheritdoc}
     */
    public function escape(string $value): string
    {
        if (!$this->isConnected()) {
            throw new DatabaseException("Cannot escape value: Not connected to database.");
        }

        return $this->pdo->quote($value);
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'mysql';
    }

    /**
     * {@inheritdoc}
     */
    public function emptyTable(string $tableName, array $options = []): bool
    {
        $options = array_merge([
            'use_truncate' => true
        ], $options);

        // Quote table name for MySQL
        $quotedTable = $this->quoteIdentifier($tableName);

        // Try TRUNCATE first if enabled
        if ($options['use_truncate']) {
            try {
                $sql = "TRUNCATE TABLE {$quotedTable}";
                $statement = $this->prepare($sql);
                $result = $statement->execute();
                return $result !== false;
            } catch (\Exception $e) {
                $this->lastError = $e->getMessage();
                // Fall through to DELETE fallback
            }
        }

        // Fallback to DELETE
        try {
            $sql = "DELETE FROM {$quotedTable}";
            $statement = $this->prepare($sql);
            $result = $statement->execute();
            return $result !== false;
        } catch (\Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function optimizeTable(string $tableName): bool
    {
        $quotedTable = $this->quoteIdentifier($tableName);
        $sql = "OPTIMIZE TABLE {$quotedTable}";
        $statement = $this->prepare($sql);
        $result = $statement->execute();
        return $result !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function formatSqlFunction(string $type, array $parameters, ?string $alias): string
    {
        $aliasStr = $alias ? ' AS '.$this->quoteIdentifier($alias) : '';

        switch (strtoupper($type)) {
            case 'YEAR':
                return 'YEAR('.$this->quoteIdentifier($parameters['column']).')'.$aliasStr;

            case 'MONTH':
                return 'MONTH('.$this->quoteIdentifier($parameters['column']).')'.$aliasStr;

            case 'DAY':
                return 'DAY('.$this->quoteIdentifier($parameters['column']).')'.$aliasStr;

            case 'HOUR':
                return 'HOUR('.$this->quoteIdentifier($parameters['column']).')'.$aliasStr;

            case 'MINUTE':
                return 'MINUTE('.$this->quoteIdentifier($parameters['column']).')'.$aliasStr;

            case 'SECOND':
                return 'SECOND('.$this->quoteIdentifier($parameters['column']).')'.$aliasStr;

            case 'DATE':
                return 'DATE('.$this->quoteIdentifier($parameters['column']).')'.$aliasStr;

            case 'NOW':
                return 'NOW()'.$aliasStr;

            case 'RAND':
                return 'RAND()'.$aliasStr;

            case 'CONCAT':
                $fields = array_map([$this, 'formatFieldOrValue'], $parameters['fields']);
                if (!empty($parameters['separator'])) {
                    return "CONCAT_WS('".$parameters['separator']."', ".implode(', ', $fields).')'.$aliasStr;
                }
                return 'CONCAT('.implode(', ', $fields).')'.$aliasStr;

            case 'GROUP_CONCAT':
                $distinct = !empty($parameters['distinct']) ? 'DISTINCT ' : '';
                $fields = array_map([$this, 'formatFieldOrValue'], $parameters['fields']);
                $concatFields = implode(', ', $fields);
                $separator = $parameters['separator'] ?? ',';
                $orderClause = '';

                if (!empty($parameters['order'])) {
                    $orders = is_array($parameters['order']) ? $parameters['order'] : [$parameters['order']];
                    $orderClause = ' ORDER BY '.implode(', ', array_map([$this, 'quoteIdentifier'], $orders));
                }

                return 'GROUP_CONCAT('.$distinct.$concatFields.$orderClause." SEPARATOR '".$separator."')".$aliasStr;

            case 'COUNT':
                $distinct = !empty($parameters['distinct']) ? 'DISTINCT ' : '';
                $column = $parameters['column'] === '*' ? '*' : $this->quoteIdentifier($parameters['column']);
                return 'COUNT('.$distinct.$column.')'.$aliasStr;

            case 'SUM':
                $distinct = !empty($parameters['distinct']) ? 'DISTINCT ' : '';
                return 'SUM('.$distinct.$this->quoteIdentifier($parameters['column']).')'.$aliasStr;

            case 'AVG':
                $distinct = !empty($parameters['distinct']) ? 'DISTINCT ' : '';
                return 'AVG('.$distinct.$this->quoteIdentifier($parameters['column']).')'.$aliasStr;

            case 'MAX':
                return 'MAX('.$this->quoteIdentifier($parameters['column']).')'.$aliasStr;

            case 'MIN':
                return 'MIN('.$this->quoteIdentifier($parameters['column']).')'.$aliasStr;

            case 'DISTINCT':
                return 'DISTINCT '.$this->quoteIdentifier($parameters['column']).$aliasStr;

            case 'IFNULL':
                return 'IFNULL('.$this->quoteIdentifier($parameters['column1']).', '.$this->formatFieldOrValue($parameters['column2']).')'.$aliasStr;

            case 'DATEDIFF':
                return 'DATEDIFF('.$this->quoteIdentifier($parameters['column1']).', '.$this->quoteIdentifier($parameters['column2']).')'.$aliasStr;

            case 'DATE_FORMAT':
                return 'DATE_FORMAT('.$this->quoteIdentifier($parameters['column']).", '".$parameters['format']."')".$aliasStr;

            case 'COLUMN':
                return $this->quoteIdentifier($parameters['column']);

            default:
                throw new \InvalidArgumentException("Unsupported SQL function: {$type}");
        }
    }

    /**
     * Format field name or value for SQL usage
     *
     * @param mixed $value
     * @return string
     */
    protected function formatFieldOrValue($value): string
    {
        if (is_string($value) && preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*)?$/', $value)) {
            // Looks like a field name
            return $this->quoteIdentifier($value);
        }

        // Treat as literal value
        return is_string($value) ? "'".str_replace("'", "''", $value)."'" : (string) $value;
    }

    /**
     * Quote identifier for MySQL using backticks.
     *
     * @param string $identifier The identifier to quote
     * @return string The quoted identifier
     */
    protected function quoteIdentifier(string $identifier): string
    {
        $parts = explode('.', $identifier);
        foreach ($parts as &$part) {
            $part = '`'.str_replace('`', '``', $part).'`';
        }
        return implode('.', $parts);
    }
}
