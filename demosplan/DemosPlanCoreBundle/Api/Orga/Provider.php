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
use demosplan\DemosPlanCoreBundle\Api\Orga\Resource as OrgaResource;
use demosplan\DemosPlanCoreBundle\Repository\OrgaRepository;
use InvalidArgumentException;
use Webmozart\Assert\Assert;

class Provider implements ProviderInterface
{
    public function __construct(
        private readonly OrgaRepository $orgaRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        Assert::same($operation->getClass(), OrgaResource::class);

        if (!isset($uriVariables['id'])) {
            return null;
        }

        try {
            $orga = $this->orgaRepository->getEntityByIdentifier($uriVariables['id'], [], ['id']);
        } catch (InvalidArgumentException) {
            return null;
        }

        return OrgaResource::fromEntity($orga);
    }
}
