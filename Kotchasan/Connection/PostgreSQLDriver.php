<?php

namespace Kotchasan\Connection;

use Kotchasan\Exception\DatabaseException;
use Kotchasan\Execution\PDOStatement;
use Kotchasan\Execution\StatementInterface;

/**
 * Class PostgreSQLDriver
 *
 * PostgreSQL-specific driver implementation.
 *
 * @package Kotchasan\Connection
 */
class PostgreSQLDriver implements DriverInterface
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
                \PDO::ATTR_EMULATE_PREPARES => false
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
     * Builds the DSN string for PostgreSQL.
     *
     * @param array $config The configuration array.
     * @return string The DSN string.
     */
    protected function buildDsn(array $config): string
    {
        $dsn = 'pgsql:';

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

        // Add schema if specified
        if (isset($config['schema'])) {
            $dsn .= ';options=--search_path='.$config['schema'];
        }

        // Add application name
        if (isset($config['application_name'])) {
            $dsn .= ';application_name='.$config['application_name'];
        } else {
            $dsn .= ';application_name=Kotchasan';
        }

        // Add SSL mode
        if (isset($config['sslmode'])) {
            $dsn .= ';sslmode='.$config['sslmode'];
        }

        // Add SSL certificate
        if (isset($config['sslcert'])) {
            $dsn .= ';sslcert='.$config['sslcert'];
        }

        // Add SSL key
        if (isset($config['sslkey'])) {
            $dsn .= ';sslkey='.$config['sslkey'];
        }

        // Add SSL root certificate
        if (isset($config['sslrootcert'])) {
            $dsn .= ';sslrootcert='.$config['sslrootcert'];
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

        // In PostgreSQL, sequences are typically named with pattern: table_column_seq
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
        return 'pgsql';
    }

    /**
     * {@inheritdoc}
     */
    public function emptyTable(string $tableName, array $options = []): bool
    {
        $options = array_merge([
            'use_truncate' => true,
            'restart_identity' => true,
            'cascade' => false
        ], $options);

        // Quote table name for PostgreSQL
        $quotedTable = $this->quoteIdentifier($tableName);

        // Try TRUNCATE first if enabled
        if ($options['use_truncate']) {
            try {
                $sql = "TRUNCATE TABLE {$quotedTable}";

                if ($options['restart_identity']) {
                    $sql .= ' RESTART IDENTITY';
                }

                if ($options['cascade']) {
                    $sql .= ' CASCADE';
                }

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
        // PostgreSQL does not have a direct OPTIMIZE TABLE command.
        // Instead, we can use VACUUM to clean up the table.
        $quotedTable = $this->quoteIdentifier($tableName);
        $sql = "VACUUM FULL {$quotedTable}";

        try {
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
    public function formatSqlFunction(string $type, array $parameters, ?string $alias): string
    {
        $aliasStr = $alias ? ' AS '.$this->quoteIdentifier($alias) : '';

        switch (strtoupper($type)) {
            case 'YEAR':
                return 'EXTRACT(YEAR FROM '.$this->quoteIdentifier($parameters['column']).')'.$aliasStr;

            case 'MONTH':
                return 'EXTRACT(MONTH FROM '.$this->quoteIdentifier($parameters['column']).')'.$aliasStr;

            case 'DAY':
                return 'EXTRACT(DAY FROM '.$this->quoteIdentifier($parameters['column']).')'.$aliasStr;

            case 'HOUR':
                return 'EXTRACT(HOUR FROM '.$this->quoteIdentifier($parameters['column']).')'.$aliasStr;

            case 'MINUTE':
                return 'EXTRACT(MINUTE FROM '.$this->quoteIdentifier($parameters['column']).')'.$aliasStr;

            case 'SECOND':
                return 'EXTRACT(SECOND FROM '.$this->quoteIdentifier($parameters['column']).')'.$aliasStr;

            case 'DATE':
                return $this->quoteIdentifier($parameters['column']).'::DATE'.$aliasStr;

            case 'NOW':
                return 'NOW()'.$aliasStr;

            case 'RAND':
                return 'RANDOM()'.$aliasStr;

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

                return 'STRING_AGG('.$distinct.$concatFields.", '".$separator."'".$orderClause.')'.$aliasStr;

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
                return 'COALESCE('.$this->quoteIdentifier($parameters['column1']).', '.$this->formatFieldOrValue($parameters['column2']).')'.$aliasStr;

            case 'DATEDIFF':
                return '('.$this->quoteIdentifier($parameters['column1']).' - '.$this->quoteIdentifier($parameters['column2']).')'.$aliasStr;

            case 'DATE_FORMAT':
                return 'TO_CHAR('.$this->quoteIdentifier($parameters['column']).", '".$parameters['format']."')".$aliasStr;

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
     * Quote identifier for PostgreSQL using double quotes.
     *
     * @param string $identifier The identifier to quote
     * @return string The quoted identifier
     */
    protected function quoteIdentifier(string $identifier): string
    {
        $parts = explode('.', $identifier);
        foreach ($parts as &$part) {
            $part = '"'.str_replace('"', '""', $part).'"';
        }
        return implode('.', $parts);
    }
}
