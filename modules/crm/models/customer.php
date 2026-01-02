<?php
/**
 * @filesource modules/crm/models/customer.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Crm\Customer;

/**
 * API Customer Model
 *
 * CRM Customer with comprehensive metrics for production use
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Get customer by ID
     *
     * @param (int) $id
     *
     * @return object|null
     */
    public static function get($id)
    {
        if ($id === 0) {
            return (object) [
                'id' => 0
            ];
        } else {
            return static::createQuery()
                ->select()
                ->from('customers')
                ->where([['id', $id]])
                ->first();
        }
    }

    /**
     * Save customer data
     * Return customer ID
     *
     * @param \Kotchasan\DB $db Database connection
     * @param int $id customer ID (0 for new customer)
     * @param array $save Data to save
     *
     * @return int customer ID
     */
    public static function save($db, $id, $save)
    {
        if ($id === 0) {
            return $db->insert('customers', $save);
        } else {
            $db->update('customers', [['id', $id]], $save);
            return $id;
        }
    }
}
