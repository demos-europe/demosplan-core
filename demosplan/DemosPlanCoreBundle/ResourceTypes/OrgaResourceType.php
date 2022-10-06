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

use demosplan\DemosPlanCoreBundle\Entity\User\Address;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanUserBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanUserBundle\Logic\RoleService;
use Doctrine\Common\Collections\Collection;
use EDT\PathBuilding\End;
use EDT\Querying\Contracts\FunctionInterface;
use EDT\Querying\Contracts\PathsBasedInterface;
use Tightenco\Collect\Support\Collection as TightencoCollection;

/**
 * @template-extends DplanResourceType<Orga>
 *
 * @property-read End                              $deleted
 * @property-read OrgaStatusInCustomerResourceType $statusInCustomers
 * @property-read End                              $name
 * @property-read MasterToebResourceType           $masterToeb
 * @property-read CustomerResourceType             $customers @deprecated Use {@link OrgaResourceType::$statusInCustomers} instead
 * @property-read End                              $ccEmail2 @deprecated Create attribute with better name instead of using this one
 * @property-read End                              $city @deprecated Use a {@link Address} relationships instead
 * @property-read End                              $competence
 * @property-read End                              $contactPerson
 * @property-read End                              $copy
 * @property-read End                              $paperCopy
 * @property-read End                              $copySpec
 * @property-read End                              $paperCopySpec
 * @property-read End                              $email2 @deprecated Use {@link OrgaResourceType::$participationEmail} instead
 * @property-read End                              $participationEmail
 * @property-read End                              $phone @deprecated Use a {@link Address} relationships instead
 * @property-read End                              $emailNotificationEndingPhase
 * @property-read End                              $emailNotificationNewStatement
 * @property-read End                              $postalcode @deprecated Use a {@link Address} relationships instead
 * @property-read End                              $reviewerEmail
 * @property-read End                              $emailReviewerAdmin
 * @property-read End                              $showlist
 * @property-read End                              $showname
 * @property-read End                              $state @deprecated Use a {@link Address} relationships instead
 * @property-read End                              $street @deprecated Use a {@link Address} relationships instead
 * @property-read End                              $houseNumber @deprecated Use a {@link Address} relationships instead
 * @property-read End                              $submissionType
 * @property-read End                              $types @deprecated Use {@link OrgaResourceType::$statusInCustomers} instead
 * @property-read End                              $url
 * @property-read End                              $registrationStatuses @deprecated use {@link OrgaResourceType::$statusInCustomers} instead
 * @property-read DepartmentResourceType           $departments
 * @property-read SlugResourceType                 $currentSlug
 * @property-read BrandingResourceType             $branding
 * @property-read RoleResourceType                 $allowedRoles
 */
final class OrgaResourceType extends DplanResourceType
{
    /**
     * @deprecated use {@link OrgaResourceType::$statusInCustomers} instead
     */
    private const REGISTRATION_STATUSES_STATUS = 'status';

    /**
     * @deprecated use {@link OrgaResourceType::$statusInCustomers} instead
     */
    private const REGISTRATION_STATUSES_TYPE = 'type';

    /**
     * @deprecated use {@link OrgaResourceType::$statusInCustomers} instead
     */
    private const REGISTRATION_STATUSES_SUBDOMAIN = 'subdomain';

    /**
     * @var RoleService
     */
    private $roleService;

    public function __construct(RoleService $roleService)
    {
        $this->roleService = $roleService;
    }

    public function getEntityClass(): string
    {
        return Orga::class;
    }

    public static function getName(): string
    {
        return 'Orga';
    }

    public function getAccessCondition(): PathsBasedInterface
    {
        $extendedOrgaAccess = $this->currentUser->hasAnyPermissions(
            'area_manage_orgadata',
            'area_manage_orgas',
            'area_manage_orgas_all',
            'area_organisations',
            'area_report_mastertoeblist'
        );

        $mandatoryConditions = $this->getMandatoryConditions();

        // permissions allow the user to access all organisation resources
        if ($extendedOrgaAccess) {
            return $this->conditionFactory->allConditionsApply(
                ...$mandatoryConditions
            );
        }

        $organisationId = $this->currentUser->getUser()->getOrga()->getId();

        // if no special permissions are given, the user can at least access its own organisation
        return $this->conditionFactory->allConditionsApply(
            $this->conditionFactory->propertyHasValue($organisationId, ...$this->id),
            ...$mandatoryConditions
        );
    }

