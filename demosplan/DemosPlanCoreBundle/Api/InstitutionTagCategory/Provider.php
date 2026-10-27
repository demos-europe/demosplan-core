<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\InstitutionTagCategory;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use demosplan\DemosPlanCoreBundle\Api\InstitutionTagCategory\Resource as InstitutionTagCategoryResource;
use demosplan\DemosPlanCoreBundle\Repository\InstitutionTagCategoryRepository;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Webmozart\Assert\Assert;

class Provider implements ProviderInterface
{
    public function __construct(
        private readonly AccessChecker $accessChecker,
        private readonly InstitutionTagCategoryRepository $categoryRepository,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        Assert::same($operation->getClass(), InstitutionTagCategoryResource::class);

        if (!$this->accessChecker->isAvailable()) {
            throw new AccessDeniedHttpException(sprintf('Access denied: insufficient permissions to access %s', $operation->getShortName()));
        }

        if (!isset($uriVariables['id'])) {
            return null;
        }

        try {
            $category = $this->categoryRepository->getEntityByIdentifier(
                $uriVariables['id'],
                $this->accessChecker->getAccessConditions(),
                ['id']
            );
        } catch (InvalidArgumentException) {
            return null;
        }

        return InstitutionTagCategoryResource::fromEntity($category);
    }
}
