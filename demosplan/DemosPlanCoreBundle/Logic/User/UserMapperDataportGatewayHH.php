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
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\DuplicateGwIdException;
use demosplan\DemosPlanCoreBundle\Logic\HttpCall;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use demosplan\DemosPlanCoreBundle\ValueObject\Credentials;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\String\UnicodeString;
use Symfony\Component\Yaml\Parser;

class UserMapperDataportGatewayHH extends UserMapperDataportGateway
{
    /**
     * @var MasterToebService
     */
    protected $masterToebService;

    public function __construct(
        AddressService $addressService,
        CustomerHandler $customerHandler,
        GlobalConfigInterface $globalConfig,
        HttpCall $httpCall,
        LoggerInterface $logger,
        MasterToebService $masterToebService,
        OrgaService $orgaService,
        private readonly UserRepository $userRepository,
        UserService $userService,
        RequestStack $requestStack
    ) {
        parent::__construct(
            $addressService,
            $customerHandler,
            $globalConfig,
            $httpCall,
            $logger,
            $orgaService,
            $userService,
            $requestStack
        );

        $this->masterToebService = $masterToebService;
    }

    protected function loadRoleMapping()
    {
        $yaml = new Parser();
        // Lädt das Rollenmapping
        $this->roles = collect(
            $yaml->parse(
                file_get_contents(DemosPlanPath::getRootPath('demosplan/DemosPlanCoreBundle/Logic/User').'/UserMapperDataportGatewayHH.yml')
            )
        );
    }

