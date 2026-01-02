<?php
/**
 * FileBrowser API Endpoint
 * Standalone API handler with flexible configuration
 *
 * @author Goragod Wiriya
 * @version 2.0
 */

// Load configuration
$config = require __DIR__.'/config.php';

// Start session if using session-based auth
if ($config['authMode'] === 'session' || $config['session']['csrfEnabled']) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

// Set JSON headers
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token, Authorization');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Include the Files model
require_once __DIR__.'/models/files.php';

/**
 * Send JSON response
 */
function sendResponse($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send error response
 */
function sendError($message, $code = 400)
{
    sendResponse(['success' => false, 'error' => $message], $code);
}
/**
 * Parse JSON body if content type is JSON
 */
$jsonBody = null;
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (strpos($contentType, 'application/json') !== false) {
    $rawBody = file_get_contents('php://input');
    $jsonBody = json_decode($rawBody, true) ?: [];
}

/**
 * Get request parameter
 * Checks GET first, then JSON body, then POST
 */
function getParam($key, $default = null)
{
    global $jsonBody;

    // Check GET first (action is usually in URL)
    if (isset($_GET[$key])) {
        return $_GET[$key];
    }
    // Then check JSON body
    if ($jsonBody !== null && isset($jsonBody[$key])) {
        return $jsonBody[$key];
    }
    // Then check POST (form data)
    if (isset($_POST[$key])) {
        return $_POST[$key];
    }
    return $default;
}

/**
 * Check authentication based on config
 */
function checkAuth($config, $action)
{
    // Dev mode - skip auth
    if ($config['devMode']) {
        return true;
    }

    // Public operations
    if (in_array($action, $config['publicOperations'])) {
        return true;
    }

    switch ($config['authMode']) {
        case 'none':
            return true;

        case 'session':
            $session = $config['session'];

            // Check user session
            if ($session['userKey'] !== null) {
                if (empty($_SESSION[$session['userKey']])) {
                    return false;
                }
            }

            // Check permission if configured
            if ($session['permissionKey'] !== null && $session['permissionValue'] !== null) {
                $userPerm = $_SESSION[$session['permissionKey']] ?? null;
                if ($userPerm !== $session['permissionValue']) {
                    return false;
                }
            }

            // Check operation-specific permission
            if (isset($config['operationPermissions'][$action])) {
                $requiredPerm = $config['operationPermissions'][$action];
                $userPerm = $_SESSION[$session['permissionKey']] ?? null;
                if ($userPerm !== $requiredPerm) {
                    return false;
                }
            }

            return true;

        case 'token':
            $token = $config['token'];
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

            // Extract token from header
            if (!empty($token['prefix'])) {
                if (strpos($authHeader, $token['prefix']) !== 0) {
                    return false;
                }
                $tokenValue = substr($authHeader, strlen($token['prefix']));
            } else {
                $tokenValue = $authHeader;
            }

            if (empty($tokenValue)) {
                return false;
            }

            // Validate with callback
            if (is_callable($token['validator'])) {
                return $token['validator']($tokenValue);
            }

            // Validate against API keys
            if (!empty($token['apiKeys'])) {
                return in_array($tokenValue, $token['apiKeys']);
            }

            return false;

        case 'callback':
            if (is_callable($config['authCallback'])) {
                return $config['authCallback']();
            }
            return false;

        default:
            return false;
    }
}

/**
 * Check CSRF token for write operations
 */
function checkCsrf($config)
{
    // Dev mode - skip CSRF
    if ($config['devMode']) {
        return true;
    }

    // Custom CSRF callback
    if (is_callable($config['csrfCallback'])) {
        return $config['csrfCallback']();
    }

    // Session-based CSRF
    if ($config['authMode'] === 'session' && $config['session']['csrfEnabled']) {
        $csrfKey = $config['session']['csrfKey'];
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        $sessionToken = $_SESSION[$csrfKey] ?? '';

        // Allow if no session token set
        if (empty($sessionToken)) {
            return true;
        }

        return hash_equals($sessionToken, $headerToken);
    }

    return true;
}

// Get action
$action = getParam('action', '');
if (empty($action)) {
    sendError('Missing action parameter');
}

// Authentication check
if (!checkAuth($config, $action)) {
    sendError('Unauthorized', 401);
}

// Initialize Files model
try {
    $files = new FileBrowserFiles([
        'baseDir' => $config['baseDir'],
        'webUrl' => $config['webUrl'],
        'maxFileSize' => $config['maxFileSize'],
        'allowedExtensions' => $config['allowedExtensions'] ?? null
    ]);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}

// CSRF check for write operations
$writeActions = ['upload', 'create_folder', 'rename', 'delete', 'copy', 'move'];
if (in_array($action, $writeActions)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('Method not allowed', 405);
    }
    if (!checkCsrf($config)) {
        sendError('Invalid CSRF token', 403);
    }
}

