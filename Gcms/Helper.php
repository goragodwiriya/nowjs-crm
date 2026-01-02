<?php
/**
 * @filesource Gcms/Helper.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Gcms;

use Kotchasan\Language;

/**
 * Helper class
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Helper extends \Kotchasan\KBase
{
    /**
     * คืนค่า Label ของ Username ตามการตั้งค่า
     *
     * @param string $separator
     *
     * @return string
     */
    public static function usernameLabel($separator = '/')
    {
        $fields = [];
        foreach (['username', 'email'] as $item) {
            if (in_array($item, self::$cfg->login_fields)) {
                $fields[] = Language::get($item);
            }
        }
        return implode($separator, $fields);
    }
}
