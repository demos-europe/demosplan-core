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

use demosplan\DemosPlanUserBundle\Exception\UserNotFoundException;
use EDT\JsonApi\ResourceTypes\RelationshipBuilder;
use EDT\Querying\Contracts\PathException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use demosplan\DemosPlanCoreBundle\Event\IsOriginalStatementAvailableEvent;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\GetPropertiesEvent;
use demosplan\DemosPlanCoreBundle\ResourceTypes\OriginalStatementResourceType;
use demosplan\DemosPlanUserBundle\Logic\CurrentUserInterface;

class OriginalStatementResourceTypeSubscriber implements EventSubscriberInterface
{
    private CurrentUserInterface $currentUser;

    public function __construct(CurrentUserInterface $currentUser)
    {
        $this->currentUser = $currentUser;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            IsOriginalStatementAvailableEvent::class => 'isOriginalStatementAvailable',
            GetPropertiesEvent::class                => 'getOriginalStatementProperties',
        ];
    }

    public function isOriginalStatementAvailable(IsOriginalStatementAvailableEvent $event)
    {
        if ($this->currentUser->hasPermission('feature_import_statement_via_email')) {
            $event->setIsOriginalStatementAvailable(true);
        }
    }

    /**
     * @throws PathException
     * @throws UserNotFoundException
     */
    public function getOriginalStatementProperties(GetPropertiesEvent $event): void
    {
        $resourceType = $event->getType();
        if (!$resourceType instanceof OriginalStatementResourceType) {
            return;
        }

        if ($this->currentUser->hasPermission('feature_import_statement_via_email')) {
            $property = (new RelationshipBuilder(
                $resourceType->statements,
                $resourceType->getEntityClass(),
                false
            ))->readable()->aliasedPath($resourceType->statementsCreatedFromOriginal);
            $event->addProperty($property);
        }
    }
}
