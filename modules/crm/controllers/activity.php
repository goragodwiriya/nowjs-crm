<?php
/**
 * @filesource modules/crm/controllers/activity.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 */

namespace Crm\Activity;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;

/**
 * API Activity Controller
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * GET /api/crm/activity/get
     * Get activity details by ID
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
            $data = \Crm\Activity\Model::get($id);
            if (!$data) {
                return $this->redirectResponse('/404', 'No data available', 404);
            }

            return $this->successResponse([
                'data' => $data,
                'options' => [
                    'customers' => \Crm\Customers\Model::getCustomerOptions(),
                    'deals' => \Crm\Deals\Model::getDealOptions(),
                    'owners' => \Crm\Users\Model::getOwnerOptions()
                ]
            ], 'Activity details retrieved');

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * POST /api/crm/activity/save
     * Save activity details (create or update)
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
            $activity = \Crm\Activity\Model::get($request->post('id')->toInt());

            if (!$activity) {
                return $this->errorResponse('No data available', 404);
            }

            if ($activity->id === 0) {
                // New activity
                $save['created_at'] = date('Y-m-d H:i:s');
            }

            $db = \Kotchasan\DB::create();

            // Validate activity fields
            $errors = $this->validateFields($request, $save, $activity, $db);

            if (empty($errors)) {
                // Save activity
                $id = \Crm\Activity\Model::save($db, $activity->id, $save);

                // Log
                \Index\Log\Model::add($id, 'Crm', 'Saved activity: '.$save['subject'], $login->id);

                // Redirect to activities list
                return $this->redirectResponse('/activities', 'Saved successfully');
            }

            // Error response
            return $this->formErrorResponse($errors, 400);

        } catch (\Exception $e) {
            // Error response
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Parse activity input from request
     *
     * @param Request $request
     * @return array
     */
    protected function parseInput(Request $request): array
    {
        $save = [
            'type' => $request->post('type')->filter('a-z_'),
            'subject' => $request->post('subject')->topic(),
            'description' => $request->post('description')->textarea(),
            'customer_id' => $request->post('customer_id')->toInt(),
            'deal_id' => $request->post('deal_id')->toInt(),
            'owner_id' => $request->post('owner_id')->toInt(),
            'start_time' => $request->post('start_time')->date(),
            'end_time' => $request->post('end_time')->date(),
            'duration_minutes' => $request->post('duration_minutes')->toInt(),
            'location' => $request->post('location')->topic(),
            'status' => $request->post('status')->filter('a-z_'),
            'outcome' => $request->post('outcome')->textarea(),
            'priority' => $request->post('priority')->filter('a-z'),
            'reminder_at' => $request->post('reminder_at')->date(),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        return $save;
    }

    /**
     * Validate activity fields for required and format validation
     *
     * @param Request $request HTTP request
     * @param array &$save Save data (modified by reference)
     * @param object $activity Existing activity
     * @param object $db Database connection
     *
     * @return array Validation errors
     */
    protected function validateFields($request, &$save, $activity, $db)
    {
        $errors = [];

        // Valid type values
        $validTypes = ['call', 'meeting', 'email', 'task', 'note', 'lunch', 'demo', 'follow_up'];

        // Valid status values
        $validStatuses = ['scheduled', 'completed', 'cancelled', 'no_show'];

        // Valid priority values
        $validPriorities = ['low', 'medium', 'high'];

        // Validate subject (required)
        if (empty($save['subject'])) {
            $errors['activity_subject'] = 'Please fill in';
        }

        // Validate type (required and must be valid)
        if (empty($save['type'])) {
            $errors['activity_type'] = 'Please select a type';
        } elseif (!in_array($save['type'], $validTypes)) {
            $errors['activity_type'] = 'Invalid type value';
        }

        // Validate status (if provided, must be valid)
        if (!empty($save['status']) && !in_array($save['status'], $validStatuses)) {
            $errors['activity_status'] = 'Invalid status value';
        }

        // Validate priority (if provided, must be valid)
        if (!empty($save['priority']) && !in_array($save['priority'], $validPriorities)) {
            $errors['activity_priority'] = 'Invalid priority value';
        }

        // Validate duration_minutes (must be non-negative)
        if ($save['duration_minutes'] !== null && $save['duration_minutes'] < 0) {
            $errors['activity_duration_minutes'] = 'Duration must be non-negative';
        }

        // Validate end_time is after start_time
        if (!empty($save['start_time']) && !empty($save['end_time'])) {
            if (strtotime($save['end_time']) < strtotime($save['start_time'])) {
                $errors['activity_end_time'] = 'End time must be after start time';
            }
        }

        return $errors;
    }
}
