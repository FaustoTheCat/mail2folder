<?php

declare(strict_types=1);

namespace OCA\Mail2Folder\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * @extends QBMapper<ProcessedMail>
 */
class ProcessedMailMapper extends QBMapper
{
    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, 'mail2folder_processed', ProcessedMail::class);
    }

    /**
     * Check if a mail UID has already been processed.
     */
    public function isProcessed(int $uid): bool
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('id')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($uid, IQueryBuilder::PARAM_INT)));

        $result = $qb->executeQuery();
        $row = $result->fetch();
        $result->closeCursor();

        return $row !== false;
    }

    /**
     * Check if a message ID has already been processed.
     */
    public function isMessageIdProcessed(string $messageId): bool
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('id')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('message_id', $qb->createNamedParameter($messageId)));

        $result = $qb->executeQuery();
        $row = $result->fetch();
        $result->closeCursor();

        return $row !== false;
    }

    /**
     * Get processed mails for a user, ordered by most recent first.
     *
     * @return ProcessedMail[]
     */
    public function findByUser(string $userId, int $limit = 50): array
    {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
            ->from($this->getTableName())
            ->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
            ->orderBy('processed_at', 'DESC')
            ->setMaxResults($limit);

        return $this->findEntities($qb);
    }

    /**
     * Delete old processed entries (cleanup).
     */
    public function deleteOlderThan(\DateTime $before): int
    {
        $qb = $this->db->getQueryBuilder();
        $qb->delete($this->getTableName())
            ->where($qb->expr()->lt(
                'processed_at',
                $qb->createNamedParameter($before, IQueryBuilder::PARAM_DATE)
            ));

        return $qb->executeStatement();
    }
}