    public function getValidUser(Credentials $credentials): ?User
    {
        $this->logger->info('getValidUser with', [self::class]);
        $request = $this->getRequest();
        $token = trim((string) $credentials->getToken());
        $this->logger->debug('Incoming Token: ', [$token]);

        $this->salt = $this->globalConfig->getSalt();

        // add possibility to test orgaswitcher with local "redirects"
        if ($request->query->has('TokenTest')) {
            $token = trim((string) $request->get('TokenTest', null));
        }

        // gateway variables might be stored in session when verifying Orga/Departmentchange
        $this->data = $request->getSession()->get('unknownChange_gatewayData', null);
        $userChangedOrgaDepartment = $request->request->has('ChangedOrganisation') || $request->request->has('ChangedDepartment');
        $userUpdatedOrgaDepartment = $request->request->has('UpdatedOrganisation') || $request->request->has('UpdatedDepartment');

        // -> Frage das Gateway mit dem Token an, also if gatewayData is saved but user does not come from Orga Verify page
        if (is_null($this->data) || !($userChangedOrgaDepartment || $userUpdatedOrgaDepartment)) {
            $this->logger->info('Call Gateway for Token '.$token);
            $aResult = $this->authenticateByService($token);
            if (false === $aResult) {
                return null;
            }

            // GatewayXML parsen
            $this->data = $this->parseXML($aResult);
            if (false === $this->data) {
                return null;
            }
        }

        // Log Modeid
        $this->logger->info('ModeId: '.$this->data['modeID']);

        // Institution oder Planungsbüro
        // Wenn neue Institution, muss sie gemerged werden, weil eine Orga angelegt wird. Die Nutzer dieser neuen Orga werden dabei einer Orga in der MasterTöbListe (bzw. deren Orga) zugewiesen
        // es dürfen nicht automatisch alle Werte aus dem Gateway übernommen werden, weil die Werte aus der MasterTöb gewinnen
        // Firmennutzer aus dem Gateway
        if ('3' == $this->data['modeID']) {
            // Nutzer prüfen
            $user = $this->createOrUpdateUser();

            // if user changed Orga delete Orga and Department
            // orga changes could be detected automatically as gateway generously gives us an id
            if ($user->getOrga() instanceof Orga && $user->getOrga()->getGwId() != $this->data['user']['COMPANYID']) {
                $this->logger->info('User OrgaId and OSI OrgaGwId differ', [
                    'userOrgaGwId' => $user->getOrga()->getGwId(),
                    'OsiOrgaId'    => $this->data['user']['COMPANYID'],
                ]);
                $user = $this->cleanUserOrgaDepartment($user);
            }

            // if user already has orga, it needs to be updated eventually with current data from gateway
            // only check Orga changes as user is mapped to orga and department from mastertoeblist
            if ($user->getOrga() instanceof Orga) {
                // User ist vorhanden, check, ob die Daten aktualisiert werden müssen
                // Es kann nur ein User aus der DB kommen, weil das Feld gwId unique ist
                $this->logger->info('Der ToebNutzer ist einer Organisation zugewiesen', [$user->getOrga()->getName()]);
                $userOrga = $user->getOrga();
                $this->updateOrga($userOrga, true);
                // Am Department muss bei Institutionen nichts verändert werden, weil alle Werte aus der MasterTöbListe kommen
                // und die gwId nicht genutzt wird, weil die Verknüpfung über die MasterTöbListe kommt
            }

            $orga = $this->createOrgaIfNeededAndAddUser($user);

            // Auf Department prüfen
            if (!$user->getDepartment() instanceof Department) {
                $departmentName = $this->getDepartmentNameFromGwData();
                /** @var Department[] $departments */
                $departments = $this->userService->getDepartmentByFields(
                    ['gwId' => md5($this->data['user']['COMPANYID'].$departmentName)]
                );
                if (empty($departments)) {
                    // Anlegen eines Departments
                    $department = $this->createDepartment($departmentName, $orga->getId());
                    $this->userService->departmentAddUser($department->getId(), $user);
                } else {
                    // Nutzer zur vorhandene Department hinzufügen
                    $this->userService->departmentAddUser($departments[0]->getId(), $user);
                }
            }

            return $user;
        }

        if ('2' == $this->data['modeID']) {
            $this->logger->info('Check User Mode 2');
            $user = null;
            // check possible roles except institution roles
            $normalRoles = [
                Role::PLANNING_AGENCY_ADMIN,
                Role::PLANNING_AGENCY_WORKER,
                Role::PRIVATE_PLANNING_AGENCY,
                Role::PUBLIC_AGENCY_SUPPORT,
                Role::PLATFORM_SUPPORT,
                Role::ORGANISATION_ADMINISTRATION,
                Role::BOARD_MODERATOR,
                Role::CONTENT_EDITOR,
                Role::PROCEDURE_CONTROL_UNIT,
            ];

            if ([] !== array_intersect($normalRoles, $this->getRoles())) {
                $this->logger->info('User ist ein Nicht-Töb');
                // Does user exist?
                $user = $this->getMode2UserByLogin($this->data['user']['LOGINNAME']);
                if (!$user instanceof User) {
                    $this->logger->info('User does not exist create with roles', ['roles' => $normalRoles]);
                    // Anlegen des Nutzers
                    $user = $this->createUserMode2($normalRoles);
                } else {
                    // Update der FachplanerRollen des Users
                    $userFpRoles = array_intersect($this->getRoles(), $normalRoles);
                    $this->logger->info('Update planner user roles', [$userFpRoles]);
                    $this->userService->setUserRoles($user->getId(), $userFpRoles);
                    // Update user, exclude roles
                    $user = $this->updateUser($user, false);
                }

                // user claimed orga changed
                if ($userChangedOrgaDepartment) {
                    $user = $this->cleanUserOrgaDepartment($user);
                }

                // Auf Organisation prüfen, eventuell erstellen oder aktualisieren
                if ($user->getOrga() instanceof Orga) {
                    $this->logger->info('User has orga', [$user->getOrga()->getName()]);
                    $userOrga = $user->getOrga();

                    $departmentNameIs = $user->getDepartment() instanceof Department ? $user->getDepartment()->getName() : '';
                    $departmentNameExpected = $this->data['user']['DEPARTMENT'].' - '.$this->data['user']['SUBDEPARTMENT'];

                    $orgaNameChanged = (new UnicodeString($this->data['user']['AUTHORITY']))->normalize()->toString() != (new UnicodeString($userOrga->getName()))->normalize()->toString();
                    $departmentNameChanged = (new UnicodeString($departmentNameExpected))->normalize()->toString() != (new UnicodeString($departmentNameIs))->normalize()->toString();

                    // organame and Department has NOT changed, check other data to be updated
                    if ((!$orgaNameChanged && !$departmentNameChanged) || $userChangedOrgaDepartment || $userUpdatedOrgaDepartment) {
                        $this->logger->info('Organame and Department has NOT changed or should be updated');
                        try {
                            $userOrga = $this->updateOrgaMode2($userOrga);
                        } catch (DuplicateGwIdException) {
                            // when orga to change to exists and user claimed that orgadata has changed
                            // move user to existing orga.
                            $user = $this->cleanUserOrgaDepartment($user);
                        }
                    } else {
                        $this->logger->info('Could not decide whether user changed Orga. Send to verification page');
                        // organame and/or Department HAS changed. We cannot decide whether user changed orga and/or department
                        // or Orga and/or department has been renamed
                        $this->saveTempUserVerificationData(
                            $request,
                            $userOrga->getName(),
                            $departmentNameIs,
                            $departmentNameExpected
                        );

                        if ($orgaNameChanged) {
                            $this->verificationRoute = 'DemosPlan_user_verify_orga_switch_or_update';

                            return $user;
                        }

                        if ($departmentNameChanged) {
                            $this->verificationRoute = 'DemosPlan_user_verify_department_switch_or_update';

                            return $user;
                        }
                    }
                }

                $orgaGwId = md5($this->data['user']['AUTHORITY'].$this->data['user']['DEPARTMENT']);
                if (!$user->getOrga() instanceof Orga) {
                    $this->logger->info('User does not have any orga');
                    // gibt es eine Orga mit der GatewayId?
                    $existingOrgas = $this->orgaService->getOrgaByFields(['gwId' => $orgaGwId]);
                    // Orga konnte gefunden werden, weise den Nutzer der bestehenden Orga zu
                    if (1 === count($existingOrgas)) {
                        $this->logger->info('Found orga', ['gwId' => $orgaGwId]);
                        /** @var Orga[] $existingOrgas */
                        $userOrga = $existingOrgas[0];
                    } else {
                        // Orga konnte nicht gefunden werden, lege die Orga neu an, weise den Nutzer der neuen Orga zu
                        $this->logger->info('Could not find orga', ['gwId' => $orgaGwId]);
                        $userOrga = $this->createOrgaMode2();
                    }
                    $userOrga = $this->orgaService->orgaAddUser($userOrga->getId(), $user);
                    $this->logger->info('Added user to orga', ['user' => $user->getLogin(), 'orga' => $userOrga->getName(), 'oId' => $userOrga->getId()]);
                }
                // Auf Departement prüfen eventuell erstellen oder aktualisieren
                if ($user->getDepartment() instanceof Department) {
                    $this->logger->info('User has department', ['department' => $user->getDepartment()->getName()]);
                    $userDepartment = $user->getDepartment();

                    $mode2DepartmentGwId = $this->getMode2DepartmentGwId($orgaGwId);
                    // check that department to be renamed to does not already exist.
                    // in that case user has switched Departments in Government gateway and should change department
                    /** @var Department[] $departments */
                    $departments = $this->userService->getDepartmentByFields(['gwId' => $mode2DepartmentGwId]);

                    // add user to existing department
                    if (1 === count($departments) && $departments[0] instanceof Department) {
                        $this->logger->info('Add user to existing department', [$departments[0]->getName()]);

                        $this->userService->updateUser(
                            $user->getId(),
                            ['departmentId' => $departments[0]->getId()]
                        );
                    } else {
                        // Department data has changed in Government gateway, update department
                        $this->logger->info('Update department');
                        $this->updateDepartmentMode2($userDepartment, $orgaGwId);
                    }
                } elseif (isset($userOrga) && $userOrga instanceof Orga) {
                    // Department erstellen und Nutzer/Orga zuordnen.
                    $departmentName = $this->data['user']['DEPARTMENT'].' - '.$this->data['user']['SUBDEPARTMENT'];
                    $departmentGwId = md5($userOrga->getGwId().$departmentName);
                    $existingDepartments = $this->userService->getDepartmentByFields(['gwId' => $departmentGwId]);
                    // Department konnte gefunden werden, weise den Nutzer der bestehenden Orga zu
                    if (1 === count($existingDepartments)) {
                        $this->logger->info('Found department', ['gwId' => $departmentGwId]);
                        /** @var Department $userDepartment */
                        $userDepartment = $existingDepartments[0];
                    } else {
                        // Department konnte nicht gefunden werden, lege die Orga neu an, weise den Nutzer der neuen Orga zu
                        $this->logger->info('Could not find department', ['gwId' => $departmentGwId]);

                        $userDepartment = $this->createDepartmentMode2(
                            $userOrga->getGwId(),
                            $userOrga->getId()
                        );
                    }
                    $userDepartment = $this->userService->departmentAddUser(
                        $userDepartment->getId(),
                        $user
                    );
                    $this->logger->info('Added user to department',
                        ['user' => $user->getLogin(), 'department' => $userDepartment->getName(), 'dId' => $userDepartment->getId()]);
                }

                $user->setIntranet(true);
            }

            // check TöB roles
            $toebRoles = [Role::PUBLIC_AGENCY_WORKER, Role::PUBLIC_AGENCY_COORDINATION];
            if ([] !== array_intersect($toebRoles, $this->getRoles())) {
                $publicAgencyUser = $this->getMode2UserByLogin('T'.$this->data['user']['LOGINNAME']);

                // user claimed orga changed
                if ($publicAgencyUser instanceof User && $userChangedOrgaDepartment) {
                    $publicAgencyUser = $this->cleanUserOrgaDepartment($publicAgencyUser);
                }

                // Wenn es den Nutzer noch nicht gibt oder er keiner Orga oder Department zugewiesen ist
                // Hat der User Orga oder Department gewechselt, kommt er auch hier hin
                if (!$publicAgencyUser instanceof User || (is_null($publicAgencyUser->getOrga()) || is_null($publicAgencyUser->getDepartment()))) {
                    if (!$publicAgencyUser instanceof User) {
                        $getUserContext = [
                            'foundUser' => $publicAgencyUser,
                            'userData' => $this->data['user']
                        ];
                        $this->logger->info('Could not find user with data', $getUserContext);
                        $this->logger->info('User does not exist create with roles', ['roles' => $toebRoles]);
                        $publicAgencyUser = $this->createUserMode2($toebRoles, 'T');
                    }

                    // Anlegen neuer Töb
                    $unknownRoles = $this->extractUnknownRoles();
                    $masterToebEntries = [];
                    foreach ($unknownRoles as $unknownRole) {
                        if (null != ($masterToeb = $this->masterToebService->getMasterToebByGroupName($unknownRole))) {
                            $masterToebEntries = array_merge($masterToebEntries, $masterToeb);
                        }
                    }
                    // Wenn mehr als ein Eintrag existiert muss geprüft werden welcher der richtige ist.
                    /** @var MasterToeb[] $masterToebEntries */
                    if (count($masterToebEntries) > 1) {
                        $countMasterToebOrga = count($masterToebEntries);
                        for ($i = 0; $i < $countMasterToebOrga; ++$i) {
                            $masterToeb = $masterToebEntries[$i];
                            if ($this->data['user']['AUTHORITY'] != $masterToeb->getOrgaName() || $this->data['user']['DEPARTMENT'] != $masterToeb->getDepartmentName()) {
                                unset($masterToebEntries[$i]);
                            }
                        }
                        $masterToebEntries = array_values($masterToebEntries);
                    }
                    if (1 === count($masterToebEntries)) {
                        $this->logger->info('Found MasterToebEntry', [$masterToebEntries[0]->getOrgaName()]);

                        $this->userService->departmentAddUser(
                            $masterToebEntries[0]->getDId(),
                            $publicAgencyUser
                        );
                        $this->orgaService->orgaAddUser($masterToebEntries[0]->getOId(), $publicAgencyUser);
                    }
                    // Orga anlegen, damit sie später gemerged werden kann
                    if ([] === $masterToebEntries) {
                        $this->logger->info('User does not have any orga');
                        // Gibt es schon eine -nicht gemergte- Orga für den Benutzer?
                        // Gehe bis auf das Leitzeichen herunter, weil die Angaben aus dem Gateway ansonsten identisch sind
                        $orgaGwId = md5($this->data['user']['AUTHORITY'].$this->data['user']['DEPARTMENT'].$this->data['user']['SUBDEPARTMENT'].$this->data['user']['AUTHORITYSIGN']);
                        // gibt es eine Orga mit der GatewayId?
                        $existingOrgas = $this->orgaService->getOrgaByFields(['gwId' => $orgaGwId]);
                        // Orga konnte gefunden werden, weise den Nutzer der bestehenden Orga zu
                        if (1 === count($existingOrgas)) {
                            $this->logger->info('Found orga', ['gwId' => $orgaGwId]);
                            /** @var Orga $userOrga */
                            $userOrga = $existingOrgas[0];
                            $userOrga->addUser($publicAgencyUser);
                        } else {
                            // Orga konnte nicht gefunden werden, lege die Orga neu an, weise den Nutzer der neuen Orga zu
                            $this->logger->info('Could not find orga', ['gwId' => $orgaGwId]);
                            $userOrga = $this->createOrgaMode2(true);
                            $userOrga = $this->orgaService->orgaAddUser($userOrga->getId(), $publicAgencyUser);
                        }
                        $this->logger->info('Added user to orga', ['user' => $publicAgencyUser->getLogin(), 'orga' => $userOrga->getName(), 'oId' => $userOrga->getId()]);

                        // Department erstellen und Nutzer/Orga zuordnen.
                        $departmentName = $this->data['user']['DEPARTMENT'].' - '.$this->data['user']['SUBDEPARTMENT'].' - '.$this->data['user']['AUTHORITYSIGN'];
                        $departmentGwId = md5($userOrga->getGwId().$departmentName);
                        $existingDepartments = $this->userService->getDepartmentByFields(['gwId' => $departmentGwId]);
                        // Department konnte gefunden werden, weise den Nutzer der bestehenden Orga zu
                        if (1 === count($existingDepartments)) {
                            $this->logger->info('Found department', ['gwId' => $departmentGwId]);
                            /** @var Department $userDepartment */
                            $userDepartment = $existingDepartments[0];
                            $userDepartment->addUser($publicAgencyUser);
                        } else {
                            // Department konnte nicht gefunden werden, lege die Orga neu an, weise den Nutzer der neuen Orga zu
                            $this->logger->info('Es konnte kein Department zur gwId '.$departmentGwId.' gefunden werden');
                            $userDepartment = $this->createDepartmentMode2(
                                $userOrga->getGwId(),
                                $userOrga->getId(),
                                true
                            );
                            $userDepartment = $this->userService->departmentAddUser(
                                $userDepartment->getId(),
                                $publicAgencyUser
                            );
                            $userOrga->addDepartment($userDepartment);
                        }
                        $this->logger->info('Added user to department',
                            ['user' => $publicAgencyUser->getLogin(), 'department' => $userDepartment->getName(), 'dId' => $userDepartment->getId()]);
                    }
                    // Update user, exclude roles
                    $publicAgencyUser = $this->updateToebUserMode2($publicAgencyUser);
                } else {
                    // User ist vorhanden, check, ob die Daten aktualisiert werden müssen
                    // Es kann nur ein User aus der DB kommen, weil das Feld gwId unique ist
                    if ($publicAgencyUser->getOrga() instanceof Orga) {
                        $this->logger->info('PublicAgency user has orga', [$publicAgencyUser->getOrga()->getName()]);
                        $userOrga = $publicAgencyUser->getOrga();

                        // changes does not need to be checked, as changes from gateway
                        // should not override mastertoeb changes

                        $this->updateOrgaMode2($userOrga, true);
                    } else {
                        $this->logger->info('PublicAgency user does not have any orga', [$publicAgencyUser->getLogin()]);
                    }
                    if ($publicAgencyUser->getDepartment() instanceof Department) {
                        // changes does not need to be checked, as changes from gateway should not override mastertoeb changes
                        $this->logger->info('PublicAgency user has department', [$publicAgencyUser->getDepartment()->getName()]);
                        $userDepartment = $publicAgencyUser->getDepartment();
                        $this->updateDepartmentMode2(
                            $userDepartment,
                            $userDepartment->getOrga()->getGwId(),
                            true
                        );
                    } else {
                        $this->logger->info('PublicAgency user does not have any department', [$publicAgencyUser->getDepartment()->getName()]);
                    }

                    // Update der ToebRollen des Users
                    $userToebRoles = array_intersect($this->getRoles(), $toebRoles);
                    $this->logger->info('Update public agency user roles', [$userToebRoles]);
                    $this->userService->setUserRoles($publicAgencyUser->getId(), $userToebRoles);

                    // Update user, exclude roles
                    $publicAgencyUser = $this->updateToebUserMode2($publicAgencyUser);
                }

                $publicAgencyUser->setIntranet(true);

                // when user is PublicAgency user only, return it, otherwise planner user wins
                if (null === $user) {
                    $user = $publicAgencyUser;
                } else {
                    // save publicAgency user as twin user for planner
                    $user->setTwinUser($publicAgencyUser);
                    $this->userService->updateUserObject($user);
                    $publicAgencyUser->setTwinUser($user);
                    $this->userService->updateUserObject($publicAgencyUser);

                    $request->getSession()->set('session2UserId', $publicAgencyUser->getId());
                }
            }

            return $user;
        }

        if ('1' == $this->data['modeID']) {
            return $this->handleCitizen();
        }

        // default: User konnte nicht authentifiziert werden
        return null;
    }

