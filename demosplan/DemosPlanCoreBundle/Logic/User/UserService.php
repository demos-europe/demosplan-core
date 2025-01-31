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
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Contracts\Services\UserServiceInterface;
use demosplan\DemosPlanCoreBundle\Entity\Branding;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\CouldNotDeleteAddressesOfDepartmentException;
use demosplan\DemosPlanCoreBundle\Exception\CouldNotDeleteDraftStatementsOfDepartmentException;
use demosplan\DemosPlanCoreBundle\Exception\CouldNotDetachMasterToebOfDepartmentException;
use demosplan\DemosPlanCoreBundle\Exception\CouldNotWipeDepartmentException;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\DuplicateGwIdException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\NullPointerException;
use demosplan\DemosPlanCoreBundle\Exception\UserAlreadyExistsException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\ContentService;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\EntityHelper;
use demosplan\DemosPlanCoreBundle\Logic\Logger\ProdLogger;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\DraftStatementService;
use demosplan\DemosPlanCoreBundle\Repository\BrandingRepository;
use demosplan\DemosPlanCoreBundle\Repository\DepartmentRepository;
use demosplan\DemosPlanCoreBundle\Repository\OrgaRepository;
use demosplan\DemosPlanCoreBundle\Repository\StatementVoteRepository;
use demosplan\DemosPlanCoreBundle\Repository\UserRepository;
use demosplan\DemosPlanCoreBundle\Repository\UserRoleInCustomerRepository;
use demosplan\DemosPlanCoreBundle\Types\UserFlagKey;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use demosplan\DemosPlanCoreBundle\ValueObject\TestUserValueObject;
use demosplan\DemosPlanCoreBundle\ValueObject\User\CustomerResourceInterface;
use demosplan\DemosPlanCoreBundle\ValueObject\User\OrgaUsersPair;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use DOMDocument;
use Exception;
use LSS\XML2Array;
use Pagerfanta\Pagerfanta;
use RuntimeException;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Contracts\Translation\TranslatorInterface;
use Illuminate\Support\Collection as IlluminateCollection;

use function array_key_exists;

class UserService extends CoreService implements UserServiceInterface
{
    /**
     * The hash function that is being used to generate password hashes.
     */
    private const PW_HASH = 'sha512';

    /**
     * @var ContentService
     */
    protected $contentService;

    /**
     * @var PermissionsInterface
     */
    protected $permissions;

    /**
     * @var MasterToebService
     */
    protected $serviceMasterToeb;

    /**
     * @var DraftStatementService
     */
    protected $draftStatementService;

    /**
     * @var AddressService
     */
    protected $addressService;

    /**
     * @var OrgaService
     */
    protected $orgaService;

    public function __construct(
        AddressService $addressService,
        private readonly BrandingRepository $brandingRepository,
        ContentService $serviceContent,
        private readonly CustomerService $customerService,
        private readonly DepartmentRepository $departmentRepository,
        DraftStatementService $draftStatementService,
        private readonly EntityHelper $entityHelper,
        private readonly GlobalConfigInterface $globalConfig,
        MasterToebService $serviceMasterToeb,
        private readonly MessageBagInterface $messageBag,
        private readonly OrgaRepository $orgaRepository,
        OrgaService $orgaService,
        private readonly PasswordHasherFactoryInterface $passwordHasherFactory,
        PermissionsInterface $permissions,
        private readonly ProdLogger $prodLogger,
        private readonly ReportService $reportService,
        private readonly StatementVoteRepository $statementVoteRepository,
        private readonly TranslatorInterface $translator,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly UserRepository $userRepository,
        private readonly UserRoleInCustomerRepository $userRoleInCustomerRepository
    ) {
        $this->addressService = $addressService;
        $this->contentService = $serviceContent;
        $this->draftStatementService = $draftStatementService;
        $this->orgaService = $orgaService;
        $this->permissions = $permissions;
        $this->serviceMasterToeb = $serviceMasterToeb;
    }

    /**
     * Try to find a user that is valid for current customer.
     */
    public function getValidUser(string $login): ?User
    {
        try {
            $user = $this->findDistinctUserByEmailOrLogin($login);

            if (false === $user) {
                $this->logger->warning('Could not find one distinct user by login or email. Maybe given email is not unique',
                    [DemosPlanTools::varExport($login, true)]);

                return null;
            }

            if (null === $user->getPassword() || '' === $user->getPassword()) {
                $this->logger->warning('User has empty password field. Refill with alternative pass', [$user->getLogin()]);
                $user->setPassword($user->getAlternativeLoginPassword());
                $this->updateUserObject($user);
                $this->logger->info('User password updated');
            }

            // check whether user organisation is registered within current customer
            $this->prodLogger->info('Verified user password');
            $currentCustomer = $this->customerService->getCurrentCustomer();
            $subdomain = $currentCustomer->getSubdomain();
            $orga = $user->getOrga();
            if ($user->hasRole(Role::CUSTOMER_MASTER_USER)) {
                return $user;
            }
            if (!$orga instanceof Orga) {
                // the user object is intentionally not logged as a whole to avoid sensitive data in the log files
                $this->logger->log('error', 'Currently only users in customer master role group are allowed to have no orga', ['userId' => $user->getId(), 'subdomain' => $subdomain]);
                throw new NullPointerException('The orga of a user that is not in the role group of customer master users is null. This is probably an invalid database state.');
            }

            // user needs to have a role in current customer
            if (0 === count($user->getRoles())) {
                $this->getLogger()->info('User is not registered in current customer',
                    [
                        'user'              => $user->getId(),
                        'currentCustomer'   => $currentCustomer->getId(),
                    ]
                );

                return null;
            }

            // citizen orga is always registered in customer
            if (User::ANONYMOUS_USER_ORGA_ID === $orga->getId()) {
                return $user;
            }

            $this->prodLogger->info('Check whether user orga is registered in customer');
            if ($orga->isRegisteredInSubdomain($subdomain)) {
                $this->prodLogger->info('Check whether any user orgaType is accepted');

                // user should only be able to log in if any OrgaType is accepted in customer
                $orgaTypes = $this->orgaService->getAcceptedOrgaTypes($orga, $currentCustomer);
                if ([] !== $orgaTypes) {
                    return $user;
                }

                $this->getLogger()->warning('User Orga has no accepted orgaType in current customer');
            }

            $this->getLogger()->warning('User Orga is not registered in current customer',
                [
                    'user'              => $user->getId(),
                    'userOrga'          => $orga->getId(),
                    'currentCustomer'   => $currentCustomer->getId(),
                ]
            );

            return null;
        } catch (Exception $e) {
            $this->logger->error('Fehler bei der Abfrage der Usercredentials: ', [$e]);
        }

        return null;
    }

