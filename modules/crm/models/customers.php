<?php
/**
 * @filesource modules/crm/models/customers.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Crm\Customers;

/**
 * API v1 Customers Model
 *
 * CRM Customers with comprehensive metrics for production use
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Get customer details by ID
     *
     * @param (int) $id
     *
     * @return object|null
     */
    public static function view($id)
    {
        $customer = static::createQuery()
            ->select('C.*', 'U.name owner')
            ->from('customers C')
            ->join('user U', [['U.id', 'C.owner_id']], 'LEFT')
            ->where([['C.id', $id]])
            ->first();

        if ($customer) {
            $customer->province = \Kotchasan\Province::get($customer->provinceID);
        }

        return $customer;
    }

    /**
     * Query data to send to DataTable
     *
     * @param array $params
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    public static function toDataTable($params)
    {
        // Filters (AND conditions)
        $where = [];
        if (!empty($params['status'])) {
            $where[] = ['C.status', $params['status']];
        }
        if (!empty($params['source'])) {
            $where[] = ['C.source', $params['source']];
        }
        if (!empty($params['company_type'])) {
            $where[] = ['C.company_type', $params['company_type']];
        }
        if (!empty($params['rating'])) {
            $where[] = ['C.rating', (int) $params['rating']];
        }
        if (!empty($params['owner_id'])) {
            $where[] = ['C.owner_id', (int) $params['owner_id']];
        }

        // Default query
        $query = static::createQuery()
            ->select(
                'C.id',
                'C.name',
                'C.company_type',
                'C.industry',
                'C.email',
                'C.phone',
                'C.status',
                'C.source',
                'C.rating',
                'C.annual_revenue',
                'C.employee_count',
                'C.created_at',
                'U.name owner_name'
            )
            ->from('customers C')
            ->join('user U', [['U.id', 'C.owner_id']], 'LEFT')
            ->where($where);

        // Search (OR condition)
        if (!empty($params['search'])) {
            $search = '%'.$params['search'].'%';
            $where = [
                ['C.name', 'LIKE', $search],
                ['C.email', 'LIKE', $search],
                ['C.phone', 'LIKE', $search],
                ['C.industry', 'LIKE', $search],
                ['C.city', 'LIKE', $search],
                ['C.tax_id', 'LIKE', $search],
                ['U.name', 'LIKE', $search]
            ];

            $query->where($where, 'OR');
        }

        return $query;
    }

    /**
     * Get recent CRM Customers data
     *
     * @param int $limit Number of recent customers to retrieve
     *
     * @return array Customers data with all metrics
     */
    public static function recent($limit = 5)
    {
        return static::createQuery()
            ->select('name', 'status', 'source', 'created_at')
            ->from('customers')
            ->orderBy('created_at', 'DESC')
            ->limit($limit)
            ->cacheOn()
            ->execute()
            ->fetchAll();
    }

    /**
     * Save customer
     *
     * @param int $id 0 = insert, > 0 = update
     * @param array $save
     *
     * @return mixed
     */
    public static function save($id, $save)
    {
        if ($id === 0) {
            return \Kotchasan\DB::create()->insert('customers', $save);
        } else {
            return \Kotchasan\DB::create()->update('customers', [['id', $id]], $save);
        }
    }

    /**
     * Delete customer
     *
     * @param int|array $ids
     *
     * @return int
     */
    public static function remove($ids)
    {
        return \Kotchasan\DB::create()->delete('customers', [['id', $ids]]);
    }

    /**
     * Update customer status
     *
     * @param array $ids Customer ID
     * @param string $status New status
     *
     * @return int Number of updated customers
     */
    public static function updateStatus($ids, $status)
    {
        return \Kotchasan\DB::create()->update('customers', [['id', $ids]], ['status' => $status]);
    }

    /**
     * list of customrs
     *
     * @return array
     */
    public static function getCustomerOptions()
    {
        return static::createQuery()
            ->select('id value', 'name text')
            ->from('customers')
            ->where([['status', '!=', 'churned']])
            ->orderBy('name')
            ->execute()
            ->fetchAll();
    }
}
