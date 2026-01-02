<?php
/**
 * @filesource modules/crm/models/activities.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 */

namespace Crm\Activities;

/**
 * API Activities Model
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
                'A.id', 'A.type', 'A.subject', 'A.start_time', 'A.end_time',
                'A.duration_minutes', 'A.status', 'A.priority', 'A.created_at',
                'C.name AS customer', 'U.name AS owner'
            )
            ->from('activities A')
            ->join('customers C', [['C.id', 'A.customer_id']], 'LEFT')
            ->join('user U', [['U.id', 'A.owner_id']], 'LEFT');

        // Filters (AND conditions)
        $where = [];
        if (!empty($params['status'])) {
            $where[] = ['A.status', $params['status']];
        }
        if (!empty($params['type'])) {
            $where[] = ['A.type', $params['type']];
        }

        if (!empty($where)) {
            $query->where($where);
        }

        // Search (OR condition)
        if (!empty($params['search'])) {
            $search = '%'.$params['search'].'%';
            $orWhere = [
                ['A.subject', 'LIKE', $search],
                ['A.description', 'LIKE', $search],
                ['C.name', 'LIKE', $search]
            ];

            $query->where($orWhere, 'OR');
        }

        return $query;
    }

    /**
     * Get form options
     */
    public static function getFormOptions()
    {
        $customers = static::createQuery()
            ->select('id', 'name')->from('customers')
            ->where([['status', '!=', 'churned']])
            ->orderBy('name')->cacheOn()->execute()->fetchAll();

        $deals = static::createQuery()
            ->select('id', 'title AS name')->from('deals')
            ->where([['stage', '!=', 'lost']])
            ->orderBy('title')->execute()->fetchAll();

        $owners = static::createQuery()
            ->select('id', 'name')->from('user')
            ->where([['active', 1]])
            ->orderBy('name')->cacheOn()->execute()->fetchAll();

        return ['customers' => $customers, 'deals' => $deals, 'owners' => $owners];
    }

    /**
     * Get activity by ID
     */
    public static function get($id)
    {
        return static::createQuery()
            ->select('A.*', 'C.name AS customer')
            ->from('activities A')
            ->join('customers C', [['C.id', 'A.customer_id']], 'LEFT')
            ->where([['A.id', $id]])
            ->execute()->fetch();
    }

    /**
     * Save activity
     */
    public static function save($data)
    {
        $db = static::createQuery();

        if (!empty($data['id'])) {
            $id = $data['id'];
            unset($data['id']);
            $db->update('activities')->set($data)->where([['id', $id]])->execute();
            return $id;
        } else {
            unset($data['id']);
            return $db->insert('activities', $data);
        }
    }

    /**
     * Remove activities
     */
    public static function remove($ids)
    {
        if (empty($ids)) {
            return 0;
        }

        static::createQuery()->delete('activities')->where([['id', $ids]])->execute();
        return count($ids);
    }

    /**
     * Update status
     */
    public static function updateStatus($ids, $status)
    {
        $valid = ['scheduled', 'completed', 'cancelled', 'no_show'];
        if (empty($ids) || !in_array($status, $valid)) {
            return 0;
        }

        static::createQuery()->update('activities')->set(['status' => $status])->where([['id', $ids]])->execute();
        return count($ids);
    }

    /**
     * Get recent activities
     */
    public static function recent($limit = 5)
    {
        return static::createQuery()
            ->select('A.id', 'A.type', 'A.subject', 'A.status', 'A.created_at', 'C.name AS customer_name')
            ->from('activities A')
            ->join('customers C', [['C.id', 'A.customer_id']], 'LEFT')
            ->orderBy('A.created_at', 'DESC')
            ->limit($limit)->execute()->fetchAll();
    }
}
