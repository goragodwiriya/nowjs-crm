<?php
/**
 * @filesource modules/crm/controllers/contact.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Crm\Contact;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;

/**
 * API Contact Controller
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * GET /api/crm/contact/get
     * Get contact details by ID
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
            $data = \Crm\Contact\Model::get($id);
            if (!$data) {
                return $this->redirectResponse('/404', 'No data available', 404);
            }

            return $this->successResponse([
                'data' => $data,
                'options' => [
                    'customers' => \Crm\Customers\Model::getCustomerOptions(),
                    'owners' => \Crm\Users\Model::getOwnerOptions()
                ]
            ], 'Contact details retrieved');

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * POST /api/crm/contact/save
     * Save contact details (create or update)
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
            $contact = \Crm\Contact\Model::get($request->post('id')->toInt());

            if (!$contact) {
                return $this->errorResponse('No data available', 404);
            }

            if ($contact->id === 0) {
                // New contact
                $save['created_at'] = date('Y-m-d H:i:s');
            }

            $db = \Kotchasan\DB::create();

            // Validate contact fields
            $errors = $this->validateFields($request, $save, $contact, $db);

            if (empty($errors)) {
                // Save contact
                $id = \Crm\Contact\Model::save($db, $contact->id, $save);

                // Log
                \Index\Log\Model::add($id, 'Crm', 'Saved contact: '.$save['first_name'].' '.$save['last_name'], $login->id);

                // Redirect to contacts list
                return $this->redirectResponse('/contacts', 'Saved successfully');
            }

            // Error response
            return $this->formErrorResponse($errors, 400);

        } catch (\Exception $e) {
            // Error response
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Parse contact input from request
     *
     * @param Request $request
     * @return array
     */
    protected function parseInput(Request $request): array
    {
        // Prepare data for saving
        $save = [
            'customer_id' => $request->post('customer_id')->toInt(),
            'first_name' => $request->post('first_name')->topic(),
            'last_name' => $request->post('last_name')->topic(),
            'email' => $request->post('email')->email(),
            'phone' => $request->post('phone')->number(),
            'mobile' => $request->post('mobile')->number(),
            'job_title' => $request->post('job_title')->topic(),
            'department' => $request->post('department')->topic(),
            'linkedin' => $request->post('linkedin')->url(),
            'is_primary' => $request->post('is_primary')->toInt(),
            'is_decision_maker' => $request->post('is_decision_maker')->toInt(),
            'status' => $request->post('status')->filter('a-z'),
            'owner_id' => $request->post('owner_id')->toInt(),
            'notes' => $request->post('notes')->textarea(),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        return $save;
    }

    /**
     * Validate contact fields for required and format validation
     *
     * @param Request $request HTTP request
     * @param array &$save Save data (modified by reference)
     * @param object $contact Existing contact
     * @param object $db Database connection
     *
     * @return array Validation errors
     */
    protected function validateFields($request, &$save, $contact, $db)
    {
        $errors = [];

        // Valid status values
        $validStatuses = ['active', 'inactive'];

        // Validate first_name (required)
        if (empty($save['first_name'])) {
            $errors['contact_first_name'] = 'Please fill in';
        }

        // Validate customer_id (required)
        if (empty($save['customer_id'])) {
            $errors['contact_customer_id'] = 'Please select a customer';
        }

        // Validate email format (if provided)
        if (!empty($save['email']) && !filter_var($save['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['contact_email'] = 'Invalid email format';
        }

        // Validate email uniqueness (if provided)
        if (!empty($save['email'])) {
            $exists = $db->first('contacts', [
                ['email', $save['email']],
                ['id', '!=', $contact->id]
            ]);
            if ($exists) {
                $errors['contact_email'] = 'This email already exists';
            }
        }

        // Validate LinkedIn URL format (if provided)
        if (!empty($save['linkedin']) && !filter_var($save['linkedin'], FILTER_VALIDATE_URL)) {
            $errors['contact_linkedin'] = 'Invalid URL format';
        }

        // Validate status (if provided, must be valid)
        if (!empty($save['status']) && !in_array($save['status'], $validStatuses)) {
            $errors['contact_status'] = 'Invalid status value';
        }

        // Validate is_primary (0 or 1)
        if ($save['is_primary'] < 0 || $save['is_primary'] > 1) {
            $errors['contact_is_primary'] = 'Invalid value';
        }

        // Validate is_decision_maker (0 or 1)
        if ($save['is_decision_maker'] < 0 || $save['is_decision_maker'] > 1) {
            $errors['contact_is_decision_maker'] = 'Invalid value';
        }

        return $errors;
    }
}
