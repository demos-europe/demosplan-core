<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use DateTime;
use DemosEurope\DemosplanAddon\Logic\ApiRequest\FluentRepository;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\MasterToeb;
use demosplan\DemosPlanCoreBundle\Entity\User\MasterToebVersion;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\MissingDataException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\ORMException;
use Exception;

/**
 * @template-extends FluentRepository<MasterToeb>
 */
class MasterToebRepository extends FluentRepository implements ArrayInterface
{
    /**
     * Get a list of all mastertoeb entries from the DB, ordered be the name of the organisation (nulls last).
     *
     * @param bool $asArray return as array or object
     *
     * @return MasterToeb[]|array - ordered list of mastertoeb entries
     */
    public function getMasterToebList($asArray = false)
    {
        $dql = 'SELECT masterToeb, -masterToeb.orgaName as HIDDEN orgaName1 FROM demosplan\DemosPlanCoreBundle\Entity\User\MasterToeb as masterToeb ORDER BY orgaName1 DESC, masterToeb.orgaName ASC';
        $query = $this->getEntityManager()->createQuery($dql);
        $masterToebs = $asArray ? $query->getArrayResult() : $query->getResult();

        if (is_null($masterToebs)) {
            $this->logger->error('Get masterToebs failed.');
        }

        return $masterToebs;
    }

    /**
     * Get all masterToebs of a specific gatewayGroup.
     * Ordered by the name of the organisation (nulls last).
     *
     * @param string $gatewayName
     *
     * @return array of results
     */
    public function getByGatewayName($gatewayName)
    {
        $dql = 'SELECT masterToeb, -masterToeb.orgaName as HIDDEN orgaName1 FROM demosplan\DemosPlanCoreBundle\Entity\User\MasterToeb as masterToeb WHERE masterToeb.gatewayGroup = :gatewayName ORDER BY orgaName1 DESC, masterToeb.orgaName ASC';
        $query = $this->getEntityManager()->createQuery($dql);
        $query->setParameter('gatewayName', $gatewayName);
        $masterToebs = $query->getResult();

        if (is_null($masterToebs)) {
            $this->logger->error('Get masterToebs failed.');
        }

        return $masterToebs;
    }

    /**
     * Get all Reports related to the mastertoeblist.
     *
     * @return ReportEntry[]
     */
    public function getReport()
    {
        return $this->getEntityManager()->getRepository(ReportEntry::class)
            ->findBy(['group' => 'mastertoeb'], ['createDate' => 'desc']);
    }

    /**
     * Get a specific entity by ID.
     *
     * @param string $entityId - identifies the specific entry
     *
     * @return MasterToeb
     *
     * @throws EntityNotFoundException
     */
    public function get($entityId)
    {
        $masterToeb = $this->findOneBy(['ident' => $entityId]);
        if (is_null($masterToeb)) {
            $this->logger->error('Get release failed: MasterToeb with ID: '.$entityId.' not found.');
            throw new EntityNotFoundException('Get MasterToeb with ID: '.$entityId.' not found.');
        }

        return $masterToeb;
    }

    /**
     * Add an organisation to the DB.
     *
     * @param string $masterToebIdent
     * @param string $orgaIdent
     *
     * @throws EntityNotFoundException
     * @throws ORMException
     */
    public function addOrga($masterToebIdent, $orgaIdent)
    {
        $masterToeb = $this->find($masterToebIdent);

        if (is_null($masterToeb)) {
            $this->logger->error('Add orga failed: MasterToeb with ID: '.$masterToebIdent.' not found.');
            throw new EntityNotFoundException('Add orga failed: MasterToeb with ID: '.$masterToebIdent.' not found.');
        }

        $this->addVersion($masterToeb);
        $masterToeb->setModifiedDate(new DateTime());
        $masterToeb->setOrga($this->getEntityManager()->getReference(Orga::class, $orgaIdent));
        $this->getEntityManager()->persist($masterToeb);
        $this->getEntityManager()->flush();
    }

