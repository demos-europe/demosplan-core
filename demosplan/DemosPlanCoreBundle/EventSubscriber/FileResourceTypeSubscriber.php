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

use demosplan\DemosPlanCoreBundle\Event\GetFilePropertiesEvent;
use demosplan\DemosPlanCoreBundle\Event\IsFileAvailableEvent;
use demosplan\DemosPlanCoreBundle\Event\IsFileDirectlyAccessibleEvent;
use demosplan\DemosPlanCoreBundle\Permissions\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\ResourceTypes\FileResourceType;
use demosplan\DemosPlanUserBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanUserBundle\Logic\CurrentUserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FileResourceTypeSubscriber implements EventSubscriberInterface
{
    /**
     * @var CurrentUserInterface
     */
    private $currentUser;

    /**
     * @var PermissionsInterface
     */
    private $permissions;

    public function __construct(CurrentUserInterface $currentUser, PermissionsInterface $permissions)
    {
        $this->currentUser = $currentUser;
        $this->permissions = $permissions;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            IsFileAvailableEvent::class             => 'isFileAvailable',
            IsFileDirectlyAccessibleEvent::class    => 'isFileDirectlyAccessible',
            GetFilePropertiesEvent::class           => 'getFileProperties',
        ];
    }

    /**
     * @throws UserNotFoundException
     */
    public function isFileAvailable(IsFileAvailableEvent $event): void
    {
        if ($this->currentUser->hasPermission('feature_import_statement_pdf')) {
            $event->setIsFileAvailable(true);
        }
    }

    /**
     * @throws UserNotFoundException
     */
    public function isFileDirectlyAccessible(IsFileDirectlyAccessibleEvent $event): void
    {
        if ($this->currentUser->hasPermission('feature_import_statement_pdf')) {
            $event->setIsDirectlyAccessible(true);
        }
    }

    /**
     * @throws UserNotFoundException
     */
    public function getFileProperties(GetFilePropertiesEvent $event): void
    {
        if (!$event->getType() instanceof FileResourceType) {
            return;
        }
        $properties = $event->getProperties();
        if ($this->currentUser->hasPermission('feature_import_statement_pdf')) {
            $properties['id']->filterable()->sortable();
            $properties['hash']->readable(true)->filterable()->sortable();
            $properties['filename']->readable(true, [self::class, 'getFileName']);
            $properties['created']->readable(true, [$this, 'getCreated']);
        }
    }
}