    /**
     * Create organisation.
     *
     * @param bool $isToeb
     *
     * @return Orga
     *
     * @throws Exception
     */
    protected function createOrgaMode2($isToeb = false)
    {
        // Beim Anlegen der Orga gilt folgendes
        // orga.name = AUTHORITY
        // orga.street = STREET
        // orga.houseNumber = STREETNUMBER
        // orga.city = CITY
        // orga.postalcode = ZIPCODE
        // orga.state = COUNTRY
        // orga.gwId = MD5('AUTHORITY' + 'DEPARTMENT')

        $orgaAddress = [
            'city'        => $this->data['user']['CITY'],
            'postalcode'  => $this->data['user']['ZIPCODE'],
            'street'      => $this->data['user']['STREET'],
            'houseNumber' => $this->data['user']['STREETNUMBER'],
            'state'       => $this->data['user']['COUNTRY'],
        ];
        $address = $this->addressService->addAddress($orgaAddress);

        // Default organisation type: Kommune
        $type = OrgaType::MUNICIPALITY;

        // Gehe bei internen Töb bis auf das Leitzeichen herunter, weil die Angaben aus dem Gateway ansonsten identisch sind
        if ($isToeb) {
            $gwId = md5($this->data['user']['AUTHORITY'].$this->data['user']['DEPARTMENT'].$this->data['user']['SUBDEPARTMENT'].$this->data['user']['AUTHORITYSIGN']);
        } else {
            $gwId = md5($this->data['user']['AUTHORITY'].$this->data['user']['DEPARTMENT']);
        }

        // Anlegen einer Organisation
        $orgaData = [
            'customer'  => $this->customerHandler->getCurrentCustomer(),
            'name'      => $this->data['user']['AUTHORITY'],
            'type'      => $type,
            'gwId'      => $gwId,
            'address'   => $address,
        ];
        $orga = $this->orgaService->addOrga($orgaData);

        $this->logger->info('Organisation hinzugefügt',
            [
                'orgaData'    => $orgaData,
                'addressData' => $orgaAddress,
                'newOrgaId'   => $orga->getId(),
            ]
        );

        return $orga;
    }

