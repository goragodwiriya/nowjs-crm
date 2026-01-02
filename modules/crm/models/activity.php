<?php
/**
 * @filesource modules/crm/models/activity.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 */

namespace Crm\Activity;

/**
 * API Activity Model
 *
 * CRM Activity for production use
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Get activity by ID
     *
     * @param (int) $id
     *
     * @return object|null
     */
    public static function get($id)
    {
        if ($id === 0) {
            return (object) [
                'id' => 0,
                'type' => 'call',
                'status' => 'scheduled',
                'priority' => 'medium',
                'duration_minutes' => 30
            ];
        } else {
            return static::createQuery()
                ->select()
                ->from('activities')
                ->where([['id', $id]])
                ->first();
        }
    }

    /**
     * Save activity data
     * Return activity ID
     *
     * @param \Kotchasan\DB $db Database connection
     * @param int $id activity ID (0 for new activity)
     * @param array $save Data to save
     *
     * @return int activity ID
     */
    public static function save($db, $id, $save)
    {
        if ($id === 0) {
            return $db->insert('activities', $save);
        } else {
            $db->update('activities', [['id', $id]], $save);
            return $id;
        }
    }
}
