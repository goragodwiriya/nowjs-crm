<?php
/**
 * @filesource modules/crm/models/contacts.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Crm\Contacts;

use Kotchasan\Database\Sql;

/**
 * API Contacts Model
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Query data to send to DataTable
     *
     * @param array $params
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    public static function toDataTable($params)
    {
        // Default query
        $query = static::createQuery()
            ->select(
                'C.id',
                'C.first_name',
                'C.last_name',
                'C.email',
                'C.phone',
                'C.mobile',
                'C.job_title',
                'C.department',
                'C.is_primary',
                'C.is_decision_maker',
                'C.status',
                'C.created_at',
                'CU.name AS customer'
            )
            ->from('contacts C')
            ->join('customers CU', [['CU.id', 'C.customer_id']], 'LEFT');

        // Filters (AND conditions)
        $where = [];
        if (!empty($params['status'])) {
            $where[] = ['C.status', $params['status']];
        }
        if (!empty($params['customer_id'])) {
            $where[] = ['C.customer_id', $params['customer_id']];
        }

        if (!empty($where)) {
            $query->where($where);
        }

        // Search (OR condition)
        if (!empty($params['search'])) {
            $search = '%'.$params['search'].'%';
            $orWhere = [
                ['C.first_name', 'LIKE', $search],
                ['C.last_name', 'LIKE', $search],
                ['C.email', 'LIKE', $search],
                ['C.phone', 'LIKE', $search],
                ['CU.name', 'LIKE', $search]
            ];

            $query->where($orWhere, 'OR');
        }

        return $query;
    }

    /**
     * Get form options
     *
     * @return array
     */
    public static function getFormOptions()
    {
        // Get customers
        $customers = static::createQuery()
            ->select('id', 'name')
            ->from('customers')
            ->where([['status', '!=', 'churned']])
            ->orderBy('name')
            ->cacheOn()
            ->execute()
            ->fetchAll();

        // Get owners
        $owners = static::createQuery()
            ->select('id', 'name')
            ->from('user')
            ->where([['active', 1]])
            ->orderBy('name')
            ->cacheOn()
            ->execute()
            ->fetchAll();

        return [
            'customers' => $customers,
            'owners' => $owners
        ];
    }

    /**
     * Get contact by ID
     *
     * @param int $id Contact ID
     * @return object|null
     */
    public static function get($id)
    {
        return static::createQuery()
            ->select('C.*', 'CU.name AS customer')
            ->from('contacts C')
            ->join('customers CU', [['CU.id', 'C.customer_id']], 'LEFT')
            ->where([['C.id', $id]])
            ->execute()
            ->fetch();
    }

    /**
     * Save contact
     *
     * @param array $data Contact data
     * @return int|bool Contact ID on success
     */
    public static function save($data)
    {
        $db = static::createQuery();

        if (!empty($data['id'])) {
            $id = $data['id'];
            unset($data['id']);

            $result = $db->update('contacts')
                ->set($data)
                ->where([['id', $id]])
                ->execute();

            return $result !== false ? $id : false;
        } else {
            unset($data['id']);
            return $db->insert('contacts', $data);
        }
    }

    /**
     * Remove contacts
     *
     * @param array $ids Contact IDs
     * @return int
     */
    public static function remove($ids)
    {
        if (empty($ids)) {
            return 0;
        }

        static::createQuery()
            ->delete('contacts')
            ->where([['id', $ids]])
            ->execute();

        return count($ids);
    }

    /**
     * Update status for multiple contacts
     *
     * @param array $ids Contact IDs
     * @param string $status New status
     * @return int
     */
    public static function updateStatus($ids, $status)
    {
        if (empty($ids) || !in_array($status, ['active', 'inactive'])) {
            return 0;
        }

        static::createQuery()
            ->update('contacts')
            ->set(['status' => $status])
            ->where([['id', $ids]])
            ->execute();

        return count($ids);
    }

    /**
     * Get recent contacts
     *
     * @param int $limit
     * @return array
     */
    public static function recent($limit = 5)
    {
        return static::createQuery()
            ->select('C.id', 'C.first_name', 'C.last_name', 'C.email', 'C.status', 'C.created_at', 'CU.name AS customer')
            ->from('contacts C')
            ->join('customers CU', [['CU.id', 'C.customer_id']], 'LEFT')
            ->orderBy('C.created_at', 'DESC')
            ->limit($limit)
            ->execute()
            ->fetchAll();
    }

    /**
     * list of contacts
     *
     * @return array
     */
    public static function getContactOptions()
    {
        return static::createQuery()
            ->select('id value', Sql::CONCAT(['first_name', ' ', 'last_name'], 'text'))
            ->from('contacts')
            ->execute()
            ->fetchAll();
    }
}