    /**
     * Add Entity to the DB.
     *
     * @return MasterToeb Entity
     */
    public function add(array $data)
    {
        if (!array_key_exists('orgaName', $data)) {
            $this->logger->error('Add MasterToeb failed: No orgaName in given array');
            throw new MissingDataException('Add MasterToeb failed: No orgaName in given array');
        } elseif (!$this->isValidOrgaName($data['orgaName'])) {
            $this->logger->warning('Update MasterToeb failed: Given organisation name is invalid.');
            throw new InvalidArgumentException('Update MasterToeb failed: Given organisation name is invalid.');
        }

        $toAdd = $this->generateObjectValues(new MasterToeb(), $data);
        $this->getEntityManager()->persist($toAdd);
        $this->getEntityManager()->flush();

        return $toAdd;
    }

    /**
     * Merge two organisations into one.
     *
     * Details:
     *
     * The gatewayID of the sourceOrganisation will replace the gatewayID of the masterToebOrganisation.
     * All user of the sourceOrganisation (users of organisation itself and users of departments of the organisation)
     * will be assigned directly to the masterToebOrganisation and to the department of the masterToeb.
     *
     * All departmets of the sourceOrganisation and the sourceOrganisation itself, will be deleted.
     *
     * @param string $organisationId   ID of the source-Organisation
     * @param string $masterToebId     ID of the masterToeb, whose shadow-Organisation will be merged
     * @param bool   $deleteSourceOrga indicates whether the sourceOrga will be deleted
     *
     * @return array simple overview of the mergeresult (incomplete)
     *
     * @throws EntityNotFoundException
     * @throws Exception
     */
    public function mergeOrganisations($organisationId, $masterToebId, $deleteSourceOrga = true)
    {
        try {
            $entityManager = $this->getEntityManager();
            /** @var MasterToeb $masterToeb */
            $masterToeb = $this->find($masterToebId);
            if (null === $masterToeb) {
                $this->logger->error('Merge organisations failed: MasterToeb with ID: '.$masterToebId.' not found.');
                throw new EntityNotFoundException(sprintf('Merge organisations failed: MasterToeb with ID: %s not found.', $masterToebId));
            }

            /** @var DepartmentRepository $departmentRepository */
            $departmentRepository = $entityManager->getRepository(Department::class);
            /** @var OrgaRepository $orgaRepository */
            $orgaRepository = $entityManager->getRepository(Orga::class);

            $masterToebDepartment = $departmentRepository->find($masterToeb->getDId());
            /** @var Orga $masterToebOrga */
            $masterToebOrga = $orgaRepository->find($masterToeb->getOId());
            /** @var Orga $sourceOrga */
            $sourceOrga = $orgaRepository->find($organisationId);
            $mergeResponse = $this->createMergeResponse($sourceOrga, $masterToeb);

            // setzte neue GWID auf masterToeb Organisation
            $sourceOrgaGwId = $sourceOrga->getGwId();
            $sourceOrga->setGwId(null);
            $sourceOrgaDepartmentGwId = null;
            $entityManager->persist($sourceOrga);
            $entityManager->flush();
            $masterToebOrga->setGwId($sourceOrgaGwId);

            $orgaUsers = $sourceOrga->getUsers();
            $orgaDepartments = $sourceOrga->getDepartments();

            $this->logger->info('About to merge orgas',
                [
                    'sourceOrgaId'   => $sourceOrga->getId(),
                    'sourceOrgaName' => $sourceOrga->getName(),
                    'targetOrgaId'   => $masterToebOrga->getId(),
                    'targetOrgaName' => $masterToebOrga->getName(),
                ]
            );

            // Lade alle Userlisten der Departments der Organisation
            $departmentUsersList = [];
            foreach ($orgaDepartments as $singleDepartment) {
                $departmentUsersList[] = $singleDepartment->getUsers();
                $sourceOrgaDepartmentGwId = $singleDepartment->getGwId();
            }

            // Füge alle User aller Departments aus der SourceOrga der MT-Orga hinzu (also dem Department und der Orga)
            foreach ($departmentUsersList as $singleDepartmentUserList) {
                foreach ($singleDepartmentUserList as $singleDepartmentUser) {
                    $this->logger->info('Add Department user to new Orga and department',
                        [
                            'userId' => $singleDepartmentUser->getId(),
                        ]
                    );
                    $masterToebDepartment->addUser($singleDepartmentUser);
                    $masterToebOrga->addUser($singleDepartmentUser);
                }
            }

            // Lade alle User der Orga, die direkt der Orga zugeornet sind
            // Füge alle User der SourceOrga der MT-Orga hinzu (also dem Department und der Orga)
            foreach ($orgaUsers as $singleOrgaUser) {
                $this->logger->info('Add Orga user to new Orga and department',
                    [
                        'userId' => $singleOrgaUser->getId(),
                    ]
                );
                $masterToebOrga->addUser($singleOrgaUser);
                $masterToebDepartment->addUser($singleOrgaUser);
            }

            $entityManager->persist($masterToebOrga);
            $entityManager->persist($masterToebDepartment);

            foreach ($orgaDepartments as $departmentsOfSourceOrga) {
                // löse die User von den Departments
                $oldDepUsers = $departmentsOfSourceOrga->getUsers();
                foreach ($oldDepUsers as $oldDepUser) {
                    $departmentsOfSourceOrga->removeUser($oldDepUser);
                }
                $entityManager->remove($departmentsOfSourceOrga);
            }

            // detach users from (old) sourceOrga:
            foreach ($orgaUsers as $oldOrgaUser) {
                $sourceOrga->removeUser($oldOrgaUser);
            }

            if ($deleteSourceOrga) {
                $this->logger->info('Delete source orga');
                $orgaRepo = $orgaRepository->getEntityManager();
                $orgaRepo->remove($sourceOrga);
                $orgaRepo->flush();
            }

            // Setze die GwId des zu mergenden Departments ein, damit nachfolgende User dem gemergten Department
            // zugewiesen werden können
            // Erst zuletzt, dail die GwId unique ist und der alte Eintrag erst gelöscht werden muss
            $masterToebDepartment->setGwId($sourceOrgaDepartmentGwId);
            $entityManager->persist($masterToebDepartment);

            $entityManager->flush();

            return $mergeResponse;
        } catch (Exception $e) {
            $this->logger->error('mergeOrganisations() failed: ', [$e]);
            throw $e;
        }
    }

