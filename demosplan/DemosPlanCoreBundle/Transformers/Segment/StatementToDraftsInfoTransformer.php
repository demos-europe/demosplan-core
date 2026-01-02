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

use DemosEurope\DemosplanAddon\Contracts\DraftsInfoTransformerInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
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

        // Check if statement is already segmented with new architecture
        if ($statement->isSegmented()) {
            return $this->transformSegmentedStatement($statement);
        }

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
     * Transform an already-segmented statement into drafts info format.
     * This handles statements using the new order-based segmentation architecture.
     */
    private function transformSegmentedStatement(Statement $statement): string
    {
        $contentBlocks = [];

        // Collect all segments and text sections
        $allBlocks = [];

        foreach ($statement->getSegmentsOfStatement() as $segment) {
            $allBlocks[] = [
                'type' => 'segment',
                'order' => $segment->getOrderInStatement(),
                'data' => [
                    'id' => $segment->getId(),
                    'text' => $segment->getText(),
                    'textRaw' => $segment->getText(),
                    'tags' => [], // Tags would need to be loaded if needed
                    'place' => $segment->getPlace() ? $segment->getPlace()->getId() : null,
                    'status' => $segment->getStatus(),
                ],
            ];
        }

        foreach ($statement->getTextSections() as $textSection) {
            $allBlocks[] = [
                'type' => 'textSection',
                'order' => $textSection->getOrderInStatement(),
                'data' => [
                    'text' => $textSection->getText(),
                    'textRaw' => $textSection->getTextRaw(),
                ],
            ];
        }

        // Sort by order
        usort($allBlocks, fn($a, $b) => $a['order'] <=> $b['order']);

        // Convert to content blocks format
        foreach ($allBlocks as $block) {
            $contentBlocks[] = array_merge(['type' => $block['type'], 'order' => $block['order']], $block['data']);
        }

        $draftsInfo = [
            'data' => [
                'id' => Uuid::uuid(),
                'type' => 'slicing transaction',
                'attributes' => [
                    'statementId' => $statement->getId(),
                    'procedureId' => $statement->getProcedureId(),
                    'segmentationStatus' => 'SEGMENTED',
                    'contentBlocks' => $contentBlocks,
                    'textualReference' => $statement->getText(),
                ],
            ],
        ];

        return Json::encode($draftsInfo);
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
