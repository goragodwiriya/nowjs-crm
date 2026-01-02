<?php
/**
 * @filesource modules/crm/controllers/deal.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Crm\Deal;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;

/**
 * API Deal Controller
 *
 * Handles user management endpoints with 100 mock users
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * GET /api/crm/deal/get
     * Get deal details by ID
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
            $data = \Crm\Deal\Model::get($id);
            if (!$data) {
                return $this->redirectResponse('/404', 'No data available', 404);
            }

            return $this->successResponse([
                'data' => $data,
                'options' => [
                    'customers' => \Crm\Customers\Model::getCustomerOptions(),
                    'contacts' => \Crm\Contacts\Model::getContactOptions(),
                    'owners' => \Crm\Users\Model::getOwnerOptions()
                ]
            ], 'Deal details retrieved');

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * POST /api/crm/deal/save
     * Save deal details (create or update)
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
            $deal = \Crm\Deal\Model::get($request->post('id')->toInt());

            if (!$deal) {
                return $this->errorResponse('No data available', 404);
            }

            if ($deal->id === 0) {
                // New user
                $save['currency'] = 'THB';
                $save['create_date'] = date('Y-m-d H:i:s');
            }

            $db = \Kotchasan\DB::create();

            // Validate login fields
            $errors = $this->validateFields($request, $save, $deal, $db);

            if (empty($errors)) {
                // Save user
                \Crm\Deal\Model::save($db, $deal->id, $save);

                // Log
                \Index\Log\Model::add($save['id'], 'Crm', 'Saved deal: '.$save['title'], $login->id);

                // Redirect to previous page
                return $this->redirectResponse('back', 'Saved successfully');
            }

            // Error response
            return $this->formErrorResponse($errors, 400);

        } catch (\Exception $e) {
            // Error response
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Parse user input from request
     *
     * @param Request $request
     * @return array
     */
    protected function parseInput(Request $request): array
    {
        // Prepare data for saving
        $save = [
            'title' => $request->post('title')->topic(),
            'customer_id' => $request->post('customer_id')->toInt(),
            'contact_id' => $request->post('contact_id')->toInt(),
            'pipeline_id' => $request->post('pipeline_id')->toInt(),
            'stage_id' => $request->post('stage_id')->toInt(),
            'stage' => $request->post('stage')->filter('a-z'),
            'value' => $request->post('value')->toDouble(),
            'probability' => $request->post('probability')->toInt(),
            'expected_close_date' => $request->post('expected_close_date')->date(),
            'actual_close_date' => $request->post('actual_close_date')->date(),
            'lost_reason' => $request->post('lost_reason')->topic(),
            'owner_id' => $request->post('owner_id')->toInt(),
            'source' => $request->post('source')->filter('a-z'),
            'priority' => $request->post('priority')->filter('a-z'),
            'notes' => $request->post('notes')->textarea(),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        return $save;
    }

    /**
     * Validate deal fields for duplicates and required fields
     *
     * @param array &$save Save data (modified by reference)
     * @param object $deal Existing deal
     * @param object $db Database connection
     *
     * @return array
     */
    protected function validateFields($request, &$save, $deal, $db)
    {
        $errors = [];

        // Validate title (required)
        if (empty($save['title'])) {
            $errors['title'] = 'Please fill in';
        }

        // Validate customer_id (required)
        if (empty($save['customer_id'])) {
            $errors['customer_id'] = 'Please select a customer';
        }

        // Validate stage (required)
        if (empty($save['stage'])) {
            $errors['stage'] = 'Please select a stage';
        }

        // Validate value (must be positive)
        if ($save['value'] < 0) {
            $errors['value'] = 'Value must be positive';
        }

        // Validate probability (0-100)
        if ($save['probability'] < 0 || $save['probability'] > 100) {
            $errors['probability'] = 'Probability must be between 0 and 100';
        }

        // Validate expected_close_date (required)
        if (empty($save['expected_close_date'])) {
            $errors['expected_close_date'] = 'Please fill in';
        }

        // Validate owner_id (required)
        if (empty($save['owner_id'])) {
            $errors['owner_id'] = 'Please select an owner';
        }

        return $errors;
    }
}