    /**
     * Generates a simple overview of the result of merging to organisations.
     *
     * @param Orga       $sourceOrga
     * @param MasterToeb $masterToeb
     *
     * @return array simple overview of the result
     */
    private function createMergeResponse($sourceOrga, $masterToeb): array
    {
        $shadowOrga = $masterToeb->getOrga();

        $shadowOrganisation = [
            'ident' => $shadowOrga->getId(),
            'name'  => $shadowOrga->getName(),
            'gwId'  => $shadowOrga->getGwId(),
        ];

        $sourceOrganisation = [
            'ident' => $sourceOrga->getId(),
            'name'  => $sourceOrga->getName(),
            'gwId'  => $sourceOrga->getGwId(),
        ];

        $resultOrganisation = [
            'ident' => $shadowOrga->getId(),
            'name'  => $shadowOrga->getName(),
            'gwId'  => $sourceOrga->getGwId(),
        ];

        return [
            'shadowOrganisation' => $shadowOrganisation,
            'sourceOrganisation' => $sourceOrganisation,
            'resultOrganisation' => $resultOrganisation,
            'masterToebId'       => $masterToeb->getIdent(),
        ];
    }

    /**
     * Get all MasterToebs, which organisations are not marked as deleted.
     *
     * @return array
     */
    public function getOrganisationsOfMasterToeb()
    {
        $allMasterToebs = $this->findAll();
        $masterToebsWithNotDeletedOrgas = [];
        /** @var MasterToeb $masterToeb */
        foreach ($allMasterToebs as $masterToeb) {
            // nur in der MasterTöbliste existierende Orgas können nicht gelöscht sein
            if (is_null($masterToeb->getOrganisation())) {
                $masterToebsWithNotDeletedOrgas[] = $masterToeb;
                continue;
            }
            if (!$masterToeb->getOrganisation()->isDeleted()) {
                $masterToebsWithNotDeletedOrgas[] = $masterToeb;
            }
        }

        return $masterToebsWithNotDeletedOrgas;
    }

