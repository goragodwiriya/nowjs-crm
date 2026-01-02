<?php
/**
 * @filesource modules/index/controllers/config.php
 *
 * Website Configuration Controller
 *
 * Returns website settings from self::$cfg for frontend use
 * Filters sensitive data (passwords, secrets, tokens) from public response
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 */

namespace Index\Config;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;

/**
 * Website Configuration Controller
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * List of keys to include in public response
     * Only these keys will be returned for unauthenticated requests
     */
    const PUBLIC_KEYS = [
        'web_title',
        'web_description',
        'login_message',
        'login_message_style',
        'login_header_color',
        'login_footer_color',
        'login_color',
        'login_bg_color',
        'user_register',
        'user_forgot',
        'telegram_bot_username',
        'facebook_appId',
        'google_client_id',
        'line_channel_id'
    ];

    /**
     * GET index/config/login
     * Get login configuration
     *
     * @param Request $request
     *
     * @return Response
     */
    public function login(Request $request)
    {
        // Validate HTTP method
        \Kotchasan\ApiController::validateMethod($request, 'GET');

        // Set cache headers (5 minutes) to reduce server load
        header('Cache-Control: public, max-age=300');
        header('Expires: '.gmdate('D, d M Y H:i:s', time() + 300).' GMT');

        $config = [];

        foreach (self::PUBLIC_KEYS as $key) {
            if (isset(self::$cfg->$key)) {
                $config[$key] = self::$cfg->$key;
            }
        }

        // Add logo URL if exists
        $img = DATA_FOLDER.'images/logo'.self::$cfg->stored_img_type;
        if (is_file(ROOT_PATH.$img)) {
            $config['logo'] = WEB_URL.$img;
        } else {
            $config['logo'] = WEB_URL.'images/logo.svg';
        }

        return $this->successResponse($config, 'Configuration loaded');
    }

    /**
     * GET index/config/theme
     * Get theme configuration
     *
     * @param Request $request
     *
     * @return Response
     */
    public function themeSettings(Request $request)
    {
        // Validate HTTP method
        \Kotchasan\ApiController::validateMethod($request, 'GET');

        // Set cache headers (5 minutes) to reduce server load
        header('Cache-Control: public, max-age=300');
        header('Expires: '.gmdate('D, d M Y H:i:s', time() + 300).' GMT');

        $config = self::$cfg->theme ?? [];

        foreach (['logo', 'bg_image'] as $key) {
            $img = DATA_FOLDER.'images/'.$key.self::$cfg->stored_img_type;
            if (file_exists(ROOT_PATH.$img)) {
                $config['--'.$key] = WEB_URL.$img;
            }
        }

        foreach (self::$cfg->color_status as $key => $value) {
            $config['--status'.$key] = $value;
        }

        return $this->successResponse([
            'variables' => $config
        ], 'Configuration loaded');
    }
}
