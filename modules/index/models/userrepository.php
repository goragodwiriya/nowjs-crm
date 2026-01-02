<?php
/**
 * @filesource modules/index/models/userrepository.php
 *
 * User Repository - Data Access Layer
 * Handles all database operations for users
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 */

namespace Index\UserRepository;

/**
 * User Repository
 *
 * Data Access Layer for user operations
 * Separates database queries from business logic
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Create a new user
     *
     * @param array $data User data
     *
     * @return int User ID
     */
    public static function createUser(array $data)
    {
        return \Kotchasan\DB::create()->insert('user', $data);
    }

    /**
     * Find user by username
     *
     * @param string $username
     *
     * @return object|null
     */
    public static function findByUsername($username)
    {
        return \Kotchasan\DB::create()->first('user', [['username', $username]]);
    }

    /**
     * Check if field value is unique
     *
     * @param string $field Field name (username, phone, email)
     * @param mixed $value Field value
     * @param int $excludeId User ID to exclude from check
     *
     * @return bool True if unique
     */
    public static function isFieldUnique($field, $value, $excludeId = 0)
    {
        if (empty($value)) {
            return true;
        }

        $user = \Kotchasan\DB::create()->first('user', [[$field, $value]], ['id']);

        if ($excludeId > 0) {
            return $user ? $user->id == $excludeId : true;
        }

        return !$user;
    }
}
