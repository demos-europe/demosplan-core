<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment\Interfaces;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;

interface SegmentHandlerInterface
{
    public function findById(string $segmentId): Segment;

    /**
     * @return array<Segment>
     */
    public function findByProcedure(Procedure $procedure): array;

    /**
     * @return array<Segment>
     */
    public function findAll(): array;

    /**
     * @param array<Segment> $segments
     */
    public function updateObjects(array $segments, DateTime $updateTime): void;

    public function addSegments(array $segments): void;

    public function delete(string $entityId): bool;

    public function deleteObject(Segment $entity): void;
}
