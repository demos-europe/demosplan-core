<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventListener;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use FOS\ElasticaBundle\Event\PostTransformEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class IndexFilesListener implements EventSubscriberInterface
{
    /**
     * @var FileService
     */
    private $fileService;

    public function __construct(FileService $fileService)
    {
        $this->fileService = $fileService;
    }

    /**
     * Add Files to Statement index.
     */
    public function addFiles(PostTransformEvent $event): void
    {
        $statement = $event->getObject();
        if (!$statement instanceof Statement) {
            return;
        }
        $document = $event->getDocument();
        // add multiple Files to Statement if available
        // needs to be fetched from file service as $statement does not have
        // newest reference if just uploaded a new file
        $files = $this->fileService->getEntityFileString(Statement::class, $statement->getId(), 'file');
        if (count($files) > 0) {
            $document->set('files', $files);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostTransformEvent::class => 'addFiles',
        ];
    }
}
