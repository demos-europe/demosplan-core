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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Services\HTMLSanitizer;
use EDT\PathBuilding\End;

/**
 * Boilerplate means "Textbausteine"/"_predefined_texts", not "ProcedureBlueprints".
 *
 * @template-extends DplanResourceType<Boilerplate>
 *
 * @property-read End $ident
 * @property-read End $title
 * @property-read End $text
 * @property-read End $verified
 * @property-read End $pendingDeletion not exposed as a readable attribute, used only in
 *                 {@see BoilerplateResourceType::getAccessConditions()}
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

    public function getIdentifierPropertyPath(): array
    {
        return $this->ident->getAsNames();
    }

    public function isAvailable(): bool
    {
        // DPLAN-18271, Clarified Decision 12: the editor fetches boilerplate content for
        // unlink materialization via this resource type — caseworkers with only
        // feature_segment_recommendation_edit (not area_admin_boilerplates) must pass this
        // gate too, or that content fetch fails for exactly the target audience.
        return $this->currentUser->hasAnyPermissions('area_admin_boilerplates', 'feature_segment_recommendation_edit');
    }

    protected function getAccessConditions(): array
    {
        $procedure = $this->currentProcedureService->getProcedure();
        if (!$procedure instanceof Procedure) {
            return [$this->conditionFactory->false()];
        }

        return [
            $this->conditionFactory->propertyHasValue($procedure->getId(), $this->procedure->id),
            // DPLAN-18271: a boilerplate pending async deletion is, conceptually, already
            // gone (see BoilerplateDeletionService) — must not be listed or fetched.
            $this->conditionFactory->propertyHasValue(false, $this->pendingDeletion),
        ];
    }

    public function getDefaultSortMethods(): array
    {
        return [
            $this->sortMethodFactory->propertyAscending($this->title),
        ];
    }

    protected function getProperties(): array
    {
        return [
            $this->createIdentifier()->readable()->aliasedPath($this->ident),
            $this->createAttribute($this->title)->readable(true),
            $this->createAttribute($this->procedureId)
                ->readable(true)->aliasedPath($this->procedure->id),
            $this->createAttribute($this->text)->sortable()
                ->readable(true, fn (Boilerplate $boilerplate): string => $this->htmlSanitizer->purify($boilerplate->getText())),
            $this->createAttribute($this->categoriesTitle)
                ->readable(true, fn (Boilerplate $boilerplate): array => $boilerplate->getCategoryTitles()),
            $this->createAttribute($this->verified)
                ->readable(true, fn (Boilerplate $boilerplate): bool => $boilerplate->isVerified()),
            // defaultInclude used because of recursion
            $this->createToOneRelationship($this->group)->readable(true, null, true),
        ];
    }
}
