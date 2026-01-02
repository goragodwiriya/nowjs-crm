<?php
/**
 * @filesource modules/crm/controllers/fileupload.php
 *
 * File Management API Controller
 *
 * @copyright 2025 Goragod.com
 * @license https://www.kotchasan.com/license/
 */

namespace Crm\Fileupload;

use Kotchasan\ApiController;
use Kotchasan\Http\Request;

/**
 * API v1 Fileupload Controller
 *
 * Handles file upload, download, and management
 *
 * @author Goragod Wiriya <admin@goragod.com>
 * @since 1.0
 */
class Controller extends \Kotchasan\ApiController
{
    /**
     * POST /crm/fileupload/upload
     * Upload file
     *
     * @param Request $request
     * @return mixed
     */
    public function upload(Request $request)
    {
        ApiController::validateMethod($request, 'POST');

        try {
            // ค่าที่ส่งมา
            $id = $request->post('id')->toInt();
            $name = $request->post('name')->topic();

            // ไฟล์ที่ส่งมา
            $uploadedFiles = $request->getUploadedFiles();
            $avatar = isset($uploadedFiles['avatar']) ? $uploadedFiles['avatar'] : null;
            $otherFiles = isset($uploadedFiles['files']) ? $uploadedFiles['files'] : null;

            // ตรวจสอบ avatar - รูปภาพเท่านั้น
            $avatarData = [];
            $allowedImageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

            if ($avatar && is_object($avatar) && method_exists($avatar, 'getClientMediaType')) {
                try {
                    $avatarType = $avatar->getClientMediaType();
                    $avatarSize = $avatar->getSize();
                    $avatarName = $avatar->getClientFilename();

                    if ($avatarType && in_array($avatarType, $allowedImageTypes)) {
                        $avatarData[] = [
                            'size' => \Kotchasan\Text::formatFileSize($avatarSize),
                            'type' => $avatarType,
                            'name' => $avatarName
                        ];
                    }
                } catch (\Exception $e) {
                    // Skip invalid avatar file
                }
            }

            // กรองเฉพาะไฟล์รูปภาพและ PDF
            $files = [];
            $allowedTypes = [
                'image/jpeg',
                'image/jpg',
                'image/png',
                'image/gif',
                'image/webp',
                'application/pdf'
            ];

            if ($otherFiles) {
                // ตรวจสอบว่าเป็น array หรือ single object
                $fileList = is_array($otherFiles) ? $otherFiles : [$otherFiles];

                foreach ($fileList as $file) {
                    if ($file && is_object($file) && method_exists($file, 'getClientMediaType')) {
                        try {
                            $fileType = $file->getClientMediaType();
                            $fileSize = $file->getSize();
                            $fileName = $file->getClientFilename();

                            // ตรวจสอบว่าเป็นรูปภาพหรือ PDF
                            if ($fileType && in_array($fileType, $allowedTypes)) {
                                $files[] = [
                                    'size' => \Kotchasan\Text::formatFileSize($fileSize),
                                    'type' => $fileType,
                                    'name' => $fileName
                                ];
                            }
                        } catch (\Exception $e) {
                            // Skip invalid file
                            continue;
                        }
                    }
                }
            }
            $actions = [
                [
                    'type' => 'notification',
                    'message' => 'Upload complete!'
                ],
                [
                    'type' => 'render',
                    'target' => '#upload-output',
                    'template' => 'upload-result.html'
                ]
            ];
            return $this->successResponse([
                'id' => $id,
                'name' => $name,
                'avatar' => $avatarData,
                'files' => $files,
                'actions' => $actions
            ], 'File uploaded successfully', 201);

        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    /**
     * GET /crm/fileupload
     * List user files
     *
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        ApiController::validateMethod($request, 'GET');

        $data = [
            'id' => 1,
            'name' => 'John Doe & Friends',
            'avatar' => [
                [
                    'url' => 'files/sample.jpg',
                    'name' => 'sample.jpg'
                ]
            ],
            'files' => [
                [
                    'url' => 'files/sample.jpg',
                    'name' => 'sample.jpg'
                ],
                [
                    'url' => 'files/sample.pdf',
                    'name' => 'sample.pdf'
                ]
            ]
        ];

        return $this->successResponse($data, 'Files retrieved successfully');
    }
}
