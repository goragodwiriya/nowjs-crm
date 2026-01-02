<?php
// นำเข้าภาษา
$dir = ROOT_PATH.'language/';
if (is_dir(ROOT_PATH.'language/')) {
    // ตาราง language
    $table = $db_config['prefix'].'_language';
    // อ่านไฟล์ภาษาที่ติดตั้ง
    $f = opendir($dir);
    if ($f) {
        while (false !== ($text = readdir($f))) {
            if (preg_match('/^([a-z]{2,2})\.(php|json)$/', $text, $match)) {
                if ($db->fieldExists($table, $match[1]) == false) {
                    // เพิ่มคอลัมน์ภาษา ถ้ายังไม่มีภาษาที่ต้องการ
                    $db->query("ALTER TABLE `$table` ADD `$match[1]` TEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci AFTER `en`");
                }
                if ($match[2] == 'php') {
                    importPHP($db, $table, $match[1], $dir.$text);
                } else {
                    importJSON($db, $table, $match[1], $dir.$text);
                }
            }
        }
        closedir($f);
    }
    $content[] = '<li class="correct">นำเข้า `'.$table.'` สำเร็จ</li>';
}

/**
 * นำเข้าข้อมูลไฟล์ภาษา PHP
 *
 * @param Db $db             Database Class
 * @param string   $table ชื่อตาราง language
 * @param string   $lang           ชื่อภาษา
 * @param string   $file_name      ไฟล์ภาษา
 */
function importPHP($db, $table, $lang, $file_name)
{
    foreach (include ($file_name) as $key => $value) {
        if (is_array($value)) {
            $type = 'array';
        } elseif (is_int($value)) {
            $type = 'int';
        } else {
            $type = 'text';
        }
        $search = $db->first($table, ['key' => $key, 'js' => 0]);
        if ($type == 'array') {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        if ($search) {
            $db->update($table, [
                'id' => $search->id
            ], [
                $lang => $value
            ]);
        } else {
            $db->insert($table, [
                'key' => $key,
                'js' => 0,
                'type' => $type,
                $lang => $value
            ]);
        }
    }
}

/**
 * นำเข้าข้อมูลไฟล์ภาษา JSON
 *
 * @param Database $db         Database Object
 * @param string   $table      ชื่อตาราง language
 * @param string   $lang       ชื่อภาษา
 * @param string   $file_name  ไฟล์ภาษา JSON
 */
function importJSON($db, $table, $lang, $file_name)
{
    $content = file_get_contents($file_name);
    $data = json_decode($content, true);
    if ($data === null) {
        return; // Invalid JSON
    }
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $type = 'array';
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        } elseif (is_int($value)) {
            $type = 'int';
        } else {
            $type = 'text';
        }
        $search = $db->first($table, ['key' => $key, 'js' => 1]);
        if ($search) {
            $db->update($table, [
                'id' => $search->id
            ], [
                $lang => $value
            ]);
        } else {
            $db->insert($table, [
                'key' => $key,
                'js' => 1,
                'type' => $type,
                $lang => $value
            ]);
        }
    }
}
