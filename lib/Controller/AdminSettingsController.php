<?php

declare(strict_types=1);

namespace OCA\Mail2Folder\Controller;

use OCA\Mail2Folder\Service\ImapService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;

class AdminSettingsController extends Controller
{
    private const APP_ID = 'mail2folder';

    private IConfig $config;
    private ImapService $imapService;

    public function __construct(
        string $appName,
        IRequest $request,
        IConfig $config,
        ImapService $imapService
    ) {
        parent::__construct($appName, $request);
        $this->config = $config;
        $this->imapService = $imapService;
    }

    public function save(): JSONResponse
    {
        $keys = [
            'imap_host',
            'imap_port',
            'imap_username',
            'imap_password',
            'imap_encryption',
            'imap_folder',
            'subject_pattern',
            'fallback_user',
            'poll_interval',
            'delete_after_processing',
            'subfolder_mode',
            'save_body',
        ];

        foreach ($keys as $key) {
            $value = $this->request->getParam($key);
            if ($value !== null) {
                if ($key === 'imap_password' && $value === '********') {
                    continue;
                }
                $this->config->setAppValue(self::APP_ID, $key, (string)$value);
            }
        }

        return new JSONResponse(['status' => 'ok']);
    }

    public function test(): JSONResponse
    {
        $host = $this->request->getParam('imap_host', '');
        $port = (int)$this->request->getParam('imap_port', '993');
        $username = $this->request->getParam('imap_username', '');
        $password = $this->request->getParam('imap_password', '');
        $encryption = $this->request->getParam('imap_encryption', 'ssl');

        if ($password === '********') {
            $password = $this->config->getAppValue(self::APP_ID, 'imap_password', '');
        }

        if (empty($host) || empty($username) || empty($password)) {
            return new JSONResponse([
                'status' => 'error',
                'message' => 'Bitte Host, Benutzername und Passwort ausfüllen.',
            ]);
        }

        $result = $this->imapService->testConnection($host, $port, $username, $password, $encryption);

        if ($result === true) {
            return new JSONResponse([
                'status' => 'ok',
                'message' => 'Verbindung erfolgreich!',
            ]);
        }

        return new JSONResponse([
            'status' => 'error',
            'message' => (string)$result,
        ]);
    }
}