    /**
     * @return User
     *
     * @throws Exception
     */
    protected function updateToebUserMode2(User $user)
    {
        $gwUserLogin = $this->data['user']['LOGINNAME'];

        // As mode2 Users need to be splitted into 2 internal Users
        // we have to make sure that Toeb user is updated with correctly prefixed
        // Gateway Login name
        $this->data['user']['LOGINNAME'] = 'T'.$gwUserLogin;
        $user = $this->updateUser($user, false);
        $this->data['user']['LOGINNAME'] = $gwUserLogin;

        return $user;
    }

    /**
     * Update der Organisation mit den aktuellen Werten aus dem GW.
     *
     * @param bool $isToeb
     *
     * @return Orga
     *
     * @throws Exception
     */
    protected function updateOrgaMode2(Orga $orga, $isToeb = false)
    {
        // orga.city = CITY
        // orga.postalcode = ZIPCODE
        // orga.state = COUNTRY
        // orga.gwId = MD5('AUTHORITY' + 'DEPARTMENT')
        $update = [];
        if ($orga->getStreet() != $this->data['user']['STREET']) {
            $update['address_street'] = $this->data['user']['STREET'].' '.$this->data['user']['STREETNUMBER'];
        }
        if ($orga->getHouseNumber() != $this->data['user']['STREETNUMBER']) {
            $update['address_houseNumber'] = $this->data['user']['STREETNUMBER'];
        }
        if ($orga->getCity() != $this->data['user']['CITY']) {
            $update['address_city'] = $this->data['user']['CITY'];
        }
        if ($orga->getPostalcode() != $this->data['user']['ZIPCODE']) {
            $update['address_postalcode'] = $this->data['user']['ZIPCODE'];
        }
        if ($orga->getState() != $this->data['user']['COUNTRY']) {
            $update['address_state'] = $this->data['user']['COUNTRY'];
        }

        // Für Töb wird der Name nicht übernommen, hier gilt die Angabe aus der MasterTöbliste
        // Update bei internen Töb ggf. auch die GwId, damit nachfolgende User einer gemergten Orga dieser zugewiesen werden können
        if ($isToeb) {
            $toebMd5OrgaHash = md5($this->data['user']['AUTHORITY'].$this->data['user']['DEPARTMENT'].$this->data['user']['SUBDEPARTMENT'].$this->data['user']['AUTHORITYSIGN']);
            if ($orga->getGwId() != $toebMd5OrgaHash) {
                $update['gwId'] = $toebMd5OrgaHash;
            }
        } else {
            // Update der GwId mit den Angaben aus dem GW, damit die Orga mit der Orga im GW matcht, auch wenn der Organame und der Departmentname
            // aus der Mastertöbliste gewinnen soll
            // Einträge aus der MasterTöbliste haben keine GwId. Beim Login läuft es über die MasterTöbListe, darin steht die OrgaId
            $fpMd5OrgaHash = md5($this->data['user']['AUTHORITY'].$this->data['user']['DEPARTMENT']);
            if ($orga->getGwId() != $fpMd5OrgaHash) {
                $update['gwId'] = $fpMd5OrgaHash;
            }
            if ($orga->getName() != $this->data['user']['AUTHORITY']) {
                $update['name'] = $this->data['user']['AUTHORITY'];
            }
        }

        if (!empty($update)) {
            $this->logger->info('Update Orga: ', ['id' => $orga->getId(), 'update' => DemosPlanTools::varExport($update, true), 'isToeb' => DemosPlanTools::varExport($isToeb, true)]);
            $orga = $this->userService->updateOrga($orga->getId(), $update);
        } else {
            $this->logger->info('Keine Veränderungen der Orgadaten aus dem Gateway');
        }

        return $orga;
    }

