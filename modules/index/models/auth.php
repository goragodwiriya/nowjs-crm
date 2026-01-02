<?php
/**
 * @filesource modules/index/models/auth.php
 *
 * Authentication Model - Production-grade security
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 */

namespace Index\Auth;

use Kotchasan\Database\Sql;

/**
 * Authentication Model
 *
 * Handles secure authentication with database
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Maximum login attempts before lockout
     */
    const MAX_LOGIN_ATTEMPTS = 5;

    /**
     * Lockout duration in seconds (30 minutes)
     */
    const LOCKOUT_DURATION = 1800;

    /**
     * Token expiry time in seconds (24 hours)
     */
    const TOKEN_EXPIRY = 86400;

    /**
     * Refresh token expiry time in seconds (7 days)
     */
    const REFRESH_TOKEN_EXPIRY = 604800;

    /**
     * Authenticate user with username/email and password
     *
     * @param string $username Username or email
     * @param string $password Plain text password
     * @param string $clientIp Client IP for logging
     *
     * @return array Authentication result
     */
    public static function authenticate($username, $password, $clientIp = null)
    {
        // Sanitize input
        $username = trim($username);

        // Check for empty credentials
        if (empty($username) || empty($password)) {
            return [
                'success' => false,
                'message' => 'Username and password are required.',
                'code' => 'INVALID_INPUT'
            ];
        }

        // Check rate limiting
        $rateLimitResult = self::checkRateLimit($username, $clientIp);
        if (!$rateLimitResult['allowed']) {
            return [
                'success' => false,
                'message' => $rateLimitResult['message'],
                'code' => 'RATE_LIMITED',
                'retry_after' => $rateLimitResult['retry_after']
            ];
        }

        // Query user from database
        $user = self::findUserByCredentials($username);

        if (!$user) {
            // Record failed attempt
            self::recordLoginAttempt($username, $clientIp, false);

            // Generic error message to prevent user enumeration
            return [
                'success' => false,
                'message' => 'Invalid username or password',
                'code' => 'AUTH_FAILED'
            ];
        }

        // Verify password
        if (!self::verifyPassword($password, $user->password, $user->salt)) {
            // Record failed attempt
            self::recordLoginAttempt($username, $clientIp, false);

            return [
                'success' => false,
                'message' => 'Invalid username or password',
                'code' => 'AUTH_FAILED'
            ];
        }

        // Check if account is active
        if ($user->active != 1 && $user->id != 1) {
            return [
                'success' => false,
                'message' => 'Your account is not active',
                'code' => 'ACCOUNT_INACTIVE'
            ];
        }

        // Check if email verification is pending
        if (!empty($user->activatecode) && $user->id != 1) {
            return [
                'success' => false,
                'message' => 'Please verify your email address first',
                'code' => 'EMAIL_NOT_VERIFIED'
            ];
        }

        // Record successful login
        self::recordLoginAttempt($username, $clientIp, true);

        // Generate tokens
        $tokens = self::generateTokens($user->id);

        // Update user record with new token
        self::updateUserToken($user->id, $tokens['access_token'], $tokens['expires_at']);

        // Log successful login
        self::logLoginActivity($user->id, 'LOGIN_SUCCESS', $clientIp);

        // Prepare user data (exclude sensitive fields)
        $userData = self::sanitizeUserData($user);

        return [
            'success' => true,
            'message' => 'Login successful',
            'user' => $userData,
            'token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in' => self::TOKEN_EXPIRY,
            'token_type' => 'Bearer'
        ];
    }

    /**
     * Find user by username or email
     *
     * @param string $username
     *
     * @return object|null
     */
    public static function findUserByCredentials($username)
    {
        // Build WHERE conditions based on login fields
        $where = [];
        $loginFields = self::$cfg->login_fields ?? ['username'];

        foreach ($loginFields as $field) {
            $fieldName = ($field === 'email' || $field === 'username') ? 'username' : $field;
            $where[$fieldName] = ['U.'.$fieldName, $username];
        }

        $user = static::createQuery()
            ->select('U.*', Sql::GROUP_CONCAT(['D.name', '|', 'D.value'], 'metas', ','))
            ->from('user U')
            ->join('user_meta D', [['D.member_id', Sql::column('U.id')]], 'LEFT')
            ->where([['U.username', '!=', '']])
            ->where(array_values($where), 'OR')
            ->groupBy('U.id')
            ->first();

        if ($user) {
            $user->permission = self::parsePermission($user->permission);
            $user->metas = self::parseMeta($user->metas);
        }

        return $user;
    }

    /**
     * Verify password against stored hash
     *
     * @param string $password Plain text password
     * @param string $hash Stored password hash
     * @param string $salt User's salt
     *
     * @return bool
     */
    public static function verifyPassword($password, $hash, $salt)
    {
        $passwordKey = self::$cfg->password_key ?? '';
        $computedHash = sha1($passwordKey.$password.$salt);

        return hash_equals($hash, $computedHash);
    }

    /**
     * Hash password for storage
     *
     * @param string $password Plain text password
     * @param string|null $salt Optional salt (generates new one if not provided)
     *
     * @return array ['hash' => string, 'salt' => string]
     */
    public static function hashPassword($password, $salt = null)
    {
        $salt = $salt ?? \Kotchasan\Password::uniqid();
        $passwordKey = self::$cfg->password_key ?? '';
        $hash = sha1($passwordKey.$password.$salt);

        return [
            'hash' => $hash,
            'salt' => $salt
        ];
    }

    /**
     * Set authentication cookie
     *
     * @param string $name Cookie name
     * @param string $value Cookie value
     */
    public static function setCookie($name, $value)
    {
        $options = [
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'expires' => time() + self::TOKEN_EXPIRY
        ];

        setcookie($name, $value, $options);
    }

    /**
     * Generate access and refresh tokens
     *
     * @param int $userId
     *
     * @return array
     */
    public static function generateTokens($userId)
    {
        $now = time();

        // Generate cryptographically secure tokens
        $accessToken = self::generateSecureToken($userId, $now, self::TOKEN_EXPIRY);
        $refreshToken = self::generateSecureToken($userId, $now, self::REFRESH_TOKEN_EXPIRY, 'refresh');

        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_at' => $now + self::TOKEN_EXPIRY,
            'refresh_expires_at' => $now + self::REFRESH_TOKEN_EXPIRY
        ];
    }

    /**
     * Generate a secure token
     *
     * @param int $userId
     * @param int $timestamp
     * @param int $expiry
     * @param string $type
     *
     * @return string
     */
    private static function generateSecureToken($userId, $timestamp, $expiry, $type = 'access')
    {
        // Create payload
        $payload = [
            'sub' => $userId,
            'iat' => $timestamp,
            'exp' => $timestamp + $expiry,
            'type' => $type,
            'jti' => bin2hex(random_bytes(16))
        ];

        // Encode payload
        $payloadJson = json_encode($payload);
        $payloadBase64 = rtrim(strtr(base64_encode($payloadJson), '+/', '-_'), '=');

        // Create signature
        $secret = self::getTokenSecret();
        $signature = hash_hmac('sha256', $payloadBase64, $secret);

        // Return token
        return $payloadBase64.'.'.$signature;
    }

    /**
     * Verify and decode a token
     *
     * @param string $token
     *
     * @return array|null Returns decoded payload or null if invalid
     */
    public static function verifyToken($token)
    {
        if (empty($token)) {
            return null;
        }

        // Split token
        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        list($payloadBase64, $signature) = $parts;

        // Verify signature
        $secret = self::getTokenSecret();
        $expectedSignature = hash_hmac('sha256', $payloadBase64, $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }

        // Decode payload
        $payloadJson = base64_decode(strtr($payloadBase64, '-_', '+/'));
        $payload = json_decode($payloadJson, true);

        if (!$payload || !isset($payload['exp']) || !isset($payload['sub'])) {
            return null;
        }

        // Check expiration
        if ($payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }

    /**
     * Get token secret from config
     *
     * @return string
     */
    private static function getTokenSecret()
    {
        // Try to get from config
        $secret = self::$cfg->jwt_secret ?? self::$cfg->password_key ?? null;

        if (empty($secret)) {
            // Generate and store if not exists
            $secret = bin2hex(random_bytes(32));
            // In production, this should be stored in config
        }

        return $secret;
    }

    /**
     * Update user token in database
     *
     * @param int $userId
     * @param string $token
     * @param int $expiresAt Unix timestamp
     */
    public static function updateUserToken($userId, $token, $expiresAt)
    {
        // Convert timestamp to datetime format for database
        $expiresDateTime = date('Y-m-d H:i:s', $expiresAt);

        \Kotchasan\DB::create()->update('user', [['id', $userId]], [
            'token' => $token,
            'token_expires' => $expiresDateTime,
            'visited' => Sql::create('`visited` + 1')
        ]);
    }

    /**
     * Get user by token
     *
     * @param string $token
     *
     * @return object|null
     */
    public static function getUserByToken($token)
    {
        // First verify the token structure
        $payload = self::verifyToken($token);
        if (!$payload) {
            return null;
        }

        $userId = $payload['sub'];
        $currentDateTime = date('Y-m-d H:i:s');

        // Get user from database
        $user = static::createQuery()
            ->select('U.*', Sql::GROUP_CONCAT(['D.name', '|', 'D.value'], 'metas'))
            ->from('user U')
            ->join('user_meta D', [['D.member_id', Sql::column('U.id')]], 'LEFT')
            ->where([
                ['U.id', $userId],
                ['U.token', $token],
                ['U.token_expires', '>=', $currentDateTime]
            ])
            ->groupBy('U.id')
            ->first();

        if ($user) {
            $user->permission = self::parsePermission($user->permission);
            $user->metas = self::parseMeta($user->metas);
        }

        return $user;
    }

    /**
     * Get user by ID
     *
     * @param int $userId
     *
     * @return object|null
     */
    public static function getUserById($userId)
    {
        $user = static::createQuery()
            ->select('U.*', Sql::GROUP_CONCAT(['D.name', '|', 'D.value'], 'metas', ','))
            ->from('user U')
            ->join('user_meta D', [['D.member_id', Sql::column('U.id')]], 'LEFT')
            ->where([['U.id', $userId]])
            ->groupBy('U.id')
            ->first();

        if ($user) {
            $user->permission = self::parsePermission($user->permission);
            $user->metas = self::parseMeta($user->metas);
        }

        return $user;
    }

    /**
     * Refresh access token
     *
     * @param string $refreshToken
     *
     * @return array
     */
    public static function refreshAccessToken($refreshToken)
    {
        // Verify refresh token
        $payload = self::verifyToken($refreshToken);

        if (!$payload || ($payload['type'] ?? '') !== 'refresh') {
            return [
                'success' => false,
                'message' => 'Invalid refresh token',
                'code' => 'INVALID_TOKEN'
            ];
        }

        $userId = $payload['sub'];

        // Get user
        $user = self::getUserById($userId);
        if (!$user || $user->active != 1) {
            return [
                'success' => false,
                'message' => 'No data available or inactive',
                'code' => 'USER_INVALID'
            ];
        }

        // Generate new tokens
        $tokens = self::generateTokens($userId);

        // Update user record
        self::updateUserToken($userId, $tokens['access_token'], $tokens['expires_at']);

        return [
            'success' => true,
            'token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in' => self::TOKEN_EXPIRY,
            'token_type' => 'Bearer',
            'user' => self::sanitizeUserData($user)
        ];
    }

    /**
     * Logout user
     *
     * @param string $token
     * @param int|null $userId
     *
     * @return bool
     */
    public static function logout($token, $userId = null)
    {
        $where = [];
        if ($userId) {
            $where[] = ['id', $userId];
        }
        if ($token) {
            $where[] = ['token', $token];
        }
        if (!empty($where)) {
            // Clear token in database
            \Kotchasan\Model::createQuery()
                ->update('user')
                ->where($where, count($where) == 1 ? 'AND' : 'OR')
                ->set([
                    'token' => null,
                    'token_expires' => null
                ])->execute();

            // Log logout
            self::logLoginActivity($userId, 'Logout', null);
        }

        return true;
    }

    /**
     * Check rate limiting for login attempts
     *
     * @param string $username
     * @param string|null $clientIp
     *
     * @return array
     */
    public static function checkRateLimit($username, $clientIp = null)
    {
        $key = self::getRateLimitKey($username, $clientIp);
        $now = time();

        // Check from cache/session
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        $attempts = $_SESSION['login_attempts'][$key] ?? null;

        if ($attempts) {
            // Check if locked out
            if ($attempts['locked_until'] && $attempts['locked_until'] > $now) {
                $retryAfter = $attempts['locked_until'] - $now;
                return [
                    'allowed' => false,
                    'message' => "Too many login attempts. Please try again in ".ceil($retryAfter / 60)." minutes.",
                    'retry_after' => $retryAfter
                ];
            }

            // Reset if lockout expired
            if ($attempts['locked_until'] && $attempts['locked_until'] <= $now) {
                unset($_SESSION['login_attempts'][$key]);
            }
        }

        return ['allowed' => true];
    }

    /**
     * Record login attempt for rate limiting
     *
     * @param string $username
     * @param string|null $clientIp
     * @param bool $success
     */
    public static function recordLoginAttempt($username, $clientIp, $success)
    {
        $key = self::getRateLimitKey($username, $clientIp);
        $now = time();

        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }

        if ($success) {
            // Clear attempts on successful login
            unset($_SESSION['login_attempts'][$key]);
            return;
        }

        // Initialize or update attempts
        if (!isset($_SESSION['login_attempts'][$key])) {
            $_SESSION['login_attempts'][$key] = [
                'count' => 0,
                'first_attempt' => $now,
                'locked_until' => null
            ];
        }

        $attempts = &$_SESSION['login_attempts'][$key];
        $attempts['count']++;
        $attempts['last_attempt'] = $now;

        // Check if should lock
        if ($attempts['count'] >= self::MAX_LOGIN_ATTEMPTS) {
            $attempts['locked_until'] = $now + self::LOCKOUT_DURATION;
        }
    }

    /**
     * Get rate limit key
     *
     * @param string $username
     * @param string|null $clientIp
     *
     * @return string
     */
    private static function getRateLimitKey($username, $clientIp)
    {
        // Use both username and IP for more granular control
        return md5($username.':'.($clientIp ?? 'unknown'));
    }

    /**
     * Log login activity
     *
     * @param int $userId
     * @param string $action
     * @param string|null $clientIp
     */
    public static function logLoginActivity($userId, $action, $clientIp)
    {
        try {
            \Index\Log\Model::add($userId, 'Auth', $action.' IP: '.($clientIp ?? 'unknown'), $userId);
        } catch (\Exception $e) {
            // Silently fail - logging should not break authentication
        }
    }

    /**
     * Sanitize user data for response (remove sensitive fields)
     *
     * @param object $user
     *
     * @return array
     */
    public static function sanitizeUserData($user)
    {
        $data = (array) $user;

        // Remove sensitive fields
        unset(
            $data['password'],
            $data['salt'],
            $data['token'],
            $data['token_expires'],
            $data['activatecode']
        );

        return $data;
    }

    /**
     * Parse and Normalize permission
     * Ensure API always returns an array of non-empty permission keys
     *
     * @param mixed $permission
     *
     * @return array
     */
    public static function parsePermission($permission)
    {
        if (is_array($permission)) {
            $perms = $permission;
        } elseif (is_string($permission)) {
            $perms = empty($permission)
            ? []
            : explode(',', trim($permission, " \t\n\r\0\x0B,"));
        } else {
            $perms = [];
        }

        $perms = array_map('trim', $perms);
        $perms = array_filter($perms, function ($v) {
            return $v !== '';
        });
        return array_values($perms);
    }

    /**
     * Parse and normalize meta data from GROUP_CONCAT format
     * Converts 'key1|value1,key2|value2' into ['key1' => ['value1'], 'key2' => ['value2']]
     *
     * @param string|null $metaData The concatenated meta data string
     *
     * @return array Associative array of meta keys to value arrays
     */
    public static function parseMeta($metaData)
    {
        if (empty($metaData)) {
            return [];
        }

        $metas = [];
        foreach (explode(',', $metaData) as $meta) {
            if (strpos($meta, '|') === false) {
                continue; // Skip malformed entries
            }

            [$key, $value] = explode('|', $meta, 2);
            $metas[$key][] = $value;
        }

        return $metas;
    }

    /**
     * Get user for edit profile
     *
     * @param int $userId
     *
     * @return array|null
     */
    public static function getEditProfile($userId)
    {
        $user = static::createQuery()
            ->select(
                'U.id',
                'U.username',
                'U.name',
                'U.phone',
                'U.status',
                'U.active',
                'U.sex',
                'U.provinceID',
                'U.address',
                'U.address2',
                'U.zipcode',
                'U.birthday',
                'U.website',
                'U.company'
            )
            ->from('user U')
            ->where([['U.id', $userId]])
            ->first();

        if (!$user) {
            return null;
        }

        $userData = (array) $user;

        // Add email field (username is email in this system)
        $userData['email'] = $userData['username'];

        // Format avatar
        if (!empty($userData['avatar']) && file_exists(ROOT_PATH.$userData['avatar'])) {
            $userData['avatar'] = [
                ['url' => WEB_URL.$userData['avatar'], 'name' => basename($userData['avatar'])]
            ];
        } else {
            $userData['avatar'] = [];
        }

        return $userData;
    }

    /**
     * Update user profile
     *
     * @param int $userId
     * @param array $data
     * @param string|null $newPassword
     *
     * @return array
     */
    public static function updateProfile($userId, array $data, $newPassword = null)
    {
        // Validate user exists
        $user = self::getUserById($userId);
        if (!$user) {
            return [
                'success' => false,
                'message' => 'No data available',
                'code' => 'USER_NOT_FOUND'
            ];
        }

        // Prepare update data
        $updateData = [];

        // Allowed fields for update
        $allowedFields = ['name', 'phone', 'sex', 'provinceID', 'address', 'address2', 'zipcode', 'birthday', 'website', 'company'];

        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        // Handle password change
        if (!empty($newPassword)) {
            if (strlen($newPassword) < 8) {
                return [
                    'success' => false,
                    'message' => 'Password must be at least 8 characters',
                    'code' => 'INVALID_PASSWORD'
                ];
            }

            $passwordData = self::hashPassword($newPassword);
            $updateData['password'] = $passwordData['hash'];
            $updateData['salt'] = $passwordData['salt'];
        }

        // Update database
        if (!empty($updateData)) {
            \Kotchasan\DB::create()->update('user', [['id', $userId]], $updateData);
        }

        return [
            'success' => true,
            'message' => 'Profile updated successfully'
        ];
    }

    /**
     * Get options for forms
     *
     * @return array
     */
    public static function getFormOptions()
    {
        return [
            'genders' => \Index\Helper\Model::getGenderOptions(),
            'statuses' => \Index\Helper\Model::getUserStatusOptions(),
            'provinces' => \Index\Helper\Model::getProvinceOptions()
        ];
    }
}
