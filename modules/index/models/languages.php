<?php
/**
 * @filesource modules/index/models/languages.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Index\Languages;

/**
 * Language Model
 *
 * Handles language table operations
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Query data to send to DataTable
     *
     * @param array $params
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    public static function toDataTable($params)
    {
        // Filters (AND conditions)
        $where = [['js', $params['js']]];

        // Default query
        $query = static::createQuery()
            ->select(
                'id',
                'key',
                'th',
                'en'
            )
            ->from('language')
            ->where($where);

        // Search (OR condition)
        if (!empty($params['search'])) {
            $search = '%'.$params['search'].'%';
            $where = [
                ['key', 'LIKE', $search],
                ['th', 'LIKE', $search],
                ['en', 'LIKE', $search]
            ];

            $query->where($where, 'OR');
        }

        return $query;
    }

    /**
     * Delete translations and regenerate language files
     * Return number of deleted translations
     *
     * @param int|array $ids Translation ID or array of translation IDs
     *
     * @return int
     */
    public static function remove($ids)
    {
        if (empty($ids)) {
            return 0;
        }
        $deleted = \Kotchasan\DB::create()->delete('language', [['id', $ids]]);

        // Regenerate language files after deletion
        if ($deleted > 0) {
            self::exportToFile();
        }

        return $deleted;
    }

    /**
     * Import translations from JSON files to database (js=1)
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public static function importFromJson()
    {
        $basePath = ROOT_PATH.'language/';

        // Scan for all language files (2-letter codes)
        $languages = self::scanLanguageFiles($basePath, 'json');

        if (empty($languages)) {
            return [
                'success' => false,
                'message' => 'No JSON translation files found'
            ];
        }

        // Read all language translations
        $allTranslations = [];
        foreach ($languages as $lang) {
            $file = $basePath.$lang.'.json';
            $content = file_get_contents($file);
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }
            $allTranslations[$lang] = $data;
        }

        if (empty($allTranslations)) {
            return [
                'success' => false,
                'message' => 'No valid JSON translation files'
            ];
        }

        return self::processTranslations($allTranslations, $languages, 1);
    }

    /**
     * Import translations from PHP files to database (js=0)
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public static function importFromPhp()
    {
        $basePath = ROOT_PATH.'language/';

        // Scan for all language files (2-letter codes)
        $languages = self::scanLanguageFiles($basePath, 'php');

        if (empty($languages)) {
            return [
                'success' => false,
                'message' => 'No PHP translation files found'
            ];
        }

        // Read all language translations
        $allTranslations = [];
        foreach ($languages as $lang) {
            $file = $basePath.$lang.'.php';
            $data = include $file;

            if (!is_array($data)) {
                continue;
            }
            $allTranslations[$lang] = $data;
        }

        if (empty($allTranslations)) {
            return [
                'success' => false,
                'message' => 'No valid PHP translation files'
            ];
        }

        return self::processTranslations($allTranslations, $languages, 0);
    }

    /**
     * Scan directory for language files (2-letter language codes)
     *
     * @param string $path      Directory path
     * @param string $extension File extension (json or php)
     *
     * @return array List of language codes found
     */
    private static function scanLanguageFiles($path, $extension)
    {
        $languages = [];

        if (!is_dir($path)) {
            return $languages;
        }

        $files = glob($path.'*.'.$extension);
        foreach ($files as $file) {
            $filename = basename($file, '.'.$extension);
            // Only 2-letter language codes
            if (preg_match('/^[a-z]{2}$/', $filename)) {
                $languages[] = $filename;
            }
        }

        return $languages;
    }

    /**
     * Import translations from both JSON and PHP files to database
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public static function importFromFile()
    {
        $jsonResult = self::importFromJson();
        $phpResult = self::importFromPhp();

        // Combine results
        $messages = [
            'JSON: '.$jsonResult['message'],
            'PHP: '.$phpResult['message']
        ];

        return [
            'success' => $jsonResult['success'] || $phpResult['success'],
            'message' => implode(' | ', $messages)
        ];
    }

    /**
     * Ensure language column exists in database
     *
     * @param string $lang Language code (2 letters)
     *
     * @return bool
     */
    private static function ensureLanguageColumn($lang)
    {
        if (in_array($lang, ['th', 'en'])) {
            return true;
        }

        $db = \Kotchasan\DB::create();

        // Check if column exists using fieldExists method
        try {
            if (!$db->fieldExists('language', $lang)) {
                // Add new column using raw SQL
                $tableName = \Kotchasan\Model::getTableName('language');
                $db->raw("ALTER TABLE `{$tableName}` ADD COLUMN `{$lang}` TEXT NULL AFTER `en`");
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Process translations and save to database
     *
     * @param array $allTranslations All language translations [lang => [key => value]]
     * @param array $languages       List of language codes
     * @param int   $js              JavaScript flag (1 for JSON/JS, 0 for PHP)
     *
     * @return array ['success' => bool, 'message' => string]
     */
    private static function processTranslations($allTranslations, $languages, $js)
    {
        $db = \Kotchasan\DB::create();
        $insertCount = 0;
        $updateCount = 0;
        $columnsAdded = [];

        // Ensure all language columns exist
        foreach ($languages as $lang) {
            if (self::ensureLanguageColumn($lang)) {
                if (!in_array($lang, ['th', 'en'])) {
                    $columnsAdded[] = $lang;
                }
            }
        }

        // Collect all keys from all languages
        $allKeys = [];
        foreach ($allTranslations as $lang => $translations) {
            foreach (array_keys($translations) as $key) {
                $allKeys[$key] = true;
            }
        }

        foreach (array_keys($allKeys) as $key) {
            // Get values for all languages
            $langValues = [];
            $type = 'text';

            foreach ($languages as $lang) {
                $value = $allTranslations[$lang][$key] ?? null;

                // Detect type from first non-null value
                if ($value !== null && $type === 'text') {
                    $type = self::detectType($value);
                }

                // Prepare value for storage
                if ($lang === 'en') {
                    // Store empty if en value equals key or is missing
                    if ($value === null || $value === $key) {
                        $langValues[$lang] = '';
                    } else {
                        $langValues[$lang] = self::prepareValue($value);
                    }
                } else {
                    $langValues[$lang] = $value !== null ? self::prepareValue($value) : '';
                }
            }

            // Check if key already exists with same js flag
            $existing = $db->first('language', [['key', $key], ['js', $js]]);

            if ($existing) {
                // Update existing translation
                $updateData = ['type' => $type];
                foreach ($langValues as $lang => $val) {
                    $updateData[$lang] = $val;
                }
                $db->update('language', [['id', $existing->id]], $updateData);
                $updateCount++;
            } else {
                // Insert new translation
                $insertData = [
                    'key' => $key,
                    'type' => $type,
                    'js' => $js
                ];
                foreach ($langValues as $lang => $val) {
                    $insertData[$lang] = $val;
                }
                $db->insert('language', $insertData);
                $insertCount++;
            }
        }

        $message = sprintf('Imported %d new, Updated %d existing', $insertCount, $updateCount);
        if (!empty($columnsAdded)) {
            $message .= ' (Added columns: '.implode(', ', $columnsAdded).')';
        }

        return [
            'success' => true,
            'message' => $message
        ];
    }

    /**
     * Detect value type (text, int, array)
     *
     * @param mixed $value
     *
     * @return string
     */
    private static function detectType($value)
    {
        if (is_array($value)) {
            return 'array';
        }
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            return 'int';
        }
        return 'text';
    }

    /**
     * Prepare value for storage (convert array to JSON)
     *
     * @param mixed $value
     *
     * @return string
     */
    private static function prepareValue($value)
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        return (string) $value;
    }

    /**
     * Export translations from database to JSON files (js=1)
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public static function exportToJson()
    {
        $basePath = ROOT_PATH.'language/';

        // Ensure directory exists
        if (!is_dir($basePath)) {
            if (!mkdir($basePath, 0755, true)) {
                return [
                    'success' => false,
                    'message' => 'Cannot create translations directory'
                ];
            }
        }

        return self::exportTranslations($basePath, 'json', 1);
    }

    /**
     * Export translations from database to PHP files (js=0)
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public static function exportToPhp()
    {
        $basePath = ROOT_PATH.'language/';

        // Ensure directory exists
        if (!is_dir($basePath)) {
            if (!mkdir($basePath, 0755, true)) {
                return [
                    'success' => false,
                    'message' => 'Cannot create language directory'
                ];
            }
        }

        return self::exportTranslations($basePath, 'php', 0);
    }

    /**
     * Export translations from database to both JSON and PHP files
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public static function exportToFile()
    {
        $jsonResult = self::exportToJson();
        $phpResult = self::exportToPhp();

        // Combine results
        $messages = [];

        if ($jsonResult['success']) {
            $messages[] = 'JSON: '.$jsonResult['message'];
        } else {
            $messages[] = 'JSON: '.$jsonResult['message'];
        }

        if ($phpResult['success']) {
            $messages[] = 'PHP: '.$phpResult['message'];
        } else {
            $messages[] = 'PHP: '.$phpResult['message'];
        }

        return [
            'success' => $jsonResult['success'] || $phpResult['success'],
            'message' => implode(' | ', $messages)
        ];
    }

    /**
     * Export translations from database to files
     *
     * @param string $basePath  Base directory path
     * @param string $extension File extension (json or php)
     * @param int    $js        JavaScript flag (1 for JSON, 0 for PHP)
     *
     * @return array ['success' => bool, 'message' => string]
     */
    private static function exportTranslations($basePath, $extension, $js)
    {
        $db = \Kotchasan\DB::create();

        // Get all translations for the specified js flag
        $records = $db->select('language', [['js', $js]], ['orderBy' => 'key']);

        if (empty($records)) {
            return [
                'success' => false,
                'message' => 'No translations found in database'
            ];
        }

        // Detect available language columns
        $languageColumns = self::getLanguageColumns();

        if (empty($languageColumns)) {
            return [
                'success' => false,
                'message' => 'No language columns found'
            ];
        }

        // Group translations by language
        $translations = [];
        foreach ($languageColumns as $lang) {
            $translations[$lang] = [];
        }

        foreach ($records as $record) {
            $key = $record->key;
            $type = $record->type ?? 'text';

            foreach ($languageColumns as $lang) {
                $value = $record->$lang ?? '';

                // Skip empty values for non-en languages
                if ($value === '') {
                    continue;
                }

                // Convert from stored format based on type
                $translations[$lang][$key] = self::restoreValue($value, $type);
            }
        }

        // Write files
        $filesWritten = [];
        $errors = [];

        foreach ($translations as $lang => $data) {
            if (empty($data)) {
                continue;
            }

            $file = $basePath.$lang.'.'.$extension;

            if ($extension === 'json') {
                $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                // PHP format
                $content = self::generatePhpContent($lang, $data);
            }

            if (file_put_contents($file, $content) !== false) {
                $filesWritten[] = $lang.'.'.$extension;

                // Clear opcache if available
                if (function_exists('opcache_invalidate')) {
                    opcache_invalidate($file);
                }
            } else {
                $errors[] = $lang.'.'.$extension;
            }
        }

        if (!empty($errors)) {
            return [
                'success' => !empty($filesWritten),
                'message' => sprintf('Written: %d, Failed: %d (%s)',
                    count($filesWritten),
                    count($errors),
                    implode(', ', $errors))
            ];
        }

        return [
            'success' => true,
            'message' => sprintf('Exported %d files (%s)',
                count($filesWritten),
                implode(', ', $filesWritten))
        ];
    }

    /**
     * Get available language columns from database
     *
     * @return array List of language column names
     */
    private static function getLanguageColumns()
    {
        // Default language columns
        $defaultColumns = ['th', 'en'];

        $db = \Kotchasan\DB::create();

        // Check for additional language columns
        $additionalColumns = [];
        $possibleLanguages = ['ja', 'zh', 'ko', 'de', 'fr', 'es', 'it', 'pt', 'ru', 'ar', 'vi', 'ms'];

        foreach ($possibleLanguages as $lang) {
            if ($db->fieldExists('language', $lang)) {
                $additionalColumns[] = $lang;
            }
        }

        return array_merge($defaultColumns, $additionalColumns);
    }

    /**
     * Restore value from stored format
     *
     * @param string $value Stored value
     * @param string $type  Value type (text, int, array)
     *
     * @return mixed Restored value
     */
    private static function restoreValue($value, $type)
    {
        if ($type === 'array') {
            $decoded = json_decode($value, true);
            return $decoded !== null ? $decoded : $value;
        }
        if ($type === 'int') {
            return is_numeric($value) ? (int) $value : $value;
        }
        return $value;
    }

    /**
     * Generate PHP file content
     *
     * @param string $lang Language code
     * @param array  $data Translation data
     *
     * @return string PHP file content
     */
    private static function generatePhpContent($lang, $data)
    {
        $lines = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                // Format array
                $arrayItems = [];
                foreach ($value as $k => $v) {
                    if (is_int($k)) {
                        $keyPart = $k.' => ';
                    } else {
                        $keyPart = "'".addslashes($k)."' => ";
                    }

                    if (is_string($v)) {
                        $arrayItems[] = $keyPart."'".addslashes($v)."'";
                    } elseif (is_int($v) || is_float($v)) {
                        $arrayItems[] = $keyPart.$v;
                    } else {
                        $arrayItems[] = $keyPart."'".addslashes((string) $v)."'";
                    }
                }
                $lines[] = "'".addslashes($key)."' => array(\n    ".implode(",\n    ", $arrayItems)."\n  )";
            } elseif (is_int($value)) {
                $lines[] = "'".addslashes($key)."' => ".$value;
            } else {
                $lines[] = "'".addslashes($key)."' => '".addslashes($value)."'";
            }
        }

        return "<?php\n/* language/{$lang}.php */\nreturn array(\n  ".implode(",\n  ", $lines)."\n);\n";
    }
}
