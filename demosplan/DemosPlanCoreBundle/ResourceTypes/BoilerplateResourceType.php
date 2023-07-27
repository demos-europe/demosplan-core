<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceTypes;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Boilerplate;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Services\HTMLSanitizer;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\PathsBasedInterface;

/**
 * Boilerplate means "Textbausteine"/"_predefined_texts", not "ProcedureBlueprints".
 *
 * @template-extends DplanResourceType<Boilerplate>
 *
 * @property-read End $ident
 * @property-read End $title
 * @property-read End $text
 * @property-read End $categoriesTitle @deprecated use a relationship instead
 * @property-read End $procedureId @deprecated use a relationship instead
 * @property-read ProcedureResourceType $procedure
 * @property-read BoilerplateGroupResourceType $group
 */
final class BoilerplateResourceType extends DplanResourceType
{
    public function __construct(private readonly HTMLSanitizer $htmlSanitizer)
    {
    }

    public static function getName(): string
    {
        return 'Boilerplate';
    }

    public function getEntityClass(): string
    {
        return Boilerplate::class;
    }

    public function isAvailable(): bool
    {
        return $this->currentUser->hasPermission('area_admin_boilerplates');
    }

    public function getAccessCondition(): PathsBasedInterface
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (null === $procedure) {
            return $this->conditionFactory->false();
        }

        return $this->conditionFactory->propertyHasValue(
            $procedure->getId(),
            $this->procedure->id
        );
    }

    public function getDefaultSortMethods(): array
    {
        return [
            $this->sortMethodFactory->propertyAscending($this->title),
        ];
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
            $this->createAttribute($this->id)->readable(true)->aliasedPath($this->ident),
            $this->createAttribute($this->title)->readable(true),
            $this->createAttribute($this->procedureId)
                ->readable(true)->aliasedPath($this->procedure->id),
            $this->createAttribute($this->text)->sortable()
                ->readable(true, fn (Boilerplate $boilerplate): string => $this->htmlSanitizer->purify($boilerplate->getText()), true),
            $this->createAttribute($this->categoriesTitle)
                ->readable(true, fn (Boilerplate $boilerplate): array => $boilerplate->getCategoryTitles()),
            // defaultInclude used because of recursion
            $this->createToOneRelationship($this->group, true)->readable(true),
        ];
    }
}
