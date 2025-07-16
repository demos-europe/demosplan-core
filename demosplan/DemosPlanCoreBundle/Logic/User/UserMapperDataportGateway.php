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
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\HttpCall;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use DOMDocument;
use Exception;
use Illuminate\Support\Collection;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use ReflectionException;
use SimpleXMLElement;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\String\UnicodeString;

abstract class UserMapperDataportGateway implements UserMapperInterface
{
    /**
     * @var UserService
     */
    protected $userService;

    /**
     * @var GlobalConfigInterface
     */
    protected $globalConfig;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var string
     */
    protected $salt;

    /**
     * @var Collection
     */
    protected $roles;

    /**
     * @var array
     */
    protected $data;

    /**
     * @var CustomerHandler
     */
    protected $customerHandler;

    /**
     * @var AddressService
     */
    protected $addressService;
    /**
     * @var OrgaService
     */
    protected $orgaService;

    /**
     * Variable set when user needs to be redirected to a distinct verification route
     * Could be used when orga or department changed.
     *
     * @var string|null
     */
    protected $verificationRoute;

    public function __construct(
        AddressService $addressService,
        CustomerHandler $customerHandler,
        GlobalConfigInterface $globalConfig,
        private readonly HttpCall $httpCall,
        LoggerInterface $logger,
        OrgaService $orgaService,
        UserService $userService,
        private RequestStack $requestStack,
    ) {
        $this->customerHandler = $customerHandler;
        $this->addressService = $addressService;
        $this->loadRoleMapping();
        $this->orgaService = $orgaService;
        $this->logger = $logger;
        $this->globalConfig = $globalConfig;
        $this->userService = $userService;
    }

    /**
     * Hook to load Role mappings if any.
     */
    protected function loadRoleMapping()
    {
    }

    /**
     * @return false|string
     */
    protected function authenticateByService(string $token)
    {
        $url = $this->globalConfig->getGatewayAuthenticateURL();
        $authenticateMethod = $this->globalConfig->getGatewayAuthenticateMethod();
        $postData = $this->getAuthPostString($authenticateMethod, $token);
        $this->logger->info('OSI Auth message', [$postData]);
        $this->httpCall->setContentType('text/xml; charset=utf-8');
        $response = $this->httpCall->request('POST', $url, $postData);
        $this->logger->info('Response from OSI', [$response]);
        [$userDataResult, $xmlUserData] = $this->extractDataFromResponse($response['body'] ?? '');

        if (!$this->isValidResult($userDataResult)) {
            return false;
        }
        $this->logger->info('Extracted Userdata', [$xmlUserData]);

        return $xmlUserData;
    }

    protected function isValidResult(string $result): bool
    {
        /*
         * Rückgabewert prüfen (http://red2.berlin.demos-europe.eu/attachments/download/1059/WebService_f%C3%BCr_externe_Fachverfahren.pdf)
         *
         * 1 OK
         * 100 Allgemeiner Fehler
         * 102 WebService Fehler
         * 104 Datenbank Fehler
         * 106 DataError
         * 110 Unbekannter Fehler
         * 120 Request Timeout - (> 1 Minute)
         * 500 Nicht Implementiert
         * 1000 Unbekannter Fehler
         */
        // Fehler abfangen wenn kein GetUserDataResult vorhanden ist
        if ('1' !== $result) {
            $this->logger->error('Authenticate failed, return code', [$result]);
            if ($this->globalConfig->isProxyEnabled()) {
                $this->logger->debug('HTTP proxy is active', [
                    'host' => $this->globalConfig->getProxyHost(),
                    'port' => $this->globalConfig->getProxyPort(),
                ]);
            }

            return false;
        }

        return true;
    }

