<?php
/**
 * @filesource modules/crm/models/deal.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Crm\Deal;

/**
 * API v1 Deals Model
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
     * Get deal by ID
     * $id = 0 return new deal (for Create)
     *
     * @param int $id
     *
     * @return object|null
     */
    public static function get($id)
    {
        if ($id === 0) {
            return (object) [
                'id' => 0,
                'pipeline_id' => 1,
                'stage_id' => 1,
                'title' => '',
                'description' => '',
                'value' => 0,
                'currency' => 'THB',
                'status' => 'open',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
        } else {
            return static::createQuery()
                ->select()
                ->from('deals')
                ->where([['id', $id]])
                ->first();
        }
    }

    /**
     * Save deal data
     * Return deal ID
     *
     * @param \Kotchasan\DB $db Database connection
     * @param int $id deal ID (0 for new deal)
     * @param array $save Data to save
     *
     * @return int deal ID
     */
    public static function save($db, $id, $save)
    {
        if ($id === 0) {
            return $db->insert('deals', $save);
        } else {
            $db->update('deals', [['id', $id]], $save);
            return $id;
        }
    }
}
