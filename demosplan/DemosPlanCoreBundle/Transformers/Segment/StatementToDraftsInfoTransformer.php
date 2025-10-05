<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Transformers\Segment;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use DemosEurope\DemosplanAddon\Contracts\DraftsInfoTransformerInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\LockedByAssignmentException;
use demosplan\DemosPlanCoreBundle\Exception\StatementAlreadySegmentedException;
use demosplan\DemosPlanCoreBundle\Exception\StatementNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\TagService;
use demosplan\DemosPlanCoreBundle\Validator\DraftsInfoValidator;
use demosplan\DemosPlanCoreBundle\Validator\SegmentableStatementValidator;
use Faker\Provider\Uuid;

/**
 * Transforms a Statement to DraftsInfo (with one single DraftSegment containing
 * the whole Statement text).
 *
 * Class StatementToDraftsInfoTransformer
 */
class StatementToDraftsInfoTransformer implements DraftsInfoTransformerInterface
{
    public function __construct(private readonly DraftsInfoValidator $draftsInfoValidator, private readonly SegmentableStatementValidator $segmentableStatementValidator, private readonly StatementHandler $statementHandler, private readonly TagService $tagService)
    {
    }

    /**
     * Transforms a Statement to DraftsInfo (with one single DraftSegment
     * containing the whole Statement text).
     *
     * @throws StatementNotFoundException
     * @throws LockedByAssignmentException
     * @throws StatementAlreadySegmentedException
     */
    public function transform($statementId): string
    {
        $draftsInfo = [];
        $this->segmentableStatementValidator->validate($statementId);
        /** @var Statement $statement */
        $statement = $this->statementHandler->getStatement($statementId);
        $result = $statement->getDraftsListJson();
        if (empty($result)) {
            $text = $statement->getText();
            $draftsInfo['data'] = [
                'id'         => Uuid::uuid(),
                'type'       => 'slicing transaction',
                'attributes' => [
                    'statementId'      => $statement->getId(),
                    'procedureId'      => $statement->getProcedureId(),
                    'textualReference' => $text,
                    'segments'         => [],
                ],
            ];
            if (($result = Json::encode($draftsInfo)) === '' || ($result = Json::encode($draftsInfo)) === '0') {
                throw new InvalidArgumentException('Error when json_encoding drafts info');
            }
        }

        $this->draftsInfoValidator->validate($result);

        return $this->adaptDraftsInfo($result, $statement->getProcedureId());
    }

    /**
     * If PI sent Tags that already exist in the Procedure, their ids must be replaced by the already existing ones.
     */
    private function adaptDraftsInfo(string $draftsListJson, string $procedureId): string
    {
        $draftsListObject = Json::decodeToMatchingType($draftsListJson);
        $segments = $draftsListObject->data->attributes->segments;
        foreach ($segments as $segment) {
            $this->replaceExistingTagIds($segment, $procedureId);
        }

        return Json::encode($draftsListObject);
    }

    private function replaceExistingTagIds(object $segment, string $procedureId): void
    {
        foreach ($segment->tags as $piTag) {
            $existingTag = $this->tagService->findUniqueByTitle($piTag->tagName, $procedureId);
            if ($existingTag instanceof Tag) {
                $piTag->id = $existingTag->getId();
            }
        }
    }

    /**
     * Returns true for text formats.
     */
    public function supports(string $format): bool
    {
        return self::STATEMENT === $format;
    }
}
