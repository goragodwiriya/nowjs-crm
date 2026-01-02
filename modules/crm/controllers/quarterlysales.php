<?php
/**
 * @filesource modules/crm/controllers/quarterly-sales.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Crm\QuarterlySales;

use Kotchasan;
use Kotchasan\ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;

/**
 * API v1 QuarterlySales Controller
 *
 * Handles quarterly sales endpoints
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Kotchasan\ApiController
{
    /**
     * GET /crm/quarterlysales
     * Get quarterly sales data
     *
     * Query Parameters:
     * - year: Year to get data for (default: current year)
     * - compare: Comma-separated years to compare (e.g., "2023,2024")
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

            // If compare parameter is provided, return comparison data
            if (!empty($compare)) {
                $years = array_map('intval', explode(',', $compare));
                $data = \Crm\QuarterlySales\Model::getQuarterlySalesComparison($years);
            } else {
                // Return single year data
                $data = \Crm\QuarterlySales\Model::getQuarterlySales($year ?: null);
            }

            return $this->successResponse($data, 'Quarterly sales data retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * GET /crm/quarterlysales/monthly
     * Get monthly sales for a specific quarter
     *
     * Query Parameters:
     * - year: Year (required)
     * - quarter: Quarter 1-4 (required)
     *
     * @param Request $request
     * @return Response
     */
    public function monthly(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');

            $year = $request->get('year')->toInt();
            $quarter = $request->get('quarter')->toInt();

            if (!$year || !$quarter || $quarter < 1 || $quarter > 4) {
                return $this->errorResponse('Invalid year or quarter parameter', 400);
            }

            $data = \Crm\QuarterlySales\Model::getMonthlySalesByQuarter($year, $quarter);

            return $this->successResponse($data, 'Monthly sales data retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