    public function findDistinctUserByEmailOrLogin($loginOrEmail)
    {
        $user = $this->userRepository->findOneBy(['login' => $loginOrEmail, 'deleted' => false]);

        if ($user instanceof User) {
            return $user;
        }
        // User not found, try to find user by email address:
        $this->getLogger()->info('Could not find user by given login',
            [$loginOrEmail]);

        // important to return null in case of more than one user was found!
        $foundUsers = $this->userRepository->findBy(['email' => $loginOrEmail, 'deleted' => false]);

        if (1 === count($foundUsers) && $foundUsers[0] instanceof User) {
            $this->logger->info('User found by email');

            return $foundUsers[0];
        }

        if ([] === $foundUsers) {
            $this->getLogger()->warning('Could not find user by login or email.',
                ['loginOrEmail' => $loginOrEmail]);

            return false;
        }

        // In case more than one user was found, do not login
        if (1 < count($foundUsers)) {
            $this->getLogger()->warning('Found more than one user matched to given mail.',
                ['loginOrEmail' => $loginOrEmail]);
            $this->messageBag->add('warning', 'warning.use.login');

            return false;
        }

        return false;
    }

    /**
     * Add User.
     *
     * Research notes:
     *
     * This method is called either from different places and is expected to behave
     * a little bit differently in each case:
     *
     * From the manager and support roles, when creating new users,
     * this will most likely get all fields **including roles**
     *
     * The other way users are created in demosplan is through the Gateway authenticators.
     * Those create users without roles and **add them afterwards**.
     *
     * @param array $data
     *
     * @throws Exception
     */
    public function addUser($data): User
    {
        try {
            if (!array_key_exists(UserFlagKey::IS_NEW_USER->value, $data)) {
                $data[UserFlagKey::IS_NEW_USER->value] = true;
            }

            if (!array_key_exists(UserFlagKey::PROFILE_COMPLETED->value, $data)) {
                $data[UserFlagKey::PROFILE_COMPLETED->value] = false;
            }

            if (!array_key_exists(UserFlagKey::ACCESS_CONFIRMED->value, $data)) {
                $data[UserFlagKey::ACCESS_CONFIRMED->value] = false;
            }

            if (!array_key_exists(UserFlagKey::INVITED->value, $data)) {
                $data[UserFlagKey::INVITED->value] = false;
            }

            if (!array_key_exists(UserFlagKey::NO_USER_TRACKING->value, $data)) {
                $data[UserFlagKey::NO_USER_TRACKING->value] = false;
            }

            if (!array_key_exists(UserFlagKey::SUBSCRIBED_TO_NEWSLETTER->value, $data)) {
                $data[UserFlagKey::SUBSCRIBED_TO_NEWSLETTER->value] = false;
            }

            if (!array_key_exists(UserFlagKey::DRAFT_STATEMENT_SUBMISSION_REMINDER_ENABLED->value, $data)) {
                $data[UserFlagKey::DRAFT_STATEMENT_SUBMISSION_REMINDER_ENABLED->value] = true;
            }

            if (!array_key_exists(UserFlagKey::WANTS_FORUM_NOTIFICATIONS->value, $data)) {
                $data[UserFlagKey::WANTS_FORUM_NOTIFICATIONS->value] = false;
            }

            $data['customer'] = $this->customerService->getCurrentCustomer();

            return $this->userRepository->add($data);
        } catch (UserAlreadyExistsException $userException) {
            $this->logger->error('Der gewählte Nutzername existiert bereits.');
            throw $userException;
        } catch (Exception $e) {
            $this->logger->error('Fehler bem Anlegen des Users: ', [$e]);
            throw $e;
        }
    }

    /**
     * Generate a new random password.
     *
     * WARNING: This is not a hashed password but the plain text variant
     *
     * This method should be used in places where we need to inform the user
     * about their new password, e.g. when recovering from a password loss.
     *
     * @throws Exception
     */
    public function generateNewRandomPassword(): string
    {
        return substr(hash(self::PW_HASH, random_bytes(500)), 0, 10);
    }

    /**
     * Add User.
     *
     * @param string $userId
     * @param array  $roles
     *
     * @return User
     *
     * @throws Exception
     *
     * @internal param array $data
     */
    public function setUserRoles($userId, $roles = [])
    {
        try {
            return $this->userRepository
                ->update($userId, ['roles' => $roles]);
        } catch (Exception $e) {
            $this->logger->error('Fehler bem Anlegen der Rollen des Users: ', [$e]);
            throw $e;
        }
    }

    /**
     * Overrides the data of the given user.
     *
     * @param string $entityId - Id of the user to wipe data
     *
     * @return bool|User - updated user
     */
    public function wipeUser($entityId)
    {
        try {
            $user = $this->userRepository
                ->wipe($entityId);

            // delete all existing user roles
            $this->userRoleInCustomerRepository->clearUserRoles(
                $entityId,
                $this->customerService->getCurrentCustomer()
            );

            return $user;
        } catch (Exception $e) {
            $this->logger->error('Fehler beim Löschen des Users: ', [$e]);

            return false;
        }
    }

