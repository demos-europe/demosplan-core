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
 * copies Statements within procedure.
 *
 * You find general RPC API usage information
 * {@link http://dplan-documentation.demos-europe.eu/development/application-architecture/web-api/jsonrpc/ here}.
 * Accepted parameters by this route are the following:
 * ```
 * "params": {
 *   "statementIds": <JSON array of statementIds>,
 * }
 * ```
 * the statementIds field is required, however the array statementIds may be empty.
 **/
class RpcBulkCopyStatements extends AbstractRpcStatementBulkAction
{
    public const RPC_JSON_SCHEMA_PATH = 'json-schema/rpc-statements-bulk-copy-schema.json';

    public const STATEMENTS_BULK_COPY_METHOD = 'statements.bulk.copy';

    protected function checkIfAuthorized(string $procedureId): bool
    {
        try {
            return $this->procedureService->isUserAuthorized($procedureId)
                && $this->isAvailable();
        } catch (Exception $e) {
            return false;
        }
    }

    protected function getJsonSchemaPath(): string
    {
        return DemosPlanPath::getConfigPath(self::RPC_JSON_SCHEMA_PATH);
    }

    protected function handleStatementAction(array $statements): bool
    {
        try {
            foreach ($statements as $statement) {
                $copyResult = $this->statementCopier->copyStatementObjectWithinProcedure($statement);
                if (!$copyResult instanceof Statement) {
                    return false;
                }
            }

            return true;
        } catch (Exception $e) {
            return false;
        }
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
        return self::STATEMENTS_BULK_COPY_METHOD === $method;
    }
}
