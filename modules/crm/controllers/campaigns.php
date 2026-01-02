<?php
/**
 * @filesource modules/crm/controllers/campaigns.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 */

namespace Crm\Campaigns;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;

/**
 * API Campaigns Controller
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
    protected $allowedSortColumns = ['id', 'name', 'type', 'status', 'budget', 'start_date', 'end_date', 'created_at'];

    /**
     * @var array
     */
    public static $typeIcons = [
        'email' => 'icon-email',
        'social' => 'icon-share',
        'event' => 'icon-event',
        'webinar' => 'icon-published1',
        'advertisement' => 'icon-ads',
        'other' => 'icon-file'
    ];

    /**
     * @var array
     */
    public static $statusBadges = [
        'draft' => 'status3',
        'scheduled' => 'status0',
        'active' => 'status2',
        'paused' => 'status1',
        'completed' => 'status5',
        'cancelled' => 'status4'
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
            'type' => $request->get('type')->filter('a-z')
        ];
    }

    /**
     * Check authorization for campaign management
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
        return \Crm\Campaigns\Model::toDataTable($params);
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
     * Handle edit action (redirect to campaign form)
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

        return $this->redirectResponse('/campaign?id='.$id);
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
        $removeCount = \Crm\Campaigns\Model::remove($ids);

        if (empty($removeCount)) {
            return $this->errorResponse('Delete action failed', 400);
        }

        \Index\Log\Model::add(0, 'Crm', 'Delete Campaign ID(s) : '.implode(', ', $ids), $login->id);

        return $this->redirectResponse('reload', 'Deleted '.$removeCount.' campaign(s) successfully');
    }

    /**
     * Handle status action (status|draft, status|active, etc.)
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

        $validStatuses = ['draft', 'scheduled', 'active', 'paused', 'completed', 'cancelled'];
        if (!in_array($status, $validStatuses)) {
            return $this->errorResponse('Invalid status', 400);
        }

        $updateCount = \Crm\Campaigns\Model::updateStatus($ids, $status);

        if (empty($updateCount)) {
            return $this->errorResponse('Update status failed', 400);
        }

        \Index\Log\Model::add(0, 'Crm', 'Update Campaign ID(s) : '.implode(', ', $ids).' to status : '.$status, $login->id);

        return $this->redirectResponse('reload', 'Updated '.$updateCount.' campaign(s) successfully');
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
            $options[] = ['value' => $status, 'text' => ucfirst($status)];
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