    /**
     * @param string $aResult
     *
     * @return array
     */
    protected function parseXML($aResult)
    {
        try {
            $dom = new DOMDocument();
            $dom->validateOnParse = true;
            $dom->loadXML($aResult);

            // user data array
            $uData = [];

            // roles data array
            $rData = [];

            $modeID = 0;
            $searchNode = $dom->getElementsByTagName('HHGW');
            foreach ($searchNode as $searchNodeSingle) {
                $this->logger->debug('Parse gateway xml response ');
                $modeID = $searchNodeSingle->getAttribute('MODEID');
                $this->logger->debug('Recognized MODEID: '.$modeID);

                $xmlAttributes = [];
                if ('3' == $modeID) {
                    // Firmenkunde
                    $xmlAttributes = [
                        'USERID',
                        'MODEID',
                        'USERMODE',
                        'LOGINNAME',
                        'TITLE',
                        'PREFIX',
                        'FIRSTNAME',
                        'LASTNAME',
                        'EMAIL',
                        'LANGUAGE',
                        'MASTERUSER',
                        'USERORGANISATION',
                        'PHONENUMBER',
                        'FAX',
                        'USERSTREET',
                        'USERSTREETNUMBER',
                        'USERCITY',
                        'USERZIPCODE',
                        'USERCOUNTRY',
                        'COMPANYID',
                        'COMPANYNAME',
                        'COMPANYORGANISATION',
                        'REGNUMBER',
                        'COMPANYSTREET',
                        'COMPANYSTREETNUMBER',
                        'COMPANYCITY',
                        'COMPANYZIPCODE',
                        'COMPANYCOUNTRY',
                        'MAILBOX',
                        'BOXZIPCODE',
                        'BILLSTREET',
                        'BILLSTREETNUMBER',
                        'BILLCITY',
                        'BILLZIPCODE',
                        'BILLCOUNTRY',
                        'BILLBOX',
                        'BILLBOXZIPCODE',
                        'CERTIFICATEID',
                    ];
                } elseif ('2' == $modeID) {
                    // Intranet
                    $xmlAttributes = [
                        'USERID',
                        'MODEID',
                        'USERMODE',
                        'LOGINNAME',
                        'FIRSTNAME',
                        'LASTNAME',
                        'EMAIL',
                        'LANGUAGE',
                        'AUTHORITYSIGN',
                        'FEDERAL_STATE',
                        'AUTHORITY',
                        'DEPARTMENT',
                        'SUBDEPARTMENT',
                        'STREET',
                        'STREETNUMBER',
                        'CITY',
                        'ZIPCODE',
                        'COUNTRY',
                        'USERPHONENUMBER',
                    ];
                } elseif ('1' == $modeID) {
                    // Bürger
                    $xmlAttributes = [
                        'USERID',
                        'MODEID',
                        'USERMODE',
                        'LOGINNAME',
                        'TITLE',
                        'PREFIX',
                        'FIRSTNAME',
                        'LASTNAME',
                        'EMAIL',
                        'LANGUAGE',
                        'STREET',
                        'STREETNUMBER',
                        'STREETEXTENSION',
                        'CITY',
                        'ZIPCODE',
                        'COUNTRY',
                    ];
                }

                // Parse und trim die wichtigsten Parameter
                foreach ($xmlAttributes as $key) {
                    $uData[$key] = $searchNodeSingle->getAttribute($key);
                    $uData[$key] = $this->decode($uData[$key]);
                    $this->logger->debug('Recognized '.$key.': '.$uData[$key]);
                }
            }

            // Parse die Rollen
            $roleXmlAttributes = ['ROLEID', 'ROLENAME', 'PERMISSION', 'ISDEFAULT'];
            $searchNode = $dom->getElementsByTagName('ROLES');
            $i = 0;
            foreach ($searchNode as $searchNodeSingle) {
                // Parse und trim die wichtigsten Parameter
                $this->logger->debug('Parse role nr. '.$i);
                foreach ($roleXmlAttributes as $key) {
                    $rData[$i][$key] = $searchNodeSingle->getAttribute($key);
                    // Es können kodierte Zeichen enthalten sein
                    $rData[$i][$key] = $this->decode($rData[$i][$key]);
                    $this->logger->debug('Recognized '.$key.': '.$rData[$i][$key]);
                }
                ++$i;
            }
            // Die Rolle des Bürgers kann explizit gesetzt werden & kommt nur als "Defaultrole" Id 246 aus dem GW
            if ('1' == $modeID) {
                // Setze die Rolle Bürger
                unset($rData);
                $rData[]['ROLENAME'] = 'Bürger';
            }

            return ['modeID' => $modeID, 'user' => $uData, 'roles' => $rData];
        } catch (Exception $e) {
            $this->logger->error('Parse gateway XML failed: ', [$e]);

            return false;
        }
    }

