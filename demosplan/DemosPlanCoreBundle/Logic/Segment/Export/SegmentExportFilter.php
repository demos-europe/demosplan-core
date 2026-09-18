<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment\Export;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\JsonApiActionService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementExportTagFilter;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementSegmentResourceType;
use Doctrine\ORM\Query\QueryException;
use EDT\JsonApi\RequestHandling\UrlParameter;
use Symfony\Component\HttpFoundation\RequestStack;

class SegmentExportFilter
{
    public function __construct(
        protected readonly JsonApiActionService $jsonApiActionService,
        protected readonly RequestStack $requestStack,
        protected readonly StatementExportTagFilter $statementExportTagFilter,
        protected readonly StatementSegmentResourceType $statementSegmentResourceType,
    )
    {
    }

    /**
     * @throws UserNotFoundException
     * @throws QueryException
     */
    public function filter(): array
    {
        /** @var Segment[] $segmentEntities */
        $segmentEntities = array_values(
            $this->jsonApiActionService->getObjectsByQueryParams(
                $this->requestStack->getCurrentRequest()->query,
                $this->statementSegmentResourceType,
            )->getList()
        );

        return $segmentEntities;
    }
}
