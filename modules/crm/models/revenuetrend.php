<?php
/**
 * @filesource modules/crm/models/revenuetrend.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Crm\RevenueTrend;

use Kotchasan\Database\Sql;

/**
 * API v1 RevenueTrend Model
 *
 * CRM Revenue Trend with comprehensive metrics for production use
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Get Revenue Trend Statistics for Chart
     * Returns data formatted for Line charts
     *
     * @param int $year Year to get data for (default: current year)
     * @param int $months Number of months to show (default: 12)
     * @return array Revenue trend data
     */
    public static function getRevenueTrend($year = null, $months = 12)
    {
        if ($year === null) {
            $year = (int) date('Y');
        }

        // Month names in Thai
        $monthNames = [
            1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.',
            4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.',
            7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.',
            10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
        ];

        // Query revenue grouped by month
        $results = static::createQuery()
            ->select(
                Sql::create("MONTH(`actual_close_date`) AS `month`"),
                Sql::SUM('value', 'total_revenue'),
                Sql::COUNT('id', 'deals_count')
            )
            ->from('deals')
            ->where([
                ['stage', 'won'],
                [Sql::YEAR('actual_close_date'), $year]
            ])
            ->groupBy(Sql::create("MONTH(`actual_close_date`)"))
            ->orderBy(Sql::create("MONTH(`actual_close_date`)"))
            ->execute()
            ->fetchAll();

        // Initialize all months with 0
        $monthlyData = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthlyData[$m] = ['label' => $monthNames[$m], 'value' => 0];
        }

        // Fill in actual revenue
        foreach ($results as $row) {
            $month = (int) $row->month;
            if (isset($monthlyData[$month])) {
                $monthlyData[$month]['value'] = (double) $row->total_revenue;
            }
        }

        // Format data for Graph Component (Line chart)
        return [
            [
                'name' => "รายได้ {$year}",
                'data' => array_values($monthlyData)
            ]
        ];
    }

    /**
     * Get Revenue Trend Comparison (Multiple Years)
     * Returns data formatted for Multi-line charts
     *
     * @param array $years Array of years to compare
     * @return array Revenue trend comparison data
     */
    public static function getRevenueTrendComparison($years = null)
    {
        if ($years === null) {
            $currentYear = (int) date('Y');
            $years = [$currentYear - 1, $currentYear];
        }

        // Month names in Thai
        $monthNames = [
            1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.',
            4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.',
            7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.',
            10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
        ];

        $datasets = [];

        foreach ($years as $year) {
            // Query revenue grouped by month for this year
            $results = static::createQuery()
                ->select(
                    Sql::create("MONTH(`actual_close_date`) AS `month`"),
                    Sql::SUM('value', 'total_revenue')
                )
                ->from('deals')
                ->where([
                    ['stage', 'won'],
                    [Sql::YEAR('actual_close_date'), $year]
                ])
                ->groupBy(Sql::create("MONTH(`actual_close_date`)"))
                ->orderBy(Sql::create("MONTH(`actual_close_date`)"))
                ->execute()
                ->fetchAll();

            // Initialize months
            $monthlyData = [];
            for ($m = 1; $m <= 12; $m++) {
                $monthlyData[$m] = ['label' => $monthNames[$m], 'value' => 0];
            }

            // Fill in actual revenue
            foreach ($results as $row) {
                $month = (int) $row->month;
                if (isset($monthlyData[$month])) {
                    $monthlyData[$month]['value'] = (double) $row->total_revenue;
                }
            }

            // Add dataset for this year
            $datasets[] = [
                'name' => "รายได้ {$year}",
                'data' => array_values($monthlyData)
            ];
        }

        return $datasets;
    }

    /**
     * Get Revenue Trend for Last N Months
     * Returns rolling N months of revenue data
     *
     * @param int $months Number of months to show (default: 6)
     * @return array Revenue trend data
     */
    public static function getRevenueLastMonths($months = 6)
    {
        // Month names in Thai
        $monthNames = [
            1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.',
            4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.',
            7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.',
            10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
        ];

        // Calculate date range
        $endDate = date('Y-m-t'); // Last day of current month
        $startDate = date('Y-m-01', strtotime("-".($months - 1)." months"));

        // Query revenue grouped by month
        $results = static::createQuery()
            ->select(
                Sql::create("DATE_FORMAT(`actual_close_date`, '%Y-%m') AS `month_key`"),
                Sql::create("MONTH(`actual_close_date`) AS `month`"),
                Sql::create("YEAR(`actual_close_date`) AS `year`"),
                Sql::SUM('value', 'total_revenue'),
                Sql::COUNT('id', 'deals_count')
            )
            ->from('deals')
            ->where([
                ['stage', 'won'],
                ['actual_close_date', '>=', $startDate],
                ['actual_close_date', '<=', $endDate]
            ])
            ->groupBy(Sql::create("DATE_FORMAT(`actual_close_date`, '%Y-%m')"))
            ->orderBy(Sql::create("DATE_FORMAT(`actual_close_date`, '%Y-%m')"))
            ->execute()
            ->fetchAll();

        // Build month labels
        $monthlyData = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $timestamp = strtotime("-{$i} months");
            $monthKey = date('Y-m', $timestamp);
            $monthNum = (int) date('n', $timestamp);
            $year = date('Y', $timestamp);

            $monthlyData[$monthKey] = [
                'label' => $monthNames[$monthNum].' '.substr($year, 2),
                'value' => 0
            ];
        }

        // Fill in actual revenue
        foreach ($results as $row) {
            $monthKey = $row->month_key;
            if (isset($monthlyData[$monthKey])) {
                $monthlyData[$monthKey]['value'] = (double) $row->total_revenue;
            }
        }

        return [
            [
                'name' => 'รายได้',
                'data' => array_values($monthlyData)
            ]
        ];
    }

    /**
     * Get Revenue Growth Rate
     * Returns month-over-month growth rate
     *
     * @param int $months Number of months to analyze
     * @return array Growth rate data
     */
    public static function getRevenueGrowthRate($months = 12)
    {
        $data = static::getRevenueLastMonths($months);

        if (empty($data[0]['data'])) {
            return [];
        }

        $values = array_column($data[0]['data'], 'value');
        $labels = array_column($data[0]['data'], 'label');

        $growthData = [];
        for ($i = 1; $i < count($values); $i++) {
            $prevValue = $values[$i - 1];
            $currentValue = $values[$i];

            $growthRate = 0;
            if ($prevValue > 0) {
                $growthRate = round((($currentValue - $prevValue) / $prevValue) * 100, 2);
            } elseif ($currentValue > 0) {
                $growthRate = 100;
            }

            $growthData[] = [
                'label' => $labels[$i],
                'value' => $growthRate
            ];
        }

        return [
            [
                'name' => 'อัตราการเติบโต (%)',
                'data' => $growthData
            ]
        ];
    }
}