    /**
     * Because of unknowing structure, we have to decode in 4 ways.
     *
     * @param string $string - string to decode
     *
     * @return string - decoded string
     */
    public function decode($string)
    {
        // Es können kodierte Zeichen enthalten sein.
        // die XML Doppeldekodierung, die aus dem GW kommt wird von DOMElement::getAttribute() einfach decodiert:
        //  Testplanungsb&amp;amp;#252;ro => Testplanungsb&amp;#252;ro
        $string = (new UnicodeString($string))->normalize()->toString();
        $string = html_entity_decode((string) $string, ENT_QUOTES);

        return trim($string);
    }

    /**
     * Get system roles.
     */
    protected function getRoles(): array
    {
        $roles = [];

        $this->logger->debug('Roles parsed: ', [DemosPlanTools::varExport($this->data['roles'], true)]);

        foreach ($this->data['roles'] as $role) {
            $rolesFound = $this->findRole($role['ROLENAME']);

            if (0 < $rolesFound->count()) {
                $roleFound = $rolesFound->first();
                $roles[] = $roleFound['role'];
                $this->logger->debug('Role found', ['name' => $role['ROLENAME'], 'role' => $roleFound['role']]);
            }
        }

        if (empty($roles)) {
            $this->logger->debug('Roles not found', [DemosPlanTools::varExport($this->data['roles'], true)]);
            $this->logger->debug('Set default role: RINTPA (Interessent)');
            $roles[] = Role::PROSPECT;
        }

        // Wenn User Mode 1 gesetzt ist handelt es sich um einen Bürger
        // dann ist die Bürgerrolle zu setzen
        // compare with string '1' as it is parsed as a string from gatewaystring
        if ('1' === $this->data['user']['MODEID']) {
            $roles = [Role::CITIZEN];
        }

        $this->logger->info('UserRoles found', [DemosPlanTools::varExport($roles, true)]);

        return $roles;
    }

    /**
     * extract unknown roles.
     */
    protected function extractUnknownRoles(): array
    {
        $roles = [];
        foreach ($this->data['roles'] as $role) {
            $rolesFound = $this->findRole($role['ROLENAME']);
            if (0 === $rolesFound->count()) {
                $roles[] = $role['ROLENAME'];
                $this->logger->debug('Unknown Role found', [$role['ROLENAME']]);
            }
        }

        return $roles;
    }

    /**
     * Finds a given role in existing roles.
     *
     * @param string $roleName
     */
    protected function findRole($roleName): Collection
    {
        return $this->roles->filter(fn ($value) =>
            // compare filtered strings to avoid encoding problems
            (new UnicodeString($value['key']))->normalize()->toString() === (new UnicodeString($roleName))->normalize()->toString())->values();
    }

