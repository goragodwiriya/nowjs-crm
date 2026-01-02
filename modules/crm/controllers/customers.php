<?php
/**
 * @filesource modules/crm/controllers/customers.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Crm\Customers;

use Gcms\Api as ApiController;
use Kotchasan\Currency;
use Kotchasan\Date;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;

/**
 * API v1 Customers Controller - Mock Data Only
 *
 * Handles user management endpoints with 100 mock users
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Table
{
    /**
     * Allowed sort columns
     *
     * @var array
     */
    protected $allowedSortColumns = ['id', 'name', 'status', 'source', 'rating', 'annual_revenue', 'employee_count', 'created_at', 'updated_at'];

    /**
     * @var array
     */
    public static $icons = [
        'website' => 'icon-world',
        'referral' => 'icon-link',
        'cold_call' => 'icon-phone',
        'advertisement' => 'icon-ads',
        'trade_show' => 'icon-product',
        'social_media' => 'icon-share',
        'other' => 'icon-file'
    ];

    /**
     * @var array
     */
    public static $statusBadges = [
        'lead' => 'status0',
        'prospect' => 'status1',
        'customer' => 'status2',
        'inactive' => 'status3',
        'churned' => 'status4'
    ];

    /**
     * Get custom parameters for table
     *
     * @param Request $request
     * @param object $login
     *
     * @return array
     */
    protected function getCustomParams(Request $request, $login): array
    {
        return [
            'active' => $request->get('active')->topic(),
            'status' => $request->get('status')->topic()
        ];
    }

    /**
     * Check authorization for user management
     * Only admins can access, demo mode is blocked
     *
     * @param Request $request
     * @param $login
     *
     * @return mixed
     */
    protected function checkAuthorization(Request $request, $login)
    {
        if (!ApiController::hasPermission($login, ['can_manage_crm', 'can_view_crm'])) {
            return $this->errorResponse('Admin permission required', 403);
        }

        return true;
    }

    /**
     * Query data to send to DataTable
     *
     * @param array $params
     * @param object $login
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    protected function toDataTable($params, $login = null)
    {
        return \Crm\Customers\Model::toDataTable($params);
    }

    /**
     * Get filters for table response
     *
     * @param array $params
     * @param object $login
     *
     * @return array
     */
    protected function getFilters($params, $login = null)
    {
        return [
            'status' => self::getStatusOptions()
        ];
    }

    /**
     * Handle edit action (redirect to contact form)
     *
     * @param Request $request
     * @param object $login
     *
     * @return array
     */
    protected function handleEditAction(Request $request, $login)
    {
        if (!ApiController::hasPermission($login, ['can_manage_crm', 'can_view_crm'])) {
            return $this->errorResponse('Failed to process request', 403);
        }

        $id = $request->post('id')->toInt();

        return $this->redirectResponse('/customer?id='.$id);
    }

    /**
     * Handle delete action
     *
     * @param Request $request
     * @param object $login
     *
     * @return array
     */
    protected function handleDeleteAction(Request $request, $login)
    {
        if (!ApiController::canModify($login, ['can_manage_crm', 'can_view_crm'])) {
            return $this->errorResponse('Failed to process request', 403);
        }

        $ids = $request->request('ids', [])->toInt();
        $removeCount = \Crm\Customers\Model::remove($ids);

        if (empty($removeCount)) {
            return $this->errorResponse('Delete action failed', 400);
        }

        \Index\Log\Model::add(0, 'Crm', 'Delete Customer ID(s) : '.implode(', ', $ids), $login->id);

        return $this->redirectResponse('reload', 'Deleted '.$removeCount.' customer(s) successfully');
    }

    /**
     * Handle status action
     *
     * @param Request $request
     * @param object $login
     *
     * @return array
     */
    protected function handleStatusAction(Request $request, $login)
    {
        if (!ApiController::canModify($login, ['can_manage_crm', 'can_view_crm'])) {
            return $this->errorResponse('Failed to process request', 403);
        }

        $ids = $request->request('ids', [])->toInt();
        $status = $request->request('status')->filter('a-z');

        if (!isset(self::$statusBadges[$status])) {
            return $this->errorResponse('Invalid stage', 400);
        }

        $updateCount = \Crm\Customers\Model::updateStatus($ids, $status);

        if (empty($updateCount)) {
            return $this->errorResponse('Update action failed', 400);
        }

        \Index\Log\Model::add(0, 'Crm', 'Update Customer ID(s) : '.implode(', ', $ids).' to : '.ucwords($status), $login->id);

        return $this->redirectResponse('reload', 'Updated '.$updateCount.' customer(s) successfully');
    }

    /**
     * Handle edit action
     *
     * @param Request $request
     * @param object $login
     *
     * @return array
     */
    protected function handleViewAction(Request $request, $login)
    {
        if (!ApiController::hasPermission($login, ['can_manage_crm', 'can_view_crm'])) {
            return $this->errorResponse('Failed to process request', 403);
        }

        $id = $request->post('id')->toInt();

        // get customer for view
        $customer = \Crm\Customers\Model::view($id);

        // check customer
        if (!$customer) {
            return $this->errorResponse('Customer not found', 404);
        }

        // format data
        $customer->annual_revenue = Currency::format($customer->annual_revenue);
        $customer->rating = str_repeat('⭐', $customer->rating);
        $customer->source = ucwords(str_replace('_', ' ', $customer->source));
        $customer->status = ucwords($customer->status);
        $customer->created_at = Date::format($customer->created_at, 'd M Y H:i');
        $customer->updated_at = Date::format($customer->updated_at, 'd M Y H:i');

        // return response to modal
        return $this->successResponse([
            'data' => $customer,
            'actions' => [
                'type' => 'modal',
                'title' => 'Customer Details',
                'dataKey' => 'data'
            ]
        ], 'Customer action completed');
    }

    /**
     * GET /crm/customers/recent
     * Get recent customers (for Dashboard)
     *
     * @param Request $request
     *
     * @return Response
     */
    public function recent(Request $request)
    {
        try {
            // Validate request method and CSRF token
            ApiController::validateMethod($request, 'GET');

            // get recent customers
            $result = \Crm\Customers\Model::recent(5);

            // return response
            return $this->successResponse([
                'data' => $result
            ], 'Recent customers retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get icon source
     *
     * @param string $source
     *
     * @return string
     */
    public static function getIconSource($source)
    {
        return self::$icons[$source] ?? '';
    }

    /**
     * Get status badge
     *
     * @param string $status
     *
     * @return string
     */
    public static function getStatusBadge($status)
    {
        return self::$statusBadges[$status] ?? '';
    }

    /**
     * Get stage options
     *
     * @return array
     */
    public static function getStatusOptions()
    {
        $options = [];
        foreach (self::$statusBadges as $status => $class) {
            $options[] = ['value' => $status, 'text' => ucwords($status)];
        }
        return $options;
    }
}
