<?php
/**
 * @filesource modules/index/controllers/languages.php
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 *
 * @see https://www.kotchasan.com/
 */

namespace Index\Languages;

use Gcms\Api as ApiController;
use Kotchasan\Http\Request;
use Kotchasan\Http\Response;

/**
 * API Language Controller
 *
 * Handles language management endpoints
 *
 * @author Goragod Wiriya <admin@goragod.com>
 *
 * @since 1.0
 */
class Controller extends \Gcms\Table
{

    /**
     * Allowed sort columns for SQL injection prevention
     *
     * @var array
     */
    protected $allowedSortColumns = ['id', 'key', 'th', 'en'];

    /**
     * Get custom parameters for data table
     *
     * @param Request $request
     * @param object $login
     *
     * @return array
     */
    protected function getCustomParams(Request $request, $login = null): array
    {
        return [
            'js' => $request->get('js')->toInt()
        ];
    }

    /**
     * Query data to send to DataTable
     *
     * @param array $params
     * @param object $login
     *
     * @return \Kotchasan\QueryBuilder\QueryBuilderInterface
     */
    protected function toDataTable($params = [], $login = null)
    {
        return \Index\Languages\Model::toDataTable($params);
    }

    /**
     * Get filters for table response
     *
     * @param array $params
     * @param object $login
     *
     * @return array
     */
    protected function getFilters($params = [], $login = null)
    {
        return [
            'status' => \Index\Helper\Model::getUserStatusOptions()
        ];
    }

    /**
     * Get columns for table response
     *
     * @param array $params
     * @param object $login
     *
     * @return array
     */
    protected function getColumns($params = [], $login = null)
    {
        // Fixed columns (always present)
        $columns = [
            [
                'field' => 'id',
                'label' => 'ID',
                'sort' => 'id',
                'type' => 'number',
                'i18n' => true
            ],
            [
                'field' => 'key',
                'label' => 'Key',
                'sort' => 'key',
                'searchable' => true,
                'i18n' => true
            ]
        ];

        $language = \Kotchasan\DB::create()->first('language');
        foreach ($language as $key => $value) {
            if (!in_array($key, ['id', 'key', 'type', 'js'])) {
                $columns[] = [
                    'field' => $key,
                    'label' => strtoupper($key),
                    'sort' => $key,
                    'i18n' => true
                ];
            }
        }

        return $columns;
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
        if (!ApiController::isSuperAdmin($login)) {
            return $this->errorResponse('Failed to process request', 403);
        }

        $ids = $request->request('ids', [])->toInt();
        $removeCount = \Index\Languages\Model::remove($ids);

        if (empty($removeCount)) {
            return $this->errorResponse('Delete action failed', 400);
        }

        \Index\Log\Model::add(0, 'Index', 'Delete Language ID(s) : '.implode(', ', $ids), $login->id);

        return $this->redirectResponse('reload', 'Deleted '.$removeCount.' language(s) successfully');
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
        $id = $request->post('id')->toInt();
        return $this->redirectResponse('/language?id='.$id);
    }

    /**
     * Handle import action - Import translations from th.json file
     *
     * @param Request $request
     * @param object $login
     *
     * @return Response
     */
    protected function handleImportAction(Request $request, $login)
    {
        if (!ApiController::isSuperAdmin($login)) {
            return $this->errorResponse('Failed to process request', 403);
        }

        $result = \Index\Languages\Model::importFromFile();

        if ($result['success']) {
            \Index\Log\Model::add(0, 'Index', 'Import Language: '.$result['message'], $login->id);
            return $this->redirectResponse('reload', $result['message']);
        }

        return $this->errorResponse($result['message'], 400);
    }
}
