<?php
/**
 * @filesource modules/crm/models/contact.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Crm\Contact;

/**
 * API Contact Model
 *
 * CRM Contact with comprehensive metrics for production use
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Get contact by ID
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
                'status' => 'active',
                'is_primary' => 0,
                'is_decision_maker' => 0
            ];
        } else {
            return static::createQuery()
                ->select()
                ->from('contacts')
                ->where([['id', $id]])
                ->first();
        }
    }

    /**
     * Save contact data
     * Return contact ID
     *
     * @param \Kotchasan\DB $db Database connection
     * @param int $id contact ID (0 for new contact)
     * @param array $save Data to save
     *
     * @return int contact ID
     */
    public static function save($db, $id, $save)
    {
        if ($id === 0) {
            return $db->insert('contacts', $save);
        } else {
            $db->update('contacts', [['id', $id]], $save);
            return $id;
        }
    }
}
