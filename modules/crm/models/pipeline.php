<?php
/**
 * @filesource modules/crm/models/pipeline.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Crm\Pipeline;

/**
 * API Pipeline Model
 *
 * CRM Pipeline data with deals grouped by stage
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Get all deals grouped by stage for Kanban board
     *
     * @return array Deals data grouped by stage
     */
    public static function getDealsGroupedByStage()
    {
        // Fetch all active deals with customer info
        $result = static::createQuery()
            ->select(
                'D.id',
                'D.title',
                'D.stage',
                'D.value',
                'D.probability',
                'D.created_at',
                'C.name AS customer'
            )
            ->from('deals D')
            ->join('customers C', [['C.id', 'D.customer_id']], 'LEFT')
            ->where([['D.stage', '!=', '']])
            ->orderBy('D.updated_at', 'DESC')
            ->execute()
            ->fetchAll();

        // Initialize stages with empty arrays
        $stages = [
            'lead' => [],
            'qualified' => [],
            'proposal' => [],
            'negotiation' => [],
            'won' => [],
            'lost' => []
        ];

        // Group deals by stage
        foreach ($result as $deal) {
            $stage = $deal->stage;
            if (isset($stages[$stage])) {
                // Format values for display
                $deal->value = '฿'.number_format($deal->value, 0);
                $deal->probability = $deal->probability.'%';
                $deal->created_at = date('d/m/Y', strtotime($deal->created_at));
                $stages[$stage][] = $deal;
            }
        }

        return $stages;
    }

    /**
     * Update deal stage
     *
     * @param int $id Deal ID
     * @param string $stage New stage value
     * @return bool Success status
     */
    public static function updateDealStage($id, $stage)
    {
        if (empty($id)) {
            throw new \Exception('Required ID', 400);
        }

        $validStages = ['lead', 'qualified', 'proposal', 'negotiation', 'won', 'lost'];
        if (!in_array($stage, $validStages)) {
            throw new \Exception('Invalid stage value', 400);
        }

        // Update deal stage
        $result = \Kotchasan\DB::create()->update('deals', [['id', (int) $id]], ['stage' => $stage]);

        return $result !== false;
    }

    /**
     * Get a single deal by ID
     *
     * @param int $id Deal ID
     * @return object|null Deal data
     */
    public static function get($id)
    {
        return static::createQuery()
            ->select('D.*', 'C.name AS customer')
            ->from('deals D')
            ->join('customers C', [['C.id', 'D.customer_id']], 'LEFT')
            ->where([['D.id', $id]])
            ->execute()
            ->fetch();
    }

    /**
     * Save deal (create or update)
     *
     * @param array $data Deal data
     * @return int|bool Deal ID on success, false on failure
     */
    public static function save($data)
    {
        $db = static::createQuery();

        if (!empty($data['id'])) {
            // Update existing deal
            $id = $data['id'];
            unset($data['id']);

            $result = $db->update('deals')
                ->set($data)
                ->where([['id', $id]])
                ->execute();

            return $result !== false ? $id : false;
        } else {
            // Create new deal
            unset($data['id']);
            $data['pipeline_id'] = $data['pipeline_id'] ?? 1;
            $data['stage_id'] = $data['stage_id'] ?? 1;

            return $db->insert('deals', $data);
        }
    }

    /**
     * Delete deal
     *
     * @param int $id Deal ID
     * @return bool Success status
     */
    public static function deleteDeal($id)
    {
        $db = static::createQuery();
        $result = $db->delete('deals')
            ->where([['id', $id]])
            ->execute();

        return $result !== false;
    }
}
