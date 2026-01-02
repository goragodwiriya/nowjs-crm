<?php

namespace Kotchasan\QueryBuilder\SqlBuilder;

/**
 * Class MySqlSqlBuilder
 *
 * MySQL-specific SQL builder implementation.
 *
 * @package Kotchasan\QueryBuilder\SqlBuilder
 */
class MySqlSqlBuilder extends AbstractSqlBuilder
{
    /**
     * {@inheritdoc}
     */
    protected array $quoteChars = ['`', '`'];

    /**
     * {@inheritdoc}
     */
    protected array $supportedDrivers = ['mysql', 'mysqli'];

    /**
     * {@inheritdoc}
     */
    public function getDriverName(): string
    {
        return 'mysql';
    }

    /**
     * {@inheritdoc}
     */
    public function buildWhereClause(array $wheres, array &$bindings): string
    {
        if (empty($wheres)) {
            return '';
        }

        $conditions = [];
        foreach ($wheres as $where) {
            $boolean = $where['boolean'] ?? 'AND';
            $condition = $this->processWhereCondition($where, $bindings);

            if (!empty($condition)) {
                if ($where['type'] === 'nested' && isset($where['query'])) {
                    // Handle nested queries
                    $nestedWhere = $this->buildWhereClause($where['query']->wheres, $bindings);
                    if (!empty($nestedWhere)) {
                        $condition = '('.str_replace('WHERE ', '', $nestedWhere).')';
                    }
                }

                if (!empty($conditions)) {
                    $conditions[] = ' '.$boolean.' ';
                }
                $conditions[] = $condition;
            }
        }

        return empty($conditions) ? '' : 'WHERE '.implode('', $conditions);
    }

    /**
     * {@inheritdoc}
     */
    public function buildHavingClause(array $havings, array &$bindings): string
    {
        if (empty($havings)) {
            return '';
        }

        $conditions = [];
        foreach ($havings as $having) {
            $boolean = $having['boolean'] ?? 'AND';
            $condition = $this->processWhereCondition($having, $bindings);

            if (!empty($condition)) {
                if (!empty($conditions)) {
                    $conditions[] = ' '.$boolean.' ';
                }
                $conditions[] = $condition;
            }
        }

        return empty($conditions) ? '' : 'HAVING '.implode('', $conditions);
    }

    /**
     * {@inheritdoc}
     */
    public function buildLimitClause(?int $limit, ?int $offset = null): string
    {
        if ($limit === null) {
            return '';
        }

        if ($offset !== null && $offset > 0) {
            // MySQL format: LIMIT offset, limit
            return 'LIMIT '.$offset.', '.$limit;
        } else {
            // Simple limit without offset
            return 'LIMIT '.$limit;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildInsertStatement(string $table, array $data, array &$bindings, bool $ignore = false): string
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Insert data cannot be empty');
        }

        $quotedTable = $this->quoteIdentifier($table);

        $insertClause = 'INSERT ';
        if ($ignore) {
            $insertClause .= 'IGNORE ';
        }
        $insertClause .= 'INTO ';

        // Handle batch insert (array of arrays)
        if (isset($data[0]) && is_array($data[0])) {
            $columns = array_keys($data[0]);
            $quotedColumns = array_map([$this, 'quoteIdentifier'], $columns);

            $values = [];
            foreach ($data as $row) {
                $rowValues = [];
                foreach ($columns as $column) {
                    $rowValues[] = '?';
                    $bindings[] = $row[$column] ?? null;
                }
                $values[] = '('.implode(', ', $rowValues).')';
            }

            return $insertClause.$quotedTable.' ('.implode(', ', $quotedColumns).') VALUES '.implode(', ', $values);
        }

        // Single row insert
        $columns = array_keys($data);
        $quotedColumns = array_map([$this, 'quoteIdentifier'], $columns);

        $placeholders = [];
        foreach ($data as $value) {
            $placeholders[] = '?';
            $bindings[] = $value;
        }

        return $insertClause.$quotedTable.' ('.implode(', ', $quotedColumns).') VALUES ('.implode(', ', $placeholders).')';
    }

    /**
     * {@inheritdoc}
     */
    public function buildUpdateStatement(string $table, array $data, array $wheres, array &$bindings): string
    {
        if (empty($data)) {
            throw new \InvalidArgumentException('Update data cannot be empty');
        }

        $quotedTable = $this->quoteIdentifier($table);

        // Build SET clause
        $setParts = [];
        foreach ($data as $column => $value) {
            $quotedColumn = $this->quoteIdentifier($column);

            // Check if value is a Sql object or SqlFunction (raw SQL expression)
            if ($value instanceof \Kotchasan\Database\Sql) {
                // Use raw SQL expression directly
                $setParts[] = $quotedColumn.' = '.$value->toSql();
                // Merge any bindings from the Sql object
                foreach ($value->getValues([]) as $v) {
                    $bindings[] = $v;
                }
            } elseif ($value instanceof \Kotchasan\QueryBuilder\SqlFunction) {
                // Use SqlFunction's toSql method
                $setParts[] = $quotedColumn.' = '.$value->toSql();
            } elseif ($value instanceof \Kotchasan\QueryBuilder\RawExpression) {
                // Use RawExpression's toSql method
                $setParts[] = $quotedColumn.' = '.$value->toSql();
            } else {
                // Regular value - use placeholder
                $setParts[] = $quotedColumn.' = ?';
                $bindings[] = $value;
            }
        }

        $sql = 'UPDATE '.$quotedTable.' SET '.implode(', ', $setParts);

        // Add WHERE clause
        $whereClause = $this->buildWhereClause($wheres, $bindings);
        if (!empty($whereClause)) {
            $sql .= ' '.$whereClause;
        }

        return $sql;
    }

    /**
     * {@inheritdoc}
     */
    public function buildDeleteStatement(string $table, array $wheres, array &$bindings): string
    {
        $quotedTable = $this->quoteIdentifier($table);

        $sql = 'DELETE FROM '.$quotedTable;

        // Add WHERE clause
        $whereClause = $this->buildWhereClause($wheres, $bindings);
        if (!empty($whereClause)) {
            $sql .= ' '.$whereClause;
        }

        return $sql;
    }
}