    /**
     * Create user.
     *
     * @param int $mode Gatewaymode
     *
     * @return User
     */
    protected function createUser($mode = 1)
    {
        $userData = [
            'login'         => $this->data['user']['LOGINNAME'],
            'gender'        => ('Frau' === $this->data['user']['PREFIX'] ? 'female' : 'male'),
            'firstname'     => $this->data['user']['FIRSTNAME'],
            'lastname'      => $this->data['user']['LASTNAME'],
            'email'         => $this->data['user']['EMAIL'],
            'gwId'          => $this->data['user']['USERID'],
            'password'      => md5($this->data['user']['LOGINNAME'].$this->data['user']['USERID'].$this->salt),
        ];
        // Bürger
        if (1 == $mode) {
            $userAddress = [
                'city'       => $this->data['user']['CITY'],
                'postalcode' => $this->data['user']['ZIPCODE'],
                'state'      => $this->data['user']['COUNTRY'],
            ];
            $userData['address'] = $this->addressService->addAddress($userAddress);
        }
        $user = $this->userService->addUser($userData);
        $this->logger->info('Lege Nutzer mit folgenden Daten an',
            [
                'userData'  => $userData,
                'newUserId' => $user->getId(),
            ]
        );

        return $this->userService->setUserRoles($user->getId(), $this->getRoles());
    }

    /**
     * Diese Funktion erstellt Nutzer die sich mit Mode 2 anmelden
     * Hamburg spezifische Version.
     *
     * @param array  $roles  //Rollen die beachtet werden sollen
     * @param string $prefix //Prefix wird beim login und externId vorangestellt
     *
     * @return User
     *
     * @throws Exception
     */
    protected function createUserMode2($roles = [], $prefix = '')
    {
        $userData = [
            'login'         => $prefix.$this->data['user']['LOGINNAME'],
            'gender'        => '',
            'firstname'     => $this->data['user']['FIRSTNAME'],
            'lastname'      => $this->data['user']['LASTNAME'],
            'email'         => $this->data['user']['EMAIL'],
            'gwId'          => $prefix.$this->data['user']['USERID'],
            'password'      => md5($this->data['user']['LOGINNAME'].$this->data['user']['USERID'].$this->salt),
        ];
        $user = $this->userService->addUser($userData);
        $this->logger->info('Lege Nutzer mit folgenden Daten an',
            [
                'userData'  => $userData,
                'newUserId' => $user->getId(),
            ]
        );

        $roles = [] !== $roles ? array_intersect($roles, $this->getRoles()) : $this->getRoles();
        $this->logger->info('Gebe dem User die Rollen '.DemosPlanTools::varExport($roles, true));

        return $this->userService->setUserRoles($user->getId(), $roles);
    }

