<?php
/**
 * @filesource modules/index/models/users.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Index\Users;

/**
 * API Users Model
 *
 * Handles user table operations
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Get social icon
     *
     * @param int $social
     *
     * @return string
     */
    public static function getSocialIcon($social)
    {
        return self::$socialIcons[$social] ?? 'icon-user';
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
        if (isset($params['status']) && $params['status'] !== '') {
            $where[] = ['U.status', (int) $params['status']];
        }

        // Default query
        $query = static::createQuery()
            ->select(
                'U.id',
                'U.username',
                'U.name',
                'U.phone',
                'U.status',
                'U.active',
                'U.social',
                'U.create_date'
            )
            ->from('user U')
            ->where($where);

        // Search (OR condition)
        if (!empty($params['search'])) {
            $search = '%'.$params['search'].'%';
            $where = [
                ['U.name', 'LIKE', $search],
                ['U.username', 'LIKE', $search],
                ['U.phone', 'LIKE', $search]
            ];

            $query->where($where, 'OR');
        }

        return $query;
    }

    /**
     * Delete user
     * Return number of deleted users
     *
     * @param int|array $ids User ID or array of user IDs
     *
     * @return int
     */
    public static function remove($ids)
    {
        $remove_ids = [];
        // Delete file
        foreach ((array) $ids as $id) {
            if ($id == 1) {
                continue;
            }

            // The name of the folder where the files of the member you want to delete are stored.
            foreach (self::$cfg->member_images as $item => $value) {
                $img = ROOT_PATH.DATA_FOLDER.$item.'/'.$id.self::$cfg->stored_img_type;
                if (file_exists($img)) {
                    unlink($img);
                }
            }
            $remove_ids[] = $id;
        }

        if (empty($remove_ids)) {
            return 0;
        }

        // Remove user
        return \Kotchasan\DB::create()->delete('user', [['id', $remove_ids]]);
    }
}
