<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment\Handler;

use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Exception\LockedByAssignmentException;
use demosplan\DemosPlanCoreBundle\Exception\StatementAlreadySegmentedException;
use demosplan\DemosPlanCoreBundle\Exception\StatementNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Segment\DraftsInfoService;
use demosplan\DemosPlanCoreBundle\Validator\DraftsInfoValidator;
use demosplan\DemosPlanCoreBundle\Validator\SegmentableStatementValidator;
use Exception;
use JsonSchema\Exception\InvalidSchemaException;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

class DraftsInfoHandler
{
    public function __construct(private readonly DraftsInfoService $draftsInfoService, private readonly DraftsInfoValidator $draftsInfoValidator, private readonly SegmentableStatementValidator $segmentableStatementValidator)
    {
    }

    /**
     * Given a string in json format implementing a draftsList, saves it as a Field for their Statement.
     *
     * @return string the statement ID extracted from the given data
     *
     * @throws StatementNotFoundException
     * @throws FileNotFoundException
     * @throws InvalidSchemaException
     * @throws LockedByAssignmentException
     * @throws StatementAlreadySegmentedException
     * @throws Exception
     */
    public function save(string $data): string
    {
        $this->draftsInfoValidator->validate($data);
        $dataArray = Json::decodeToArray($data);
        $statementId = $this->extractStatementId($dataArray);
        $this->segmentableStatementValidator->validate($statementId);

        $this->draftsInfoService->save($statementId, $data);

        return $statementId;
    }

    /**
     * @param array<mixed> $draftsInfo
     */
    public function extractStatementId(array $draftsInfo): string
    {
        return $this->extractString($draftsInfo, 'statementId');
    }

    /**
     * @param array<mixed> $draftsInfo
     */
    public function extractProcedureId(array $draftsInfo): string
    {
        return $this->extractString($draftsInfo, 'procedureId');
    }

    /**
     * @param array<mixed> $draftsInfo
     */
    public function extractTextualReference(array $draftsInfo): string
    {
        return $this->extractString($draftsInfo, 'textualReference');
    }

    /**
     * @param array<mixed> $draftsInfo
     *
     * @return array<mixed>
     */
    public function extractDraftsList(array $draftsInfo): array
    {
        return $this->extractArray($draftsInfo, 'segments');
    }

    /**
     * @param array<mixed> $draftsInfo
     *
     * @return array<mixed>
     *
     * @throws InvalidSchemaException
     */
    public function extractArray(array $draftsInfo, string $key): array
    {
        $value = $draftsInfo['data']['attributes'][$key];
        if (null === $value) {
            throw new InvalidSchemaException('A value for $key could not be extracted from Drafts Info.');
        }

        return $value;
    }

    /**
     * @param array<mixed> $draftsInfo
     */
    private function extractString(array $draftsInfo, string $key): string
    {
        if (!($value = $draftsInfo['data']['attributes'][$key])) {
            throw new InvalidSchemaException("A value for key '$key' could not be extracted from Drafts Info.");
        }

        return $value;
    }
}