    /**
     * Update user.
     *
     * Check for differences in user attributes & update if a difference is detected.
     *
     * @param bool $updateRoles
     *
     * @return User $user
     *
     * @throws Exception
     */
    protected function updateUser(User $user, $updateRoles = true)
    {
        $update = [];
        if ($user->getLogin() != $this->data['user']['LOGINNAME']) {
            $update['login'] = $this->data['user']['LOGINNAME'];
        }
        if ($user->getFirstname() != $this->data['user']['FIRSTNAME']) {
            $update['firstname'] = $this->data['user']['FIRSTNAME'];
        }
        if ($user->getLastname() != $this->data['user']['LASTNAME']) {
            $update['lastname'] = $this->data['user']['LASTNAME'];
        }
        if ($user->getEmail() != $this->data['user']['EMAIL']) {
            $update['email'] = $this->data['user']['EMAIL'];
        }

        if (array_key_exists('STREET', $this->data['user']) && $user->getStreet() != $this->data['user']['STREET']) {
            $update['address_street'] = $this->data['user']['STREET'];
        }
        if (array_key_exists('STREETNUMBER', $this->data['user']) && $user->getHouseNumber() != $this->data['user']['STREETNUMBER']) {
            $update['address_houseNumber'] = $this->data['user']['STREETNUMBER'];
        }
        if (array_key_exists('USERSTREET', $this->data['user']) && $user->getStreet() != $this->data['user']['USERSTREET']) {
            $update['address_street'] = $this->data['user']['USERSTREET'];
        }

        if (array_key_exists('CITY', $this->data['user']) && $user->getCity() != $this->data['user']['CITY']) {
            $update['address_city'] = $this->data['user']['CITY'];
        }
        if (array_key_exists('ZIPCODE', $this->data['user']) && $user->getPostalcode() != $this->data['user']['ZIPCODE']) {
            $update['address_postalcode'] = $this->data['user']['ZIPCODE'];
        }
        if (array_key_exists('COUNTRY', $this->data['user']) && $user->getState() != $this->data['user']['COUNTRY']) {
            $update['address_state'] = $this->data['user']['COUNTRY'];
        }
        // Mode 3 Users has their own literals... no comment
        if (array_key_exists('USERCITY', $this->data['user']) && $user->getCity() != $this->data['user']['USERCITY']) {
            $update['address_city'] = $this->data['user']['USERCITY'];
        }
        if (array_key_exists('USERZIPCODE', $this->data['user']) && $user->getPostalcode() != $this->data['user']['USERZIPCODE']) {
            $update['address_postalcode'] = $this->data['user']['USERZIPCODE'];
        }
        if (array_key_exists('USERCOUNTRY', $this->data['user']) && $user->getState() != $this->data['user']['USERCOUNTRY']) {
            $update['address_state'] = $this->data['user']['USERCOUNTRY'];
        }

        if (array_key_exists('login', $update)) {
            $update['password'] = md5($this->data['user']['LOGINNAME'].$this->data['user']['USERID'].$this->salt);
        }

        // when we need to update a user s/he actively logs in and therefore
        // should not be deleted, even if s/he have been deleted in dplan before
        if ($user->isDeleted()) {
            $update['deleted'] = false;
        }

        if (!empty($update)) {
            $user = $this->userService->updateUser($user->getId(), $update);
            $this->logger->debug('Update User: ', ['id' => $user->getId(), 'update'.DemosPlanTools::varExport($update, true)]);
        }

        if ($updateRoles) {
            $userRoles = $this->getRoles();
            $this->logger->info('Set User Roles ', [DemosPlanTools::varExport($userRoles, true)]);
            $user = $this->userService->setUserRoles($user->getId(), $userRoles);
        }

        return $user;
    }

