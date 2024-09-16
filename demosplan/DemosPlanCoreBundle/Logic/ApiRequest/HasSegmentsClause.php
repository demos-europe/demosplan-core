<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\Querying\Contracts\PropertyPathAccessInterface;
use EDT\Querying\PropertyPaths\PathInfo;
use EDT\Querying\PropertyPaths\PropertyPath;
use Stringable;

class HasSegmentsClause implements ClauseFunctionInterface, Stringable
{
    public function __construct(private readonly string $procedureId)
    {
    }

    public function getPropertyPaths(): array
    {
        $idPath = new PropertyPath(null, '', PropertyPathAccessInterface::DIRECT, ['id']);

        return [new PathInfo($idPath, true)];
    }

    public function asDql(array $valueReferences, array $propertyAliases, string $mainEntityAlias): string
    {
        $procedureIdReference = array_pop($valueReferences);
        $statementIdAlias = array_pop($propertyAliases);
        $segmentClass = Segment::class;

        return "EXISTS (SELECT IDENTITY(seg.parentStatementOfSegment) FROM $segmentClass seg WHERE seg.procedure = $procedureIdReference AND seg.parentStatementOfSegment = $statementIdAlias)";
    }

    public function getClauseValues(): array
    {
        return [$this->procedureId];
    }

    public function apply(array $propertyValues)
    {
        throw new NotYetImplementedException();
    }

    public function __toString(): string
    {
        return static::class;
    }
}
