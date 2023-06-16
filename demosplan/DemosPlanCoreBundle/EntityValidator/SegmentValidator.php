<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EntityValidator;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SegmentValidator
{
    public function __construct(private readonly LoggerInterface $logger, private readonly ValidatorInterface $validator)
    {
    }

    /**
     * Given an array of segmentIds, segment entities and a procedureId, validates that there
     * are as many segmentIds as segment entities and that they all belong to the procedure.
     *
     * @param array<int, string>  $segmentIds
     * @param array<int, Segment> $segments
     *
     * @throws InvalidArgumentException
     */
    public function validateSegments(
        array $segmentIds,
        array $segments,
        string $procedureId
    ): void {
        if (count($segmentIds) !== count($segments)) {
            $this->logger->error('Some Segment ids found no match: ', $segmentIds);
            throw new InvalidArgumentException();
        }
        $filteredByProcedureSegments = array_filter(
            $segments,
            fn(Segment $segment) => $segment->getProcedureId() === $procedureId
        );
        if (count($filteredByProcedureSegments) !== count($segments)) {
            $this->logger->error(
                'Some Segment ids don\'t belong to procedure#'.$procedureId, $segmentIds);
            throw new InvalidArgumentException();
        }
    }

    /**
     * Validates a segment object based on entity annotations.
     */
    public function validate(Segment $segment, string ...$additionalValidationGroups): ConstraintViolationListInterface
    {
        $additionalValidationGroups[] = Segment::VALIDATION_GROUP_SEGMENT_MANDATORY;
        $additionalValidationGroups[] = Segment::VALIDATION_GROUP_DEFAULT;

        return $this->validator->validate($segment, null, $additionalValidationGroups);
    }
}