    /**
     * Get the conditions an {@link Orga} entity must fulfill to be considered as resource of this
     * resource type regardless of the permissions enabled. **All** conditions must apply.
     * Depending on the permission you may need to add additional conditions to reduce the set of
     * {@link Orga} entities in the database further.
     *
     * @return array<int, FunctionInterface<bool>>
     */
    public function getMandatoryConditions(): array
    {
        // Regardless of permissions or organisation affiliation we never show deleted organisations
        // or organisations of a foreign customer.
        return [
            $this->conditionFactory->propertyHasValue(false, ...$this->deleted),
            $this->conditionFactory->propertyHasValue(
                $this->currentCustomerService->getCurrentCustomer()->getId(),
                ...$this->statusInCustomers->customer->id
            ),
        ];
    }

    public function isAvailable(): bool
    {
        return true;
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
        $statusInCustomers = $this->createToManyRelationship($this->statusInCustomers);
        $properties = [
            $this->createAttribute($this->id)->sortable()->filterable()->readable(true),
            $this->createAttribute($this->name)->sortable()->filterable()->readable(true),
            $this->createAttribute($this->ccEmail2)->readable(true),
            $this->createAttribute($this->city)->readable(true, static function (Orga $orga): string {
                return $orga->getCity();
            }),
            $this->createAttribute($this->competence)->readable(true),
            $this->createAttribute($this->contactPerson)->readable(true),
            $this->createAttribute($this->copy)->aliasedPath($this->paperCopy)->readable(true),
            $this->createAttribute($this->copySpec)->aliasedPath($this->paperCopySpec)->readable(true),
            $this->createAttribute($this->email2)->readable(true),
            $this->createAttribute($this->participationEmail)->aliasedPath($this->email2)->readable(true),
            $this->createAttribute($this->phone)->readable(true, static function (Orga $orga): string {
                return $orga->getPhone();
            }),
            $this->createAttribute($this->emailNotificationEndingPhase)->readable(true, [$this, 'getEmailNotificationEndingPhase']),
            $this->createAttribute($this->emailNotificationNewStatement)->readable(true, [$this, 'getEmailNotificationNewStatement']),
            $this->createAttribute($this->postalcode)->readable(true, static function (Orga $orga): string {
                return $orga->getPostalcode();
            }),
            $this->createAttribute($this->reviewerEmail)->aliasedPath($this->emailReviewerAdmin)->readable(true),
            $this->createAttribute($this->showlist)->readable(true),
            $this->createAttribute($this->showname)->readable(true),
            $this->createAttribute($this->state)->readable(true, static function (Orga $orga): string {
                return $orga->getState();
            }),
            $this->createAttribute($this->street)->readable(true, static function (Orga $orga): string {
                return $orga->getStreet();
            }),
            $this->createAttribute($this->houseNumber)->readable(true, static function (Orga $orga): string {
                return $orga->getHouseNumber();
            }),
            $this->createAttribute($this->submissionType)->readable(true, static function (Orga $orga): string {
                return $orga->getSubmissionType();
            }),
            $this->createAttribute($this->types)->readable(true, function (Orga $orga): array {
                return $orga->getTypes($this->globalConfig->getSubdomain());
            }),
            $this->createAttribute($this->url)->readable(true),
            $this->createAttribute($this->registrationStatuses)->readable(true, [$this, 'getRegistrationStatuses']),
            $this->createToOneRelationship($this->currentSlug, true)->readable(true),
            $this->createToManyRelationship($this->customers)->readable(false, static function (Orga $orga): Collection {
                return $orga->getCustomers();
            }),
            $this->createToManyRelationship($this->departments)->readable(false, static function (Orga $orga): TightencoCollection {
                return $orga->getDepartments();
            }),
            $statusInCustomers,
        ];

        if ($this->currentUser->hasPermission('feature_orga_branding_edit')) {
            $properties[] = $this->createToOneRelationship($this->branding)->readable();
        }

        if ($this->currentUser->hasPermission('feature_mastertoeblist')) {
            $properties[] = $this->createToOneRelationship($this->masterToeb)->sortable()->filterable()->readable();
        }

        // OrgaStatusInCustomer @organisation-list filtering for orga
        if ($this->currentUser->hasPermission('area_organisations')) {
            $statusInCustomers->sortable()->filterable();
        } else {
            $statusInCustomers->readable(false, [$this, 'getRegistration']);
        }

        if ($this->currentUser->hasPermission('area_manage_users')) {
            $properties[] = $this->createToManyRelationship($this->allowedRoles)
                ->readable(false, [$this, 'getAllowedRoles']);
        }

        return $properties;
    }

