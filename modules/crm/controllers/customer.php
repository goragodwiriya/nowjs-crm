<?php
/**
 * @filesource modules/crm/controllers/customer.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Crm\Customer;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;

/**
 * API Customers Controller
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends ApiController
{
    /**
     * GET /api/crm/customer/get
     * Get customer details by ID
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
            $data = \Crm\Customer\Model::get($id);
            if (!$data) {
                return $this->redirectResponse('/404', 'No data available', 404);
            }

            return $this->successResponse([
                'data' => $data,
                'options' => [
                    'status' => \Crm\Helper\Model::getCustomerStatusOptions(),
                    'source' => \Crm\Helper\Model::getCustomerSourceOptions(),
                    'province' => \Crm\Helper\Model::getProvinceOptions(),
                    'owners' => \Crm\Users\Model::getOwnerOptions()
                ]
            ], 'Customer details retrieved');

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * POST /api/crm/customer/save
     * Save customer details (create or update)
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
            $customer = \Crm\Customer\Model::get($request->post('id')->toInt());

            if (!$customer) {
                return $this->errorResponse('No data available', 404);
            }

            if ($customer->id === 0) {
                // New customer
                $save['created_at'] = date('Y-m-d H:i:s');
            }

            $db = \Kotchasan\DB::create();

            // Validate customer fields
            $errors = $this->validateFields($request, $save, $customer, $db);

            if (empty($errors)) {
                // Save customer
                $id = \Crm\Customer\Model::save($db, $customer->id, $save);

                // Log
                \Index\Log\Model::add($id, 'Crm', 'Saved customer: '.$save['name'], $login->id);

                // Redirect to customers list
                return $this->redirectResponse('/customers', 'Saved successfully');
            }

            // Error response
            return $this->formErrorResponse($errors, 400);

        } catch (\Exception $e) {
            // Error response
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Parse customer input from request
     *
     * @param Request $request
     * @return array
     */
    protected function parseInput(Request $request): array
    {
        // Prepare data for saving
        $save = [
            'name' => $request->post('name')->topic(),
            'company_type' => $request->post('company_type')->filter('a-z'),
            'industry' => $request->post('industry')->topic(),
            'email' => $request->post('email')->email(),
            'website' => $request->post('website')->url(),
            'phone' => $request->post('phone')->number(),
            'fax' => $request->post('fax')->number(),
            'address' => $request->post('address')->textarea(),
            'provinceID' => $request->post('provinceID')->toInt(),
            'zipcode' => $request->post('zipcode')->number(),
            'tax_id' => $request->post('tax_id')->number(),
            'rating' => $request->post('rating')->toInt(),
            'annual_revenue' => $request->post('annual_revenue')->toDouble(),
            'employee_count' => $request->post('employee_count')->toInt(),
            'source' => $request->post('source')->filter('a-z_'),
            'status' => $request->post('status')->filter('a-z_'),
            'owner_id' => $request->post('owner_id')->toInt(),
            'notes' => $request->post('notes')->textarea(),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        return $save;
    }

    /**
     * Validate customer fields for required and format validation
     *
     * @param Request $request HTTP request
     * @param array &$save Save data (modified by reference)
     * @param object $customer Existing customer
     * @param object $db Database connection
     *
     * @return array Validation errors
     */
    protected function validateFields($request, &$save, $customer, $db)
    {
        $errors = [];

        // Valid status values
        $validStatuses = \Crm\Customers\Controller::$statusBadges;

        // Valid source values
        $validSources = \Crm\Customers\Controller::$icons;

        // Validate name (required)
        if (empty($save['name'])) {
            $errors['customer_name'] = 'Please fill in';
        }

        // Validate email format (if provided)
        if (!empty($save['email']) && !filter_var($save['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['customer_email'] = 'Invalid email format';
        }

        // Validate email uniqueness (if provided)
        if (!empty($save['email'])) {
            $exists = $db->first('customers', [
                ['email', $save['email']],
                ['id', '!=', $customer->id]
            ]);
            if ($exists) {
                $errors['customer_email'] = 'This email already exists';
            }
        }

        // Validate website URL format (if provided)
        if (!empty($save['website']) && !filter_var($save['website'], FILTER_VALIDATE_URL)) {
            $errors['customer_website'] = 'Invalid URL format';
        }

        // Validate rating (0-5)
        if ($save['rating'] < 0 || $save['rating'] > 5) {
            $errors['customer_rating'] = 'Rating must be between 0 and 5';
        }

        // Validate annual_revenue (must be non-negative)
        if ($save['annual_revenue'] < 0) {
            $errors['customer_annual_revenue'] = 'Annual revenue must be non-negative';
        }

        // Validate employee_count (must be non-negative)
        if ($save['employee_count'] < 0) {
            $errors['customer_employee_count'] = 'Employee count must be non-negative';
        }

        // Validate status (if provided, must be valid)
        if (!empty($save['status']) && !isset($validStatuses[$save['status']])) {
            $errors['customer_status'] = 'Invalid status value';
        }

        // Validate source (if provided, must be valid)
        if (!empty($save['source']) && !isset($validSources[$save['source']])) {
            $errors['customer_source'] = 'Invalid source value';
        }

        // Validate tax_id format (if provided, must be 13 digits for Thai tax ID)
        if (!empty($save['tax_id']) && !preg_match('/^[0-9]{12,13}$/', $save['tax_id'])) {
            $errors['customer_tax_id'] = 'Tax ID must be 13 digits';
        }

        // Validate zipcode format (if provided, must be 5 digits for Thai zipcode)
        if (!empty($save['zipcode']) && !preg_match('/^[0-9]{5}$/', $save['zipcode'])) {
            $errors['customer_zipcode'] = 'Zipcode must be 5 digits';
        }

        return $errors;
    }
}