    /**
     * Create department.
     *
     * @return Department
     *
     * @throws Exception
     */
    protected function createDepartmentMode2(?string $orgaGwId, string $orgaId, bool $isToeb = false)
    {
        // Beim anlegen des Department gilt folgendes
        // department.name =  'DEPARTMENT' + ' - ' +  'SUBDEPARTMENT' <- Stand DSL, dann wird falsches Department zugewiesen
        // department.gwId = MD5(orga.gwId + department.name)
        // Gehe bei internen Töb bis auf das Leitzeichen herunter, weil die Angaben aus dem Gateway ansonsten identisch sind
        if ($isToeb) {
            $departmentName = $this->data['user']['DEPARTMENT'].' - '.$this->data['user']['SUBDEPARTMENT'].' - '.$this->data['user']['AUTHORITYSIGN'];
        } else {
            $departmentName = $this->data['user']['DEPARTMENT'].' - '.$this->data['user']['SUBDEPARTMENT'];
        }
        $departmentGwId = md5($orgaGwId.$departmentName);
        $departmentData = [
            'name' => $departmentName,
            'gwId' => $departmentGwId,
        ];
        $department = $this->userService->addDepartment($departmentData, $orgaId);
        $this->logger->info('Department hinzugefügt',
            [
                'departmentData'  => $departmentData,
                'newDepartmentId' => $department->getId(),
            ]
        );

        return $department;
    }

