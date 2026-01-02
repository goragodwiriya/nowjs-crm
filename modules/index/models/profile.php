<?php
/**
 * @filesource modules/index/models/profile.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Index\Profile;

/**
 * API Profile Model
 *
 * Handles user table operations
 *`
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Get user by ID
     * $id = 0 return new (for Register)
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
                'username' => '',
                'name' => '',
                'phone' => '',
                'status' => 0,
                'social' => 'user',
                'active' => 1
            ];
        } else {
            return static::createQuery()
                ->select()
                ->from('user')
                ->where([['id', $id]])
                ->first();
        }
    }

    /**
     * Get details by ID with province info
     *
     * @param int $id
     *
     * @return object|null
     */
    public static function view($id)
    {
        return static::createQuery()
            ->select('U.*')
            ->from('user U')
            ->where([['U.id', $id]])
            ->first();
    }

    /**
     * Save data
     * Return ID
     *
     * @param \Kotchasan\DB $db Database connection
     * @param int $id User ID (0 for new user)
     * @param array $save Data to save
     *
     * @return int User ID
     */
    public static function save($db, $id, $save)
    {
        if ($id === 0) {
            return $db->insert('user', $save);
        } else {
            $db->update('user', [['id', $id]], $save);
            return $id;
        }
    }
}
