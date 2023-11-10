<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\AssessmentTable;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Exception;

/**
 * deletes Statements within procedure.
 *
 * You find general RPC API usage information
 * {@link http://dplan-documentation.ad.berlin.demos-europe.eu/development/application-architecture/web-api/jsonrpc/ here}.
 * Accepted parameters by this route are the following:
 * ```
 * "params": {
 *   "statementIds": <JSON array of statementIds>,
 * }
 * ```
 * the statementIds field is required, however the array statementIds may be empty.
 **/
class RpcBulkDeleteStatements extends AbstractRpcStatementBulkAction
{
    final public const RPC_JSON_SCHEMA_PATH = 'json-schema/rpc-statements-bulk-delete-schema.json';

    final public const STATEMENTS_BULK_DELETE_METHOD = 'statements.bulk.delete';

    protected function checkIfAuthorized(string $procedureId): bool
    {
        try {
            $orgaId = $this->currentUser->getUser()->getOrga()->getId();

            return $this->assessmentTableServiceOutput->isOrgaAuthorized($procedureId, $orgaId)
                && $this->isAvailable();
        } catch (Exception) {
            return false;
        }
    }

    protected function getJsonSchemaPath(): string
    {
        return DemosPlanPath::getConfigPath(self::RPC_JSON_SCHEMA_PATH);
    }

    protected function handleStatementAction(array $statements): bool
    {
        /** @var Statement $statement */
        foreach ($statements as $statement) {
            if (!$this->statementDeleter->deleteStatementObject($statement)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws UserNotFoundException
     */
    private function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('area_admin_assessmenttable');
    }

    public function supports(string $method): bool
    {
        return self::STATEMENTS_BULK_DELETE_METHOD === $method;
    }
}
