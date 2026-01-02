<?php
/**
 * @filesource modules/crm/controllers/dashboard.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Crm\Dashboard;

use Kotchasan;
use Kotchasan\ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;

/**
 * API Crm Dashboard Controller
 *
 * Handles dashboard management endpoints with mock data
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Kotchasan\ApiController
{

    /**
     * GET /crm/dashboard
     * Get dashboard data
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request)
    {
        try {
            // Validate request method (GET requests don't need CSRF token)
            ApiController::validateMethod($request, 'GET');

            $data = \Crm\Dashboard\Model::crm();

            return $this->successResponse($data, 'Dashboard data retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * GET /crm/dashboard/teamperformance
     * Get team performance data
     *
     * @param Request $request
     * @return Response
     */
    public function teamperformance(Request $request)
    {
        try {
            // Validate request method (GET requests don't need CSRF token)
            ApiController::validateMethod($request, 'GET');

            $data = \Crm\Dashboard\Model::getTeamPerformance();

            return $this->successResponse($data, 'Team performance data retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
