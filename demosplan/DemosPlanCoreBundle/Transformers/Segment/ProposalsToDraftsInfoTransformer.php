<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Transformers\Segment;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceObject;
use demosplan\DemosPlanCoreBundle\Logic\Workflow\PlaceService;
use demosplan\DemosPlanCoreBundle\Utilities\Json;
use demosplan\DemosPlanStatementBundle\Exception\StatementNotFoundException;
use demosplan\DemosPlanStatementBundle\Logic\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Interfaces\DraftsInfoTransformerInterface;
use Psr\Log\LoggerInterface;

class ProposalsToDraftsInfoTransformer implements DraftsInfoTransformerInterface
{
    /** @var StatementService */
    private $statementService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var PlaceService
     */
    private $placeService;

    public function __construct(LoggerInterface $logger, PlaceService $placeService, StatementService $statementService)
    {
        $this->logger = $logger;
        $this->placeService = $placeService;
        $this->statementService = $statementService;
    }

    /**
     * Transforms Proposals to a DraftsList.
     *
     * @param ResourceObject $segmentedStatement
     *
     * @return string
     */
    public function transform($segmentedStatement)
    {
        if (!$segmentedStatement instanceof ResourceObject) {
            $this->logger->error('PI-Communication-Error: Invalid Resource Object');
            $actualType = gettype($segmentedStatement);
            throw new InvalidArgumentException("Expected type ResourceObject, got {$actualType}");
        }

        $statementId = $segmentedStatement->get('relationships.statement.data.id');
        $this->logger->info('PI-Communication-Info: Statement Id : '.$statementId);
        $statement = $this->statementService->getStatement($statementId);
        if (null === $statement) {
            $this->logger->error('PI-Communication-Error: No Statement found for given Statement Id');
            throw new BadRequestException('invalid data in meta field for statement ID', 0, StatementNotFoundException::createFromId($statementId));
        }
        $draftSegmentJson = $this->convertToDraftSegmentJson($segmentedStatement, $statement);

        return Json::encode($draftSegmentJson);
    }

    /**
     * Returns true for Proposal formats.
     */
    public function supports(string $format): bool
    {
        return self::PROPOSALS === $format;
    }

    /**
     * @return array<mixed>
     */
    protected function convertToDraftSegmentJson(ResourceObject $segmentedStatement, Statement $statement): array
    {
        $this->logger->info('PI-Communication-Info: convertToDraftSegmentJson Start');
        $defaultPlace = $this->placeService->findFirstOrderedBySortIndex($statement->getProcedureId());

        $result = [
            'data' => [
                'id'         => $segmentedStatement->get('id'),
                'type'       => $segmentedStatement->get('type'),
                'attributes' => [
                    'statementId'      => $statement->getId(),
                    'procedureId'      => $statement->getProcedureId(),
                    'textualReference' => $statement->getText(),
                    'segments'         => collect($segmentedStatement->get('draftSegments'))->map(
                        static function (ResourceObject $draftSegment, string $draftSegmentId) use ($defaultPlace) {
                            $position = $draftSegment->get('position');

                            return [
                                'id'        => $draftSegmentId,
                                'charStart' => $position['start'],
                                'charEnd'   => $position['stop'],
                                'place'     => [
                                    'id'   => $defaultPlace->getId(),
                                    'name' => $defaultPlace->getName(),
                                ],
                                'tags' => collect($draftSegment->get('tags'))->map(
                                    static function (ResourceObject $tag, $tagId) {
                                        return [
                                            'id'       => $tagId,
                                            'tagName'  => $tag->get('title'),
                                            'tagScore' => 0,
                                        ];
                                    }
                                )->values(),
                            ];
                        }
                    )->values(),
                ],
            ],
        ];

        $this->logger->info('PI-Communication-Info: convertToDraftSegmentJson End');

        return $result;
    }
}
