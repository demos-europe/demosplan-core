<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\Orga;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use demosplan\DemosPlanCoreBundle\Api\Orga\OrgaResource as OrgaResource;
use demosplan\DemosPlanCoreBundle\Repository\OrgaRepository;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Webmozart\Assert\Assert;

class OrgaProvider implements ProviderInterface
{
    public function __construct(
        private readonly OrgaAccessChecker $accessChecker,
        private readonly OrgaRepository $orgaRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        Assert::same($operation->getClass(), OrgaResource::class);

        if (!$this->accessChecker->isAvailable()) {
            throw new AccessDeniedHttpException(sprintf('Access denied: insufficient permissions to access %s', $operation->getShortName()));
        }

        if (!isset($uriVariables['id'])) {
            return null;
        }

        try {
            $orga = $this->orgaRepository->getEntityByIdentifier(
                $uriVariables['id'],
                $this->accessChecker->getAccessConditions(),
                ['id']
            );
        } catch (InvalidArgumentException) {
            return null;
        }

        return OrgaResource::fromEntity($orga);
    }
}
