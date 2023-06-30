<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use DateTime;
use DemosEurope\DemosplanAddon\Logic\ResourceChange;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\DuplicateInternIdException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\PropertiesUpdater;
use demosplan\DemosPlanCoreBundle\Logic\ResourceTypeService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementResourceType;
use Exception;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class StatementResourceTypeService extends ResourceTypeService
{
    /**
     * @var CurrentUserInterface
     */
    protected $currentUser;

    /**
     * @var ResourceTypeService
     */
    protected $resourceTypeService;

    public function __construct(
        ValidatorInterface $validator,
        CurrentUserInterface $currentUser,
        ResourceTypeService $resourceTypeService,
        private readonly StatementService $statementService,
        private readonly StatementDeleter $statementDeleter
    ) {
        $this->currentUser = $currentUser;
        $this->resourceTypeService = $resourceTypeService;
        parent::__construct($validator);
    }

    /**
     * @throws Exception
     */
    public function update(
        Statement $object,
        StatementResourceType $resourceType,
        array $propertiesToUpdate
    ): ResourceChange {
        $updater = new PropertiesUpdater($propertiesToUpdate);

        $resourceChange = new ResourceChange($object, $resourceType, $propertiesToUpdate);

        $updater->ifPresent($resourceType->submitDate, static function ($value) use ($object): void {
            $object->setSubmit(new DateTime($value));
        });
        $updater->ifPresent($resourceType->assignee, $object->setAssignee(...));
        $updater->ifPresent($resourceType->fullText, $object->setText(...));
        $updater->ifPresent($resourceType->submitType, $object->setSubmitType(...));
        $updater->ifPresent($resourceType->internId, function ($internIdToSet) use ($object): void {
            // check for unique
            $isUnique = $this->statementService->isInternIdUniqueForProcedure($internIdToSet, $object->getProcedureId());
            if (!$isUnique) {
                throw DuplicateInternIdException::create($internIdToSet, $object->getProcedureId());
            }

            $object->getOriginal()->setInternId($internIdToSet);
        });

        // perform the actual update of the StatementMeta
        $meta = $object->getMeta();
        $updater->ifPresent($resourceType->authorName, $meta->setAuthorName(...));
        $updater->ifPresent($resourceType->initialOrganisationName, $meta->setOrgaName(...));
        $updater->ifPresent($resourceType->initialOrganisationCity, $meta->setOrgaCity(...));
        $updater->ifPresent($resourceType->initialOrganisationDepartmentName, $meta->setOrgaDepartmentName(...));
        $updater->ifPresent($resourceType->initialOrganisationPostalCode, $meta->setOrgaPostalCode(...));
        $updater->ifPresent($resourceType->initialOrganisationStreet, $meta->setOrgaStreet(...));
        $updater->ifPresent($resourceType->initialOrganisationHouseNumber, $meta->setHouseNumber(...));
        // authoredDate should be less or equal to the submitDate
        $submitDate = $object->getSubmitDateString();
        $updater->ifPresent($resourceType->authoredDate, function ($value) use ($meta, $submitDate): void {
            if ('' === $value || strtotime((string) $submitDate) < strtotime($value)) {
                $value = $submitDate;
            }
            $meta->setAuthoredDate(new DateTime($value));
        });
        $updater->ifPresent($resourceType->submitterEmailAddress, $object->setSubmitterEmailAddress(...));
        $updater->ifPresent($resourceType->submitName, $meta->setSubmitName(...));
        $updater->ifPresent($resourceType->submitterName, $meta->setSubmitName(...));
        $updater->ifPresent($resourceType->submitterHouseNumber, $meta->setHouseNumber(...));
        $updater->ifPresent($resourceType->submitterStreet, $meta->setOrgaStreet(...));
        $updater->ifPresent($resourceType->submitterCity, $meta->setOrgaCity(...));
        $updater->ifPresent($resourceType->submitterPostalCode, $meta->setOrgaPostalCode(...));
        $updater->ifPresent($resourceType->memo, $object->setMemo(...));
        $updater->ifPresent($resourceType->segmentDraftList, $object->setDraftsListJson(...));
        $updater->ifPresent($resourceType->similarStatementSubmitters, $object->setSimilarStatementSubmitters(...));

        $this->resourceTypeService->validateObject($object);
        $this->resourceTypeService->validateObject($meta);

        return $resourceChange;
    }

    public function deleteStatement(Statement $statement): bool
    {
        return $this->statementDeleter->deleteStatementObject($statement);
    }
}
