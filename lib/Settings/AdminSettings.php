<?php

declare(strict_types=1);

namespace OCA\Mail2Folder\Settings;

use OCA\Mail2Folder\AppInfo\Application;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\Settings\ISettings;

class AdminSettings implements ISettings
{
    private IConfig $config;

    public function __construct(IConfig $config)
    {
        $this->config = $config;
    }

    public function getForm(): TemplateResponse
    {
        $appId = Application::APP_ID;

        $params = [
            'imap_host' => $this->config->getAppValue($appId, 'imap_host', 'imap.ionos.de'),
            'imap_port' => $this->config->getAppValue($appId, 'imap_port', '993'),
            'imap_username' => $this->config->getAppValue($appId, 'imap_username', ''),
            'imap_password_set' => !empty($this->config->getAppValue($appId, 'imap_password', '')),
            'imap_encryption' => $this->config->getAppValue($appId, 'imap_encryption', 'ssl'),
            'imap_folder' => $this->config->getAppValue($appId, 'imap_folder', 'INBOX'),
            'subject_pattern' => $this->config->getAppValue($appId, 'subject_pattern', '[{username}]'),
            'fallback_user' => $this->config->getAppValue($appId, 'fallback_user', ''),
            'poll_interval' => $this->config->getAppValue($appId, 'poll_interval', '300'),
            'delete_after_processing' => $this->config->getAppValue($appId, 'delete_after_processing', 'no'),
            'subfolder_mode' => $this->config->getAppValue($appId, 'subfolder_mode', 'date'),
            'save_body' => $this->config->getAppValue($appId, 'save_body', 'no'),
        ];

        return new TemplateResponse($appId, 'admin', $params);
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
