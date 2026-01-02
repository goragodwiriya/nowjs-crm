<?php
/**
 * @filesource modules/crm/models/campaigns.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 */

namespace Crm\Campaigns;

/**
 * API Campaigns Model
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
            ->select('C.*', 'U.name AS owner')
            ->from('campaigns C')
            ->join('user U', [['U.id', 'C.owner_id']], 'LEFT');

        // Filters (AND conditions)
        $where = [];
        if (!empty($params['status'])) {
            $where[] = ['C.status', $params['status']];
        }
        if (!empty($params['type'])) {
            $where[] = ['C.type', $params['type']];
        }

        if (!empty($where)) {
            $query->where($where);
        }

        // Search (OR condition)
        if (!empty($params['search'])) {
            $search = '%'.$params['search'].'%';
            $orWhere = [
                ['C.name', 'LIKE', $search],
                ['C.description', 'LIKE', $search]
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
        $owners = static::createQuery()
            ->select('id', 'name')->from('user')
            ->where([['active', 1]])
            ->orderBy('name')->cacheOn()->execute()->fetchAll();

        return ['owners' => $owners];
    }

    /**
     * Get campaign by ID
     */
    public static function get($id)
    {
        return static::createQuery()
            ->select('*')->from('campaigns')
            ->where([['id', $id]])
            ->execute()->fetch();
    }

    /**
     * Save campaign
     */
    public static function save($data)
    {
        $db = static::createQuery();

        if (!empty($data['id'])) {
            $id = $data['id'];
            unset($data['id']);
            $db->update('campaigns')->set($data)->where([['id', $id]])->execute();
            return $id;
        } else {
            unset($data['id']);
            return $db->insert('campaigns', $data);
        }
    }

    /**
     * Remove campaigns
     */
    public static function remove($ids)
    {
        if (empty($ids)) {
            return 0;
        }

        static::createQuery()->delete('campaigns')->where([['id', $ids]])->execute();
        return count($ids);
    }

    /**
     * Update status
     */
    public static function updateStatus($ids, $status)
    {
        $valid = ['draft', 'scheduled', 'active', 'paused', 'completed', 'cancelled'];
        if (empty($ids) || !in_array($status, $valid)) {
            return 0;
        }

        static::createQuery()->update('campaigns')->set(['status' => $status])->where([['id', $ids]])->execute();
        return count($ids);
    }
}