// Route action
try {
    switch ($action) {
        case 'get_preset_categories':
            $result = [
                'success' => true,
                'data' => [
                    'categories' => $config['presetCategories']
                ]
            ];
            break;

        case 'get_presets':
            $category = getParam('category', '');
            $presetsPath = '/presets'.($category && $category !== 'all' ? '/'.$category : '');
            $presetsResult = $files->getFiles($presetsPath);
            if (!isset($presetsResult['error'])) {
                $result = [
                    'success' => true,
                    'data' => [
                        'files' => $presetsResult['items'] ?? [],
                        'path' => $presetsResult['path'] ?? $presetsPath
                    ]
                ];
            } else {
                // Return empty files if presets folder doesn't exist
                $result = [
                    'success' => true,
                    'data' => ['files' => [], 'path' => $presetsPath]
                ];
            }
            break;

        case 'get_files':
            $path = getParam('path', '/');
            $filesResult = $files->getFiles($path);
            if (!isset($filesResult['error'])) {
                $result = [
                    'success' => true,
                    'data' => [
                        'files' => $filesResult['items'] ?? [],
                        'path' => $filesResult['path'] ?? $path
                    ]
                ];
            } else {
                $result = $filesResult;
            }
            break;

        case 'get_folder_tree':
            $path = getParam('path', '/');
            $depth = (int) getParam('depth', 3);
            $result = ['success' => true, 'tree' => $files->getFolderTree($path, min($depth, 5))];
            break;

        case 'upload':
            $path = getParam('path', '/');
            // Check for file in common field names
            $uploadFile = null;
            if (isset($_FILES['file'])) {
                $uploadFile = $_FILES['file'];
            } elseif (isset($_FILES['files'])) {
                // Handle files[] array - take first file
                $uploadedFiles = $_FILES['files'];
                if (is_array($uploadedFiles['name'])) {
                    $uploadFile = [
                        'name' => $uploadedFiles['name'][0],
                        'type' => $uploadedFiles['type'][0],
                        'tmp_name' => $uploadedFiles['tmp_name'][0],
                        'error' => $uploadedFiles['error'][0],
                        'size' => $uploadedFiles['size'][0]
                    ];
                } else {
                    $uploadFile = $uploadedFiles;
                }
            }
            if (!$uploadFile) {
                sendError('No file uploaded');
            }
            $result = $files->upload($uploadFile, $path);
            break;

        case 'create_folder':
            $path = getParam('path', '/');
            $name = getParam('name', '');
            if (empty($name)) {
                sendError('Folder name required');
            }
            $result = $files->createFolder($path, $name);
            break;

        case 'rename':
            $path = getParam('path', '');
            $name = getParam('name', '');
            if (empty($path) || empty($name)) {
                sendError('Path and name required');
            }
            $result = $files->rename($path, $name);
            break;

        case 'delete':
            $path = getParam('path', '');
            if (empty($path)) {
                sendError('Path required');
            }
            $result = $files->delete($path);
            break;

        case 'copy':
            $source = getParam('source', '');
            $destination = getParam('destination', '');
            if (empty($source) || empty($destination)) {
                sendError('Source and destination required');
            }
            $result = $files->copy($source, $destination);
            break;

        case 'move':
            $source = getParam('source', '');
            $destination = getParam('destination', '');
            if (empty($source) || empty($destination)) {
                sendError('Source and destination required');
            }
            $result = $files->move($source, $destination);
            break;

        default:
            sendError('Unknown action: '.$action);
    }

    // Check for error in result
    if (isset($result['error'])) {
        sendError($result['error']);
    }

    sendResponse($result);
} catch (Exception $e) {
    sendError($e->getMessage(), 500);
}
