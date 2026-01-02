<?php
/**
 * @filesource modules/index/controllers/menus.php
 *
 * Production-grade API Authentication Controller
 *
 * Security Features:
 * - Rate limiting with exponential backoff
 * - Secure password verification (timing-safe comparison)
 * - CSRF protection
 * - httpOnly cookies for tokens
 * - Activity logging
 * - Input sanitization
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 */

namespace Index\Menus;

use Gcms\Api as ApiController;

/**
 * API Authentication Controller
 *
 * Handles user authentication endpoints with production-grade security
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Kotchasan\KBase
{
    /**
     * Get menu data
     *
     * @param Object $login
     *
     * @return array
     */
    public static function getMenus($login)
    {
        // Menu data - 2-level nested structure
        $menus = [
            [
                'title' => 'Dashboard',
                'url' => '/',
                'icon' => 'icon-dashboard'
            ],
            [
                'title' => 'CRM',
                'icon' => 'icon-support',
                'children' => [
                    [
                        'title' => 'Pipeline',
                        'url' => '/pipeline',
                        'icon' => 'icon-grid'
                    ],
                    [
                        'title' => 'Deals',
                        'url' => '/deals',
                        'icon' => 'icon-wallet'
                    ],
                    [
                        'title' => 'Customers',
                        'url' => '/customers',
                        'icon' => 'icon-customer'
                    ],
                    [
                        'title' => 'Contacts',
                        'url' => '/contacts',
                        'icon' => 'icon-phone'
                    ],
                    [
                        'title' => 'Activities',
                        'url' => '/activities',
                        'icon' => 'icon-event'
                    ],
                    [
                        'title' => 'Campaigns',
                        'url' => '/campaigns',
                        'icon' => 'icon-flag'
                    ]
                ]
            ]
        ];

        // Add admin menus if user is admin
        if (ApiController::hasPermission($login, 'can_config')) {
            $submenus = [
                [
                    'title' => 'General Settings',
                    'url' => '/general-settings',
                    'icon' => 'icon-cog'
                ],
                [
                    'title' => 'Theme Settings',
                    'url' => '/theme-settings',
                    'icon' => 'icon-brush'
                ],
                [
                    'title' => 'Email Settings',
                    'url' => '/email-settings',
                    'icon' => 'icon-email'
                ],
                [
                    'title' => 'Cookie Policy',
                    'url' => '/cookie-policy',
                    'icon' => 'icon-verfied'
                ],
                [
                    'title' => 'Manage languages',
                    'url' => '/languages',
                    'icon' => 'icon-language'
                ]
            ];

            $adminMenu = ApiController::isAdmin($login);
            $adminMenu &= ApiController::isNotDemoMode($login);
            if ($adminMenu || ApiController::isSuperAdmin($login)) {
                $menus[] = [
                    'title' => 'Users',
                    'url' => '/users',
                    'icon' => 'icon-users'
                ];
                $submenus[] = [
                    'title' => 'Member status',
                    'url' => '/user-status',
                    'icon' => 'icon-star0'
                ];
                $submenus[] = [
                    'title' => 'Permissions',
                    'url' => '/permission',
                    'icon' => 'icon-list'
                ];
                $submenus[] = [
                    'title' => 'API Settings',
                    'url' => '/api-settings',
                    'icon' => 'icon-host'
                ];
                $submenus[] = [
                    'title' => 'LINE Settings',
                    'url' => '/line-settings',
                    'icon' => 'icon-line'
                ];
                $submenus[] = [
                    'title' => 'Telegram Settings',
                    'url' => '/telegram-settings',
                    'icon' => 'icon-telegram'
                ];
                $submenus[] = [
                    'title' => 'SMS Settings',
                    'url' => '/sms-settings',
                    'icon' => 'icon-mobile'
                ];
            }
            $submenus[] = [
                'title' => 'Usage history',
                'url' => '/usage',
                'icon' => 'icon-report'
            ];
            $menus[] = [
                'title' => 'Settings',
                'icon' => 'icon-settings',
                'children' => $submenus
            ];
        }

        return $menus;
    }
}