    /**
     * Get all organisations, which are not in the masterToebList and do not have the name "Bürger".
     *
     * @return array<int, Orga>
     */
    public function getNewOrganisations(string $subdomain): array
    {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();

        $query = $queryBuilder
            ->select('orga')
            ->from(Orga::class, 'orga')
            ->leftJoin('orga.masterToeb', 'masterToeb')
            ->where($queryBuilder->expr()->isNull('masterToeb'))
            ->andWhere('orga.deleted = :deleted')
            ->andWhere('orga.id != :defaultCitizenOrganisationId')
            ->andWhere('orga.name != :disallowedOrgaName')
            ->setParameter('deleted', 0)
            ->setParameter('defaultCitizenOrganisationId', User::ANONYMOUS_USER_ORGA_ID)
            ->setParameter('disallowedOrgaName', 'Manuelle Eingabe')  // Legacy Sonderfall BopHH importierte Organisationen
            ->orderBy('orga.name', 'ASC')
            ->getQuery();

        $orgas = $query->getResult();

        // sortiere Orgas aus, die User mit Fachplanerrollen oder Planungsbüro haben (manuelle Eingabe?)
        $unassignedToebOrgas = [];
        $fpRoles = [Role::PLANNING_AGENCY_ADMIN, Role::PLANNING_AGENCY_WORKER, Role::PRIVATE_PLANNING_AGENCY, Role::CITIZEN, Role::PLATFORM_SUPPORT];
        /** @var Orga $orga */
        foreach ($orgas as $orga) {
            // prüfe, ob es ein Planungsbüro ist
            if (in_array(OrgaType::PLANNING_AGENCY, $orga->getTypes($subdomain), true)) {
                continue;
            }

            // prüfe, ob einer der User eine Fachplanerrolle hat
            $users = $orga->getUsers();
            /** @var User $user */
            foreach ($users as $user) {
                $userRoles = $user->getDplanRolesArray();
                if ([] !== array_intersect($fpRoles, $userRoles)) {
                    continue 2;
                }
            }

            // ignore organisation without any users
            if (0 === count($users)) {
                continue;
            }

            $unassignedToebOrgas[] = $orga;
        }

        return $unassignedToebOrgas;
    }

    /**
     * Check if the given name, is a valid organisationname.
     *
     * @param string $orgaName
     *
     * @return bool true if the given name is valid, otherwise false
     */
    private function isValidOrgaName($orgaName): bool
    {
        return null !== $orgaName && 2 < strlen($orgaName);
    }

    /**
     * Update a specifc entity.
     *
     * @param string $entityId - identifies the entity to update
     * @param array  $data     - contains the data to put into the entity
     *
     * @return MasterToeb - updated entity
     *
     * @throws Exception
     */
    public function update($entityId, array $data)
    {
        try {
            $toUpdate = $this->find($entityId);
            if (is_null($toUpdate)) {
                $this->logger->error(
                    'Update MasterToeb failed:  Entry not found.', ['id' => $entityId]);
                throw new EntityNotFoundException('Update MasterToeb failed: MasterToeb not found.');
            }

            if (array_key_exists('orgaName', $data) && !$this->isValidOrgaName($data['orgaName'])) {
                $this->logger->warning('Update MasterToeb failed: Given organisation name is invalid.');
                throw new InvalidArgumentException('Update MasterToeb failed: Given organisation name is invalid.');
            }

            $this->addVersion($toUpdate);
            $entity = $this->generateObjectValues($toUpdate, $data);
            $this->getEntityManager()->persist($entity);
            $this->getEntityManager()->flush();

            return $entity;
        } catch (Exception $e) {
            $this->logger->warning('Update masterToeb failed Reason: ', [$e]);
            throw $e;
        }
    }

