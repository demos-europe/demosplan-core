<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use EDT\JsonApi\ResourceTypes\AbstractResourceType;
use EDT\JsonApi\ResourceTypes\RelationshipBuilder;
use EDT\Querying\Contracts\EntityBasedInterface;
use EDT\Querying\Contracts\PathException;
use EDT\Querying\Contracts\PropertyPathInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use demosplan\DemosPlanCoreBundle\Event\GetOriginalStatementPropertiesEvent;
use demosplan\DemosPlanCoreBundle\Event\IsOriginalStatementAvailableEvent;
use demosplan\DemosPlanCoreBundle\ResourceTypes\OriginalStatementResourceType;
use demosplan\DemosPlanUserBundle\Logic\CurrentUserInterface;

class OriginalStatementResourceTypeSubscriber implements EventSubscriberInterface
{
    /**
     * @var AbstractResourceType
     */

    private $abstractResourceType;
    /**
     * @var CurrentUserInterface
     */
    private $currentUser;

    public function __construct(AbstractResourceType $abstractResourceType, CurrentUserInterface $currentUser)
    {
        $this->abstractResourceType = $abstractResourceType;
        $this->currentUser          = $currentUser;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            IsOriginalStatementAvailableEvent::class            => 'isOriginalStatementAvailable',
            GetOriginalStatementPropertiesEvent::class          => 'getOriginalStatementProperties',
        ];
    }

    public function isOriginalStatementAvailable(IsOriginalStatementAvailableEvent $event)
    {
        if ($this->currentUser->hasPermission('feature_json_api_original_statement')) {
            $event->setIsOriginalStatementAvailable(true);
        }
    }

    public function getOriginalStatementProperties(GetOriginalStatementPropertiesEvent $event, OriginalStatementResourceType $resourceType)
    {
        if (!$event->getType() instanceof OriginalStatementResourceType) {
            return;
        }
        if ($this->currentUser->hasPermission('feature_import_statement_via_email')) {
            $property = $this->createToManyRelationship($resourceType->statements)->readable()
                ->aliasedPath($resourceType->statementsCreatedFromOriginal);
            $event->addProperty($property);
        }
    }

    /**
     * @template TRelationship of object
     *
     * @param PropertyPathInterface&EntityBasedInterface<TRelationship> $path
     *
     * @return RelationshipBuilder<TEntity, TRelationship>
     *
     * @throws PathException
     */
    protected function createToManyRelationship(
        PropertyPathInterface $path,
        bool $defaultInclude = false
    ): RelationshipBuilder {
        return new RelationshipBuilder($path, $this->abstractResourceType->getEntityClass(), $defaultInclude);
    }
}
