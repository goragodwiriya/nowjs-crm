<?php
/**
 * @filesource modules/crm/controllers/pipelinestats.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Crm\PipelineStats;

use Kotchasan;
use Kotchasan\ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;

/**
 * API v1 PipelineStats Controller
 *
 * Handles pipeline statistics endpoints
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Kotchasan\ApiController
{

    /**
     * GET /crm/pipelinestats
     * Get pipeline statistics data
     *
     * @param Request $request
     * @return Response
     */
    public function index(Request $request)
    {
        try {
            // Validate request method (GET requests don't need CSRF token)
            ApiController::validateMethod($request, 'GET');

            $data = \Crm\PipelineStats\Model::getPipelineStats();

            return $this->successResponse($data, 'Pipeline statistics data retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }
}
