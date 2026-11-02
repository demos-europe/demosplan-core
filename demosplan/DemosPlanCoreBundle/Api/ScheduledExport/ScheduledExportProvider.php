<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Api\ScheduledExport;

use ApiPlatform\Doctrine\Orm\State\CollectionProvider as DoctrineCollectionProvider;
use ApiPlatform\Doctrine\Orm\State\Options as DoctrineOptions;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\ExportSchedule;
use demosplan\DemosPlanCoreBundle\Repository\ScheduledExportRepository;
use Exception;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Webmozart\Assert\Assert;

class ScheduledExportProvider implements ProviderInterface
{
    public function __construct(
        private readonly ScheduledExportAccessChecker $accessChecker,
        private readonly ScheduledExportRepository $scheduledExportRepository,
        private readonly MessageBagInterface $messageBag,
        private readonly DoctrineCollectionProvider $doctrineCollectionProvider,
    ) {
    }

    /**
     * @throws Exception
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        Assert::same($operation->getClass(), ScheduledExportResource::class);

        if (!$this->accessChecker->isAvailable()) {
            throw new AccessDeniedHttpException(sprintf('Access denied: insufficient permissions to access %s', $operation->getShortName()));
        }

        if ($operation instanceof CollectionOperationInterface) {
            return $this->provideCollection($operation, $uriVariables, $context);
        }

        if (isset($uriVariables['id'])) {
            return $this->provideSingle((string) $uriVariables['id']);
        }

        return null;
    }

    /**
     * Returning null lets API Platform answer 404. That covers a scheduled export of another user, of another
     * procedure, or one belonging to the assessment table, because all three are excluded by the access
     * conditions rather than by a check here.
     */
    private function provideSingle(string $id): ?ScheduledExportResource
    {
        try {
            $scheduledExport = $this->scheduledExportRepository->getEntityByIdentifier(
                $id,
                $this->accessChecker->getAccessConditions(),
                ['id']
            );
        } catch (InvalidArgumentException) {
            $this->messageBag->add('error', 'error.scheduledExport.not.found');

            return null;
        }

        return ScheduledExportResource::fromEntity($scheduledExport);
    }

    /**
     * Fetches the schedules via API Platform's Doctrine provider, which also applies access
     * control (see {@see ScheduledExportDoctrineAccessExtension}) and sorting for us. The
     * default order and the client-sortable properties are declared on the resource.
     *
     * @return list<ScheduledExportResource>
     */
    private function provideCollection(Operation $operation, array $uriVariables, array $context): array
    {
        $operation = $operation->withStateOptions(new DoctrineOptions(
            entityClass: ExportSchedule::class,
            handleLinks: static function (): void {
                // handleLinks has to be set or API Platform throws an error, but we don't need it to do anything here.
            }
        ));

        $result = $this->doctrineCollectionProvider->provide($operation, $uriVariables, $context);
        Assert::isArray($result);

        return array_map(
            static fn (ExportSchedule $scheduledExport): ScheduledExportResource => ScheduledExportResource::fromEntity($scheduledExport),
            $result
        );
    }
}
