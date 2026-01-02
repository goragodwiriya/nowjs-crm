<?php
/**
 * @filesource modules/crm/models/deals.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Crm\Deals;

/**
 * API Deals Model
 *
 * CRM Deals with comprehensive metrics for production use
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
        // Filters (AND conditions)
        $where = [];
        if (!empty($params['stage'])) {
            $where[] = ['D.stage', $params['stage']];
        }

        // Default query
        $query = static::createQuery()
            ->select(
                'D.id',
                'D.title',
                'D.stage',
                'D.value',
                'D.probability',
                'D.expected_close_date',
                'D.created_at',
                'C.name AS customer',
                'U.name AS owner'
            )
            ->from('deals D')
            ->join('customers C', [['C.id', 'D.customer_id']], 'LEFT')
            ->join('user U', [['U.id', 'D.owner_id']], 'LEFT')
            ->where($where);

        // Search (OR condition)
        if (!empty($params['search'])) {
            $search = '%'.$params['search'].'%';
            $where = [
                ['D.title', 'LIKE', $search],
                ['C.name', 'LIKE', $search]
            ];

            $query->where($where, 'OR');
        }

        return $query;
    }
    /**
     * Remove deals
     *
     * @param array $ids Deal IDs to remove
     *
     * @return int Number of removed deals
     */
    public static function remove($ids)
    {
        if (empty($ids)) {
            return 0;
        }

        return \Kotchasan\DB::create()->delete('deals', [['id', $ids]]);
    }

    /**
     * Update deal stage
     *
     * @param array $ids Deal ID
     * @param string $stage New stage
     *
     * @return int Number of updated deals
     */
    public static function updateStage($ids, $stage)
    {
        return \Kotchasan\DB::create()->update('deals', [['id', $ids]], ['stage' => $stage]);
    }

    /**
     * Recent won deals
     *
     * @param int $limit Number of recent won deals to retrieve
     *
     * @return array Deals data with all metrics
     */
    public static function won($limit = 5)
    {
        return static::createQuery()
            ->select(
                'D.title',
                'D.value',
                'C.name AS customer_name',
                'U.name AS owner_name'
            )
            ->from('deals D')
            ->join('customers C', [['C.id', 'D.customer_id']])
            ->join('user U', [['U.id', 'D.owner_id']], 'LEFT')
            ->where([['D.stage', 'won']])
            ->orderBy('D.actual_close_date', 'DESC')
            ->limit($limit)
            ->execute()
            ->fetchAll();
    }

    /**
     * Get recent CRM Deals data
     *
     * @param int $limit Number of recent deals to retrieve
     *
     * @return array Deals data with all metrics
     */
    public static function recent($limit = 5)
    {
        return static::createQuery()
            ->select('D.title', 'C.name customer_name', 'D.value', 'D.stage')
            ->from('deals D')
            ->join('customers C', [['C.id', 'D.customer_id']], 'INNER')
            ->orderBy('D.created_at', 'DESC')
            ->limit($limit)
            ->cacheOn()
            ->execute()
            ->fetchAll();
    }

    /**
     * Get deal options for dropdowns
     *
     * @return array
     */
    public static function getDealOptions()
    {
        return static::createQuery()
            ->select('id value', 'title text')
            ->from('deals')
            ->where([['stage', '!=', 'lost']])
            ->orderBy('title')
            ->execute()
            ->fetchAll();
    }
}
