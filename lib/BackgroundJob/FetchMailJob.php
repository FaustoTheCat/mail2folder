<?php

declare(strict_types=1);

namespace OCA\Mail2Folder\BackgroundJob;

use OCA\Mail2Folder\Service\MailProcessorService;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class FetchMailJob extends TimedJob
{
    private MailProcessorService $processor;
    private IConfig $config;
    private LoggerInterface $logger;

    public function __construct(
        ITimeFactory $time,
        MailProcessorService $processor,
        IConfig $config,
        LoggerInterface $logger
    ) {
        parent::__construct($time);

        $this->processor = $processor;
        $this->config = $config;
        $this->logger = $logger;

        // Run interval: configurable, default every 5 minutes (300 seconds)
        $interval = (int)$this->config->getAppValue('mail2folder', 'poll_interval', '300');
        $this->setInterval(max(60, $interval)); // Minimum 1 minute
    }

    /**
     * @param mixed $argument Not used
     */
    protected function run(mixed $argument): void
    {
        $this->logger->debug('Mail2Folder: Background job started');

        try {
            $summary = $this->processor->processNewMails();

            if ($summary['processed'] > 0) {
                $this->logger->info('Mail2Folder: Job finished', [
                    'processed' => $summary['processed'],
                    'attachments_saved' => $summary['attachments_saved'],
                    'skipped' => $summary['skipped'],
                    'errors' => $summary['errors'],
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Mail2Folder: Background job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