    /**
     * @return array<int, Role>
     */
    public function getAllowedRoles(Orga $orga): array
    {
        $currentCustomer = $this->currentCustomerService->getCurrentCustomer();
        $acceptedOrgaTypes = $orga->getStatusInCustomers()
            ->filter(static function (OrgaStatusInCustomer $orgaStatus): bool {
                return OrgaStatusInCustomer::STATUS_ACCEPTED === $orgaStatus->getStatus();
            })
            ->filter(static function (OrgaStatusInCustomer $orgaStatus) use ($currentCustomer): bool {
                return $orgaStatus->getCustomer() === $currentCustomer;
            })
            ->map(static function (OrgaStatusInCustomer $orgaStatus): OrgaType {
                return $orgaStatus->getOrgaType();
            })->getValues();

        return $this->roleService->getGivableRoles($acceptedOrgaTypes);
    }

    public function getRegistrationStatuses(Orga $orga): array
    {
        return $this->getRegistration($orga)
            ->map(
                static function (OrgaStatusInCustomer $orgaStatusInCustomer) {
                    return [
                        OrgaResourceType::REGISTRATION_STATUSES_STATUS    => $orgaStatusInCustomer->getStatus(),
                        OrgaResourceType::REGISTRATION_STATUSES_TYPE      => $orgaStatusInCustomer->getOrgaType()->getName(),
                        OrgaResourceType::REGISTRATION_STATUSES_SUBDOMAIN => $orgaStatusInCustomer->getCustomer()->getSubdomain(),
                    ];
                }
            )->toArray();
    }

    public function getEmailNotificationNewStatement(Orga $orga): bool
    {
        $emailNotificationNewStatement = false;
        $notifications = $orga->getNotifications();
        if (isset($notifications['emailNotificationNewStatement'])) {
            $emailNotificationNewStatement = 'true' === $notifications['emailNotificationNewStatement']['content'];
        }

        return $emailNotificationNewStatement;
    }

    public function getEmailNotificationEndingPhase(Orga $orga): bool
    {
        $emailNotificationEndingPhase = false;
        $notifications = $orga->getNotifications();
        if (isset($notifications['emailNotificationEndingPhase'])) {
            $emailNotificationEndingPhase = 'true' === $notifications['emailNotificationEndingPhase']['content'];
        }

        return $emailNotificationEndingPhase;
    }

    /**
     * @return Collection<int, OrgaStatusInCustomer>
     *
     * @throws CustomerNotFoundException
     */
    public function getRegistration(Orga $orga): Collection
    {
        $currentCustomer = $this->currentCustomerService->getCurrentCustomer();
        $orgaStatuses = $orga->getStatusInCustomers();
        if (!$this->currentUser->hasPermission('area_manage_orgas_all')) {
            $orgaStatuses = $orgaStatuses
                ->filter(static function (OrgaStatusInCustomer $orgaStatusInCustomer) use ($currentCustomer) {
                    return $orgaStatusInCustomer->getCustomer()->getSubdomain() === $currentCustomer->getSubdomain();
                });
        }

        return $orgaStatuses;
    }
}
