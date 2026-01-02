<?php
/**
 * @filesource modules/index/models/log.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Index\Log;

/**
 * Manage log
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model
{
    /**
     * Add log
     *
     * @param int $id ID of updated item
     * @param string $source Source of data
     * @param string $topic Topic
     * @param int $member_id ID of member
     * @param string $reason Reason
     * @param mixed $datas Additional data
     */
    public static function add($id, $source, $topic, $member_id, $reason = null, $datas = null)
    {
        \Kotchasan\DB::create()->insert('logs', [
            'src_id' => $id,
            'source' => $source,
            'create_date' => date('Y-m-d H:i:s'),
            'topic' => $topic,
            'member_id' => $member_id,
            'datas' => is_array($datas) ? json_encode($datas, JSON_UNESCAPED_UNICODE) : $datas,
            'reason' => $reason
        ]);
    }
}
