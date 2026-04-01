<?php

declare(strict_types=1);

namespace OCA\Mail2Folder\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version1000Date20250101000000 extends SimpleMigrationStep
{
    public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
    {
        /** @var ISchemaWrapper $schema */
        $schema = $schemaClosure();

        if (!$schema->hasTable('mail2folder_processed')) {
            $table = $schema->createTable('mail2folder_processed');

            $table->addColumn('id', Types::BIGINT, [
                'autoincrement' => true,
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('message_id', Types::STRING, [
                'notnull' => true,
                'length' => 512,
            ]);
            $table->addColumn('uid', Types::BIGINT, [
                'notnull' => true,
                'unsigned' => true,
            ]);
            $table->addColumn('user_id', Types::STRING, [
                'notnull' => true,
                'length' => 64,
            ]);
            $table->addColumn('sender', Types::STRING, [
                'notnull' => false,
                'length' => 255,
            ]);
            $table->addColumn('subject', Types::STRING, [
                'notnull' => false,
                'length' => 512,
            ]);
            $table->addColumn('attachment_count', Types::INTEGER, [
                'notnull' => true,
                'default' => 0,
                'unsigned' => true,
            ]);
            $table->addColumn('processed_at', Types::DATETIME, [
                'notnull' => true,
            ]);
            $table->addColumn('status', Types::STRING, [
                'notnull' => true,
                'length' => 32,
                'default' => 'success',
            ]);
            $table->addColumn('error_message', Types::TEXT, [
                'notnull' => false,
            ]);

            $table->setPrimaryKey(['id']);
            $table->addIndex(['message_id'], 'mail2f_msgid_idx');
            $table->addIndex(['user_id'], 'mail2f_userid_idx');
            $table->addIndex(['uid'], 'mail2f_uid_idx');
        }

        return $schema;
    }
}