    /**
     * Create organisation.
     *
     * @return Orga
     *
     * @throws Exception
     */
    protected function createOrga()
    {
        $orgaAddress = [
            'city'        => $this->data['user']['COMPANYCITY'],
            'postalcode'  => $this->data['user']['COMPANYZIPCODE'],
            'street'      => $this->data['user']['COMPANYSTREET'],
            'houseNumber' => $this->data['user']['COMPANYSTREETNUMBER'],
            'fax'         => $this->data['user']['FAX'],
            'phone'       => $this->data['user']['PHONENUMBER'],
            'state'       => $this->data['user']['COMPANYCOUNTRY'],
        ];
        $address = $this->addressService->addAddress($orgaAddress);

        // Anlegen einer Organisation
        $orgaData = [
            'customer'             => $this->customerHandler->getCurrentCustomer(),
            'name'                 => $this->data['user']['COMPANYNAME'],
            'registrationStatuses' => $this->buildRegistrationStatuses(),
            'gwId'                 => $this->data['user']['COMPANYID'],
            'address'              => $address,
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
     * Update der Organisation mit den aktuellen Werten aus dem GW.
     *
     * @param bool $isInstitution only relevant for Hamburg
     *
     * @return Orga
     *
     * @throws Exception
     */
    protected function updateOrga(Orga $orga, $isInstitution = false)
    {
        $update = [];
        if ($orga->getStreet() != $this->data['user']['COMPANYSTREET']) {
            $update['address_street'] = $this->data['user']['COMPANYSTREET'];
        }
        if ($orga->getStreet() != $this->data['user']['COMPANYSTREETNUMBER']) {
            $update['address_houseNumber'] = $this->data['user']['COMPANYSTREETNUMBER'];
        }
        if ($orga->getCity() != $this->data['user']['COMPANYCITY']) {
            $update['address_city'] = $this->data['user']['COMPANYCITY'];
        }
        if ($orga->getPostalcode() != $this->data['user']['COMPANYZIPCODE']) {
            $update['address_postalcode'] = $this->data['user']['COMPANYZIPCODE'];
        }
        if ($orga->getState() != $this->data['user']['COMPANYCOUNTRY']) {
            $update['address_state'] = $this->data['user']['COMPANYCOUNTRY'];
        }
        if ($orga->getPhone() != $this->data['user']['PHONENUMBER']) {
            $update['address_phone'] = $this->data['user']['PHONENUMBER'];
        }
        if ($orga->getFax() != $this->data['user']['FAX']) {
            $update['address_fax'] = $this->data['user']['FAX'];
        }

        // Für institution wird der Name nicht übernommen, hier gilt die Angabe aus der MasterTöbliste
        if (!$isInstitution) {
            if ($orga->getGwId() != $this->data['user']['COMPANYID']) {
                $update['gwId'] = $this->data['user']['COMPANYID'];
            }
            if ($orga->getName() != $this->data['user']['COMPANYNAME']) {
                $update['name'] = $this->data['user']['COMPANYNAME'];
            }
        }

        if (!empty($update)) {
            $this->logger->info('Update Orga: ', ['id' => $orga->getId(), 'update' => DemosPlanTools::varExport($update, true)]);
            $orga = $this->userService->updateOrga($orga->getId(), $update);
        } else {
            $this->logger->info('Keine Veränderungen der Orgadaten aus dem Gateway');
        }

        return $orga;
    }

    /**
     * Create department.
     *
     * @param string $departmentName
     * @param string $orgaId
     *
     * @return Department
     *
     * @throws Exception
     */
    protected function createDepartment($departmentName, $orgaId)
    {
        $departmentData = [
            'name' => $departmentName,
            'gwId' => md5($this->data['user']['COMPANYID'].$departmentName),
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
     * Name eines Departments:
     * Default "Keine Abteilung"
     * Wenn COMPANYORGANISATION gesetzt, nimm diese
     * Wenn USERORGANISATION gesetzt, gewinnt diese vor COMPANYORGANISATION.
     *
     * @return string
     */
    protected function getDepartmentNameFromGwData()
    {
        $departmentName = Department::DEFAULT_DEPARTMENT_NAME; // Default Department
        if ('' != $this->data['user']['COMPANYORGANISATION']) {
            $departmentName = $this->data['user']['COMPANYORGANISATION'];
        }
        if ('' != $this->data['user']['USERORGANISATION']) {
            $departmentName = $this->data['user']['USERORGANISATION'];
        }

        return $departmentName;
    }

    /**
     * Update des Departments mit den aktuellen Werten aus dem GW.
     *
     * @param string $departmentName
     *
     * @return Department
     *
     * @throws Exception
     */
    protected function updateDepartment(Department $department, $departmentName)
    {
        $update = [];

        if ($department->getGwId() != md5($this->data['user']['COMPANYID'].$departmentName)) {
            $update['gwId'] = md5($this->data['user']['COMPANYID'].$departmentName);
        }

        if ($department->getName() !== $departmentName) {
            $update['name'] = $departmentName;
        }

        if (!empty($update)) {
            $this->logger->info('Update Department: ', ['id' => $department->getId(),  'update' => DemosPlanTools::varExport($update, true)]);
            $department = $this->userService->updateDepartment($department->getId(), $update);
        } else {
            $this->logger->info('Keine Veränderungen der Departmentdaten aus dem Gateway');
        }

        return $department;
    }

    /**
     * @param User $user
     *
     * @return User
     */
    protected function cleanUserOrgaDepartment($user)
    {
        $userUpdated = false;
        if ($user->getOrga() instanceof Orga) {
            $this->logger->info('Remove User '.$user->getLogin().' from Orga '.$user->getOrgaName(), ['oId' => $user->getOrganisationId()]);
            $user->getOrga()->removeUser($user);
            $userUpdated = true;
        }
        if ($user->getDepartment() instanceof Department) {
            $this->logger->info('Remove User '.$user->getLogin().' from Department '.$user->getDepartment()->getName(), ['dId' => $user->getDepartmentId()]);
            $user->getDepartment()->removeUser($user);
            $userUpdated = true;
        }
        if ($userUpdated) {
            $user = $this->userService->updateUserObject($user);
        }

        return $user;
    }

    /**
     * @param string $orgaName
     * @param string $departmentName
     * @param string $departmentNameExpected
     */
    protected function saveTempUserVerificationData(Request $request, $orgaName, $departmentName, $departmentNameExpected)
    {
        $request->getSession()->set('unknownChange_gatewayData', $this->data);

        if ('2' == $this->data['modeID']) {
            $request->getSession()->set(
                'unknownChange_gatewayOrgaName',
                $this->data['user']['AUTHORITY']
            );
        }
        if ('3' == $this->data['modeID']) {
            $request->getSession()->set(
                'unknownChange_gatewayOrgaName',
                $this->data['user']['COMPANYNAME']
            );
        }
        $request->getSession()->set('unknownChange_gatewayDepartmentName', $departmentNameExpected);
        $request->getSession()->set('unknownChange_userOrgaName', $orgaName);
        $request->getSession()->set('unknownChange_userDepartmentName', $departmentName);
    }

    /**
     * @throws ReflectionException
     * @throws MessageBagException
     */
    protected function createOrUpdateUser(): User
    {
        $user = $this->userService->findDistinctUserByEmailOrLogin(
            $this->data['user']['LOGINNAME']
        );
        if (!$user instanceof User) {
            $this->logger->info('Try to add a new user');
            // Anlegen des Nutzers
            $user = $this->createUser($this->data['modeID']);
            $this->logger->info('Created new user');
        } else {
            // Update des Nutzers wenn nötig
            $user = $this->updateUser($user);
        }

        return $user;
    }

    /**
     * @throws Exception
     */
    protected function createOrgaIfNeededAndAddUser(User $user): ?Orga
    {
        $orga = $user->getOrga();
        if (!$orga instanceof Orga) {
            // Auf Organisation prüfen
            /** @var Orga[] $orgas */
            $orgas = $this->orgaService->getOrgaByFields(
                ['gwId' => $this->data['user']['COMPANYID']]
            );
            if (empty($orgas)) {
                // Anlegen einer Organisations Addresse
                $this->logger->info('Try to add a new orga');
                $orga = $this->createOrga();
                $this->logger->info('Added a new orga');
            } else {
                $orga = $orgas[0];
            }
            // Nutzer zur vorhandenen Organisation hinzufügen
            $this->orgaService->orgaAddUser($orga->getId(), $user);
        }

        return $orga;
    }

    /**
     * @throws ReflectionException
     * @throws MessageBagException
     */
    protected function handleCitizen(): User
    {
        // Nutzer prüfen
        $user = $this->userService->findDistinctUserByEmailOrLogin(
            $this->data['user']['LOGINNAME']
        );
        if (!$user instanceof User) {
            // Anlegen des Nutzers
            $this->logger->info('Try to add a new user');
            $user = $this->createUser($this->data['modeID']);
            $this->logger->info('User added', ['UserId' => $user->getId()]);
        } else {
            // Update des Nutzers wenn nötig
            $this->logger->info('Update user Bürger');
            $user = $this->updateUser($user);
        }
        // prüfe, ob Orga und Department gesetzt sind
        if (is_null($user->getOrga())) {
            // Zu Bürgerorga hinzufügen
            /** @var Orga[] $citizenOrga */
            $citizenOrga = $this->orgaService->getOrgaByFields(['id' => User::ANONYMOUS_USER_ORGA_ID]);
            if (!is_null($citizenOrga) && 1 === count($citizenOrga)) {
                $this->logger->info('Found citizenOrga Id', [$citizenOrga[0]->getId()]);
                $this->orgaService->orgaAddUser($citizenOrga[0]->getId(), $user);
            }
        }
        // Auf Department prüfen
        if (is_null($user->getDepartment())) {
            /** @var Department[] $citizenDepartments */
            $citizenDepartments = $this->userService->getDepartmentByFields(['name' => 'anonym']);
            if (!is_null($citizenDepartments) && 1 === count($citizenDepartments)) {
                $this->logger->info(
                    'Found citizenDepartment Id: ',
                    [$citizenDepartments[0]->getId()]
                );
                $this->userService->departmentAddUser($citizenDepartments[0]->getId(), $user);
            }
        }

        return $user;
    }

    /**
     * Generate 'registrationStatus' Array key needed in orga creation to set valid
     * OrgaType in Customer.
     *
     * @throws CustomerNotFoundException
     */
    private function buildRegistrationStatuses(): array
    {
        $orgaTypeAdded = [];
        $registrationStatuses = [];
        $givenRoles = $this->getRoles();
        $customer = $this->customerHandler->getCurrentCustomer();
        foreach ($givenRoles as $role) {
            foreach (OrgaType::ORGATYPE_ROLE as $orgaType => $type) {
                if (in_array($role, $type, true) && !in_array($orgaType, $orgaTypeAdded, true)) {
                    $registrationStatuses[] = [
                        'status'    => OrgaStatusInCustomer::STATUS_ACCEPTED,
                        'subdomain' => $customer->getSubdomain(),
                        'customer'  => $customer,
                        'type'      => $orgaType,
                    ];
                    $orgaTypeAdded[] = $orgaType;
                }
            }
        }

        // set default OrgaType if no role matches to at least register orga in customer.
        // Otherwise even support could not manage orga afterwards
        if ([] === $registrationStatuses) {
            $registrationStatuses[] = [
                'status'    => OrgaStatusInCustomer::STATUS_ACCEPTED,
                'subdomain' => $customer->getSubdomain(),
                'customer'  => $customer,
                'type'      => OrgaType::DEFAULT,
            ];
        }

        return $registrationStatuses;
    }

    /**
     * @return string[]
     */
    protected function extractDataFromResponse(string $xmlResponse): array
    {
        $this->logger->info('Parse XML Response', [$xmlResponse]);
        $responseTag = $this->globalConfig->getGatewayAuthenticateMethod().'Response';
        $resultTag = $this->globalConfig->getGatewayAuthenticateMethod().'Result';
        $userDataResult = '';
        $xmlUserData = '';
        $xml = new SimpleXMLElement($xmlResponse);
        foreach ($xml->xpath('//soap:Body') as $item) {
            $userDataResultArray = $item->$responseTag->$resultTag;
            $userDataResult = isset($userDataResultArray[0]) ? (string) $userDataResultArray[0] : '';
            $xmlUserDataArray = $item->$responseTag->strXMLUserData;
            $xmlUserData = isset($userDataResultArray[0]) ? (string) $xmlUserDataArray[0] : '';
        }

        $this->logger->info('Geparste Variablen aus dem Gateway', [
            'UserDataResult' => $userDataResult,
            'XmlUserData'    => $xmlUserData,
        ]
        );

        return [$userDataResult, $xmlUserData];
    }

    private function getAuthPostString(string $authenticateMethod, string $token): string
    {
        return <<<EOT
<?xml version="1.0" encoding="utf-8"?><SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/" xmlns:ns7001="http://tempuri.org"><SOAP-ENV:Body><$authenticateMethod xmlns="http://tempuri.org/"><strToken>$token</strToken><strXMLUserData></strXMLUserData></$authenticateMethod></SOAP-ENV:Body></SOAP-ENV:Envelope>
EOT;
    }

    /**
     * Set RequestStack for testing. Better ideas anybody?
     */
    public function setRequestStack(RequestStack $requestStack): void
    {
        $this->requestStack = $requestStack;
    }

    protected function getRequest(): Request
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            throw new InvalidArgumentException('Request must be set during OSI Authentication');
        }

        return $request;
    }

    public function getVerificationRoute(): ?string
    {
        return $this->verificationRoute;
    }
}
