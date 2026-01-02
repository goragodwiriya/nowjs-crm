<?php
/**
 * @filesource modules/crm/models/users.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Crm\Users;

/**
 * users models
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * list of owners
     *
     * @return array
     */
    public static function getOwnerOptions()
    {
        return static::createQuery()
            ->select('id value', 'name text')
            ->from('user')
            ->where([
                ['active', 1]
            ])
            ->execute()
            ->fetchAll();
    }
}