    /**
     * Generates a version of a masterToeb and add it to the DB.
     *
     * @param MasterToeb $masterToeb
     */
    private function addVersion($masterToeb)
    {
        $version = new MasterToebVersion();
        $version->setMasterToeb($masterToeb);
        $version->setGatewayGroup($masterToeb->getGatewayGroup());
        $version->setOrgaName($masterToeb->getOrgaName());
        $version->setDepartmentName($masterToeb->getDepartmentName());
        $version->setSign($masterToeb->getSign());
        $version->setCcEmail($masterToeb->getCcEmail());
        $version->setContactPerson($masterToeb->getContactPerson());
        $version->setMemo($masterToeb->getMemo());
        $version->setComment($masterToeb->getComment());

        $version->setDistrictAltona($masterToeb->getDistrictAltona());
        $version->setDistrictBergedorf($masterToeb->getDistrictBergedorf());
        $version->setDistrictBsu($masterToeb->getDistrictBsu());
        $version->setDistrictEimsbuettel($masterToeb->getDistrictEimsbuettel());
        $version->setDistrictHarburg($masterToeb->getDistrictHarburg());
        $version->setDistrictHHMitte($masterToeb->getDistrictHHMitte());
        $version->setDistrictHHNord($masterToeb->getDistrictHHNord());
        $version->setDistrictWandsbek($masterToeb->getDistrictWandsbek());

        $version->setDocumentAgreement($masterToeb->getDocumentAgreement());
        $version->setDocumentRoughAgreement($masterToeb->getDocumentRoughAgreement());
        $version->setDocumentNotice($masterToeb->getDocumentNotice());
        $version->setDocumentAssessment($masterToeb->getDocumentAssessment());

        $version->setOrga($masterToeb->getOrga());
        $version->setDepartment($masterToeb->getDepartment());
        $version->setRegistered($masterToeb->getRegistered());
        $version->setCreatedDate($masterToeb->getCreatedDate());
        $version->setModifiedDate($masterToeb->getModifiedDate());

        $this->getEntityManager()->persist($version);
        $this->getEntityManager()->flush();
    }

    /**
     * Delete a specific entity.
     *
     * @param string $entityId
     *
     * @return bool|void
     *
     * @throws EntityNotFoundException
     */
    public function delete($entityId)
    {
        $toDelete = $this->find($entityId);
        if (is_null($toDelete)) {
            $this->logger->error('Delete MasterToeb failed:  Entry not found.', ['id' => $entityId]);
            throw new EntityNotFoundException('Delete MasterToeb failed: Entry not found.');
        }

        $this->getEntityManager()->remove($toDelete);
        $this->getEntityManager()->flush();
    }

