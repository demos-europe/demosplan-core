<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use Cocur\Slugify\Slugify;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Slug;
use demosplan\DemosPlanCoreBundle\Entity\User\Address;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\OrgaNotFoundException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use demosplan\DemosPlanCoreBundle\ValueObject\User\DataProtectionOrganisation;
use demosplan\DemosPlanCoreBundle\ValueObject\User\ImprintOrganisation;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\QueryBuilder;
use Exception;
use Faker\Provider\Uuid;

/**
 * @template-extends SluggedRepository<Orga>
 */
class OrgaRepository extends SluggedRepository implements ArrayInterface
{
    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     *
     * @return Orga|null
     */
    public function get($entityId)
    {
        try {
            /** @var Orga $orga */
            $orga = $this->findOneBy(['id' => $entityId]);

            return $orga;
        } catch (NoResultException) {
            return null;
        }
    }

    /**
     * Get all Planningoffices.
     *
     * @return array<int, Orga>|null
     */
    public function getPlanningOfficesList(Customer $customer): ?array
    {
        $em = $this->getEntityManager();
        // get OrgaIds
        $query = $em->createQueryBuilder()
            ->select('IDENTITY(relation_customer_orga_orga_type.orga)')
            ->from(OrgaStatusInCustomer::class, 'relation_customer_orga_orga_type')
            ->join('relation_customer_orga_orga_type.orgaType', 'ot')
            ->andWhere('ot.name = :planningOfficeOrgaTypeName')
            ->setParameter('planningOfficeOrgaTypeName', OrgaType::PLANNING_AGENCY)
            ->andWhere('relation_customer_orga_orga_type.customer = :customer')
            ->setParameter('customer', $customer)
            ->andWhere('relation_customer_orga_orga_type.status = :status')
            ->setParameter('status', OrgaStatusInCustomer::STATUS_ACCEPTED)
            ->getQuery();

        $orgaResult = $query->getResult();

        // return Entities
        return $em->createQueryBuilder()
            ->select('o')
            ->from(Orga::class, 'o')
            ->where('o.id IN (:ids)')
            ->andWhere('o.deleted = :deleted')
            ->setParameter('ids', $orgaResult)
            ->setParameter('deleted', false)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all Datainput Orgas.
     *
     * @return array Orga[]|null
     */
    public function getDataInputOrgaList()
    {
        try {
            $em = $this->getEntityManager();
            $query = $em->createQueryBuilder()
                ->select('o')
                ->from(Orga::class, 'o')
                ->join('o.users', 'ou')
                ->join('ou.roleInCustomers', 'userRoleInCustomer')
                ->join('userRoleInCustomer.role', 'ur')
                ->where('ur.code = :code')
                ->setParameter('code', Role::PROCEDURE_DATA_INPUT);
            $query = $query->getQuery();

            return $query->getResult();
        } catch (NoResultException) {
            return null;
        }
    }

    /**
     * Add Entity to database.
     *
     * @throws Exception
     */
    public function add(array $data): Orga
    {
        try {
            $orga = new Orga();
            $orga = $this->generateObjectValues($orga, $data);

            return $this->addOrgaObject($orga);
        } catch (Exception $e) {
            $this->logger->warning('Orga could not be added. ', [$e]);
            throw $e;
        }
    }

    public function addOrgaObject(Orga $orga): Orga
    {
        try {
            $em = $this->getEntityManager();

            $this->validate($orga);

            $em->persist($orga);
            // use orga ID as initial slug value
            $this->handleSlugUpdate($orga, $orga->getId());
            $em->persist($orga);
            $em->flush();

            return $orga;
        } catch (Exception $e) {
            $this->logger->warning('Orga object could not be added. ', [$e]);
            throw $e;
        }
    }

    /**
     * Update Entity.
     *
     * @param string $entityId
     *
     * @return Orga
     *
     * @throws Exception
     */
    public function update($entityId, array $data)
    {
        try {
            $em = $this->getEntityManager();
            $entity = $this->get($entityId);
            if (!$entity instanceof Orga) {
                throw OrgaNotFoundException::createFromId($entityId);
            }
            $entity = $this->generateObjectValues($entity, $data);

            $this->validate($entity);

            $em->persist($entity);
            $em->flush();

            return $entity;
        } catch (Exception $e) {
            $this->logger->warning('Update Orga failed Reason: ', [$e]);
            throw $e;
        }
    }

    /**
     * Adds a User to an Organisation.
     *
     * @param string $orgaId
     *
     * @return Orga
     *
     * @throws Exception
     */
    public function addUser($orgaId, User $user)
    {
        try {
            $em = $this->getEntityManager();
            $orgaEntity = $this->get($orgaId);
            if (!$orgaEntity instanceof Orga) {
                throw OrgaNotFoundException::createFromId($orgaId);
            }
            if (!$orgaEntity->getUsers()->contains($user)) {
                // add User
                $orgaEntity->addUser($user);
                $em->persist($orgaEntity);
                $em->flush();
            }

            return $orgaEntity;
        } catch (Exception $e) {
            $this->logger->warning('Add User to Orga failed Reason: ', [$e]);
            throw $e;
        }
    }

    /**
     * Adds a User to an Organisation.
     *
     * @param string $orgaId
     *
     * @return Orga
     *
     * @throws Exception
     */
    public function removeUser($orgaId, User $user)
    {
        try {
            $em = $this->getEntityManager();
            $orgaEntity = $this->get($orgaId);
            if (!$orgaEntity instanceof Orga) {
                throw OrgaNotFoundException::createFromId($orgaId);
            }
            $orgaEntity->removeUser($user);
            $em->persist($orgaEntity);
            $em->flush();

            return $orgaEntity;
        } catch (Exception $e) {
            $this->logger->warning('Remove User from Orga failed Reason: ', [$e]);
            throw $e;
        }
    }

    /**
     * Delete Entity.
     *
     * @param string $entityId
     *
     * @return bool
     */
    public function delete($entityId)
    {
        try {
            $em = $this->getEntityManager();
            $em->remove($em->getReference(Orga::class, $entityId));
            $em->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->warning('Delete Orga failed Reason: ', [$e]);

            return false;
        }
    }

    /**
     * @param string $name
     *
     * @return OrgaType|null
     *
     * @throws NonUniqueResultException
     */
    public function getOrgaTypeByName($name)
    {
        try {
            $query = $this->getEntityManager()
                ->createQueryBuilder()
                ->select('orgaType')
                ->from(OrgaType::class, 'orgaType')
                ->where('orgaType.name = :name')
                ->setParameter('name', $name)
                ->getQuery();

            return $query->getSingleResult();
        } catch (NoResultException) {
            return null;
        }
    }

    /**
     * @param string $label
     *
     * @return OrgaType|null
     *
     * @throws NonUniqueResultException
     */
    public function getOrgaTypeByLabel($label)
    {
        try {
            $query = $this->getEntityManager()
                ->createQueryBuilder()
                ->select('orgaType')
                ->from(OrgaType::class, 'orgaType')
                ->where('orgaType.label = :label')
                ->setParameter('label', $label)
                ->getQuery();

            return $query->getSingleResult();
        } catch (NoResultException) {
            return null;
        }
    }

    /**
     * @param Orga $entity
     *
     * @return Orga
     *
     * @throws NonUniqueResultException
     */
    public function generateObjectValues($entity, array $data)
    {
        $commonEntityFields = collect(
            [
                'ccEmail2',
                'competence',
                'contactPerson',
                'dataProtection',
                'email2',
                'emailReviewerAdmin',
                'gatewayName',
                'gwId',
                'imprint',
                'paperCopy',
                'paperCopySpec',
                'showname',
                'name',
                'street',
                'houseNumber',
                'city',
                'competence',
                'phone',
                'postalcode',
            ]
        );

        $this->setEntityFieldsOnFieldCollection($commonEntityFields, $entity, $data);

        if (array_key_exists('name', $data)) {
            $entity->setName($data['name']);
        }
        if (array_key_exists('slug', $data)) {
            $this->handleSlugUpdate($entity, $data['slug']);
        }

        // when showlist is disabled agency may not be invited to procedures!
        // Therefore we need to take extra care and measures to ensure that
        // this flag is not accidentally set to false.
        // Due to this updateShowlist needs to be explicitly set directly or
        // via UserHandler->setCanUpdateShowList() in backend
        if (array_key_exists('updateShowlist', $data)) {
            $entity->setShowlist((bool) $data['showlist']);
        }

        // Try to be as much backward compatible as possible
        // twig forms do not send showname at all when checkbox not ticked
        // API sends (bool)false. Will break as soon as showname is not transferred when
        // not edited.
        $entity->setShowname(false);
        if (array_key_exists('showname', $data) && (bool) $data['showname']) {
            $entity->setShowname(true);
        }

        if (array_key_exists('copy', $data)) {
            $entity->setPaperCopy((int) $data['copy']);
        }
        if (array_key_exists('copySpec', $data)) {
            $entity->setPaperCopySpec($data['copySpec']);
        }
        // ## Addressdata (if address already exists) ###
        if (array_key_exists('address_street', $data)) {
            $entity->setStreet($data['address_street']);
        }
        if (array_key_exists('address_houseNumber', $data)) {
            $entity->setHouseNumber($data['address_houseNumber']);
        }
        if (array_key_exists('address_postalcode', $data)) {
            $entity->setPostalcode($data['address_postalcode']);
        }
        if (array_key_exists('address_city', $data)) {
            $entity->setCity($data['address_city']);
        }
        if (array_key_exists('address_phone', $data)) {
            $entity->setPhone($data['address_phone']);
        }
        if (array_key_exists('address_fax', $data)) {
            $entity->setFax($data['address_fax']);
        }
        if (array_key_exists('address_state', $data)) {
            $entity->setState($data['address_state']);
        }
        // Add Address entity
        if (array_key_exists('address', $data) && $data['address'] instanceof Address) {
            $entity->addAddress($data['address']);
        }
        // set or reset logo
        if (array_key_exists('logo', $data) && ($data['logo'] instanceof File || null === $data['logo'])) {
            $entity->setLogo($data['logo']);
        }
        if (array_key_exists('customer', $data) && $data['customer'] instanceof Customer) {
            $entity->addCustomer($data['customer']);

            // ultimately this needs to be able to set in interface
            if (array_key_exists('type', $data)) {
                $orgaType = $this->getOrgaTypeByName($data['type']);
                if ($orgaType instanceof OrgaType) {
                    $entity->addCustomerAndOrgaType($data['customer'], $orgaType);
                }
            }
        }
        if (array_key_exists('customers', $data) && is_array($data['customers'])) {
            $entity->addCustomers($data['customers']);

            // ultimately this needs to be able to set in interface
            foreach ($data['customers'] as $customer) {
                if (array_key_exists('type', $data) && $customer instanceof Customer) {
                    $orgaType = $this->getOrgaTypeByName($data['type']);
                    if ($orgaType instanceof OrgaType) {
                        $entity->addCustomerAndOrgaType($customer, $orgaType);
                    }
                }
            }
        }
        if (array_key_exists('branding', $data)) {
            $entity->setBranding($data['branding']);
        }
        $this->manageOrgaRegistrationStatus($entity, $data);

        return $entity;
    }

    /**
     * @return array<int, DataProtectionOrganisation>
     */
    public function getDataProtectionMunicipalities(Customer $customer): array
    {
        $queryBuilder = $this->createOrgaQueryBuilderWithoutSelect(
            $customer,
            false,
            OrgaType::MUNICIPALITY,
            'accepted'
        );

        return $queryBuilder
            ->select(sprintf(
                'NEW %s(orga.id, orga.name, orga.dataProtection)',
                DataProtectionOrganisation::class
            ))
            ->andWhere('orga.dataProtection != :dataProtection')
            ->setParameter('dataProtection', '')
            ->orderBy('orga.name')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, ImprintOrganisation>
     */
    public function getImprintMunicipalities(Customer $customer): array
    {
        $queryBuilder = $this->createOrgaQueryBuilderWithoutSelect(
            $customer,
            false,
            OrgaType::MUNICIPALITY,
            'accepted'
        );

        return $queryBuilder
            ->select(sprintf(
                'NEW %s(orga.id, orga.name, orga.imprint)',
                ImprintOrganisation::class
            ))
            ->andWhere('orga.imprint != :orgaImprint')
            ->setParameter('orgaImprint', '')
            ->orderBy('orga.name')
            ->getQuery()
            ->getResult();
    }

    private function createOrgaQueryBuilderWithoutSelect(
        Customer $customer,
        bool $deleted,
        string $orgaTypeName,
        string $statusInCustomer
    ): QueryBuilder {
        return $this->getEntityManager()->createQueryBuilder()
            ->from(Orga::class, 'orga')
            ->leftJoin('orga.statusInCustomers', 'statusInCustomers')
            ->leftJoin('statusInCustomers.orgaType', 'orgaType')
            ->where('orgaType.name = :name')
            ->setParameter('name', $orgaTypeName)
            ->andWhere('orga.deleted = :deleted')
            ->setParameter('deleted', $deleted)
            ->andWhere('statusInCustomers.status = :status')
            ->setParameter('status', $statusInCustomer)
            ->andWhere('statusInCustomers.customer = :customerId')
            ->setParameter('customerId', $customer->getId());
    }

    /**
     * Adds the Orga registration status, related to the differnt orgatype and customer combinations.
     * The Info in $data comes already filtered according to the user permissions to handle domains different from
     * current.
     *
     * @throws NonUniqueResultException
     */
    private function manageOrgaRegistrationStatus(Orga $orga, array $data): void
    {
        $activationChanges = $this->getActivationChanges($data);
        foreach ($activationChanges as $activationChange) {
            $status = $activationChange['status'];
            $orgaTypeName = $activationChange['type'];
            $orgaType = $this->getOrgaTypeByName($orgaTypeName);
            $customer = $activationChange['customer'];
            $subdomain = $activationChange['subdomain'];
            if (!$orgaType instanceof OrgaType) {
                $this->getLogger()->warning('Could not find OrgaType by name', [$orgaTypeName]);
                continue;
            }
            $orgaStatusInCustomers = $orga->getStatusInCustomers();
            /** @var OrgaStatusInCustomer $orgaStatusInCustomer */
            foreach ($orgaStatusInCustomers as $orgaStatusInCustomer) {
                $orgaSubdomain = $orgaStatusInCustomer->getCustomer()->getSubdomain();
                $currentOrgaTypeName = $orgaStatusInCustomer->getOrgaType()->getName();
                if ($orgaSubdomain === $subdomain && $currentOrgaTypeName === $orgaTypeName) {
                    $orgaStatusInCustomer->setStatus($status);
                    continue 2;
                }
            }
            // when no relation to update could be found create a new one
            $orgaStatusInCustomer = new OrgaStatusInCustomer();
            $orgaStatusInCustomer->setStatus($status);
            $orgaStatusInCustomer->setOrgaType($orgaType);
            $orgaStatusInCustomer->setCustomer($customer);
            $orgaStatusInCustomer->setOrga($orga);
            $orga->addStatusInCustomer($orgaStatusInCustomer);
        }
    }

    /**
     * Returns info regarding registration status changes or empty array if none.
     */
    public function getActivationChanges(array $data): array
    {
        return $data['registrationStatuses'] ?? [];
    }

    /**
     * Overrides all relevant data field of the given organisation with default values.
     *
     * @param string $organisationId
     *
     * @return Orga|bool
     */
    public function wipe($organisationId)
    {
        try {
            $em = $this->getEntityManager();

            /** @var Orga $organisation */
            $organisation = $this->find($organisationId);

            $organisation->setName(null);
            $organisation->setGatewayName(null);
            $organisation->setCode(null);
            $organisation->setEmail2(null);
            $organisation->setCcEmail2(null);
            $organisation->setGwId(null);
            $organisation->setCompetence(null);
            $organisation->setContactPerson(null);
            $organisation->setPaperCopy(0);
            $organisation->setPaperCopySpec(null);

            $organisation->setDeleted(true);
            $organisation->setShowname(false);
            $organisation->setShowlist(false);

            $em->persist($organisation);
            $em->flush();
        } catch (Exception $e) {
            $this->logger->error('Could not wipe Orga '.$organisationId.' ', [$e]);

            return false;
        }

        return $organisation;
    }

    /**
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function findOrgaBySlug(string $slug): Orga
    {
        $slugify = new Slugify();
        $slug = $slugify->slugify($slug);
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('o')
            ->from(Orga::class, 'o')
            ->join('o.slugs', 'slug')
            ->where('slug.name = :slug')
            ->setParameter('slug', $slug)
            ->getQuery();

        return $query->getSingleResult();
    }

    public function isPublicAffairsAgency(Orga $orga): bool
    {
        foreach ($orga->getAllUsers() as $user) {
            if ($user->isPublicAgency()) {
                return true;
            }
        }

        return false;
    }

    public function isOrgaType(Orga $orga, string $orgaType): bool
    {
        $orgaTypeMapping = OrgaType::ORGATYPE_ROLE;

        if (!array_key_exists($orgaType, $orgaTypeMapping)) {
            throw new InvalidArgumentException('Orga Type "'.$orgaType.'" does not exist.');
        }
        /** @var User $user */
        foreach ($orga->getAllUsers() as $user) {
            $userRoles = $user->getDplanRolesArray();
            foreach ($userRoles as $userRole) {
                if (in_array($userRole, $orgaTypeMapping[$orgaType], true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Creates a relation between the Orga (Public Affairs Agency) and the Customer.
     * If the Orga is no PAA throws an InvalidArgumentException.
     * If the Orga and the Customer are already realted then does nothing.
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws InvalidArgumentException
     */
    public function addCustomer(Customer $customer, Orga $publicAffairsAgency)
    {
        $publicAffairsAgency->addCustomer($customer);
        $this->getEntityManager()->persist($publicAffairsAgency);
        $this->getEntityManager()->flush();
    }

    /**
     * Removes the relation between the Orga and the Customer.
     * If the Orga and the Customer are already related then does nothing.
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function removeCustomer(Customer $customer, Orga $orga)
    {
        $orga->removeCustomer($customer);
        $this->getEntityManager()->persist($orga);
        $this->getEntityManager()->flush();
    }

    /**
     * @throws NonUniqueResultException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws ConnectionException
     */
    public function createOrgaRegistration(
        string $orgaName,
        string $phone,
        string $userFirstName,
        string $userLastName,
        string $email,
        Customer $customer,
        Role $masterUserRole,
        array $orgaTypeNames
    ): Orga {
        $em = $this->getEntityManager();
        $em->getConnection()->beginTransaction();

        $user = new User();
        $user->setFirstname($userFirstName);
        $user->setLastname($userLastName);
        $user->setEmail($email);
        $user->setLogin($email);
        $user->addDplanrole($masterUserRole, $customer);

        $em->persist($user);

        $department = new Department();
        $department->setName(Department::DEFAULT_DEPARTMENT_NAME);
        $department->addUser($user);
        $em->persist($department);
        $orgaStatusInCustomers = [];

        $orgaId = Uuid::uuid();
        $orgaSlug = new Slug($orgaId);

        foreach ($orgaTypeNames as $orgaTypeName) {
            $orgaType = $this->getOrgaTypeByName($orgaTypeName);

            if (!$orgaType instanceof OrgaType) {
                $this->getLogger()->warning('Could not find Orgatype', [$orgaTypeName]);
                continue;
            }

            $orgaStatusInCustomer = new OrgaStatusInCustomer();
            $orgaStatusInCustomer->setStatus(OrgaStatusInCustomer::STATUS_PENDING);
            $orgaStatusInCustomer->setCustomer($customer);
            $orgaStatusInCustomer->setOrgaType($orgaType);

            $orgaStatusInCustomers[] = $orgaStatusInCustomer;
        }
        $orga = new Orga();
        $orga->setId($orgaId);
        $orga->setName($orgaName);
        $orga->setPhone($phone);
        $orga->addUser($user);
        $orga->addSlug($orgaSlug);
        $orga->setStatusInCustomers(new ArrayCollection($orgaStatusInCustomers));
        $orga->addDepartment($department);

        foreach ($orgaStatusInCustomers as $orgaStatusInCustomer) {
            $orgaStatusInCustomer->setOrga($orga);
        }
        $em->persist($orga);

        $em->flush();
        $em->getConnection()->commit();

        return $orga;
    }

    /**
     * @param Orga $organisation
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateObject($organisation): Orga
    {
        $this->validate($organisation);

        $this->getEntityManager()->persist($organisation);
        $this->getEntityManager()->flush();

        return $organisation;
    }
}
