<?php
/**
 * @filesource modules/crm/controllers/deals.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Crm\Deals;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;

/**
 * API v1 Deals Controller - Mock Data Only
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
    protected $allowedSortColumns = ['id', 'title', 'customer', 'value', 'stage', 'owner', 'expected_close_date', 'created_at'];

    /**
     * @var array
     */
    static $stages = ['qualified', 'won', 'negotiation', 'lost', 'lead', 'proposal'];

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
            'stage' => $request->get('stage')->filter('a-z')
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
        return \Crm\Deals\Model::toDataTable($params);
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
            'stage' => self::getStageOptions()
        ];
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
        $removeCount = \Crm\Deals\Model::remove($ids);

        if (empty($removeCount)) {
            return $this->errorResponse('Delete action failed', 400);
        }

        \Index\Log\Model::add(0, 'Crm', 'Delete Deal ID(s) : '.implode(', ', $ids), $login->id);

        return $this->redirectResponse('reload', 'Deleted '.$removeCount.' deal(s) successfully');
    }

    /**
     * Handle stage action
     *
     * @param Request $request
     * @param object $login
     *
     * @return array
     */
    protected function handleStageAction(Request $request, $login)
    {
        if (!ApiController::canModify($login, ['can_manage_crm', 'can_view_crm'])) {
            return $this->errorResponse('Failed to process request', 403);
        }

        $ids = $request->request('ids', [])->toInt();
        $stage = $request->request('stage')->filter('a-z');

        if (!in_array($stage, self::$stages)) {
            return $this->errorResponse('Invalid stage', 400);
        }

        $updateCount = \Crm\Deals\Model::updateStage($ids, $stage);

        if (empty($updateCount)) {
            return $this->errorResponse('Update action failed', 400);
        }

        \Index\Log\Model::add(0, 'Crm', 'Update Deal ID(s) : '.implode(', ', $ids).' to stage : '.$stage, $login->id);

        return $this->redirectResponse('reload', 'Updated '.$updateCount.' deal(s) successfully');
    }

    /**
     * Handle edit action
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

        return $this->redirectResponse('/deal?id='.$id);
    }

    /**
     * Handle update action
     *
     * @param Request $request
     *
     * @return array
     */
    protected function update(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);

            // Authentication check (required)
            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }
            if (!ApiController::hasPermission($login, ['can_manage_crm', 'can_view_crm'])) {
                return $this->errorResponse('Failed to process request', 403);
            }

            $id = $request->request('id')->toInt();
            $stage = $request->request('stage')->filter('a-z');

            if (!in_array($stage, self::$stages)) {
                return $this->errorResponse('Invalid stage', 400);
            }

            $updateCount = \Crm\Deals\Model::updateStage([$id], $stage);

            if (empty($updateCount)) {
                return $this->errorResponse('Update action failed', 400);
            }

            \Index\Log\Model::add(0, 'Crm', 'Update Deal ID: '.$id.' to stage : '.$stage, $login->id);

            return $this->notificationResponse('Saved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Return the CSS status class for a deal stage.
     *
     * This method finds the index of the given stage in {@see self::$stages}
     * and returns a CSS class in the form `status{index}`. If the stage is
     * not found the method returns `status4` (lead) as a safe default.
     *
     * @param string $stage Stage identifier (e.g. 'lead', 'won')
     *
     * @return string CSS class name for the stage (e.g. 'status0')
     */
    public static function getStageClass(string $stage): string
    {
        $index = array_search($stage, self::$stages, true);
        return $index === false ? 'status4' : 'status'.$index;
    }

    /**
     * GET /crm/deals/won
     * Get recent won deals (Dashboard)
     *
     * @param Request $request
     * @return Response
     */
    public function won(Request $request)
    {
        try {
            // Validate request method and CSRF token
            ApiController::validateMethod($request, 'GET');

            $result = \Crm\Deals\Model::won(5);

            // Format response
            $response = [
                'data' => $result
            ];

            return $this->successResponse($response, 'Recent won deals retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * GET /crm/deals/recent
     * Get recent deals (Dashboard)
     *
     * @param Request $request
     * @return Response
     */
    public function recent(Request $request)
    {
        try {
            // Validate request method and CSRF token
            ApiController::validateMethod($request, 'GET');

            $result = \Crm\Deals\Model::recent(5);

            // Format response
            $response = [
                'data' => $result
            ];

            return $this->successResponse($response, 'Recent deals retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Get stage options
     *
     * @return array
     */
    public static function getStageOptions()
    {
        $options = [];
        foreach (self::$stages as $stage) {
            $options[] = ['value' => $stage, 'text' => ucwords($stage)];
        }
        return $options;
    }
}
