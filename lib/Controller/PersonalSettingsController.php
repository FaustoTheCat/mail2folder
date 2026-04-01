<?php

declare(strict_types=1);

namespace OCA\Mail2Folder\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;

class PersonalSettingsController extends Controller
{
    private const APP_ID = 'mail2folder';

    private IConfig $config;
    private string $userId;

    public function __construct(
        string $appName,
        IRequest $request,
        IConfig $config,
        ?string $userId
    ) {
        parent::__construct($appName, $request);
        $this->config = $config;
        $this->userId = $userId ?? '';
    }

    /**
     * @NoAdminRequired
     */
    public function save(): JSONResponse
    {
        $targetFolder = $this->request->getParam('target_folder', 'Mail-Attachments');

        $targetFolder = trim($targetFolder, '/ ');
        $targetFolder = preg_replace('/\.\./', '', $targetFolder);

        if (empty($targetFolder)) {
            $targetFolder = 'Mail-Attachments';
        }

        $this->config->setUserValue($this->userId, self::APP_ID, 'target_folder', $targetFolder);

        return new JSONResponse([
            'status' => 'ok',
            'target_folder' => $targetFolder,
        ]);
    }

    /**
     * @NoAdminRequired
     */
    public function getAddress(): JSONResponse
    {
        $subjectPattern = $this->config->getAppValue(self::APP_ID, 'subject_pattern', '[{username}]');
        $exampleTag = str_replace('{username}', $this->userId, $subjectPattern);
        $targetFolder = $this->config->getUserValue(
            $this->userId,
            self::APP_ID,
            'target_folder',
            'Mail-Attachments'
        );

        return new JSONResponse([
            'subject_tag' => $exampleTag,
            'target_folder' => $targetFolder,
            'imap_configured' => !empty($this->config->getAppValue(self::APP_ID, 'imap_host', '')),
        ]);
    }
}
