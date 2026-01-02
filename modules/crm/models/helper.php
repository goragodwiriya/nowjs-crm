<?php
/**
 * @filesource modules/crm/models/helper.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Crm\Helper;

use Kotchasan;

/**
 * API v1 Users Controller - Mock Data Only
 *
 * Handles user management endpoints with 100 mock users
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
        return \Kotchasan\Province::getOptions();
    }

    /**
     * Get customer status options
     *
     * @return array
     */
    public static function getCustomerStatusOptions()
    {
        return [
            ['value' => 'lead', 'text' => 'Lead'],
            ['value' => 'prospect', 'text' => 'Prospect'],
            ['value' => 'customer', 'text' => 'Customer'],
            ['value' => 'inactive', 'text' => 'Inactive'],
            ['value' => 'churned', 'text' => 'Churned']
        ];
    }

    /**
     * Get customer source options
     *
     * @return array
     */
    public static function getCustomerSourceOptions()
    {
        return [
            ['value' => 'website', 'text' => 'Website'],
            ['value' => 'referral', 'text' => 'Referral'],
            ['value' => 'cold_call', 'text' => 'Cold Call'],
            ['value' => 'advertisement', 'text' => 'Advertisement'],
            ['value' => 'trade_show', 'text' => 'Trade Show'],
            ['value' => 'social_media', 'text' => 'Social Media'],
            ['value' => 'other', 'text' => 'Other']
        ];
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
     * Get social login options
     *
     * @return array
     */
    public static function getSocialOptions()
    {
        return [
            ['value' => '0', 'text' => 'Registered'],
            ['value' => '1', 'text' => 'Facebook'],
            ['value' => '2', 'text' => 'Google'],
            ['value' => '3', 'text' => 'LINE'],
            ['value' => '4', 'text' => 'Telegram']
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
        foreach (self::$cfg->member_status as $key => $value) {
            $options[] = ['value' => $key, 'text' => $value];
        }
        return $options;
    }
}
