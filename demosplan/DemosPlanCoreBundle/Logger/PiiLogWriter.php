<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logger;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Ramsey\Uuid\Uuid;

/**
 * Writes a {@see PiiLogRecord} to the `pii_log` table via DBAL.
 *
 * Separated from {@see PiiAwareLogger} so the logger can mock the writer in unit
 * tests and so the writer can be reused (e.g. by a future async/messenger path)
 * without dragging in a logger dependency.
 *
 * @noinspection PhpClassCanBeReadonlyInspection — PHPUnit cannot mock readonly classes
 */
class PiiLogWriter
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return string The generated row UUID
     *
     * @throws DBALException on insert failure (caller is responsible for fallback)
     */
    public function write(PiiLogRecord $record): string
    {
        $id = Uuid::uuid4()->toString();

        $this->connection->beginTransaction();
        try {
            $this->connection->insert('pii_log', [
                'id'              => $id,
                'created'         => $record->createdAt->format('Y-m-d H:i:s'),
                'level'           => $record->level,
                'level_name'      => $record->levelName,
                'channel'         => $record->channel,
                'message'         => $record->message,
                'pii_context'     => $record->piiContextJson,
                'non_pii_context' => $record->nonPiiContextJson,
                'content_hash'    => $record->contentHash,
                'request_id'      => $record->requestId,
                'procedure_id'    => $record->procedureId,
                'orga_id'         => $record->orgaId,
                'source_context'  => $record->sourceContext,
            ]);
            $this->connection->commit();
        } catch (DBALException $e) {
            $this->connection->rollBack();

            throw $e;
        }

        return $id;
    }
}
