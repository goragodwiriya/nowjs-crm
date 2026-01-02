<?php
/**
 * @filesource modules/crm/controllers/pipeline.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Crm\Pipeline;

use Kotchasan;
use Kotchasan\ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;

/**
 * API Pipeline Controller
 *
 * Handles pipeline/kanban board endpoints
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Kotchasan\ApiController
{
    /**
     * GET /crm/pipeline
     * Get all deals grouped by stage for Kanban board
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request)
    {
        try {
            // Validate request method
            ApiController::validateMethod($request, 'GET');

            // Get deals grouped by stage
            $data = \Crm\Pipeline\Model::getDealsGroupedByStage();

            return $this->successResponse($data, 'Pipeline data retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
