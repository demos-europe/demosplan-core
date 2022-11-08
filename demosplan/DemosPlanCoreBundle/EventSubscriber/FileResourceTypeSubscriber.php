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

use Carbon\Carbon;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Event\IsFileAvailableEvent;
use demosplan\DemosPlanCoreBundle\Event\IsFileDirectlyAccessibleEvent;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\GetPropertiesEvent;
use demosplan\DemosPlanCoreBundle\ResourceTypes\FileResourceType;
use demosplan\DemosPlanUserBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanUserBundle\Logic\CurrentUserInterface;
use EDT\JsonApi\ResourceTypes\PropertyBuilder;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FileResourceTypeSubscriber implements EventSubscriberInterface
{
    use ResourcePropertyEventSubscriberTrait;

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
            IsFileAvailableEvent::class          => 'isFileAvailable',
            IsFileDirectlyAccessibleEvent::class => 'isFileDirectlyAccessible',
            GetPropertiesEvent::class            => 'getFileProperties',
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
    public function getFileProperties(GetPropertiesEvent $event): void
    {
        $resourceType = $event->getType();
        if (!$resourceType instanceof FileResourceType) {
            return;
        }

        if (!$this->currentUser->hasPermission('feature_import_statement_pdf')) {
            return;
        }

        $properties = $event->getProperties();
        $idPropertyBuilder = $this->getPropertyBuilder($properties, $resourceType->id->getAsNamesInDotNotation());
        if (null === $idPropertyBuilder) {
            $idPropertyBuilder = new PropertyBuilder($resourceType->id, $resourceType->getEntityClass());
            $properties[] = $idPropertyBuilder;
        }
        $idPropertyBuilder->filterable()->sortable();

        $hashPropertyBuilder = $this->getPropertyBuilder($properties, $resourceType->hash->getAsNamesInDotNotation());
        if (null === $hashPropertyBuilder) {
            $hashPropertyBuilder = new PropertyBuilder($resourceType->hash, $resourceType->getEntityClass());
            $properties[] = $hashPropertyBuilder;
        }
        $hashPropertyBuilder->readable(true)->filterable()->sortable();

        $filenamePropertyBuilder = $this->getPropertyBuilder($properties, $resourceType->filename->getAsNamesInDotNotation());
        if (null === $filenamePropertyBuilder) {
            $filenamePropertyBuilder = new PropertyBuilder($resourceType->filename, $resourceType->getEntityClass());
            $properties[] = $filenamePropertyBuilder;
        }
        $filenamePropertyBuilder->readable(true, [self::class, 'getFileName']);

        $createdPropertyBuilder = $this->getPropertyBuilder($properties, $resourceType->created->getAsNamesInDotNotation());
        if (null === $createdPropertyBuilder) {
            $createdPropertyBuilder = new PropertyBuilder($resourceType->created, $resourceType->getEntityClass());
            $properties[] = $createdPropertyBuilder;
        }
        $createdPropertyBuilder->readable(true, [$this, 'getCreated']);

        $event->setProperties($properties);
    }

    public static function getFileName(File $file): string
    {
        return $file->getFilename();
    }

    public function getCreated(File $file): string
    {
        return Carbon::instance($file->getCreated())->toIso8601String();
    }
}
