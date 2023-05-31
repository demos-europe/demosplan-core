<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\MasterToeb;
use demosplan\DemosPlanCoreBundle\Entity\User\MasterToebVersion;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\DateHelper;
use demosplan\DemosPlanCoreBundle\Logic\EntityHelper;
use demosplan\DemosPlanCoreBundle\Logic\Report\MasterPublicAgencyReportEntryFactory;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use demosplan\DemosPlanCoreBundle\Repository\MasterToebRepository;
use demosplan\DemosPlanCoreBundle\Repository\MasterToebVersionRepository;
use Doctrine\ORM\EntityNotFoundException;
use Exception;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MasterToebService extends CoreService
{
    /**
     * @var UserService
     */
    protected $serviceUser;

    /**
     * @var ReportService
     */
    private $reportService;

    /**
     * @var CurrentUserInterface
     */
    private $currentUser;

    /**
     * @var MasterToebRepository
     */
    private $masterToebRepository;

    /**
     * @var MasterToebVersionRepository
     */
    private $masterToebVersionRepository;

    /**
     * @var MasterPublicAgencyReportEntryFactory
     */
    private $masterPublicAgencyReportEntryFactory;
    /**
     * @var EntityHelper
     */
    private $entityHelper;
    /**
     * @var DateHelper
     */
    private $dateHelper;
    /**
     * @var GlobalConfigInterface
     */
    private $globalConfig;

    public function __construct(
        CurrentUserInterface $currentUser,
        DateHelper $dateHelper,
        EntityHelper $entityHelper,
        GlobalConfigInterface $globalConfig,
        MasterPublicAgencyReportEntryFactory $masterPublicAgencyReportEntryFactory,
        MasterToebRepository $masterToebRepository,
        MasterToebVersionRepository $masterToebVersionRepository,
        ReportService $reportService,
        UserService $serviceUser
    ) {
        $this->currentUser = $currentUser;
        $this->dateHelper = $dateHelper;
        $this->entityHelper = $entityHelper;
        $this->masterPublicAgencyReportEntryFactory = $masterPublicAgencyReportEntryFactory;
        $this->masterToebRepository = $masterToebRepository;
        $this->masterToebVersionRepository = $masterToebVersionRepository;
        $this->reportService = $reportService;
        $this->serviceUser = $serviceUser;
        $this->globalConfig = $globalConfig;
    }

    /**
     * Get all Toebs from Master Table.
     *
     * @param bool $asArray return as array or object
     *
     * @return MasterToeb[]|array
     *
     * @throws Exception
     */
    public function getMasterToebs($asArray = false)
    {
        try {
            return $this->masterToebRepository->getMasterToebList($asArray);
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Abruf der MasterToebs: ', [$e]);
            throw $e;
        }
    }

    /**
     * Get all masterToebs of a specific gatewaygroup.
     *
     * @param string $groupName
     *
     * @return array of results
     *
     * @throws Exception
     */
    public function getMasterToebByGroupName($groupName)
    {
        try {
            return $this->masterToebRepository
                ->getByGatewayName($groupName);
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Abruf der MasterToebs by groupName: ', [$e]);
            throw $e;
        }
    }

    /**
     * Get Master toeb entry by ident.
     *
     * @param string $masterToebId
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function getMasterToeb($masterToebId)
    {
        try {
            return $this->masterToebRepository->get($masterToebId);
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Abruf eines Entries: ', [$e]);
            throw $e;
        }
    }

    /**
     * Get Master toeb entry by OrgaId.
     *
     * @param string $orgaId
     *
     * @return MasterToeb|null
     *
     * @throws Exception
     */
    public function getMasterToebByOrgaId($orgaId)
    {
        try {
            return $this->masterToebRepository
                ->findOneBy(['oId' => $orgaId]);
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Abruf eines Entries: ', [$e]);
            throw $e;
        }
    }

    /**
     * Delete Master toeb entry by ident.
     *
     * @param string $ident
     *
     * @return bool
     *
     * @throws Exception
     */
    public function deleteMasterToeb($ident)
    {
        try {
            $toClone = $this->masterToebRepository->find($ident);
            if (is_null($toClone)) {
                throw new Exception();
            }

            $forReport = clone $toClone;
            $this->masterToebRepository->delete($ident);
        } catch (Exception $e) {
            $this->logger->error('Delete MasterToeb failed.', [$e]);
            throw $e;
        }

        try {
            $this->addReportDeleteMasterToeb($forReport);
        } catch (Exception $e) {
            $this->logger->warning(
                'Add Report in deleteMasterToeb() failed Message: ', [$e]);

            return true;
        }

        return true;
    }

    /**
     * Creates a report entry to document deleting a masterToeb.
     */
    private function addReportDeleteMasterToeb(MasterToeb $masterToeb)
    {
        $entry = $this->masterPublicAgencyReportEntryFactory->createDeletionEntry(
            $masterToeb
        );

        $this->reportService->persistAndFlushReportEntries($entry);
        $this->logger->info('generate report of deleteMasterToeb(). ReportID: ', ['identifier' => $entry->getIdentifier()]);
    }

    /**
     * Detaches all masterToeb entries of an organisation.
     *
     * @param string $organisationId - identifies the organisation, whose masterToeb entries will be detached
     *
     * @return bool - true if masterToeb entries was successfully detached, otherwise false
     */
    public function detachMasterToebOfOrga($organisationId)
    {
        try {
            $masterToeb = $this->getMasterToebByOrgaId($organisationId);

            if (null !== $masterToeb) {
                $this->updateMasterToeb(
                    $masterToeb->getIdent(),
                    ['orga' => null, 'department' => null]
                );
            }
        } catch (Exception $e) {
            $this->logger->error('Fehler beim Lösen der Organisation vom masterToeb Eintrage: ', [$e]);

            return false;
        }

        return true;
    }

    /**
     * Generates a reportEntry for adding a masterToebEntry.
     */
    private function addReportAddMasterToeb(array $data): void
    {
        $entry = $this->masterPublicAgencyReportEntryFactory->createAdditionEntry($data);
        $this->reportService->persistAndFlushReportEntries($entry);
    }

    /**
     * Lege einen Master Toeb Eintrag an.
     *
     * @throws HttpException
     * @throws Exception
     */
    public function addMasterToeb(array $data): ?MasterToeb
    {
        try {
            $addedMasterToeb = $this->masterToebRepository
                ->add($data);
            try {
                $this->addReportAddMasterToeb($data);
            } catch (Exception $e) {
                $this->logger->warning('Add Report in addMasterToeb() failed Message: ', [$e]);
            }

            return $addedMasterToeb;
        } catch (Exception $e) {
            $this->logger->error('Add masterToeb failed.', [$e]);
            throw $e;
        }
    }

    /**
     * Aktualisiere einen Master Toeb Eintrag.
     *
     * @param string $ident
     *
     * @throws Exception
     */
    public function updateMasterToeb($ident, array $data)
    {
        // URL darf dem Service beim Update nicht mitgegeben werden
        if (isset($data['url'])) {
            unset($data['url']);
        }

        // trimme alle Werte, die übergeben werden
        $data = array_map('trim', $data);
        $integerValues = [
            'districtHHMitte', 'districtAltona', 'districtEimsbuettel',
            'districtHHNord', 'districtWandsbek', 'districtBergedorf',
            'districtHarburg', 'districtBsu',
        ];
        $stringToBooleanValues = ['registered'];

        // bei diesen Feldern wird ein String übergeben, der als Zahl gespeichert werden soll, wenn gesetzt
        $stringToIntValues = [
            'documentRoughAgreement',
            'documentAgreement', 'documentNotice', 'documentAssessment',
        ];

        // Werte müssen typengenau übergeben werden
        foreach ($data as $key => $value) {
            if (in_array($key, $integerValues)) {
                $data[$key] = (int) $value;
            } elseif (in_array($key, $stringToBooleanValues)) {
                $data[$key] = false;
                if (0 < strlen($value)) {
                    $data[$key] = true;
                }
            } elseif (in_array($key, $stringToIntValues)) {
                $data[$key] = 0;
                if ('true' === $value) {
                    $data[$key] = 1;
                }
            }
        }

        $entity = $this->masterToebRepository->find($ident);
        if (is_null($entity)) {
            throw new InvalidArgumentException('MasterToebEntity not found');
        }
        $before = clone $entity;
        $response = $this->masterToebRepository
            ->update($ident, $data);

        // Update der Orga
        try {
            $orga = $response->getOrga();
            $orgaUpdate = [];
            if (isset($data['gatewayGroup']) && !is_null($orga) && $data['gatewayGroup'] != $orga->getGatewayName()) {
                $orgaUpdate['gatewayName'] = $data['gatewayGroup'];
            }
            // Field email from mastertoeblist equals field email2 of orga
            if (isset($data['email']) && !is_null($orga) && $data['email'] != $orga->getEmail2()) {
                $orgaUpdate['email2'] = $data['email'];
            }
            if (isset($data['ccEmail']) && !is_null($orga) && $data['ccEmail'] != $orga->getCcEmail2()) {
                $orgaUpdate['ccEmail2'] = $data['ccEmail'];
            }
            if (isset($data['contactPerson']) && !is_null($orga) && $data['contactPerson'] != $orga->getContactPerson()) {
                $orgaUpdate['contactPerson'] = $data['contactPerson'];
            }
            // Update des Namens der Orga auch in der verknüpften OrgaEntity
            if (isset($data['orgaName']) && !is_null($orga) && $data['orgaName'] != $orga->getName()) {
                $orgaUpdate['name'] = $data['orgaName'];
            }
            if (0 < count($orgaUpdate) && !is_null($orga)) {
                $this->serviceUser->updateOrga($orga->getIdent(), $orgaUpdate, false);
            }

            // Update des Departments
            $department = $response->getDepartment();
            if (isset($data['departmentName']) && !is_null($department) && $data['departmentName'] != $department->getName()) {
                $this->serviceUser->updateDepartment($department->getId(), ['name' => $data['departmentName']]);
            }
        } catch (Exception $e) {
            $this->getLogger()->error('Update der Orga nach Update der MasterTöbEntity nicht möglich. ', [$e]);
        }

        try {
            $this->addReportUpdateMasterToeb($ident, $this->entityHelper->toArray($before), $this->entityHelper->toArray($response), $data);
        } catch (Exception $e) {
            $this->logger->warning('Add Report in updateMasterToeb() failed Message: ', [$e]);
        }
    }

    /**
     * Returns all versions of a specific masterToeb.
     *
     * @param string $masterToebIdent
     *
     * @return array|MasterToebVersion[]
     */
    public function getVersions($masterToebIdent)
    {
        return $this->masterToebVersionRepository
            ->findBy(['masterToeb' => $masterToebIdent]);
    }

    /**
     * Generates a reportEntry for merging a organisation an a masterToeb.
     */
    private function addReportMergeMasterToeb(array $resultofMerging): void
    {
        $entry = $this->masterPublicAgencyReportEntryFactory->createMergeEntry(
            $resultofMerging
        );

        $this->reportService->persistAndFlushReportEntries($entry);
    }

    /**
     * @param string $masterToebIdent
     */
    private function addReportUpdateMasterToeb($masterToebIdent, array $before, array $result, array $data)
    {
        unset(
            $before['gatewayGroup'], $before['orga'], $before['department'],
            $before['sign'], $before['email'], $before['ccEmail'],
            $before['contactPerson'], $before['memo'], $before['comment']
        );
        $before['createdDate'] = $this->dateHelper->convertDateToString($before['createdDate']);
        $before['modifiedDate'] = $this->dateHelper->convertDateToString($result['modifiedDate']);

        $entry = $this->masterPublicAgencyReportEntryFactory->createUpdateEntry(
            $masterToebIdent,
            $before,
            $data
        );

        $this->reportService->persistAndFlushReportEntries($entry);
    }

    /**
     * Get Report of Changes in MasterToebList.
     *
     * @return array
     */
    public function getMasterToebsReport()
    {
        $results = $this->masterToebRepository
            ->getReport();

        if ([] === $results) {
            return [];
        }

        $arrayEntries = [];
        foreach ($results as $reportEntry) {
            $entry = $this->entityHelper->toArray($reportEntry);
            $entry['createdDate'] = $entry['createDate']->getTimestamp() * 1000;
            $arrayEntries[] = $this->dateHelper->convertDatesToLegacy($entry);
        }

        return $arrayEntries;
    }

    /**
     * Get all organisations, which are not in the mastertoeblist.
     *
     * @return mixed
     */
    public function getOrganisations()
    {
        $results = $this->masterToebRepository
            ->getNewOrganisations($this->globalConfig->getSubdomain());

        $arrayOfResults = [];
        /** @var Orga $result */
        foreach ($results as $result) {
            $userNames = $result->getUsers()->map(
                static function (User $user): string {
                    return $user->getFullname();
                })->toArray();
            $departmentNames = [];
            /** @var Department $orgaDepartment */
            foreach ($result->getDepartments() as $orgaDepartment) {
                $departmentNames[] = $orgaDepartment->getName();
            }
            $toPush = [];
            $toPush['ident'] = $result->getIdent();
            $toPush['name'] = $result->getName();
            $toPush['email2'] = $result->getEmail2();
            $toPush['departmentNames'] = $departmentNames;
            $toPush['userNames'] = $userNames;
            $arrayOfResults[] = $toPush;
        }

        return $arrayOfResults;
    }

    /**
     * Get a list of MasterToebEntries, which organisations are not marked as deleted.
     *
     * @return array
     */
    public function getOrganisationsOfMasterToeb()
    {
        $results = $this->masterToebRepository
            ->getOrganisationsOfMasterToeb();

        $arrayOfResults = [];
        foreach ($results as $result) {
            /** @var MasterToeb $result */
            $departmentName = $result->getDepartmentName() ?? '-';
            if (null !== $result->getDepartment()) {
                $departmentName = $result->getDepartment()->getName();
            }
            $result->setDepartmentName($departmentName);
            $masterToebArray = $this->entityHelper->toArray($result);
            unset($masterToebArray['orga']);
            unset($masterToebArray['department']);
            unset($masterToebArray['ccEmail']);
            unset($masterToebArray['memo']);
            unset($masterToebArray['comment']);
            unset($masterToebArray['createdDate']);
            unset($masterToebArray['modifiedDate']);

            $arrayOfResults[] = $masterToebArray;
        }
        usort($arrayOfResults, function ($a, $b) {
            return strcmp(strtolower($a['orgaName']), strtolower($b['orgaName']));
        });

        return $arrayOfResults;
    }

    /**
     * Merge an organisation(sourceOrganisation) and a shadoworganisatin of a masterToeb.
     * The sourceOrganisation and the related departments will be deleted.
     * The user of the sourceOrganisation will be assigned to the masterToebOrganisation and to the department of these.
     *
     * @param string $organisationId - identifies the sourceOrganisations
     * @param string $masterToebId   - identifies the masterToeb
     *
     * @return bool
     *
     * @throws EntityNotFoundException
     */
    public function mergeOrganisations($organisationId, $masterToebId)
    {
        $result = $this->masterToebRepository
            ->mergeOrganisations($organisationId, $masterToebId);

        $this->addReportMergeMasterToeb($result);

        return true;
    }

    /**
     * Get Master toeb entry by departmentId.
     *
     * @return MasterToeb|null
     *
     * @throws Exception
     */
    public function getMasterToebByDepartmentId(string $departmentId)
    {
        try {
            /** @var MasterToeb|null $entry */
            $entry = $this->masterToebRepository
                ->findOneBy(['department' => $departmentId]);

            return $entry;
        } catch (Exception $e) {
            $this->logger->warning('Fehler beim Abruf eines Entries: ', [$e]);
            throw $e;
        }
    }
}
