<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\InvitableInstitution;

use ApiPlatform\Doctrine\Orm\State\CollectionProvider as DoctrineCollectionProvider;
use ApiPlatform\Doctrine\Orm\State\Options as DoctrineOptions;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\ProviderInterface;
use demosplan\DemosPlanCoreBundle\Api\Common\MappingPaginator;
use demosplan\DemosPlanCoreBundle\Api\InstitutionTag\AccessChecker as InstitutionTagAccessChecker;
use demosplan\DemosPlanCoreBundle\Api\InvitableInstitution\Resource as InvitableInstitutionResource;
use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTag;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Repository\OrgaRepository;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Webmozart\Assert\Assert;

class Provider implements ProviderInterface
{
    public function __construct(
        private readonly AccessChecker $accessChecker,
        private readonly InstitutionTagAccessChecker $tagAccessChecker,
        private readonly OrgaRepository $orgaRepository,
        private readonly DoctrineCollectionProvider $doctrineCollectionProvider,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        Assert::same($operation->getClass(), InvitableInstitutionResource::class);

        if (!$this->accessChecker->isAvailable()) {
            throw new AccessDeniedHttpException(sprintf('Access denied: insufficient permissions to access %s', $operation->getShortName()));
        }

        if ($operation instanceof CollectionOperationInterface) {
            return $this->provideCollection($operation, $uriVariables, $context);
        }

        if (isset($uriVariables['id'])) {
            return $this->provideSingle($uriVariables['id']);
        }

        return null;
    }

    private function provideSingle(string $id): ?InvitableInstitutionResource
    {
        try {
            $orga = $this->orgaRepository->getEntityByIdentifier(
                $id,
                $this->accessChecker->getAccessConditions(),
                ['id']
            );
        } catch (InvalidArgumentException) {
            return null;
        }

        return $this->toResource($orga);
    }

    /**
     * Access control, sorting and pagination are applied by API Platform's Doctrine provider
     * ({@see Extension\InvitableInstitutionAccessExtension} for the access part).
     *
     * @return PaginatorInterface<InvitableInstitutionResource>|list<InvitableInstitutionResource>
     */
    private function provideCollection(Operation $operation, array $uriVariables, array $context): PaginatorInterface|array
    {
        $operation = $operation->withStateOptions(new DoctrineOptions(
            entityClass: Orga::class,
            handleLinks: static function (): void {
                // handleLinks has to be set or API Platform throws an error, but we don't need it to do anything here.
            }
        ));

        $result = $this->doctrineCollectionProvider->provide($operation, $uriVariables, $this->addPaginationFilters($context));
        $map = fn (Orga $orga): InvitableInstitutionResource => $this->toResource($orga);

        if ($result instanceof PaginatorInterface) {
            return new MappingPaginator($result, $map);
        }

        Assert::isArray($result);

        return array_map($map, $result);
    }

    private function toResource(Orga $orga): InvitableInstitutionResource
    {
        $visibleTags = $orga->getAssignedTags()
            ->filter(fn (InstitutionTag $tag): bool => $this->tagAccessChecker->canRead($tag))
            ->getValues();

        return InvitableInstitutionResource::fromEntity($orga, $visibleTags, $this->accessChecker->canReadCustomFields());
    }

    /**
     * Because this resource supports sorting/filtering, API Platform stops forwarding plain
     * `page`/`itemsPerPage`/`pagination` query params on its own, so we add them to
     * `$context['filters']` ourselves (same workaround as the Tag resource).
     */
    private function addPaginationFilters(array $context): array
    {
        $request = $context['request'] ?? null;
        if (!$request instanceof Request) {
            return $context;
        }

        foreach (['page', 'itemsPerPage', 'pagination'] as $parameterName) {
            if ($request->query->has($parameterName) && !isset($context['filters'][$parameterName])) {
                $context['filters'][$parameterName] = $request->query->get($parameterName);
            }
        }

        return $context;
    }
}
