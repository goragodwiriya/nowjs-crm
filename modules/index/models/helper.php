<?php
/**
 * @filesource modules/index/models/helper.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Index\Helper;

use Kotchasan;

/**
 * Helper Model
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Get province options
     *
     * @return array
     */
    public static function getProvinceOptions()
    {
        $provinces = [];

        foreach (\Kotchasan\Province::all() as $id => $name) {
            $provinces[] = ['value' => (string) $id, 'text' => $name];
        }

        return $provinces;
    }

    /**
     * Get gender options
     *
     * @return array
     */
    public static function getGenderOptions()
    {
        return [
            ['value' => '', 'text' => 'Not specified'],
            ['value' => 'm', 'text' => 'Male'],
            ['value' => 'f', 'text' => 'Female']
        ];
    }

    /**
     * Get user status options
     *
     * @return array
     */
    public static function getUserStatusOptions()
    {
        $options = [];
        $memberStatus = self::$cfg->member_status ?? [0 => 'User', 1 => 'Admin'];

        foreach ($memberStatus as $key => $value) {
            $options[] = ['value' => (string) $key, 'text' => $value];
        }

        return $options;
    }

    /**
     * Get permission options
     *
     * @return array
     */
    public static function getPermissionOptions()
    {
        return [
            ['value' => 'can_config', 'text' => '{LNG_Can configure} {LNG_the system}'],
            ['value' => 'can_view_usage_history', 'text' => '{LNG_Can view} {LNG_system usage history}'],
            ['value' => 'can_view_crm', 'text' => '{LNG_Can view} CRM'],
            ['value' => 'can_manage_crm', 'text' => '{LNG_Can manage} CRM']
        ];
    }
}
