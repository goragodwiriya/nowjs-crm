<?php

namespace Kotchasan\Connection;

use Kotchasan\Exception\DatabaseException;
use Kotchasan\Execution\PDOStatement;
use Kotchasan\Execution\StatementInterface;

/**
 * Class MSSQLDriver
 *
 * MSSQL-specific driver implementation.
 *
 * @package Kotchasan\Connection
 */
class MSSQLDriver implements DriverInterface
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

            // Set specific MSSQL options
            if (isset($config['charset'])) {
                $this->pdo->exec("SET NAMES '{$config['charset']}'");
            }

            return true;
        } catch (\PDOException $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Builds the DSN string for MSSQL.
     *
     * @param array $config The configuration array.
     * @return string The DSN string.
     */
    protected function buildDsn(array $config): string
    {
        $dsn = 'sqlsrv:';

        // Add server
        if (isset($config['host'])) {
            $dsn .= 'Server='.$config['host'];
        } else {
            $dsn .= 'Server=localhost';
        }

        // Add port if specified
        if (isset($config['port'])) {
            $dsn .= ','.$config['port'];
        }

        // Add database name
        if (isset($config['database'])) {
            $dsn .= ';Database='.$config['database'];
        }

        // Add App name
        if (isset($config['appname'])) {
            $dsn .= ';APP='.$config['appname'];
        } else {
            $dsn .= ';APP=Kotchasan';
        }

        // Add connection timeout
        if (isset($config['timeout'])) {
            $dsn .= ';ConnectionTimeout='.$config['timeout'];
        }

        // Add connection pooling
        if (isset($config['pooling']) && $config['pooling'] === false) {
            $dsn .= ';ConnectionPooling=0';
        }

        // Add encrypt
        if (isset($config['encrypt']) && $config['encrypt'] === true) {
            $dsn .= ';Encrypt=1';
        }

        // Add trust server certificate
        if (isset($config['trust_server_certificate']) && $config['trust_server_certificate'] === true) {
            $dsn .= ';TrustServerCertificate=1';
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
        return 'sqlsrv';
    }

    /**
     * {@inheritdoc}
     */
    public function emptyTable(string $tableName, array $options = []): bool
    {
        $options = array_merge([
            'use_truncate' => true
        ], $options);

        // Quote table name for SQL Server
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
        // SQL Server does not have a direct OPTIMIZE TABLE command.
        // We can use DBCC commands to achieve similar results.

        // Quote table name for SQL Server
        $quotedTable = $this->quoteIdentifier($tableName);

        try {
            // Rebuild indexes to optimize the table
            $sql = "ALTER INDEX ALL ON {$quotedTable} REBUILD";
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
                return 'YEAR('.$this->quoteIdentifier($parameters['column']).')'.$aliasStr;

            case 'MONTH':
                return 'MONTH('.$this->quoteIdentifier($parameters['column']).')'.$aliasStr;

            case 'DAY':
                return 'DAY('.$this->quoteIdentifier($parameters['column']).')'.$aliasStr;

            case 'HOUR':
                return 'DATEPART(HOUR, '.$this->quoteIdentifier($parameters['column']).')'.$aliasStr;

            case 'MINUTE':
                return 'DATEPART(MINUTE, '.$this->quoteIdentifier($parameters['column']).')'.$aliasStr;

            case 'SECOND':
                return 'DATEPART(SECOND, '.$this->quoteIdentifier($parameters['column']).')'.$aliasStr;

            case 'DATE':
                return 'CAST('.$this->quoteIdentifier($parameters['column']).' AS DATE)'.$aliasStr;

            case 'NOW':
                return 'GETDATE()'.$aliasStr;

            case 'RAND':
                return 'NEWID()'.$aliasStr;

            case 'CONCAT':
                $fields = array_map([$this, 'formatFieldOrValue'], $parameters['fields']);
                if (!empty($parameters['separator'])) {
                    // MSSQL doesn't have CONCAT_WS, simulate with + and separator
                    return '('.implode(" + '".$parameters['separator']."' + ", $fields).')'.$aliasStr;
                }
                return '('.implode(' + ', $fields).')'.$aliasStr;

            case 'GROUP_CONCAT':
                $distinct = !empty($parameters['distinct']) ? 'DISTINCT ' : '';
                $fields = array_map([$this, 'formatFieldOrValue'], $parameters['fields']);
                $concatFields = implode(' + ', $fields);
                $separator = $parameters['separator'] ?? ',';
                $orderClause = '';

                if (!empty($parameters['order'])) {
                    $orders = is_array($parameters['order']) ? $parameters['order'] : [$parameters['order']];
                    $orderClause = ' WITHIN GROUP (ORDER BY '.implode(', ', array_map([$this, 'quoteIdentifier'], $orders)).')';
                }

                return 'STRING_AGG('.$distinct.$concatFields.", '".$separator."')".$orderClause.$aliasStr;

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
                return 'ISNULL('.$this->quoteIdentifier($parameters['column1']).', '.$this->formatFieldOrValue($parameters['column2']).')'.$aliasStr;

            case 'DATEDIFF':
                return 'DATEDIFF(DAY, '.$this->quoteIdentifier($parameters['column2']).', '.$this->quoteIdentifier($parameters['column1']).')'.$aliasStr;

            case 'DATE_FORMAT':
                return 'FORMAT('.$this->quoteIdentifier($parameters['column']).", '".$parameters['format']."')".$aliasStr;

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
     * Quote identifier for SQL Server using square brackets.
     *
     * @param string $identifier The identifier to quote
     * @return string The quoted identifier
     */
    protected function quoteIdentifier(string $identifier): string
    {
        $parts = explode('.', $identifier);
        foreach ($parts as &$part) {
            $part = '['.str_replace(']', ']]', $part).']';
        }
        return implode('.', $parts);
    }
}
