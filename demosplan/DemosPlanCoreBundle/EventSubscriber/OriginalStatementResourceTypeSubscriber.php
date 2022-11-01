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

use demosplan\DemosPlanCoreBundle\Event\GetOriginalStatementPropertiesEvent;
use demosplan\DemosPlanCoreBundle\Event\IsOriginalStatementAvailableEvent;
use demosplan\DemosPlanCoreBundle\ResourceTypes\OriginalStatementResourceType;
use demosplan\DemosPlanUserBundle\Logic\CurrentUserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OriginalStatementResourceTypeSubscriber implements EventSubscriberInterface
{
    /**
     * @var CurrentUserInterface
     */
    private $currentUser;

    public function __construct(CurrentUserInterface $currentUser)
    {
        $this->currentUser = $currentUser;
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
        $properties = $event->getProperties();
        if ($this->currentUser->hasPermission('feature_import_statement_via_email')) {
            $properties = $this->createToManyRelationship($resourceType->statements)->readable()
                ->aliasedPath($resourceType->statementsCreatedFromOriginal);
        }
    }
}