    /**
     * Set objectvalues by array.
     *
     * @param MasterToeb $entity - entity to fill
     * @param array      $data   - contains data to set
     *
     * @return MasterToeb
     *
     * @throws ORMException
     */
    public function generateObjectValues($entity, array $data)
    {
        if (array_key_exists('gatewayGroup', $data) && !is_null($data['gatewayGroup'])) {
            if ('' === $data['gatewayGroup']) {
                $entity->setGatewayGroup(null);
            } else {
                $entity->setGatewayGroup($data['gatewayGroup']);
            }
        }

        if (array_key_exists('orgaName', $data) && !is_null($data['orgaName'])) {
            if ('' === $data['orgaName']) {
                $entity->setOrgaName(null);
            } else {
                $entity->setOrgaName($data['orgaName']);
            }
        }

        if (array_key_exists('departmentName', $data) && !is_null($data['departmentName'])) {
            if ('' === $data['departmentName']) {
                $entity->setDepartmentName(null);
            } else {
                $entity->setDepartmentName($data['departmentName']);
            }
        }

        if (array_key_exists('sign', $data) && !is_null($data['sign'])) {
            if ('' === $data['sign']) {
                $entity->setSign(null);
            } else {
                $entity->setSign($data['sign']);
            }
        }

        if (array_key_exists('email', $data) && !is_null($data['email'])) {
            if ('' === $data['email']) {
                $entity->setEmail(null);
            } else {
                $entity->setEmail($data['email']);
            }
        }

        if (array_key_exists('memo', $data) && !is_null($data['memo'])) {
            if ('' === $data['memo']) {
                $entity->setMemo(null);
            } else {
                $entity->setMemo($data['memo']);
            }
        }

        if (array_key_exists('ccEmail', $data) && !is_null($data['ccEmail'])) {
            if ('' === $data['ccEmail']) {
                $entity->setCcEmail(null);
            } else {
                $entity->setCcEmail($data['ccEmail']);
            }
        }

        if (array_key_exists('contactPerson', $data) && !is_null($data['contactPerson'])) {
            if ('' === $data['contactPerson']) {
                $entity->setContactPerson(null);
            } else {
                $entity->setContactPerson($data['contactPerson']);
            }
        }

        if (array_key_exists('comment', $data) && !is_null($data['comment'])) {
            if ('' === $data['comment']) {
                $entity->setComment(null);
            } else {
                $entity->setComment($data['comment']);
            }
        }

        if (array_key_exists('districtHHMitte', $data) && !is_null($data['districtHHMitte'])) {
            $entity->setDistrictHHMitte($data['districtHHMitte']);
        }

        if (array_key_exists('districtAltona', $data) && !is_null($data['districtAltona'])) {
            $entity->setDistrictAltona($data['districtAltona']);
        }

        if (array_key_exists('districtEimsbuettel', $data) && !is_null($data['districtEimsbuettel'])) {
            $entity->setDistrictEimsbuettel($data['districtEimsbuettel']);
        }

        if (array_key_exists('districtHHNord', $data) && !is_null($data['districtHHNord'])) {
            $entity->setDistrictHHNord($data['districtHHNord']);
        }

        if (array_key_exists('districtWandsbek', $data) && !is_null($data['districtWandsbek'])) {
            $entity->setDistrictWandsbek($data['districtWandsbek']);
        }

        if (array_key_exists('districtBergedorf', $data) && !is_null($data['districtBergedorf'])) {
            $entity->setDistrictBergedorf($data['districtBergedorf']);
        }

        if (array_key_exists('districtHarburg', $data) && !is_null($data['districtHarburg'])) {
            $entity->setDistrictHarburg($data['districtHarburg']);
        }

        if (array_key_exists('districtBsu', $data) && !is_null($data['districtBsu'])) {
            $entity->setDistrictBsu($data['districtBsu']);
        }

        if (array_key_exists('documentRoughAgreement', $data) && !is_null($data['documentRoughAgreement'])) {
            $entity->setDocumentRoughAgreement($data['documentRoughAgreement']);
        }

        if (array_key_exists('documentAgreement', $data) && !is_null($data['documentAgreement'])) {
            $entity->setDocumentAgreement($data['documentAgreement']);
        }

        if (array_key_exists('documentNotice', $data) && !is_null($data['documentNotice'])) {
            $entity->setDocumentNotice($data['documentNotice']);
        }

        if (array_key_exists('documentAssessment', $data) && !is_null($data['documentAssessment'])) {
            $entity->setDocumentAssessment($data['documentAssessment']);
        }

        if (array_key_exists('oId', $data) && !is_null($data['oId'])) {
            $entity->setOrga($this->getEntityManager()->getReference(Orga::class, $data['oId']));
        }

        if (array_key_exists('dId', $data)) {
            $entity->setDepartment($this->getEntityManager()->getReference(Department::class, $data['dId']));
        }

        if (array_key_exists('registered', $data) && !is_null($data['registered'])) {
            $entity->setRegistered(true);
        } else {
            $entity->setRegistered(false);
        }

        return $entity;
    }
}
