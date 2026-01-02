<?php
/**
 * FileBrowser Configuration
 * Customize authentication, paths, and permissions
 *
 * @author Goragod Wiriya
 * @version 2.0
 */

// ============================================
// AUTO-DETECT PATHS
// ============================================

// Get the directory where this config file is located
$phpDir = dirname(__FILE__);

// Auto-detect base path (4 levels up from php folder to reach project root)
// php -> editor -> components -> js -> [project_root]
$projectRoot = dirname($phpDir, 4);

// Auto-detect web path from script
// Script: /Now/js/components/editor/php/filebrowser.php
// We need: /Now (project web root)
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
// Remove /js/components/editor/php/filebrowser.php to get project web root
$webProjectRoot = preg_replace('#/js/components/editor/php/[^/]+\.php$#', '', $scriptPath);

// ============================================
// FILE STORAGE CONFIGURATION
// ============================================
return [
    /**
     * Base directory for file storage (absolute path)
     * Auto-detected: [project_root]/data/uploads
     */
    'baseDir' => $projectRoot.'/data/uploads',

    /**
     * Web URL prefix for accessing files
     * Auto-detected from script path
     */
    'webUrl' => $webProjectRoot.'/data/uploads',

    /**
     * Maximum file size in bytes
     * Default: 10MB
     */
    'maxFileSize' => 10 * 1024 * 1024,

    /**
     * Allowed file extensions (whitelist)
     */
    'allowedExtensions' => [
        // Images
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico',
        // Documents
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'txt', 'rtf', 'csv',
        // Archives
        'zip', 'rar', '7z',
        // Media
        'mp3', 'mp4', 'webm', 'ogg'
    ],

    // ============================================
    // AUTHENTICATION CONFIGURATION
    // ============================================

    /**
     * Authentication mode
     * Options:
     *   'none'     - No authentication (NOT recommended for production)
     *   'session'  - PHP session-based authentication
     *   'token'    - Bearer token authentication
     *   'callback' - Custom callback function
     */
    'authMode' => 'session',

    /**
     * Session-based authentication settings
     * Used when authMode = 'session'
     */
    'session' => [
        // Session key to check for logged in user
        // Set to null to allow all users
        'userKey' => 'login',

        // Optional: Require specific permission/role
        // Set to null to skip permission check
        'permissionKey' => null,

        // Optional: Required permission value
        'permissionValue' => null,

        // CSRF token session key
        'csrfKey' => 'token',

        // Enable CSRF validation for POST requests
        'csrfEnabled' => true
    ],

    /**
     * Token-based authentication settings
     * Used when authMode = 'token'
     */
    'token' => [
        // Header name for token
        'headerName' => 'Authorization',

        // Token prefix (e.g., 'Bearer ')
        'prefix' => 'Bearer ',

        // Validation callback - receives token, returns true if valid
        // Example: function($token) { return validateJWT($token); }
        'validator' => null,

        // Alternative: List of valid API keys
        'apiKeys' => []
    ],

    /**
     * Custom authentication callback
     * Used when authMode = 'callback'
     *
     * Function signature: function(): bool
     * Return true if authenticated, false otherwise
     *
     * Example:
     *   'authCallback' => function() {
     *       return MyAuth::isLoggedIn() && MyAuth::hasPermission('file_manager');
     *   }
     */
    'authCallback' => null,

    /**
     * CSRF validation callback (for POST requests)
     * Used when authMode = 'callback' or to override default CSRF check
     *
     * Function signature: function(): bool
     * Return true if valid, false otherwise
     *
     * Example:
     *   'csrfCallback' => function() {
     *       $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
     *       return hash_equals($_SESSION['csrf_token'], $token);
     *   }
     */
    'csrfCallback' => null,

    // ============================================
    // PERMISSION SETTINGS
    // ============================================

    /**
     * Operations that require authentication
     * Set to empty array to require auth for all operations
     */
    'publicOperations' => [],

    /**
     * Operations that require special permissions
     * Map operation name to required permission
     * Only used when session.permissionKey is set
     */
    'operationPermissions' => [
        // 'delete' => 'admin',
        // 'rename' => 'editor',
    ],

    // ============================================
    // PRESET CATEGORIES
    // ============================================

    /**
     * Preset file categories
     * These appear in the "Prepared files" tab
     */
    'presetCategories' => [
        ['id' => 'images', 'name' => 'Images', 'icon' => 'icon-image'],
        ['id' => 'documents', 'name' => 'Documents', 'icon' => 'icon-file'],
        ['id' => 'media', 'name' => 'Media', 'icon' => 'icon-video']
    ],

    // ============================================
    // DEVELOPMENT / DEBUG
    // ============================================

    /**
     * Development mode
     * When true, allows unauthenticated access (for testing only!)
     * WARNING: Set to false in production!
     */
    'devMode' => true,

    /**
     * Enable debug logging
     */
    'debug' => false
];
