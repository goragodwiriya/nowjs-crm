<?php
/**
 * @filesource modules/crm/controllers/revenuetrend.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Crm\RevenueTrend;

use Kotchasan;
use Kotchasan\ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;

/**
 * API v1 RevenueTrend Controller
 *
 * Handles revenue trend endpoints
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Kotchasan\ApiController
{
    /**
     * GET /crm/revenuetrend
     * Get revenue trend data
     *
     * Query Parameters:
     * - year: Year to get data for (default: current year)
     * - compare: Comma-separated years to compare (e.g., "2023,2024")
     * - months: Number of months to show for rolling view (e.g., 6, 12)
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request)
    {
        try {
            // Validate request method (GET requests don't need CSRF token)
            ApiController::validateMethod($request, 'GET');

            // Get query parameters
            $year = $request->get('year')->toInt();
            $compare = $request->get('compare')->toString();
            $months = $request->get('months')->toInt();

            // If months parameter is provided, return rolling months view
            if ($months > 0) {
                $data = \Crm\RevenueTrend\Model::getRevenueLastMonths($months);
            }
            // If compare parameter is provided, return comparison data
            elseif (!empty($compare)) {
                $years = array_map('intval', explode(',', $compare));
                $data = \Crm\RevenueTrend\Model::getRevenueTrendComparison($years);
            }
            // Return single year data
            else {
                $data = \Crm\RevenueTrend\Model::getRevenueTrend($year ?: null);
            }

            return $this->successResponse($data, 'Revenue trend data retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * GET /crm/revenuetrend/growth
     * Get revenue growth rate data
     *
     * Query Parameters:
     * - months: Number of months to analyze (default: 12)
     *
     * @param Request $request
     * @return Response
     */
    public function growth(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');

            $months = $request->get('months')->toInt() ?: 12;

            $data = \Crm\RevenueTrend\Model::getRevenueGrowthRate($months);

            return $this->successResponse($data, 'Revenue growth rate data retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
