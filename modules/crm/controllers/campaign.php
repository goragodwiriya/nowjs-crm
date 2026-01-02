<?php
/**
 * @filesource modules/crm/controllers/campaign.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 */

namespace Crm\Campaign;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;

/**
 * API Campaign Controller
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * GET /api/crm/campaign/get
     * Get campaign details by ID
     *
     * @param Request $request
     *
     * @return Response
     */
    public function get(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');

            // Authentication check (required)
            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }

            $id = $request->get('id')->toInt();
            $data = \Crm\Campaign\Model::get($id);
            if (!$data) {
                return $this->redirectResponse('/404', 'No data available', 404);
            }

            return $this->successResponse([
                'data' => $data,
                'options' => [
                    'owners' => \Crm\Users\Model::getOwnerOptions()
                ]
            ], 'Campaign details retrieved');

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * POST /api/crm/campaign/save
     * Save campaign details (create or update)
     *
     * @param Request $request
     *
     * @return Response
     */
    public function save(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);

            // Authentication check (required)
            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->redirectResponse('/login', 'Unauthorized', 401);
            }

            // Authorization for saving
            if (!ApiController::canModify($login)) {
                return $this->errorResponse('Access denied', 403);
            }

            $save = $this->parseInput($request);
            $campaign = \Crm\Campaign\Model::get($request->post('id')->toInt());

            if (!$campaign) {
                return $this->errorResponse('No data available', 404);
            }

            if ($campaign->id === 0) {
                // New campaign
                $save['created_at'] = date('Y-m-d H:i:s');
            }

            $db = \Kotchasan\DB::create();

            // Validate campaign fields
            $errors = $this->validateFields($request, $save, $campaign, $db);

            if (empty($errors)) {
                // Save campaign
                $id = \Crm\Campaign\Model::save($db, $campaign->id, $save);

                // Log
                \Index\Log\Model::add($id, 'Crm', 'Saved campaign: '.$save['name'], $login->id);

                // Redirect to campaigns list
                return $this->redirectResponse('/campaigns', 'Saved successfully');
            }

            // Error response
            return $this->formErrorResponse($errors, 400);

        } catch (\Exception $e) {
            // Error response
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Parse campaign input from request
     *
     * @param Request $request
     * @return array
     */
    protected function parseInput(Request $request): array
    {
        $save = [
            'name' => $request->post('name')->topic(),
            'description' => $request->post('description')->textarea(),
            'type' => $request->post('type')->filter('a-z'),
            'status' => $request->post('status')->filter('a-z'),
            'budget' => $request->post('budget')->toDouble(),
            'actual_cost' => $request->post('actual_cost')->toDouble(),
            'start_date' => $request->post('start_date')->date() ?: null,
            'end_date' => $request->post('end_date')->date() ?: null,
            'target_leads' => $request->post('target_leads')->toInt(),
            'actual_leads' => $request->post('actual_leads')->toInt(),
            'target_revenue' => $request->post('target_revenue')->toDouble(),
            'actual_revenue' => $request->post('actual_revenue')->toDouble(),
            'owner_id' => $request->post('owner_id')->toInt() ?: null,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        return $save;
    }

    /**
     * Validate campaign fields for required and format validation
     *
     * @param Request $request HTTP request
     * @param array &$save Save data (modified by reference)
     * @param object $campaign Existing campaign
     * @param object $db Database connection
     *
     * @return array Validation errors
     */
    protected function validateFields($request, &$save, $campaign, $db)
    {
        $errors = [];

        // Valid type values
        $validTypes = ['email', 'social', 'event', 'webinar', 'advertisement', 'other'];

        // Valid status values
        $validStatuses = ['draft', 'scheduled', 'active', 'paused', 'completed', 'cancelled'];

        // Validate name (required)
        if (empty($save['name'])) {
            $errors['campaign_name'] = 'Please fill in';
        }

        // Validate type (required and must be valid)
        if (empty($save['type'])) {
            $errors['campaign_type'] = 'Please select a type';
        } elseif (!in_array($save['type'], $validTypes)) {
            $errors['campaign_type'] = 'Invalid type value';
        }

        // Validate status (if provided, must be valid)
        if (!empty($save['status']) && !in_array($save['status'], $validStatuses)) {
            $errors['campaign_status'] = 'Invalid status value';
        }

        // Validate budget (must be non-negative)
        if ($save['budget'] < 0) {
            $errors['campaign_budget'] = 'Budget must be non-negative';
        }

        // Validate actual_cost (must be non-negative)
        if ($save['actual_cost'] < 0) {
            $errors['campaign_actual_cost'] = 'Actual cost must be non-negative';
        }

        // Validate target_leads (must be non-negative)
        if ($save['target_leads'] < 0) {
            $errors['campaign_target_leads'] = 'Target leads must be non-negative';
        }

        // Validate actual_leads (must be non-negative)
        if ($save['actual_leads'] < 0) {
            $errors['campaign_actual_leads'] = 'Actual leads must be non-negative';
        }

        // Validate target_revenue (must be non-negative)
        if ($save['target_revenue'] < 0) {
            $errors['campaign_target_revenue'] = 'Target revenue must be non-negative';
        }

        // Validate actual_revenue (must be non-negative)
        if ($save['actual_revenue'] < 0) {
            $errors['campaign_actual_revenue'] = 'Actual revenue must be non-negative';
        }

        // Validate end_date is after start_date
        if (!empty($save['start_date']) && !empty($save['end_date'])) {
            if (strtotime($save['end_date']) < strtotime($save['start_date'])) {
                $errors['campaign_end_date'] = 'End date must be after start date';
            }
        }

        return $errors;
    }
}
