<?php

namespace Kotchasan\Connection;

use Kotchasan\Exception\DatabaseException;
use Kotchasan\Execution\PDOStatement;
use Kotchasan\Execution\StatementInterface;

/**
 * Class SQLiteDriver
 *
 * SQLite-specific driver implementation.
 *
 * @package Kotchasan\Connection
 */
class SQLiteDriver implements DriverInterface
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

            $this->lastError = null;
            return true;
        } catch (\PDOException $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
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
            return false;
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
            return false;
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
            return false;
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
            return '';
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
        return 'sqlite';
    }

    /**
     * {@inheritdoc}
     */
    public function emptyTable(string $tableName, array $options = []): bool
    {
        // SQLite doesn't support TRUNCATE, so we always use DELETE
        // Quote table name for SQLite
        $quotedTable = $this->quoteIdentifier($tableName);

        try {
            // Delete all rows
            $sql = "DELETE FROM {$quotedTable}";
            $statement = $this->prepare($sql);
            $result = $statement->execute();

            if ($result !== false) {
                // Reset auto-increment sequence if exists
                try {
                    $plainTableName = trim($tableName, '"\'`[]');
                    $resetSql = "DELETE FROM sqlite_sequence WHERE name = ?";
                    $stmt = $this->prepare($resetSql);
                    $stmt->execute([$plainTableName]);
                } catch (\Exception $e) {
                    // Ignore errors - sequence might not exist
                }
                return true;
            }
            return false;
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
        if (!$this->isConnected()) {
            return false;
        }

        try {
            // Run the VACUUM command to optimize the database
            $sql = "VACUUM";
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
                return "CAST(strftime('%Y', ".$this->quoteIdentifier($parameters['column']).') AS INTEGER)'.$aliasStr;

            case 'MONTH':
                return "CAST(strftime('%m', ".$this->quoteIdentifier($parameters['column']).') AS INTEGER)'.$aliasStr;

            case 'DAY':
                return "CAST(strftime('%d', ".$this->quoteIdentifier($parameters['column']).') AS INTEGER)'.$aliasStr;

            case 'HOUR':
                return "CAST(strftime('%H', ".$this->quoteIdentifier($parameters['column']).') AS INTEGER)'.$aliasStr;

            case 'MINUTE':
                return "CAST(strftime('%M', ".$this->quoteIdentifier($parameters['column']).') AS INTEGER)'.$aliasStr;

            case 'SECOND':
                return "CAST(strftime('%S', ".$this->quoteIdentifier($parameters['column']).') AS INTEGER)'.$aliasStr;

            case 'DATE':
                return 'DATE('.$this->quoteIdentifier($parameters['column']).')'.$aliasStr;

            case 'NOW':
                return 'DATETIME()'.$aliasStr;

            case 'RAND':
                return 'RANDOM()'.$aliasStr;

            case 'CONCAT':
                $fields = array_map([$this, 'formatFieldOrValue'], $parameters['fields']);
                if (!empty($parameters['separator'])) {
                    // SQLite doesn't have CONCAT_WS, simulate with || and separator
                    return '('.implode(" || '".$parameters['separator']."' || ", $fields).')'.$aliasStr;
                }
                return '('.implode(' || ', $fields).')'.$aliasStr;

            case 'GROUP_CONCAT':
                $distinct = !empty($parameters['distinct']) ? 'DISTINCT ' : '';
                $fields = array_map([$this, 'formatFieldOrValue'], $parameters['fields']);
                $concatFields = implode(' || ', $fields);
                $separator = $parameters['separator'] ?? ',';

                return 'GROUP_CONCAT('.$distinct.$concatFields.", '".$separator."')".$aliasStr;

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
                return '(JULIANDAY('.$this->quoteIdentifier($parameters['column1']).') - JULIANDAY('.$this->quoteIdentifier($parameters['column2']).'))'.$aliasStr;

            case 'DATE_FORMAT':
                return "strftime('".$parameters['format']."', ".$this->quoteIdentifier($parameters['column']).')'.$aliasStr;

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
     * Quote identifier for SQLite using double quotes.
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

    /**
     * Builds the DSN for the SQLite connection.
     *
     * @param array $config The configuration parameters.
     * @return string The DSN.
     */
    protected function buildDsn(array $config): string
    {
        $database = $config['database'] ?? ':memory:';
        return "sqlite:{$database}";
    }
}
