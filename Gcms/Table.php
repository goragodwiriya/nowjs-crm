<?php
/**
 * @filesource Gcms/Table.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Gcms;

use Kotchasan\ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;

/**
 * Base Table Controller
 *
 * Handles table data endpoints with pagination, sorting, and filtering
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Table extends \Kotchasan\ApiController
{
    /**
     * Allowed sort columns (empty = allow all)
     * Override in subclass to restrict sortable columns
     *
     * @var array
     */
    protected $allowedSortColumns = [];

    /**
     * GET /index/users
     * Get list of data with pagination
     *
     * @param Request $request
     *
     * @return Response
     */
    public function index(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'GET');

            // Authentication check (required)
            $login = $this->authenticateRequest($request);
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }

            // Authorization check (subclass can override)
            $authCheck = $this->checkAuthorization($request, $login);
            if ($authCheck !== true) {
                return $authCheck; // Return error response from checkAuthorization
            }

            $params = $this->parseParams($request, $login);
            $response = $this->executeDataTable($params, $login);
            $data = $this->formatDatas($response['data'], $login);

            return $this->successResponse([
                'data' => $data,
                'columns' => $this->getColumns($params, $login),
                'filters' => $this->getFilters($params, $login),
                'meta' => $response['meta']
            ], 'Data retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }
    /**
     * Check authorization for the current request
     * Override this method in subclass to implement custom authorization logic
     *
     * Examples:
     * - Check if user is admin
     * - Check if demo mode is disabled
     * - Check specific permissions
     *
     * @param Request $request
     * @param object $login Current logged-in user
     *
     * @return true|Response Return true if authorized, or error Response if not
     */
    protected function checkAuthorization(Request $request, $login)
    {
        // Default: allow all authenticated users
        // Override in subclass to add restrictions
        return true;
    }

    /**
     * POST /api/{module}/action
     * Handle bulk actions dynamically
     *
     * Action handlers are discovered by method naming convention:
     * - action 'delete' → handleDeleteAction($request, $login)
     * - action 'send_password' → handleSendPasswordAction($request, $login)
     * - action 'active_2' → handleActive2Action($request, $login)
     *
     * @param Request $request
     *
     * @return Response
     */
    public function action(Request $request)
    {
        try {
            ApiController::validateMethod($request, 'POST');
            $this->validateCsrfToken($request);

            $login = $this->authenticateRequest($request);

            // Check authentication first
            if (!$login) {
                return $this->errorResponse('Unauthorized', 401);
            }

            $action = $request->request('action')->filter('a-z_0-9');

            if (empty($action)) {
                return $this->errorResponse('Action is required', 400);
            }

            // Convert action to method name: delete → handleDeleteAction
            $methodName = 'handle'.$this->actionToMethodName($action).'Action';

            if (method_exists($this, $methodName)) {
                return $this->$methodName($request, $login);
            }

            return $this->errorResponse('Invalid action: '.$action, 400);

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    /**
     * Convert action name to method name part
     * Examples:
     * - delete → Delete
     * - send_password → SendPassword
     * - active_2 → Active2
     *
     * @param string $action
     * @return string
     */
    protected function actionToMethodName(string $action): string
    {
        return str_replace('_', '', ucwords($action, '_'));
    }

    /**
     * Parse table query parameters
     *
     * @param Request $request
     * @param object $login
     *
     * @return array
     */
    protected function parseParams(Request $request, $login): array
    {
        $params = [
            'search' => $request->get('search')->topic(),
            'page' => max(1, $request->get('page', 1)->toInt()),
            'pageSize' => min(100, max(1, $request->get('pageSize', 25)->toInt())),
            'sort' => $request->get('sort')->toString()
        ];

        // Merge custom params from subclass
        return array_merge($params, $this->getCustomParams($request, $login));
    }

    /**
     * Get custom parameters from subclass
     * Override this method to add custom query parameters
     *
     * @param Request $request
     * @param object $login
     *
     * @return array
     */
    protected function getCustomParams(Request $request, $login): array
    {
        return [];
    }

    /**
     * Format data for table response
     * Override this method to add custom data format
     *
     * @param array $datas
     * @param object $login
     *
     * @return array
     */
    protected function formatDatas(array $datas, $login): array
    {
        return $datas;
    }

    /**
     * Method for setting query for DataTable
     * Override this method to add custom query
     *
     * @param array $params
     * @param object $login
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    protected function toDataTable(array $params, $login)
    {
        return \Kotchasan\Model::createQuery();
    }

    /**
     * Get filters for table response
     * Override this method to add custom filters
     *
     * @param array $params
     * @param object $login
     *
     * @return array
     */
    protected function getFilters(array $params, $login)
    {
        return [];
    }

    /**
     * Get columns for table response
     * Override this method to add custom columns
     *
     * @param array $params
     * @param object $login
     *
     * @return array
     */
    protected function getColumns(array $params, $login)
    {
        return [];
    }

    /**
     * Parse sort string into arrays of columns and directions
     * Supports multi-column sort: "name asc,status desc"
     *
     * @param string $sortString
     * @return array ['columns' => [...], 'directions' => [...]]
     */
    protected function parseSort(string $sortString): array
    {
        $columns = [];
        $directions = [];

        if (empty($sortString)) {
            return ['columns' => $columns, 'directions' => $directions];
        }

        // Split by comma for multi-column sort
        $pairs = explode(',', $sortString);

        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if (empty($pair)) {
                continue;
            }

            // Match: column_name [asc|desc]
            if (preg_match('/^([a-z0-9_]+)(?:\s+(asc|desc))?$/i', $pair, $match)) {
                $column = $match[1];
                $direction = isset($match[2]) ? strtolower($match[2]) : 'asc';

                // Validate against allowed columns (SQL injection prevention)
                if (!empty($this->allowedSortColumns) && !in_array($column, $this->allowedSortColumns)) {
                    continue;
                }

                $columns[] = $column;
                $directions[] = $direction;
            }
        }

        return ['columns' => $columns, 'directions' => $directions];
    }

    /**
     * Execute DataTable query
     *
     * @param array $params
     * @param object $login
     *
     * @return array
     */
    protected function executeDataTable(array $params, $login)
    {
        // Parse sort
        $sortData = $this->parseSort($params['sort'] ?? '');
        $sorts = $sortData['columns'];
        $sortOrders = $sortData['directions'];

        // Set page size
        $pageSize = !empty($params['pageSize']) ? min(100, max(1, (int) $params['pageSize'])) : 25;

        // Set page
        $page = !empty($params['page']) ? max(1, (int) $params['page']) : 1;
        $offset = ($page - 1) * $pageSize;

        // Query
        $query = $this->toDataTable($params, $login);

        // Count total records
        $count = $query->copy()
            ->selectCount()
            ->first();

        $totalRecords = $count->count ?? 0;
        $totalPages = $totalRecords > 0 ? ceil($totalRecords / $pageSize) : 1;

        // Auto-correct page if it exceeds total pages
        if ($page > $totalPages) {
            $page = max(1, $totalPages);
            $offset = ($page - 1) * $pageSize;
        }

        // Limit and offset
        $query = $query->limit($pageSize, $offset);

        // Sort (only if columns are specified)
        foreach ($sorts as $key => $sort) {
            $query->orderBy($sort, $sortOrders[$key] ?? 'asc');
        }

        // Returns data for DataTables.
        return [
            'data' => $query->execute()->fetchAll(),
            'meta' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => $totalRecords,
                'totalPages' => $totalPages
            ]
        ];
    }
}
