<?php
/**
 * @filesource modules/crm/controllers/activities.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 */

namespace Crm\Activities;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;

/**
 * API Activities Controller
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
    protected $allowedSortColumns = ['id', 'type', 'subject', 'customer', 'owner', 'start_time', 'status', 'created_at'];

    /**
     * @var array
     */
    public static $typeIcons = [
        'call' => 'icon-phone',
        'meeting' => 'icon-event',
        'email' => 'icon-email',
        'task' => 'icon-list',
        'note' => 'icon-file',
        'lunch' => 'icon-food',
        'demo' => 'icon-published1',
        'follow_up' => 'icon-clock'
    ];

    /**
     * @var array
     */
    public static $statusBadges = [
        'scheduled' => 'status0',
        'completed' => 'status2',
        'cancelled' => 'status3',
        'no_show' => 'status4'
    ];

    /**
     * @var array
     */
    public static $priorityBadges = [
        'low' => 'status3',
        'medium' => 'status1',
        'high' => 'status4'
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
            'status' => $request->get('status')->filter('a-z_'),
            'type' => $request->get('type')->filter('a-z_')
        ];
    }

    /**
     * Check authorization for activity management
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
        return \Crm\Activities\Model::toDataTable($params);
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
            'status' => self::getStatusOptions(),
            'type' => self::getTypeOptions()
        ];
    }

    /**
     * Handle edit action (redirect to activity form)
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

        return $this->redirectResponse('/activity?id='.$id);
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
        $removeCount = \Crm\Activities\Model::remove($ids);

        if (empty($removeCount)) {
            return $this->errorResponse('Delete action failed', 400);
        }

        \Index\Log\Model::add(0, 'Crm', 'Delete Activity ID(s) : '.implode(', ', $ids), $login->id);

        return $this->redirectResponse('reload', 'Deleted '.$removeCount.' activity(ies) successfully');
    }

    /**
     * Handle status action (status|scheduled, status|completed, etc.)
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
        $status = $request->request('status')->filter('a-z_');

        $validStatuses = ['scheduled', 'completed', 'cancelled', 'no_show'];
        if (!in_array($status, $validStatuses)) {
            return $this->errorResponse('Invalid status', 400);
        }

        $updateCount = \Crm\Activities\Model::updateStatus($ids, $status);

        if (empty($updateCount)) {
            return $this->errorResponse('Update status failed', 400);
        }

        \Index\Log\Model::add(0, 'Crm', 'Update Activity ID(s) : '.implode(', ', $ids).' to status : '.$status, $login->id);

        return $this->redirectResponse('reload', 'Updated '.$updateCount.' activity(ies) successfully');
    }

    /**
     * GET /crm/activities/recent
     * Get recent activities for dashboard
     *
     * @param Request $request
     *
     * @return Response
     */
    public function recent(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');

            $result = \Crm\Activities\Model::recent(5);

            return $this->successResponse([
                'data' => $result
            ], 'Recent activities retrieved successfully');

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
            $options[] = ['value' => $status, 'text' => ucfirst(str_replace('_', ' ', $status))];
        }
        return $options;
    }

    /**
     * Get type options
     *
     * @return array
     */
    public static function getTypeOptions()
    {
        $options = [];
        foreach (self::$typeIcons as $type => $icon) {
            $options[] = ['value' => $type, 'text' => ucfirst(str_replace('_', ' ', $type))];
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

    /**
     * Get type icon
     *
     * @param string $type
     *
     * @return string
     */
    public static function getTypeIcon($type)
    {
        return self::$typeIcons[$type] ?? '';
    }
}
