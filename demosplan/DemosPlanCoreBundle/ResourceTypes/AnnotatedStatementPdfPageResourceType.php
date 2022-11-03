<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use demosplan\DemosPlanCoreBundle\Entity\Statement\AnnotatedStatementPdf\AnnotatedStatementPdfPage;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\UpdatableDqlResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\ResourceChange;
use demosplan\DemosPlanCoreBundle\Utilities\Json;
use demosplan\DemosPlanUserBundle\Exception\UserNotFoundException;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * @template-implements UpdatableDqlResourceTypeInterface<AnnotatedStatementPdfPage>
 * @template-extends DplanResourceType<AnnotatedStatementPdfPage>
 *
 * @property-read End $url
 * @property-read End $width
 * @property-read End $height
 * @property-read End $geoJson
 * @property-read End $confirmed
 * @property-read AnnotatedStatementPdfResourceType $annotatedStatementPdf
 * @property-read End $pageOrder
 * @property-read End $pageSortIndex
 */
final class AnnotatedStatementPdfPageResourceType extends DplanResourceType implements UpdatableDqlResourceTypeInterface
{
    public function getEntityClass(): string
    {
        return AnnotatedStatementPdfPage::class;
    }

    public static function getName(): string
    {
        return 'AnnotatedStatementPdfPage';
    }

    public function getAccessCondition(): PathsBasedInterface
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return $this->conditionFactory->false();
        }

        return $this->conditionFactory->propertyHasValue(
            $procedure->getId(),
            ...$this->annotatedStatementPdf->procedure->id
        );
    }

    public function updateObject(object $object, array $properties): ResourceChange
    {
        $resourceChange = new ResourceChange($object, $this, $properties);

        $this->resourceTypeService->updateObjectNaive($object, $properties);
        $this->resourceTypeService->validateObject($object);

        return $resourceChange;
    }

    /**
     * @return array<string,string|null>
     */
    public function getUpdatableProperties(object $updateTarget): array
    {
        return $this->toProperties(
            $this->geoJson,
            $this->confirmed
        );
    }

    /**
     * {@inheritdoc}
     *
     * @throws UserNotFoundException
     */
    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('feature_import_statement_pdf');
    }

    public function isReferencable(): bool
    {
        return true;
    }

    public function isDirectlyAccessible(): bool
    {
        return true;
    }

    protected function getProperties(): array
    {
        return [
            $this->createAttribute($this->id)
                ->readable(true)->filterable()->sortable(),
            $this->createAttribute($this->url)
                ->readable(true, function (AnnotatedStatementPdfPage $page): string {
                    if ($this->currentUser->hasPermission(
                        'feature_ai_create_annotated_statement_pdf_pages'
                    )) {
                        $url = $page->getUrl();

                        if (null !== $this->globalConfig->getHtaccessUser()) {
                            $user = $this->globalConfig->getHtaccessUser();
                            $pass = $this->globalConfig->getHtaccessPass() ?? '';
                            $url = preg_replace('!://!', '://'.$user.':'.$pass.'@', $url);
                        }

                        return $url;
                    }

                    return $page->getUrl();
                }),
            $this->createAttribute($this->width)
                ->readable(true)->filterable()->sortable(),
            $this->createAttribute($this->height)
                ->readable(true)->filterable()->sortable(),
            $this->createAttribute($this->geoJson)
                ->readable(true, static function (AnnotatedStatementPdfPage $page): array {
                    return Json::decodeToArray($page->getGeoJson());
                }),
            $this->createAttribute($this->confirmed)
                ->filterable()->sortable(),
            $this->createAttribute($this->pageSortIndex)->sortable()->aliasedPath($this->pageOrder),
            $this->createToOneRelationship($this->annotatedStatementPdf)
                ->readable()->filterable()->sortable(),
        ];
    }
}
