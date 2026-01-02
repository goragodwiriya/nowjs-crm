<?php
/**
 * @filesource modules/crm/controllers/contacts.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Crm\Contacts;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;

/**
 * API Contacts Controller
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
    protected $allowedSortColumns = ['id', 'first_name', 'last_name', 'customer', 'email', 'status', 'created_at'];

    /**
     * @var array
     */
    public static $statusBadges = [
        'active' => 'icon-valid color-red',
        'inactive' => 'icon-invalid color-silver'
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
            'status' => $request->get('status')->filter('a-z'),
            'customer_id' => $request->get('customer_id')->toInt()
        ];
    }

    /**
     * Check authorization for contact management
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
        return \Crm\Contacts\Model::toDataTable($params);
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

        return $this->redirectResponse('/contact?id='.$id);
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
        $removeCount = \Crm\Contacts\Model::remove($ids);

        if (empty($removeCount)) {
            return $this->errorResponse('Delete action failed', 400);
        }

        \Index\Log\Model::add(0, 'Crm', 'Delete Contact ID(s) : '.implode(', ', $ids), $login->id);

        return $this->redirectResponse('reload', 'Deleted '.$removeCount.' contact(s) successfully');
    }

    /**
     * Handle status action (status|active, status|inactive)
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

        if (!in_array($status, ['active', 'inactive'])) {
            return $this->errorResponse('Invalid status', 400);
        }

        $updateCount = \Crm\Contacts\Model::updateStatus($ids, $status);

        if (empty($updateCount)) {
            return $this->errorResponse('Update status failed', 400);
        }

        \Index\Log\Model::add(0, 'Crm', 'Update Contact ID(s) : '.implode(', ', $ids).' to status : '.$status, $login->id);

        return $this->redirectResponse('reload', 'Updated '.$updateCount.' contact(s) successfully');
    }

    /**
     * GET /crm/contacts/recent
     * Get recent contacts for dashboard
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

            $result = \Crm\Contacts\Model::recent(5);

            $data = [];
            foreach ($result as $row) {
                $row->status_badge = self::$statusBadges[$row->status] ?? 'status3';
                $row->name = $row->first_name.' '.$row->last_name;
                $data[] = $row;
            }

            // return response
            return $this->successResponse([
                'data' => $data
            ], 'Recent contacts retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get status options
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
}
