<?php
/**
 * @filesource modules/crm/models/dashboard.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Crm\Dashboard;

use Kotchasan\Currency;
use Kotchasan\Database\Sql;

/**
 * API Crm Dashboard Model
 *
 * CRM Dashboard with comprehensive metrics for production use
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Get complete CRM Dashboard data
     *
     * @return array Dashboard data with all metrics
     */
    public static function crm()
    {
        return [
            'overview' => static::getOverview(),
            'pipeline' => static::getPipelineMetrics(),
            'activities' => static::getActivityMetrics()
        ];
    }

    /**
     * Get overview metrics (main KPIs)
     *
     * @return array
     */
    public static function getOverview()
    {
        // Date ranges
        $currentMonth = date('Y-m-01 00:00:00');
        $lastDayMonth = date('Y-m-t 23:59:59');
        $prevMonth = date('Y-m-01 00:00:00', strtotime($currentMonth.' -1 month'));
        $lastDayPrevMonth = date('Y-m-t 23:59:59', strtotime($currentMonth.' -1 month'));

        // New customers this month
        $new_customers_this_month = static::createQuery()->selectCount()->from('customers')->where([['created_at', '>=', $currentMonth], ['created_at', '<=', $lastDayMonth]]);
        // New customers last month
        $new_customers_last_month = static::createQuery()->selectCount()->from('customers')->where([['created_at', '>=', $prevMonth], ['created_at', '<=', $lastDayPrevMonth]]);
        $leads = static::createQuery()->selectCount()->from('customers')->where([['status', 'lead']]);
        $prospects = static::createQuery()->selectCount()->from('customers')->where([['status', 'prospect']]);
        $active_customers = static::createQuery()->selectCount()->from('customers')->where([['status', 'customer']]);
        $total_contacts = static::createQuery()->selectCount()->from('contacts');
        // Total contacts
        $active_deals = static::createQuery()->selectCount()->from('deals')->where([['stage', '!=', ['won', 'lost']]]);
        $pipeline_value = static::createQuery()->select(Sql::SUM('value'))->from('deals')->where([['stage', '!=', ['won', 'lost']]]);
        // Won deals this month
        $won_deals_this_month = static::createQuery()->selectCount()->from('deals')->where([['stage', 'won'], ['actual_close_date', '>=', $currentMonth], ['actual_close_date', '<=', $lastDayMonth]]);
        $revenue_this_month = static::createQuery()->select(Sql::SUM('value'))->from('deals')->where([['stage', 'won'], ['actual_close_date', '>=', $currentMonth], ['actual_close_date', '<=', $lastDayMonth]]);
        // Won deals last month
        $won_deals_last_month = static::createQuery()->selectCount()->from('deals')->where([['stage', 'won'], ['actual_close_date', '>=', $prevMonth], ['actual_close_date', '<=', $lastDayPrevMonth]]);
        $revenue_last_month = static::createQuery()->select(Sql::SUM('value'))->from('deals')->where([['stage', 'won'], ['actual_close_date', '>=', $prevMonth], ['actual_close_date', '<=', $lastDayPrevMonth]]);

        // Lost deals this month
        $lost_deals_this_month = static::createQuery()->selectCount()->from('deals')->where([['stage', 'lost'], ['actual_close_date', '>=', $currentMonth], ['actual_close_date', '<=', $lastDayMonth]]);
        $lost_value_this_month = static::createQuery()->select(Sql::SUM('value'))->from('deals')->where([['stage', 'lost'], ['actual_close_date', '>=', $currentMonth], ['actual_close_date', '<=', $lastDayMonth]]);
        // Average deal value
        $avg_deal_value = static::createQuery()->select(Sql::AVG('value'))->from('deals')->where([['stage', 'won']]);

        // Build overview query with subqueries
        $result = static::createQuery()
            ->select(
                // Total customers
                Sql::COUNT('id', 'total_customers'),
                // New customers this month
                [$new_customers_this_month, 'new_customers_this_month'],
                // New customers last month
                [$new_customers_last_month, 'new_customers_last_month'],
                [$leads, 'leads'],
                [$prospects, 'prospects'],
                [$active_customers, 'active_customers'],
                // Total contacts
                [$total_contacts, 'total_contacts'],
                // Deals metrics
                [$active_deals, 'active_deals'],
                [$pipeline_value, 'pipeline_value'],
                // Won deals this month
                [$won_deals_this_month, 'won_deals_this_month'],
                [$revenue_this_month, 'revenue_this_month'],
                // Won deals last month
                [$won_deals_last_month, 'won_deals_last_month'],
                [$revenue_last_month, 'revenue_last_month'],
                // Lost deals this month
                [$lost_deals_this_month, 'lost_deals_this_month'],
                [$lost_value_this_month, 'lost_value_this_month'],
                // Average deal value
                [$avg_deal_value, 'avg_deal_value'],
                // Date info
                "'$currentMonth' AS `period_start`",
                "'$lastDayMonth' AS `period_end`"
            )
            ->from('customers')
            ->first();

        // Calculate growth percentages
        if ($result) {
            $result = (array) $result;
            $result['pipeline_value'] = Currency::format($result['pipeline_value']);
            $result['revenue_this_month'] = (double) $result['revenue_this_month'];
            $result['revenue_last_month'] = Currency::format($result['revenue_last_month']);
            $result['lost_value_this_month'] = (double) $result['lost_value_this_month'];
            $result['avg_deal_value'] = Currency::format($result['avg_deal_value']);

            // Customer growth
            $result['customer_growth'] = $result['new_customers_last_month'] > 0
            ? round((($result['new_customers_this_month'] - $result['new_customers_last_month']) / $result['new_customers_last_month']) * 100, 1)
            : ($result['new_customers_this_month'] > 0 ? 100 : 0);

            // Revenue growth
            $result['revenue_growth'] = $result['revenue_last_month'] > 0
            ? round((($result['revenue_this_month'] - $result['revenue_last_month']) / $result['revenue_last_month']) * 100, 1)
            : ($result['revenue_this_month'] > 0 ? 100 : 0);
            $result['revenue_this_month'] = Currency::format($result['revenue_this_month']);

            // Win rate this month
            $totalClosedThisMonth = $result['won_deals_this_month'] + $result['lost_deals_this_month'];
            $result['win_rate_this_month'] = $totalClosedThisMonth > 0
            ? round(($result['won_deals_this_month'] / $totalClosedThisMonth) * 100, 1)
            : 0;
        }

        return $result;
    }

    /**
     * Get sales pipeline metrics
     *
     * @return array
     */
    public static function getPipelineMetrics()
    {
        // Deals by stage
        $byStage = static::createQuery()
            ->select(
                'D.stage',
                Sql::COUNT('D.id', 'count'),
                Sql::SUM('D.value', 'value'),
                Sql::AVG('D.value', 'avg_value'),
                Sql::AVG('D.probability', 'avg_probability')
            )
            ->from('deals D')
            ->where([['D.stage', '!=', ['won', 'lost']]])
            ->groupBy('D.stage')
            ->execute()
            ->fetchAll();

        // Deals closing soon (next 7 days)
        $closingSoon = static::createQuery()
            ->select(
                Sql::COUNT('id', 'count'),
                Sql::SUM('value', 'value')
            )
            ->from('deals')
            ->where([
                ['stage', '!=', ['won', 'lost']],
                ['expected_close_date', '>=', date('Y-m-d')],
                ['expected_close_date', '<=', date('Y-m-d', strtotime('+7 days'))]
            ])
            ->first();
        $closingSoon->value = Currency::format($closingSoon ? $closingSoon->value : 0);

        // Weighted pipeline value
        $weightedValue = static::createQuery()
            ->select(
                Sql::create('COALESCE(SUM(`value` * `probability` / 100), 0) AS `weighted_value`')
            )
            ->from('deals')
            ->where([['stage', '!=', ['won', 'lost']]])
            ->first();

        // Average sales cycle (days from created to won)
        $avgCycle = static::createQuery()
            ->select(
                Sql::create('COALESCE(AVG(DATEDIFF(`actual_close_date`, `created_at`)), 0) AS `avg_days`')
            )
            ->from('deals')
            ->where([['stage', 'won']])
            ->first();

        return [
            'by_stage' => $byStage,
            'closing_soon' => $closingSoon,
            'weighted_pipeline_value' => Currency::format($weightedValue ? (double) $weightedValue->weighted_value : 0),
            'avg_sales_cycle_days' => $avgCycle ? round($avgCycle->avg_days, 0) : 0
        ];
    }

    /**
     * Get activity metrics
     *
     * @return array
     */
    public static function getActivityMetrics()
    {
        $today = date('Y-m-d');
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $weekEnd = date('Y-m-d', strtotime('sunday this week'));

        // Activities today
        $activitiesToday = static::createQuery()
            ->select([
                Sql::COUNT('id', 'total'),
                Sql::create("SUM(CASE WHEN `status` = 'completed' THEN 1 ELSE 0 END) AS `completed`"),
                Sql::create("SUM(CASE WHEN `status` = 'scheduled' THEN 1 ELSE 0 END) AS `scheduled`")
            ])
            ->from('activities')
            ->where([
                ['start_time', '>=', $today.' 00:00:00'],
                ['start_time', '<=', $today.' 23:59:59']
            ])
            ->first();

        // Ensure activities_today has all required properties
        if ($activitiesToday) {
            $activitiesToday->total = (int) $activitiesToday->total;
            $activitiesToday->completed = (int) $activitiesToday->completed;
            $activitiesToday->scheduled = (int) $activitiesToday->scheduled;
        } else {
            $activitiesToday = (object) ['total' => 0, 'completed' => 0, 'scheduled' => 0];
        }

        // Activities this week by type
        $activitiesByType = static::createQuery()
            ->select([
                'type',
                Sql::COUNT('id', 'count')
            ])
            ->from('activities')
            ->where([
                ['start_time', '>=', $weekStart.' 00:00:00'],
                ['start_time', '<=', $weekEnd.' 23:59:59']
            ])
            ->groupBy('type')
            ->execute()
            ->fetchAll();

        // Overdue tasks
        $overdueTasks = static::createQuery()
            ->select([
                Sql::COUNT('id', 'count')
            ])
            ->from('tasks')
            ->where([
                ['due_date', '<', $today],
                ['status', '!=', ['completed', 'cancelled']]
            ])
            ->first();

        // Tasks due today
        $tasksDueToday = static::createQuery()
            ->select([
                Sql::COUNT('id', 'total'),
                Sql::create("SUM(CASE WHEN `status` = 'completed' THEN 1 ELSE 0 END) AS `completed`")
            ])
            ->from('tasks')
            ->where([['due_date', $today]])
            ->first();

        // Ensure tasks_due_today has all required properties
        if ($tasksDueToday) {
            $tasksDueToday->total = (int) $tasksDueToday->total;
            $tasksDueToday->completed = (int) $tasksDueToday->completed;
        } else {
            $tasksDueToday = (object) ['total' => 0, 'completed' => 0];
        }

        // Upcoming tasks (next 7 days)
        $upcomingTasks = static::createQuery()
            ->select([
                Sql::COUNT('id', 'count')
            ])
            ->from('tasks')
            ->where([
                ['due_date', '>=', $today],
                ['due_date', '<=', date('Y-m-d', strtotime('+7 days'))],
                ['status', '!=', ['completed', 'cancelled']]
            ])
            ->first();

        return [
            'activities_today' => $activitiesToday,
            'activities_by_type' => $activitiesByType,
            'overdue_tasks' => $overdueTasks ? $overdueTasks->count : 0,
            'tasks_due_today' => $tasksDueToday,
            'upcoming_tasks' => $upcomingTasks ? $upcomingTasks->count : 0
        ];
    }

    /**
     * Get team performance metrics
     *
     * @return array
     */
    public static function getTeamPerformance()
    {
        $currentMonth = date('Y-m-01 00:00:00');
        $lastDayMonth = date('Y-m-t 23:59:59');

        // Revenue by salesperson (this month)
        $result = static::createQuery()
            ->select(
                'U.name',
                Sql::COUNT('D.id', 'deals_won'),
                Sql::SUM('D.value', 'revenue')
            )
            ->from('deals D')
            ->join('user U', [['U.id', 'D.owner_id']], 'LEFT')
            ->where([
                ['D.stage', 'won'],
                ['D.actual_close_date', '>=', $currentMonth],
                ['D.actual_close_date', '<=', $lastDayMonth]
            ])
            ->groupBy('D.owner_id')
            ->orderBy('revenue', 'DESC')
            ->limit(10)
            ->execute();
        $data = [];

        foreach ($result->fetchAll() as $i => $row) {
            // Convert object to array for proper manipulation
            $item = (array) $row;

            // Add rank (1-based) and badge class
            $rank = $i + 1;
            if ($i == 0) {
                $badgeClass = 'gold';
            } elseif ($i == 1) {
                $badgeClass = 'silver';
            } elseif ($i == 2) {
                $badgeClass = 'bronze';
            } else {
                $badgeClass = '';
            }

            // Send raw data
            $item['rank'] = $rank;
            $item['badge_class'] = $badgeClass;

            $data[] = $item;
        }
        return $data;
    }
}
