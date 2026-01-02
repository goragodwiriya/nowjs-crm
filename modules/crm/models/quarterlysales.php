<?php
/**
 * @filesource modules/crm/models/quarterlysales.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Crm\QuarterlySales;

use Kotchasan\Database\Sql;

/**
 * API v1 QuarterlySales Model
 *
 * CRM Quarterly Sales with comprehensive metrics for production use
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Model extends \Kotchasan\Model
{
    /**
     * Get Quarterly Sales Statistics for Chart
     * Returns data formatted for Bar charts
     *
     * @param int $year Year to get data for (default: current year)
     * @return array Quarterly sales data
     */
    public static function getQuarterlySales($year = null)
    {
        if ($year === null) {
            $year = (int) date('Y');
        }

        // Query sales grouped by quarter
        $results = static::createQuery()
            ->select(
                Sql::create("QUARTER(`actual_close_date`) AS `quarter`"),
                Sql::SUM('value', 'total_sales'),
                Sql::COUNT('id', 'deals_count')
            )
            ->from('deals')
            ->where([
                ['stage', 'won'],
                [Sql::YEAR('actual_close_date'), $year]
            ])
            ->groupBy(Sql::create("QUARTER(`actual_close_date`)"))
            ->orderBy(Sql::create("QUARTER(`actual_close_date`)"))
            ->execute()
            ->fetchAll();

        // Initialize all quarters with 0
        $quarters = [
            1 => ['label' => 'Q1', 'value' => 0],
            2 => ['label' => 'Q2', 'value' => 0],
            3 => ['label' => 'Q3', 'value' => 0],
            4 => ['label' => 'Q4', 'value' => 0]
        ];

        // Fill in actual sales
        foreach ($results as $row) {
            $quarter = (int) $row->quarter;
            if (isset($quarters[$quarter])) {
                $quarters[$quarter]['value'] = (double) $row->total_sales;
            }
        }

        // Format data for Graph Component (Bar chart)
        return [
            [
                'name' => (string) $year,
                'data' => array_values($quarters)
            ]
        ];
    }

    /**
     * Get Quarterly Sales Comparison (Multiple Years)
     * Returns data formatted for Grouped Bar charts
     *
     * @param array $years Array of years to compare
     * @return array Quarterly sales comparison data
     */
    public static function getQuarterlySalesComparison($years = null)
    {
        if ($years === null) {
            $currentYear = (int) date('Y');
            $years = [$currentYear - 1, $currentYear];
        }

        $datasets = [];

        foreach ($years as $year) {
            // Query sales grouped by quarter for this year
            $results = static::createQuery()
                ->select(
                    Sql::create("QUARTER(`actual_close_date`) AS `quarter`"),
                    Sql::SUM('value', 'total_sales')
                )
                ->from('deals')
                ->where([
                    ['stage', 'won'],
                    [Sql::YEAR('actual_close_date'), $year]
                ])
                ->groupBy(Sql::create("QUARTER(`actual_close_date`)"))
                ->orderBy(Sql::create("QUARTER(`actual_close_date`)"))
                ->execute()
                ->fetchAll();

            // Initialize quarters
            $quarters = [
                1 => 0,
                2 => 0,
                3 => 0,
                4 => 0
            ];

            // Fill in actual sales
            foreach ($results as $row) {
                $quarter = (int) $row->quarter;
                if (isset($quarters[$quarter])) {
                    $quarters[$quarter] = (double) $row->total_sales;
                }
            }

            // Add dataset for this year
            $datasets[] = [
                'name' => (string) $year,
                'data' => [
                    ['label' => 'Q1', 'value' => $quarters[1]],
                    ['label' => 'Q2', 'value' => $quarters[2]],
                    ['label' => 'Q3', 'value' => $quarters[3]],
                    ['label' => 'Q4', 'value' => $quarters[4]]
                ]
            ];
        }

        return $datasets;
    }

    /**
     * Get Monthly Sales for a specific quarter
     *
     * @param int $year Year
     * @param int $quarter Quarter (1-4)
     * @return array Monthly sales data
     */
    public static function getMonthlySalesByQuarter($year, $quarter)
    {
        // Calculate month range for quarter
        $startMonth = ($quarter - 1) * 3 + 1;
        $endMonth = $quarter * 3;

        $results = static::createQuery()
            ->select(
                Sql::create("MONTH(`actual_close_date`) AS `month`"),
                Sql::SUM('value', 'total_sales'),
                Sql::COUNT('id', 'deals_count')
            )
            ->from('deals')
            ->where([
                ['stage', 'won'],
                [Sql::YEAR('actual_close_date'), $year],
                [Sql::MONTH('actual_close_date'), '>=', $startMonth],
                [Sql::MONTH('actual_close_date'), '<=', $endMonth]
            ])
            ->groupBy(Sql::create("MONTH(`actual_close_date`)"))
            ->orderBy(Sql::create("MONTH(`actual_close_date`)"))
            ->execute()
            ->fetchAll();

        // Month names in Thai
        $monthNames = [
            1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.',
            4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.',
            7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.',
            10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
        ];

        $months = [];
        for ($m = $startMonth; $m <= $endMonth; $m++) {
            $months[$m] = ['label' => $monthNames[$m], 'value' => 0];
        }

        // Fill in actual sales
        foreach ($results as $row) {
            $month = (int) $row->month;
            if (isset($months[$month])) {
                $months[$month]['value'] = (double) $row->total_sales;
            }
        }

        return [
            [
                'name' => "Q{$quarter} {$year}",
                'data' => array_values($months)
            ]
        ];
    }
}
