<?php

declare(strict_types=1);

namespace OCA\Mail2Folder\Settings;

use OCA\Mail2Folder\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\Settings\ISettings;

class PersonalSettings implements ISettings
{
    private IConfig $config;
    private string $userId;

    public function __construct(IConfig $config, ?string $userId)
    {
        $this->config = $config;
        $this->userId = $userId ?? '';
    }

    public function getForm(): TemplateResponse
    {
        $appId = Application::APP_ID;
        $imapConfigured = !empty($this->config->getAppValue($appId, 'imap_host', ''));
        $subjectPattern = $this->config->getAppValue($appId, 'subject_pattern', '[{username}]');

        // Build example subject for this user
        $exampleTag = str_replace('{username}', $this->userId, $subjectPattern);

        $params = [
            'user_id' => $this->userId,
            'target_folder' => $this->config->getUserValue(
                $this->userId,
                $appId,
                'target_folder',
                'Mail-Attachments'
            ),
            'imap_configured' => $imapConfigured,
            'subject_tag' => $exampleTag,
            'imap_username' => $this->config->getAppValue($appId, 'imap_username', ''),
        ];

        return new TemplateResponse($appId, 'personal', $params);
    }

    public function getSection(): string
    {
        return 'additional';
    }

    public function getPriority(): int
    {
        return 50;
    }
}