    /**
     * Delete the addresses of the given user.
     *
     * @param string $entityId - Identifies the user, whose addresses will be deleted
     *
     * @return bool true if successful deleted, otherwise false
     */
    public function deleteAddressesOfUser($entityId)
    {
        try {
            /** @var User $user */
            $user = $this->userRepository->find($entityId);

            foreach ($user->getAddresses() as $address) {
                // remove addresses form user, to avoid undefined index
                // because doctrine will not do this, because there are no address sited relation, to use annotations
                $user->setAddresses([]);
                $this->addressService->deleteAddress($address->getId());
            }
        } catch (Exception $e) {
            $this->logger->error('Fehler beim Löschen der Adressen : ', [$e]);

            return false;
        }

        return true;
    }

    /**
     * Get single userobject.
     *
     * @param string $userId
     *
     * @return User|null
     *
     * @throws Exception
     */
    public function getSingleUser($userId)
    {
        try {
            return $this->userRepository->get($userId);
        } catch (NoResultException) {
            return null;
        } catch (Exception $e) {
            $this->logger->error('Fehler bei der Abfrage des Users: ', [$e]);
            throw $e;
        }
    }

    /**
     * @throws NoResultException
     */
    public function findWithCertainty(string $id): User
    {
        return $this->userRepository->get($id);
    }

    /**
     * Get single userobject by filter.
     *
     * @param array $filter
     *
     * @return array User[]
     *
     * @throws Exception
     */
    public function getUserByFields($filter)
    {
        try {
            return $this->userRepository->findBy($filter);
        } catch (Exception $e) {
            $this->logger->error('Fehler bei der Abfrage des Users: ', [$e]);
            throw $e;
        }
    }

    /**
     * Update Userdata.
     *
     * @param string $userId
     * @param array  $data
     *
     * @return User
     *
     * @throws Exception
     */
    public function updateUser($userId, $data)
    {
        try {
            // Do not treat Userflag standard data here, as they this would override
            // real Userflags e.g. in case of updates from the gateway
            return $this->userRepository->update($userId, $data);
        } catch (Exception $e) {
            $this->logger->error('Fehler bei Update des Users: ', [$e]);
            throw $e;
        }
    }

    /**
     * Update Orgadata.
     *
     * @param string $orgaId
     * @param array  $data
     * @param bool   $updateMasterToeb
     *
     * @return Orga
     *
     * @throws Exception
     */
    public function updateOrga($orgaId, $data, $updateMasterToeb = false)
    {
        try {
            /** @var Orga $orgaBefore */
            $orgaBefore = $this->orgaRepository->find($orgaId);
            $showListBefore = $orgaBefore->getShowlist();
            $emailBefore = $orgaBefore->getEmail2();
            $emailCCBefore = $orgaBefore->getCcEmail2();

            // Throw exception if update would lead to duplicate gwId
            if (array_key_exists('gwId', $data) && $data['gwId'] != $orgaBefore->getGwId()) {
                $existingGwIdOrga = $this->orgaRepository->findBy(['gwId' => $data['gwId']]);
                if (!is_null($existingGwIdOrga) && [] !== $existingGwIdOrga) {
                    throw new DuplicateGwIdException();
                }
            }

            if (array_key_exists('slug', $data)
                && !$this->permissions->hasPermission('feature_orga_slug')) {
                throw new InvalidArgumentException('orga slug must not be provided when permission is not activated');
            }

            // handle Branding
            if (array_key_exists(CustomerResourceInterface::STYLING, $data)) {
                $data['branding'] = $this->handleBrandingByUpdate($orgaBefore, $data);
            }
            // delete logo file
            if (array_key_exists('logo', $data) && null === $data['logo']) {
                $this->orgaService->deleteLogoByOrgaId($orgaId);
            }

            $orga = $this->orgaRepository->update($orgaId, $data);
            // update ggf. Notifications
            $orga = $this->orgaService->updateOrgaNotifications($orga, $data);
            $orga = $this->orgaService->updateOrgaSubmissionType($orga, $data);

            // Update des Eintrags in der MasterTöbListe
            if ($updateMasterToeb && $this->permissions->hasPermission('feature_mastertoeblist')) {
                try {
                    $masterToebEntry = $this->serviceMasterToeb->getMasterToebByOrgaId($orgaId);
                    $masterToebUpdate = [];
                    if (isset($data['email2']) && $data['email2'] != $emailBefore) {
                        $masterToebUpdate['email'] = $data['email2'];
                    }
                    if (isset($data['ccEmail2']) && $data['ccEmail2'] != $emailCCBefore) {
                        $masterToebUpdate['ccEmail'] = $data['ccEmail2'];
                    }
                    if (0 < count($masterToebUpdate) && !is_null($masterToebEntry)) {
                        $this->serviceMasterToeb->updateMasterToeb($masterToebEntry->getIdent(), $masterToebUpdate);
                    }
                } catch (Exception $e) {
                    $this->getLogger()->error('Update MasterToeb after OrgaUpdate failed ', [$e]);
                }
            }
            try {
                $data['name'] = $orga->getName();
                $this->orgaService->addReport($orga->getId(), $data, $showListBefore);
            } catch (ViolationsException $e) {
                $this->logger->warning('Add Report in updateOrga() failed due to Violation: ', [$e, $e->getViolationsAsStrings()]);
            } catch (Exception $e) {
                $this->logger->warning('Add Report in updateOrga() failed Message: ', [$e]);
            }

            return $orga;
        } catch (DuplicateGwIdException $e) {
            $this->logger->warning('Update would lead to duplicate gwId', ['orgaToUpdate' => $orgaId, 'gwIdToUpdateTo' => $data['gwId']]);
            throw $e;
        } catch (ViolationsException $e) {
            $violationFields = [];
            /** @var ConstraintViolation $violation */
            foreach ($e->getViolations() as $violation) {
                $violationFields[] = $violation->getPropertyPath();
            }
            $this->messageBag->add('error', 'error.organisation.invalid', ['fields' => implode(',', $violationFields)]);
            throw $e;
        } catch (Exception $e) {
            $this->logger->error('Fehler bem Update der Orga: ', [$e]);
            throw $e;
        }
    }

