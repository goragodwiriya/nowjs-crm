<?php
/**
 * @filesource modules/crm/models/dashboard.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Crm\PipelineStats;

use Kotchasan\Database\Sql;

/**
 * API v1 PipelineStats Model
 *
 * CRM PipelineStats with comprehensive metrics for production use
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Get Pipeline Statistics for Chart
     * Returns data formatted for Pie/Doughnut charts
     *
     * @return array Pipeline stats data with all stages
     */
    public static function getPipelineStats()
    {
        // Query deals grouped by stage
        $results = static::createQuery()
            ->select('stage', Sql::COUNT('id', 'count'))
            ->from('deals')
            ->groupBy('stage')
            ->execute()
            ->fetchAll();

        // Initialize all stages with 0
        $stages = [
            'lead' => 0,
            'qualified' => 0,
            'proposal' => 0,
            'negotiation' => 0,
            'won' => 0,
            'lost' => 0
        ];

        // Fill in actual counts
        foreach ($results as $row) {
            if (isset($stages[$row->stage])) {
                $stages[$row->stage] = (int) $row->count;
            }
        }

        // Format data for Graph Component (Pie/Doughnut chart)
        return [
            [
                'name' => 'จำนวนดีล',
                'data' => [
                    ['label' => 'Lead', 'value' => $stages['lead'], 'color' => '#3b82f6'],
                    ['label' => 'Qualified', 'value' => $stages['qualified'], 'color' => '#10b981'],
                    ['label' => 'Proposal', 'value' => $stages['proposal'], 'color' => '#f59e0b'],
                    ['label' => 'Negotiation', 'value' => $stages['negotiation'], 'color' => '#8b5cf6'],
                    ['label' => 'Won', 'value' => $stages['won'], 'color' => '#06b6d4'],
                    ['label' => 'Lost', 'value' => $stages['lost'], 'color' => '#ef4444']
                ]
            ]
        ];
    }
}
