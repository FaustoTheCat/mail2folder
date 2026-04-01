<?php

declare(strict_types=1);

namespace OCA\Mail2Folder\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getMessageId()
 * @method void setMessageId(string $messageId)
 * @method int getUid()
 * @method void setUid(int $uid)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method string|null getSender()
 * @method void setSender(?string $sender)
 * @method string|null getSubject()
 * @method void setSubject(?string $subject)
 * @method int getAttachmentCount()
 * @method void setAttachmentCount(int $count)
 * @method string getProcessedAt()
 * @method void setProcessedAt(string $processedAt)
 * @method string getStatus()
 * @method void setStatus(string $status)
 * @method string|null getErrorMessage()
 * @method void setErrorMessage(?string $errorMessage)
 */
class ProcessedMail extends Entity
{
    protected string $messageId = '';
    protected int $uid = 0;
    protected string $userId = '';
    protected ?string $sender = null;
    protected ?string $subject = null;
    protected int $attachmentCount = 0;
    protected string $processedAt = '';
    protected string $status = 'success';
    protected ?string $errorMessage = null;

    public function __construct()
    {
        $this->addType('uid', 'integer');
        $this->addType('attachmentCount', 'integer');
    }
}
