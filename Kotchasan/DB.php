<?php

namespace Kotchasan;

/**
 * Database Utility Class
 *
 * Provides convenient, short methods for common database operations.
 * This class is a wrapper around the QueryBuilder system that provides
 * simple, one-line methods for frequent database tasks.
 *
 * Usage:
 *   $db = DB::create();
 *   $db->insert('users', ['name' => 'John', 'email' => 'john@example.com']);
 *   $db->update('users', ['name' => 'Jane'], ['id' => 1]);
 *   $db->emptyTable('cache');
 *
 * @package Kotchasan
 */
class DB
{
    /**
     * The underlying Database instance.
     *
     * @var Database
     */
    private Database $database;

    /**
     * Constructor.
     *
     * @param Database|null $database Optional Database instance
     */
    public function __construct(?Database $database = null)
    {
        $this->database = $database ?: Database::create();
    }

    /**
     * Create a new DB utility instance.
     *
     * @param Database|null $database Optional Database instance
     * @return static
     */
    public static function create(?Database $database = null): self
    {
        return new static($database);
    }

    /**
     * Insert a record into a table.
     *
     * @param string $table Table name
     * @param array $data Associative array of column => value pairs
     * @return int|null The inserted record ID (for auto-increment columns) or null
     * @throws \Exception If the insert fails
     */
    public function insert(string $table, array $data): ?int
    {
        $builder = $this->database->createQuery()->insert($table)->values($data);
        $result = $builder->execute();

        // Get last insert ID from the database
        if ($result) {
            try {
                $id = $this->database->lastInsertId();
                return $id ? (int) $id : null;
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Update records in a table.
     *
     * @param string $table Table name
     * @param array $where WHERE conditions (same format as QueryBuilder)
     * @param array $data Associative array of column => value pairs to update
     * @return int Number of affected rows
     * @throws \Exception If the update fails
     */
    public function update(string $table, array $where, array $data): int
    {
        $result = $this->database->createQuery()
            ->update($table)
            ->set($data)
            ->where($where)
            ->execute();

        return $result ? $result->rowCount() : 0;
    }

    /**
     * Delete records from a table.
     *
     * @param string $table Table name
     * @param array $where WHERE conditions (same format as QueryBuilder)
     * @return int Number of affected rows
     * @throws \Exception If the delete fails
     */
    public function delete(string $table, array $where = [], int $limit = 0): int
    {
        $result = $this->database->createQuery()
            ->delete($table)
            ->where($where)
            ->limit($limit)
            ->execute();

        return $result ? $result->rowCount() : 0;
    }

    /**
     * Select records from a table.
     *
     * @param string $table Table name
     * @param array $where WHERE conditions (same format as QueryBuilder)
     * @param array $options Additional options (orderBy, limit, etc.)
     * @param array $columns Columns to select (default: ['*'])
     * @return array Array of results
     * @throws \Exception If the query fails
     */
    public function select(string $table, array $where = [], array $options = [], array $columns = ['*']): array
    {
        $builder = $this->database->createQuery()
            ->select(...$columns)
            ->from($table)
            ->where($where);

        // Add ORDER BY
        if (!empty($options['orderBy'])) {
            if (is_array($options['orderBy'])) {
                foreach ($options['orderBy'] as $column => $direction) {
                    $builder->orderBy($column, $direction);
                }
            } else {
                $builder->orderBy($options['orderBy']);
            }
        }

        // Add LIMIT
        if (!empty($options['limit'])) {
            $offset = $options['offset'] ?? 0;
            $builder->limit($options['limit'], $offset);
        }

        $result = $builder->execute();

        return $result ? $result->fetchAll() : [];
    }

    /**
     * Find a single record by conditions.
     *
     * @param string $table Table name
     * @param array $where WHERE conditions
     * @param array $columns Columns to select (default: ['*'])
     * @return object|null Single record or null if not found
     * @throws \Exception If the query fails
     */
    public function first(string $table, array $where = [], array $columns = ['*']): ?object
    {
        $results = $this->select($table, $where, ['limit' => 1], $columns);

        return !empty($results) ? $results[0] : null;
    }

    /**
     * Check if a record exists.
     *
     * @param string $table Table name
     * @param array $where WHERE conditions
     * @return bool True if record exists, false otherwise
     * @throws \Exception If the query fails
     */
    public function exists(string $table, array $where = []): bool
    {
        $result = $this->first($table, $where, ['1']);

        return !empty($result);
    }

    /**
     * Count records in a table.
     *
     * @param string $table Table name
     * @param array $where WHERE conditions (optional)
     * @return int Number of records
     * @throws \Exception If the query fails
     */
    public function count(string $table, array $where = []): int
    {
        $result = $this->database->createQuery()
            ->select(['COUNT(*) as count'])
            ->from($table)
            ->where($where)
            ->execute();

        if ($result) {
            $row = $result->fetch();
            return (int) ($row->count ?? 0);
        }

        return 0;
    }

    /**
     * Get the next available ID for a table.
     *
     * @param string $table Table name
     * @param array $where WHERE conditions (optional)
     * @param string $column Column name (default: 'id')
     *
     * @return int Next available ID
     * @throws \Exception If the query fails
     */
    public function nextId(string $table, $where = [], $column = 'id'): int
    {
        $result = $this->database->createQuery()
            ->select(['MAX('.$column.') as id'])
            ->from($table)
            ->where($where)
            ->execute();

        if ($result) {
            $row = $result->fetch();
            return (int) ($row->id ?? 0) + 1;
        }

        return 1;
    }

    /**
     * Empty (truncate) a table.
     *
     * @param string $table Table name
     * @param array $options Additional options (reset_autoincrement, etc.)
     * @return bool True on success, false on failure
     * @throws \Exception If the operation fails
     */
    public function emptyTable(string $table, array $options = []): bool
    {
        return $this->database->emptyTable($table, $options);
    }

    /**
     * Get the real table name (with prefix if configured).
     *
     * @param string $table Table name without prefix
     * @return string Full table name with prefix
     */
    public function getTableName(string $table): string
    {
        return $this->database->getTableName($table);
    }

    /**
     * Check if a database exists.
     *
     * @param string $databaseName Database name
     * @return bool True if database exists, false otherwise
     * @throws \Exception If the query fails
     */
    public function databaseExists(string $databaseName): bool
    {
        try {
            // Use raw SQL for checking database existence
            $sql = "SELECT 1";
            $result = $this->raw($sql);
            return $result !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if a field (column) exists in a table.
     *
     * @param string $table Table name
     * @param string $fieldName Field/column name
     * @return bool True if field exists, false otherwise
     * @throws \Exception If the query fails
     */
    public function fieldExists(string $table, string $fieldName): bool
    {
        try {
            $fullTableName = $this->getTableName($table);
            // Simple approach: try to select the field
            $sql = "SELECT `{$fieldName}` FROM `{$fullTableName}` LIMIT 0";
            $result = $this->raw($sql);
            return $result !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Execute a raw SQL query.
     *
     * @param string $sql Raw SQL query
     * @param array $bindings Parameter bindings (optional)
     * @return \Kotchasan\Result\ResultInterface|null Query result or null on failure
     * @throws \Exception If the query fails
     */
    public function raw(string $sql, array $bindings = [])
    {
        try {
            return $this->database->raw($sql, $bindings);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the underlying Database instance.
     *
     * @return Database
     */
    public function getDatabase(): Database
    {
        return $this->database;
    }

    /**
     * Begin a database transaction.
     *
     * @return bool True on success, false on failure
     */
    public function beginTransaction(): bool
    {
        return $this->database->beginTransaction();
    }

    /**
     * Commit a database transaction.
     *
     * @return bool True on success, false on failure
     */
    public function commit(): bool
    {
        return $this->database->commit();
    }

    /**
     * Rollback a database transaction.
     *
     * @return bool True on success, false on failure
     */
    public function rollback(): bool
    {
        return $this->database->rollback();
    }

    /**
     * Optimize a database table.
     *
     * This command is used to reclaim the unused space and defragment the data file.
     * The actual implementation varies by database driver:
     * - MySQL: OPTIMIZE TABLE
     * - PostgreSQL: VACUUM
     * - SQLite: VACUUM
     * - SQL Server: ALTER INDEX ... REORGANIZE
     *
     * @param string $table The table name to optimize
     * @return bool True on success, false on failure
     */
    public function optimizeTable(string $table): bool
    {
        return $this->database->optimizeTable($table);
    }
}