    /**
     * Update des Departments mit den aktuellen Werten aus dem GW.
     *
     * @param string $orgaGwId
     * @param bool   $isToeb
     *
     * @return Department
     *
     * @throws Exception
     */
    protected function updateDepartmentMode2(Department $department, $orgaGwId, $isToeb = false)
    {
        // department.name =  'DEPARTMENT' + ' - ' +  'SUBDEPARTMENT'
        // department.gwId = MD5(orga.gwId + department.name)
        $update = [];

        // Update der GwId mit den Angaben aus dem GW, damit die Orga mit der Orga im GW matcht, auch wenn der Organame und der Departmentname
        // aus der Mastertöbliste gewinnen soll

        $departmentName = $this->getMode2DepartmentName($isToeb);

        // der Departementname aus der Mastertöbliste soll gewinnen, deshalb nur bei Fachplanern den Namen aus dem
        // Gateway übernehmen, wenn er sich verändert hat
        if (!$isToeb && $department->getName() !== $departmentName) {
            $update['name'] = $departmentName;
        }

        if ($department->getGwId() != $this->getMode2DepartmentGwId($orgaGwId, $isToeb)) {
            $update['gwId'] = $this->getMode2DepartmentGwId($orgaGwId, $isToeb);
        }

        if (!empty($update)) {
            $this->logger->info('Update Department: ', ['id' => $department->getId(), 'update' => DemosPlanTools::varExport($update, true)]);
            $department = $this->userService->updateDepartment($department->getId(), $update);
        } else {
            $this->logger->info('Keine Veränderungen der Departmentdaten aus dem Gateway');
        }

        return $department;
    }

    /**
     * Get Department name for mode2 Departments.
     *
     * @param bool $isToeb
     *
     * @return string
     */
    protected function getMode2DepartmentName($isToeb = false)
    {
        // Gehe bei internen Töb bis auf das Leitzeichen herunter, weil die Angaben aus dem Gateway ansonsten identisch sind
        if ($isToeb) {
            $departmentName = $this->data['user']['DEPARTMENT'].' - '.$this->data['user']['SUBDEPARTMENT'].' - '.$this->data['user']['AUTHORITYSIGN'];
        } else {
            $departmentName = $this->data['user']['DEPARTMENT'].' - '.$this->data['user']['SUBDEPARTMENT'];
        }

        return $departmentName;
    }

    /**
     * Get Mode2 Department GatewayId.
     *
     * @param string $orgaGwId
     * @param bool   $isToeb
     *
     * @return string
     */
    protected function getMode2DepartmentGwId($orgaGwId, $isToeb = false)
    {
        return md5($orgaGwId.$this->getMode2DepartmentName($isToeb));
    }

    /**
     * Find user by case insensitive login from Database.
     */
    private function getMode2UserByLogin(string $login): ?User
    {
        return $this->userRepository->getFirstUserByCaseInsensitiveLogin($login);
    }
}