    public function handleBrandingByUpdate(Orga $orga, array $data): Branding
    {
        $orgaBranding = $orga->getBranding();
        if (null === $orgaBranding) {
            return $this->brandingRepository->createFromData($data);
        }

        return $orgaBranding->setCssvars($data[CustomerResourceInterface::STYLING]);
    }

    public function handleBrandingByCreate(array $data): array
    {
        $data['branding'] = $this->brandingRepository->createFromData($data);

        return $data;
    }

    /**
     * Persist changes in User Object.
     *
     * @param User $user
     *
     * @return User
     *
     * @throws Exception
     */
    public function updateUserObject($user)
    {
        return $this->userRepository
            ->updateObject($user);
    }

    /**
     * Persist changes in User Objects.
     *
     * @param array<int, User> $users
     *
     * @return User[]
     *
     * @throws Exception
     */
    public function updateUserObjects(array $users): array
    {
        return $this->userRepository->updateObjects($users);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateDepartmentObject(Department $department)
    {
        return $this->departmentRepository->updateObject($department);
    }

    /**
     * Add departmentobject.
     *
     * @param string $orgaId
     *
     * @return Orga
     *
     * @throws Exception
     */
    public function orgaAddDepartment($orgaId, Department $department)
    {
        try {
            $em = $this->getDoctrine()->getManager();

            $orga = $this->orgaService->getOrga($orgaId);
            $orga->addDepartment($department);
            $em->persist($orga);
            $em->flush();

            return $orga;
        } catch (Exception $e) {
            $this->logger->error('Fehler beim Zuweisen der Abteilung: ', [$e]);
            throw $e;
        }
    }

    /**
     * Get single departmentobject.
     *
     * @param string $departmentId
     *
     * @return Department|null
     *
     * @throws Exception
     */
    public function getDepartment($departmentId)
    {
        try {
            return $this->departmentRepository->get($departmentId);
        } catch (Exception $e) {
            $this->logger->error('Fehler bei der Abfrage der Abteilung: ', [$e]);
            throw $e;
        }
    }

    public function getSortedLegacyDepartmentsWithoutDefaultDepartment(Orga $orga): array
    {
        $sortedDepartments = $this->sortByName($orga->getDepartments());
        $filteredDepartments = array_filter($sortedDepartments, fn (Department $department): bool => $this->isNotDefaultDepartment($department));

        return array_map(fn ($object): ?array => $this->entityHelper->toArray($object), $filteredDepartments);
    }

    /**
     * Get single departmentobject by filter.
     *
     * @param array $filter
     *
     * @return array Department[]
     *
     * @throws Exception
     */
    public function getDepartmentByFields($filter)
    {
        try {
            return $this->departmentRepository->findBy($filter);
        } catch (Exception $e) {
            $this->logger->error('Fehler bei der Abfrage der Department: ', [$e]);
            throw $e;
        }
    }

    /**
     * Add departmentobject.
     *
     * @param array  $data
     * @param string $orgaId
     *
     * @return Department
     *
     * @throws Exception
     */
    public function addDepartment($data, $orgaId)
    {
        try {
            $department = $this->departmentRepository->add($data);
            $this->orgaAddDepartment($orgaId, $department);

            return $department;
        } catch (Exception $e) {
            $this->logger->error('Fehler beim Anlegen der Abteilung: ', [$e]);
            throw $e;
        }
    }

    /**
     * Update Department.
     *
     * @param string $entityId
     * @param array  $data
     *
     * @return Department
     *
     * @throws Exception
     */
    public function updateDepartment($entityId, $data)
    {
        try {
            return $this->departmentRepository->update($entityId, $data);
        } catch (Exception $e) {
            $this->logger->error('Fehler bem Update des Departments: ', [$e]);
            throw $e;
        }
    }

    /**
     * Abteilung löschen.
     *
     * @param string $entityId
     *
     * @return bool
     *
     * @throws Exception
     */
    public function deleteDepartment($entityId)
    {
        try {
            return $this->departmentRepository->delete($entityId);
        } catch (Exception $e) {
            $this->logger->error('Fehler beim Löschen des Departments: ', [$e]);

            return false;
        }
    }

    /**
     * Add a User to Department.
     *
     * @param string $departmentId
     *
     * @return Department
     *
     * @throws Exception
     */
    public function departmentAddUser($departmentId, User $user)
    {
        try {
            return $this->departmentRepository->addUser($departmentId, $user);
        } catch (Exception $e) {
            $this->logger->error('Fehler bem Update der Abteilung:', [$e]);
            throw $e;
        }
    }

    /**
     * Ruft die Benutzer zu einer Organisation ab.
     *
     * @param string $organisationId
     *
     * @return array|User[]
     */
    public function getUsersOfOrganisation($organisationId): array
    {
        try {
            $orga = $this->orgaService->getOrga($organisationId);
            if (null === $orga) {
                $this->logger->warning('No orga found for orgaId: '.DemosPlanTools::varExport($organisationId, true));

                return [];
            }
            $users = $orga->getUsers();
            $returnArray = [];
            /** @var User $user */
            foreach ($users as $user) {
                if ($user->isDeleted()) {
                    continue;
                }
                $returnArray[] = $user;
            }

            return $returnArray;
        } catch (Exception $e) {
            $this->logger->error('Fehler bei der Abfrage der User der Orga: ', [$e]);

            return [];
        }
    }

    /**
     * @throws NoResultException
     */
    public function getFirstUserInFhhnetByLogin(string $loginSuffix): UserInterface
    {
        $user = $this->userRepository->getFirstUserInFhhnetByLogin($loginSuffix);
        $user->setCurrentCustomer($this->customerService->getCurrentCustomer());

        return $user;
    }

    /**
     * @improve: incoming userId should only be type of string
     * Alle Organisationen zu einer UserId herausfinden.
     *
     * @return Orga|null
     *
     * @throws Exception
     */
    public function getUserOrga(?string $userId)
    {
        if ('' === $userId || null === $userId) {
            return null;
        }
        $user = $this->getSingleUser($userId);

        return $user instanceof User ? $user->getOrga() : null;
    }

    /**
     * Get all users of specific roles.
     *
     * @return User[]
     *
     * @throws Exception
     */
    public function getUsersOfRole(string $role)
    {
        try {
            return $this->userRepository
                ->getUsersByRole($role, $this->customerService->getCurrentCustomer());
        } catch (Exception $e) {
            $this->logger->error('Fehler bei der Abfrage des Users: ', [$e]);
            throw $e;
        }
    }

    /**
     * @return array<int, User>
     */
    public function getUndeletedUsers(): array
    {
        return $this->userRepository->findBy(['deleted' => false]);
    }

    /**
     * @param array<int, User> $users
     *
     * @return array<string, int> mapping from imploded role combination to occurrence count
     */
    public function collectRoleStatistics(array $users): array
    {
        $roles = [];
        foreach ($users as $user) {
            $userRoleNames = $this->getRoleNames($user);
            $userRoleString = implode(' / ', $userRoleNames);
            if ('' === $userRoleString) {
                $userRoleString = $this->translator->trans('statistics.user.no_roles');
            }

            if (array_key_exists($userRoleString, $roles)) {
                ++$roles[$userRoleString];
            } else {
                $roles[$userRoleString] = 1;
            }
        }

        // sort by count descending
        arsort($roles);

        return $roles;
    }

    public function createMasterUserForCustomer(string $userLogin, Customer $customer): User
    {
        return $this->userRepository->add([
            'firstname'    => '',
            'lastname'     => '',
            'email'        => $userLogin,
            'login'        => $userLogin,
            'password'     => $this->generateNewRandomPassword(),
            'customer'     => $customer,
            'roles'        => [Role::CUSTOMER_MASTER_USER],
        ]);
    }

    /**
     * @return array<int, string>
     */
    protected function getRoleNames(User $user): array
    {
        $userRoles = $user->getDplanroles();
        $userRoleNames = $userRoles->map(static fn (Role $role): string => $role->getName())->toArray();

        // sort the roles to generate the same array key for the same roles
        $userRoleNames = array_unique($userRoleNames);
        sort($userRoleNames);

        return $userRoleNames;
    }

    /**
     * Alle aktiven Benutzer zurückgeben.
     *
     * @param int           $page
     * @param int           $limit
     * @param array         $filters
     * @param Customer|null $customer
     */
    public function getAllActiveUsers($page = 1, $limit = null, $filters = [], $customer = null): array
    {
        try {
            $offset = (null !== $limit) ? ($page - 1) * $limit : null;

            $filters = array_merge($filters, ['deleted' => false]);

            $users = $this->userRepository->findBy(
                $filters,
                ['lastname' => 'ASC'],
                $limit,
                $offset
            );

            if (null === $customer) {
                $customer = $this->customerService->getCurrentCustomer();
            }

            // display only users of current Customer
            if ($customer instanceof Customer) {
                $users = collect($users)->filter(static fn (User $user) => null !== $user->getOrga() && $user->getOrga()->getCustomers()->contains($customer))->all();
            }

            // never show internal Citizen user
            return collect($users)->filter(static fn (User $user) => User::ANONYMOUS_USER_ID !== $user->getId())->all();
        } catch (Exception $e) {
            $this->logger->error('Fehler bei der Abfrage der Userlist: ', [$e]);

            return [];
        }
    }

    /**
     * Passwort ändern.
     *
     * @param string $userId
     * @param string $oldPassword
     * @param string $newPassword
     * @param bool   $verifyOld
     *
     * @throws Exception
     */
    public function changePassword($userId, $oldPassword, $newPassword, $verifyOld = true)
    {
        try {
            try {
                $user = $this->userRepository->get($userId);
            } catch (NoResultException) {
                $user = null;
            }

            if ($verifyOld && !$this->userPasswordHasher->isPasswordValid($user, $oldPassword)) {
                throw new \InvalidArgumentException("This is either not the user's old password or the user does not exist");
            }

            $newPasswordHash = $this->userPasswordHasher->hashPassword($user, $newPassword);

            $user->setPassword($newPasswordHash);
            $user->setAlternativeLoginPassword($newPasswordHash);

            $em = $this->getDoctrine()->getManager();
            $em->persist($user);
            $em->flush();
        } catch (RuntimeException $e) {
            $this->logger->error('Fehler beim Ändern des User password:', [$e]);
            throw $e;
        }
    }

    /**
     * Change the login and the email of a user.
     *
     * @return user|bool - User in case of successfully set Email, otherwise false
     *
     * @throws Exception
     */
    public function setEmailOfUser(User $user, string $email)
    {
        try {
            if (!$this->checkUniqueEmailAndLogin($email, $user)) {
                $this->messageBag->add('error', 'error.login.or.email.not.unique');

                return false;
            }

            $user->setEmail($email);
            $user = $this->userRepository->updateObject($user);

            if ($user instanceof User) {
                $this->getLogger()->info('Email of user was changed.', ['uId' => $user->getId()]);

                return $user;
            }

            return false;
        } catch (RuntimeException $e) {
            $this->getLogger()->error('Fehler beim Ändern der E-Mail-Adresse:', [$e]);
            throw $e;
        }
    }

    /**
     * Liste der InvitableInstitutionsichtbarkeitenänderungen anfordern.
     *
     * @throws Exception
     */
    public function getInvitableInstitutionShowlistChanges(): Pagerfanta
    {
        try {
            return $this->reportService->getInvitableInstitutionShowlistChanges();
        } catch (Exception $e) {
            $this->getLogger()->warning('Error getInvitableInstitutionShowlistChanges: ', [$e]);
            throw $e;
        }
    }

    /**
     * Overrides the data of the given department.
     *
     * @param Department $department The department to wipe
     *
     * @return Department Updated department if successful
     *
     * @throws CouldNotWipeDepartmentException
     */
    public function wipeDepartment(Department $department): Department
    {
        try {
            if (Department::DEFAULT_DEPARTMENT_NAME === $department->getName()) {
                throw new CouldNotWipeDepartmentException('Default Department cant be deleted');
            }

            return $this->departmentRepository->wipe($department);
        } catch (Exception $e) {
            throw new CouldNotWipeDepartmentException('', 0, $e);
        }
    }

    /**
     * Delete the addresses of the given department.
     *
     * @param Department $department Identifies the department, whose addresses will be deleted
     *
     * @throws CouldNotDeleteAddressesOfDepartmentException
     */
    public function deleteAddressesOfDepartment(Department $department)
    {
        try {
            foreach ($department->getAddresses() as $address) {
                // remove addresses form department, to avoid undefined index
                // because doctrine will not do this, because there are no address sited relation, to use annotations
                $department->setAddresses([]);
                $this->addressService->deleteAddress($address->getId());
            }

            // remove addresses form department, to avoid undefined index
            // doctrine will not do this, because there are no address sited relation, to use annotations
            $department->setAddresses([]);
        } catch (Exception $e) {
            throw new CouldNotDeleteAddressesOfDepartmentException('', 0, $e);
        }
    }

    /**
     * Deletes all draftStatements of a department.
     *
     * @param Department $department the department, whose draftstatements will be deleted
     *
     * @throws CouldNotDeleteDraftStatementsOfDepartmentException
     */
    public function deleteDraftStatementsOfDepartment(Department $department)
    {
        try {
            $draftStatements = $this->draftStatementService->getDraftStatementsOfDepartment($department->getId());
            // @improve T12803
            $result = $this->draftStatementService->deleteDraftStatements($draftStatements);
            if (true !== $result) {
                throw new CouldNotDeleteDraftStatementsOfDepartmentException('Could not delete draft statements');
            }
        } catch (Exception $e) {
            throw new CouldNotDeleteDraftStatementsOfDepartmentException('', 0, $e);
        }
    }

    /**
     * Detaches all masterToeb entries of an department.
     *
     * @param Department $department The department, whose masterToeb entries will be detached
     *
     * @throws CouldNotDetachMasterToebOfDepartmentException
     */
    public function detachMasterToebOfDepartment(Department $department)
    {
        try {
            $masterToeb = $this->serviceMasterToeb->getMasterToebByDepartmentId($department->getId());

            if (null !== $masterToeb) {
                $this->serviceMasterToeb->updateMasterToeb($masterToeb->getIdent(), ['department' => null]);
            }
        } catch (Exception $e) {
            throw new CouldNotDetachMasterToebOfDepartmentException('', 0, $e);
        }
    }

    /**
     * Delete unreleased and not submitted draftStatements of the given user.
     *
     * @param string $userId - Indicates the user whose draftStatements will be deleted
     *
     * @return bool `true` if all operations are successful, otherwise `false`
     */
    public function deleteDraftStatementsOfUser($userId): bool
    {
        try {
            $draftStatementsToDelete = $this->draftStatementService->getDeletableDraftStatementOfUser($userId);
            $result = $this->draftStatementService->deleteDraftStatements($draftStatementsToDelete);
        } catch (Exception $e) {
            $this->logger->error('Fehler beim Löschen der draftStatements: ', [$e]);

            return false;
        }

        return $result;
    }

    /**
     * Overrides all user data of the StatementVotes of the given user.
     *
     * @param string $userId
     *
     * @return bool
     */
    public function clearStatementVotes($userId)
    {
        try {
            $success = $this->statementVoteRepository->clearByUserId($userId);
        } catch (Exception $e) {
            $this->logger->error('Fehler beim Löschen der StatementVotes: ', [$e]);

            return false;
        }

        return $success;
    }

    /**
     * Store new email address to set to specific user into setting table,
     * to enable access in case of change of email address is verified.
     *
     * @param string $userId
     * @param string $newEmailAddress
     */
    public function storeNewEmail($userId, $newEmailAddress): bool
    {
        try {
            $this->contentService->setSetting('changeEmail', ['userId' => $userId, 'email' => $newEmailAddress]);
        } catch (Exception $e) {
            $this->logger->error('Fehler beim Speichern der neuen Emailaddresse: ', [$e]);

            return false;
        }

        return true;
    }

    /**
     * @param string|null $ignoreUserId - If given, ignore the user with this ID
     *
     * @return bool
     */
    public function isEmailExisting(string $newEmailAddress, $ignoreUserId = null)
    {
        try {
            $foundUsers = $this->userRepository->findBy(['email' => $newEmailAddress, 'deleted' => false]);

            if ([] === $foundUsers) {
                return false;
            }

            if (count($foundUsers) > 1) {
                return true;
            }

            // number of found users is one:
            if (!is_string($ignoreUserId)) {
                return true;
            }

            /** @var User $user */
            $user = $foundUsers[0];

            return $user->getId() !== $ignoreUserId;
        } catch (Exception $e) {
            $this->logger->error('Fehler beim Prüfen der E-Mail-Adresse: ', [$e]);

            return false;
        }
    }

    /**
     * Search for user with login, to check if login is already in use.
     *
     * @param string      $login        - Login to check for uniqueness
     * @param string|null $ignoreUserId - If given, ignore the user with this ID
     *
     * @return bool - true if given login is unique, otherwise false
     */
    public function isLoginExisting($login, $ignoreUserId = null): bool
    {
        try {
            $foundUsers = $this->userRepository->findBy(['login' => $login, 'deleted' => false]);

            if ([] === $foundUsers) {
                return false;
            }

            if (count($foundUsers) > 1) {
                return true;
            }

            // number of found users is one:
            if (!is_string($ignoreUserId)) {
                return true;
            }

            /** @var User $user */
            $user = $foundUsers[0];

            return $user->getId() !== $ignoreUserId;
        } catch (Exception $e) {
            $this->logger->error('Fehler beim prüfen der E-Mail-Adresse: ', [$e]);

            return false;
        }
    }

    /**
     * Check if given email address is unique as email of user AND of login as user.
     * This is necessary, because user can login with email address or with login.
     * Incoming email has also to be unique in space of login and email.
     * This method consider if a new user will be created or a existing user is changing his email address.
     * Will also create logs and messages for user.
     *
     * @param User|null $userToUpdate  User which updates his email address. Will be null in case of create new user.
     * @param string    $stringToCheck - String to check
     *
     * @return bool - true if given string is unique as email and as login, otherwise false
     */
    public function checkUniqueAsEmail($userToUpdate, $stringToCheck): bool
    {
        try {
            $isEmailChanging = $userToUpdate instanceof User ? ($userToUpdate->getEmail() !== $stringToCheck) : false;
            $updateUserId = $userToUpdate instanceof User ? $userToUpdate->getId() : null;

            // in case of not changing the email address, we do not want to check if the current email is unique.
            if (!$isEmailChanging && $userToUpdate instanceof User) {
                $this->getLogger()->info('Given Email is unique as email or login', ['email' => $stringToCheck]);

                return true;
            }

            // in case of email is changing, check if incoming email is already existing as email or as login.
            if (!$this->isEmailExisting($stringToCheck, $updateUserId) && !$this->isLoginExisting($stringToCheck, $updateUserId)) {
                $this->getLogger()->info('Given Email is unique as email or login', ['email' => $stringToCheck]);

                return true;
            }

            return false;
        } catch (Exception $e) {
            $this->getLogger()->error('Fehler beim Prüfen der E-Mail-Adresse: ', [$e]);

            return false;
        }
    }

    /**
     * Check if given login is unique as email of user AND as login of user.
     * This is necessary, because user can login with email address or with login.
     * Incoming login has also to be unique in space of login and email.
     * This method consider if a new user will be created or a existing user is changing his login.
     * Will also create logs and messages for user.
     *
     * @param User|null $userToUpdate  User which updates his email address. Will be null in case of create new user.
     * @param string    $stringToCheck - String to check
     *
     * @return bool - true if given string is unique as email and as login, otherwise false
     */
    public function checkUniqueAsLogin($userToUpdate, $stringToCheck): bool
    {
        try {
            $isLoginChanging = $userToUpdate instanceof User ? ($userToUpdate->getLogin() !== $stringToCheck) : false;
            $updateUserId = $userToUpdate instanceof User ? $userToUpdate->getId() : null;

            // in case of not changing the login, we do not want to check if the current login is unique.
            if (!$isLoginChanging && $userToUpdate instanceof User) {
                $this->getLogger()->info('Given Login is unique as email or login', ['login' => $stringToCheck]);

                return true;
            }

            // in case of login is changing, check if incoming login is already existing as email or as login.
            if (!$this->isEmailExisting($stringToCheck, $updateUserId) && !$this->isLoginExisting($stringToCheck, $updateUserId)) {
                $this->getLogger()->info('Given Login is unique as email or login', ['login' => $stringToCheck]);

                return true;
            }

            return false;
        } catch (Exception $e) {
            $this->getLogger()->error('Fehler beim prüfen des user-Login: ', [$e]);

            return false;
        }
    }

    /**
     * Check if given login or email address is unique as email of user AND as login of user.
     * This is necessary, because user can login with email address or with login.
     * Incoming login or email has also to be unique in space of login and email.
     * This method consider if a new user will be created or a existing user is changing his login or email.
     * Will also create logs. It will not create messages for the user.
     *
     * @param string    $stringToCheck string to check
     * @param User|null $userToUpdate  User which updates his login or email. Will be null in case of create new user.
     *
     * @return bool true if given string is unique as email and as login, otherwise false
     */
    public function checkUniqueEmailAndLogin($stringToCheck, $userToUpdate = null): bool
    {
        // ignore this check in gateway mode. In bobhh intranetusers who have toeb
        // and planner roles need to be saved as two users. In this case both will
        // (and should) have same email
        if ('gateway' === $this->globalConfig->getProjectType()) {
            return true;
        }

        $uniqueAsEmail = $this->checkUniqueAsEmail($userToUpdate, $stringToCheck);
        if (!$uniqueAsEmail) {
            $this->getLogger()->error('Given Email or Login is not unique as email or login', ['email' => $stringToCheck]);

            return false;
        }

        $uniqueAsLogin = $this->checkUniqueAsLogin($userToUpdate, $stringToCheck);
        if (!$uniqueAsLogin) {
            $this->getLogger()->error('Given Login or Login is not unique as email or login', ['login' => $stringToCheck]);

            return false;
        }

        return true;
    }

    /**
     * Returns the assignee IDs of statements and fragments.
     *
     * @param array[] $statementsOrFragments An array of statements (in their array format) or an array of fragments
     *                                       (in their array format)
     *
     * @return array[] an array of arrays, each item containing the ID of the entity and the ID of the assignee
     */
    public function getAssigneeIds($statementsOrFragments): array
    {
        return collect($statementsOrFragments)
            ->transform(
                static fn ($fragmentOrStatement) => [
                    'id'         => $fragmentOrStatement['id'],
                    'assigneeId' => $fragmentOrStatement['assignee']['uId'] ?? null,
                ]
            )
            ->values()
            ->toArray();
    }

    /**
     * @return array<int, OrgaUsersPair>
     *
     * @throws CustomerNotFoundException
     * @throws Exception
     */
    public function getOrgaUsersList(): array
    {
        $organisations = $this->orgaService->getOrganisations();

        return array_map(function (Orga $currentOrga): OrgaUsersPair {
            $currentUsers = $this->getUsersOfOrganisation($currentOrga->getId());
            // personal data of citizens must be deleted
            if ($currentOrga->isDefaultCitizenOrganisation()) {
                $currentOrga->setEmail2('');
                array_map(static function (User $user): void {
                    $user->setFirstname('');
                    $user->setLastname('');
                    $user->setLogin('');
                }, $currentUsers);
            }

            return new OrgaUsersPair($currentUsers, $currentOrga);
        }, $organisations);
    }

    protected function sortByName(IlluminateCollection $departments): array
    {
        $unsortedDepartments = $departments->getIterator();
        $unsortedDepartments->uasort(
            static fn (Department $department1, Department $department2) => strcmp((string) $department1->getName(), (string) $department2->getName())
        );

        return iterator_to_array($unsortedDepartments);
    }

    public function getTestUsers($testPassword): IlluminateCollection
    {
        return collect($this->getAllActiveUsers())
            ->filter(function (User $user) use ($testPassword) {
                if (null === $this->getValidUser($user->getLogin() ?? '')) {
                    return false;
                }

                return $this->passwordHasherFactory->getPasswordHasher($user)->verify($user->getPassword() ?? '', $testPassword, $user->getSalt());
            })
            ->map(
                static function (User $user) {
                    $roles = collect($user->getDplanroles())->map(
                        static fn (Role $role) => $role->getName()
                    )->implode(', ');

                    $testUser = new TestUserValueObject();
                    $testUser->setName($user->getFullname());
                    $testUser->setOrga($user->getOrgaName());
                    $testUser->setDepartment($user->getDepartment() instanceof Department ? $user->getDepartment()->getName() : '');
                    $testUser->setLogin($user->getLogin());
                    $testUser->setEmail($user->getEmail());
                    $testUser->setRoles($roles);
                    $testUser->lock();

                    return $testUser;
                }
            )
            ->sortBy('orga')
            ->groupBy('orga')
            ->sortBy('roles')
            ->flatten(1);
    }

    /**
     * Fetch users based on given Gatewaystings used e.g. in Tests.
     */
    public function getTestUsersOsi(string $project): IlluminateCollection
    {
        $testUserXml = match ($project) {
            'bimschgsh', 'bobsh', 'planfestsh', 'robobsh' => UserMapperDataportGatewaySHStatic::AVAILABLE_USER,
            'bobhh' => UserMapperDataportGatewayHHStatic::AVAILABLE_USER,
            default => [],
        };

        return collect($testUserXml)
            ->map(
                static function (string $user, $key) {
                    $dom = new DOMDocument();
                    $dom->loadXML($user);

                    $userArray = XML2Array::createArray($dom);
                    $userAttributes = $userArray['USERDATA']['HHGW']['@attributes'];
                    $roleString = 'Bürger';
                    if (array_key_exists('ROLES', $userArray['USERDATA'])) {
                        $roleString = collect($userArray['USERDATA']['ROLES'])->transform(static fn ($roleArray) => $roleArray['@attributes']['ROLENAME'] ?? $roleArray['ROLENAME'] ?? 'Default')
                        ->implode(', ');
                    }
                    $testUser = new TestUserValueObject();
                    $testUser->setName($key);
                    $testUser->setOrga($userAttributes['AUTHORITY'] ?? $userAttributes['COMPANYNAME'] ?? User::ANONYMOUS_USER_ORGA_NAME);
                    $testUser->setDepartment($userAttributes['DEPARTMENT'] ?? $userAttributes['COMPANYORGANISATION'] ?? '');
                    $testUser->setLogin($userAttributes['LOGINNAME']);
                    $testUser->setEmail($userAttributes['EMAIL'].', Mode '.$userAttributes['MODEID']);
                    $testUser->setRoles($roleString);
                    $testUser->lock();

                    return $testUser;
                }
            )
             ->values();
    }

    /**
     * @return Department[]
     */
    public function getAllDepartments(): array
    {
        return $this->departmentRepository->findAll();
    }

    protected function isNotDefaultDepartment(Department $department): bool
    {
        return 'Keine Abteilung' !== $department->getName();
    }

    /**
     * @return array<int,string>
     */
    public function getEmailsOfUsersOfOrgas(Customer $customer): array
    {
        $mailAddresses = [];
        /** @var Orga $orga */
        foreach ($customer->getOrgas([OrgaStatusInCustomer::STATUS_ACCEPTED]) as $orga) {
            /** @var User $user */
            foreach ($orga->getUsers() as $user) {
                // ensure that user is registered in current customer
                // avoids that citizens of other customers are notified
                if ($user->isDeleted() || !in_array($customer->getId(), $user->getCustomersIds(), true)) {
                    continue;
                }

                $mailAddresses[] = $user->getEmail();
            }
        }

        return $mailAddresses;
    }
}
