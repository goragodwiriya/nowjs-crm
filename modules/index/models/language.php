<?php
/**
 * @filesource modules/index/models/language.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Index\Language;

/**
 * API Language Model
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
     * @var array
     */
    public static $languages = ['th', 'en'];

    /**
     * Get language by ID
     * $id = 0 return new language (for Register)
     *
     * @param int $id
     *
     * @return object|null
     */
    public static function get($id)
    {
        if ($id === 0) {
            // New
            $language = [
                'id' => 0,
                'key' => '',
                'js' => 1,
                'type' => 'text',
                'translate' => []
            ];
            return (object) $language;
        } else {
            // Edit
            $data = static::createQuery()
                ->select()
                ->from('language')
                ->where([['id', $id]])
                ->first();
            if ($data) {
                if ($data->type === 'array') {
                    foreach (self::$languages as $lng) {
                        $ds = json_decode($data->$lng, true);
                        if (is_array($ds)) {
                            foreach ($ds as $key => $value) {
                                $data->translate[$key]['key'] = $key;
                                $data->translate[$key][$lng] = $value ?? '';
                            }
                        } else {
                            $data->translate[0]['key'] = '';
                            $data->translate[0][$lng] = $data->$lng ?? '';
                        }
                        unset($data->$lng);
                    }
                    // Make sure information is available in all languages.
                    foreach ($data->translate as $key => $values) {
                        foreach (self::$languages as $lng) {
                            if (!isset($data->translate[$key][$lng])) {
                                $data->translate[$key][$lng] = '';
                            }
                        }
                    }
                } else {
                    $data->translate[0]['key'] = '';
                    foreach (self::$languages as $lng) {
                        $data->translate[0][$lng] = $data->$lng ?? '';
                        unset($data->$lng);
                    }
                }
            }
            return $data;
        }
    }

    /**
     * Save language data and regenerate language files
     * Return language ID
     *
     * @param \Kotchasan\DB $db Database connection
     * @param int $id Language ID (0 for new language)
     * @param array $save Data to save
     *
     * @return int Language ID
     */
    public static function save($db, $id, $save)
    {
        if ($id === 0) {
            $id = $db->insert('language', $save);
        } else {
            $db->update('language', [['id', $id]], $save);
        }

        // Regenerate language files after save
        \Index\Languages\Model::exportToFile();

        return $id;
    }

    /**
     * @return mixed
     */
    public static function getColumns()
    {
        $columns = [
            [
                'field' => 'key',
                'label' => 'Key',
                'i18n' => true,
                'cellElement' => 'text'
            ]
        ];

        $language = \Kotchasan\DB::create()->first('language');
        foreach ($language as $key => $value) {
            if (!in_array($key, ['id', 'key', 'type', 'js'])) {
                $columns[] = [
                    'field' => $key,
                    'label' => ucfirst($key),
                    'cellElement' => 'textarea',
                    'i18n' => true
                ];
            }
        }

        return $columns;
    }

    /**
     * Prepare translate data from language object
     *
     * @param object $language
     *
     * @return array
     */
    public static function prepareTranslateData($language)
    {
        if (isset($language->translate) && is_array($language->translate)) {
            // Already prepared by get() method
            return $language->translate;
        }

        // Fallback: convert object properties to array
        $result = [];
        foreach (self::$languages as $lng) {
            if (isset($language->$lng)) {
                $result[] = [
                    'key' => $language->key ?? '',
                    $lng => $language->$lng
                ];
            }
        }

        return $result;
    }
}
