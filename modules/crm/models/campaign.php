<?php
/**
 * @filesource modules/crm/models/campaign.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 */

namespace Crm\Campaign;

/**
 * API Campaign Model
 *
 * CRM Campaign for production use
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Get campaign by ID
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
                'type' => 'email',
                'status' => 'draft',
                'budget' => 0,
                'actual_cost' => 0,
                'target_leads' => 0,
                'actual_leads' => 0,
                'target_revenue' => 0,
                'actual_revenue' => 0
            ];
        } else {
            return static::createQuery()
                ->select()
                ->from('campaigns')
                ->where([['id', $id]])
                ->first();
        }
    }

    /**
     * Save campaign data
     * Return campaign ID
     *
     * @param \Kotchasan\DB $db Database connection
     * @param int $id campaign ID (0 for new campaign)
     * @param array $save Data to save
     *
     * @return int campaign ID
     */
    public static function save($db, $id, $save)
    {
        if ($id === 0) {
            return $db->insert('campaigns', $save);
        } else {
            $db->update('campaigns', [['id', $id]], $save);
            return $id;
        }
    }
}
