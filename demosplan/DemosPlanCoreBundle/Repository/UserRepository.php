<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use Closure;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\User\Address;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\FunctionalUser;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Entity\User\UserRoleInCustomer;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Exception\OrgaNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\UserAlreadyExistsException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use demosplan\DemosPlanCoreBundle\Types\UserFlagKey;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\Querying\Utilities\Reindexer;
use Exception;
use Illuminate\Support\Collection;
use LogicException;
use RuntimeException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @template-extends CoreRepository<User>
 */
class UserRepository extends CoreRepository implements ArrayInterface, ObjectInterface, PasswordUpgraderInterface
{
    /**
     * Number of seconds to cache the login list in dev mode.
     */
    final public const LOGIN_LIST_CACHE_DURATION = 43200;

    public function __construct(
        private readonly CacheInterface $cache,
        DqlConditionFactory $dqlConditionFactory,
        ManagerRegistry $registry,
        SortMethodFactory $sortMethodFactory,
        Reindexer $reindexer,
        string $entityClass
    ) {
        parent::__construct($dqlConditionFactory, $registry, $reindexer, $sortMethodFactory, $entityClass);
    }

    /**
     * Get Entity by Id.
     *
     * @param string $userId the user ID as UUID v4
     *
     * @return User never null
     *
     * @throws NoResultException
     */
    public function get($userId): User
    {
        $user = $this->findOneBy(['id' => $userId]);
        if (!$user instanceof User) {
            throw new NoResultException();
        }

        return $user;
    }

    /**
     * Get Users by role code.
     *
     * @return User[]
     */
    public function getUsersByRole(string $code, Customer $customer): array
    {
        $builder = $this->getEntityManager()->createQueryBuilder();

        $result = [];
        try {
            $query = $builder
                ->select('user')
                ->from(User::class, 'user')
                ->join('user.roleInCustomers', 'userRoleInCustomer')
                ->join('userRoleInCustomer.role', 'role')
                ->where('role.code = :code')
                ->andWhere('userRoleInCustomer.customer = :customerId')
                ->setParameter('code', $code)
                ->setParameter('customerId', $customer->getId())
                ->getQuery();

            $result = $query->getResult();
        } catch (Exception $e) {
            $this->logger->warning('getUsersByRole failed. ', [$e]);
        }

        return $result;
    }

    /**
     * Add Entity to database.
     *
     * @throws Exception
     */
    public function add(array $data): User
    {
        $loginName = $data['login'];

        if (null !== $this->findOneBy(['login' => $loginName])) {
            $e = new UserAlreadyExistsException(sprintf('The user with the loginname %s already exists', $loginName));
            $e->setValue($loginName);
            throw $e;
        }

        try {
            $em = $this->getEntityManager();

            $user = new User();
            // returns the $user variable, thus the return value will not be null
            $user = $this->generateObjectValues($user, $data);
            $em->persist($user);

            // set current customer as this could not be done automatically
            $user->setCurrentCustomer($data['customer']);

            // Füge der Orga den User dazu
            if (isset($data['organisation'])) {
                /** @var OrgaRepository $orgaRepos */
                $orgaRepos = $this->getEntityManager()->getRepository(Orga::class);
                $orgaRepos->addUser(
                    $data['organisation']->getId(),
                    $user
                );
            }

            // Füge department den user dazu
            if (isset($data['department'])) {
                /** @var DepartmentRepository $departmentRepos */
                $departmentRepos = $this->getEntityManager()->getRepository(Department::class);
                $departmentRepos->addUser(
                    $data['department']->getId(),
                    $user
                );
            }

            $em->flush();

            $this->invalidateCachedLoginList();

            return $user;
        } catch (Exception $e) {
            $this->logger->warning('User could not be added. ', [$e]);
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Update Entity.
     *
     * @param string $entityId
     *
     * @return User
     *
     * @throws Exception
     */
    public function update($entityId, array $data)
    {
        try {
            $em = $this->getEntityManager();

            try {
                $user = $this->get($entityId);
            } catch (NoResultException) {
                $user = null;
            }
            // this is where the magical mapping happens
            $user = $this->generateObjectValues($user, $data);

            if (isset($data['organisationId'])) {
                $this->moveUserToOrganization($data['organisationId'], $user);
            }
            if (isset($data['departmentId'])) {
                $this->addDepartmentToUser($data, $user);
            }

            $em->persist($user);
            $em->flush();

            $this->invalidateCachedLoginList();

            return $user;
        } catch (Exception $e) {
            $this->logger->warning('Update User failed Reason: ', [$e]);
            throw new RuntimeException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Set Objectvalues by array
     * Set "@param" according to specific entity to get autocompletion.
     *
     * @param User $entity
     */
    public function generateObjectValues($entity, array $data): User
    {
        if (array_key_exists('login', $data)) {
            $entity->setLogin($data['login']);
        }

        $commonEntityFields = collect(
            [
                'deleted',
                'email',
                'emailCanonical',
                'firstname',
                'gender',
                'gwId',
                'lastname',
                'password',
            ]
        );

        // while executing this, the incmoing user "$entity" may be reset.
        // to avoid resetting other changes, this call has to be done at first...
        $this->generateObjectValuesForUserRoles($entity, $data,
            $data['customer'] ?? $entity->getCurrentCustomer()
        );

        $this->setUserEntityFieldsOnFieldCollection($commonEntityFields, $entity, $data);

        if (array_key_exists('password', $data) && 0 < strlen((string) $data['password'])) {
            $entity->setPassword($data['password']);
            $entity->setAlternativeLoginPassword($data['password']);
        }

        $this->generateObjectValuesForUserFlagFields($entity, $data);
        $this->generateObjectValuesForAddressFields($entity, $data);

        return $entity;
    }

    public function addObject($entity): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    /**
     * @param User $entity
     *
     * @throws Exception
     */
    public function updateObject($entity): User
    {
        try {
            if ($entity instanceof FunctionalUser) {
                throw new RuntimeException('May not update functional users');
            }

            $em = $this->getEntityManager();
            $em->persist($entity);
            $em->flush();

            $this->invalidateCachedLoginList();

            return $entity;
        } catch (Exception $e) {
            $this->logger->error('Update User failed Reason: ', [$e]);
            throw $e;
        }
    }

    protected function generateObjectValuesForAddressFields(User $user, array $data)
    {
        // ## Addressdata (if address already exists) ###
        $userAddressFields = collect(['address_postalcode', 'address_city', 'address_street', 'address_state', 'address_houseNumber']);
        $userAddressFields->each(
            function ($fieldName) use ($user, $data) {
                if (array_key_exists($fieldName, $data)) {
                    $fieldSetterMethod = sprintf('set%s', ucfirst(substr($fieldName, 8)));

                    if (!method_exists($user, $fieldSetterMethod)) {
                        throw new LogicException("Cannot set field value on unknown field {$fieldName}");
                    }

                    $user->$fieldSetterMethod($data[$fieldName]);
                }
            }
        );

        // Add Address entity
        if (array_key_exists('address', $data) && $data['address'] instanceof Address) {
            $user->addAddress($data['address']);
        }
    }

    protected function generateObjectValuesForUserFlagFields(User $user, array $data)
    {
        $userFlagFields = collect(UserFlagKey::cases());

        $userFlagFields->each(
            static function (UserFlagKey $userFlagKey) use ($user, $data) {
                if (array_key_exists($userFlagKey->value, $data)) {
                    $fieldSetterMethod = sprintf('set%s', ucfirst($userFlagKey->value));

                    // todo: change this field name so that we can use setEntityFlagField...
                    if (UserFlagKey::ACCESS_CONFIRMED->value === $userFlagKey->value) {
                        $fieldSetterMethod = 'setAccessConfirmed';
                    }

                    if (!method_exists($user, $fieldSetterMethod)) {
                        throw new LogicException("Cannot set field value on unknown field {$userFlagKey->value}");
                    }

                    $user->$fieldSetterMethod((int) $data[$userFlagKey->value]);
                }
            }
        );
    }

    /**
     * @param Customer|null $customer
     */
    protected function generateObjectValuesForUserRoles(User $user, array $data, $customer)
    {
        if (!array_key_exists('roles', $data)) {
            // no roles to change
            return;
        }

        /** @var UserRoleInCustomerRepository $userRoleInCustomerRepository */
        $userRoleInCustomerRepository = $this->getEntityManager()->getRepository(UserRoleInCustomer::class);
        $customerId = $customer instanceof Customer ? $customer->getId() : null;

        // delete all existing user roles

        $userRoleInCustomerRepository->clearUserRoles($user->getId(), $customerId);
        $user->clearRolesCache();
        // because we do not let doctrine do the work for us we need to
        // refresh $user manually after tampering with relations
        // new users may not be refreshed, as they doesn't exist in db yet
        if (null !== $user->getId()) {
            $this->getEntityManager()->refresh($user);
        }

        $roleRepository = $this->getEntityManager()->getRepository(Role::class);
        // verarbeite RollenEntities und Strings
        foreach ($data['roles'] as $role) {
            if ($role instanceof Role) {
                $user->addDplanrole($role, $customer);
            }

            // role code variant
            if (is_string($role) && strlen($role) < 36) {
                // this is called during login via Gateway
                try {
                    $roleEntity = $roleRepository->findOneBy(['code' => $role]);
                    if ($roleEntity instanceof Role) {
                        $user->addDplanrole($roleEntity, $customer);
                    }
                } catch (Exception $e) {
                    $this->logger->error('Could not add Role to User', ['exception' => $e, 'role' => $role, 'user' => $user->getLogin()]);
                }
            }

            // uuid variant
            if (is_string($role) && 36 === strlen($role)) {
                try {
                    $roleEntity = $roleRepository->find($role);

                    if ($roleEntity instanceof Role) {
                        $user->addDplanrole($roleEntity, $customer);
                    }
                } catch (Exception $e) {
                    $this->logger->error('Could not add Role to User', ['exception' => $e, 'role' => $role, 'user' => $user->getLogin()]);
                }
            }
        }
    }

    /**
     * Convenience method to call `setEntityFieldFromData` on multiple fields.
     *
     * @see CoreRepository::setEntityFieldFromData()
     */
    protected function setUserEntityFieldsOnFieldCollection(Collection $fields, User $entity, array $data)
    {
        $fields->each($this->setUserEntityFieldFromData($entity, $data));
    }

    /**
     * Returns a closure to set field names on an entity based form input data.
     *
     * @return Closure
     */
    protected function setUserEntityFieldFromData(User $entity, array $data)
    {
        return function ($fieldName) use ($entity, $data) {
            if (array_key_exists($fieldName, $data)) {
                $fieldSetterMethod = sprintf('set%s', ucfirst($fieldName));

                if (!method_exists($entity, $fieldSetterMethod)) {
                    throw new LogicException("Cannot set field value on unknown field {$fieldName}");
                }

                $entity->$fieldSetterMethod($data[$fieldName]);
            }
        };
    }

    /**
     * Delete a user by id.
     *
     * **User deletion is not yet supported**, instead use `UserRepository::wipe()`
     * to clear personally identifiable information from users.
     */
    public function delete($userId): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    /**
     * Delete a user.
     *
     * **User deletion is not yet supported**, instead use `UserRepository::wipe()`
     * to clear personally identifiable information from users.
     *
     * @param CoreEntity $entity
     */
    public function deleteObject($entity): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    /**
     * Overrides all relevant data field of the given user with default values, to remove any sensible data.
     *
     * @param string $userId
     *
     * @return User|bool
     */
    public function wipe($userId)
    {
        try {
            $em = $this->getEntityManager();

            $randomNumber = random_int(1, PHP_INT_MAX - 1);

            /** @var User $user */
            $user = $this->find($userId);

            //          wipeData:
            $user->setGender(null);
            $user->setTitle(null);
            $user->setFirstname(null);
            $user->setLastname(null);
            $user->setLogin(null);
            $user->setLanguage(null);
            $user->setDeleted(true);
            $user->setGwId(null);
            $user->setEmail($randomNumber);
            $user->setSalt(null);
            $user->setPassword(null);
            $user->setAlternativeLoginPassword(null);
            $user->setNewsletter(false);
            $user->setIntranet(false);
            $user->setForumNotification(false);
            $user->setAccessConfirmed(false);
            $user->setInvited(false);
            $user->setNoPiwik(false);
            $user->setProfileCompleted(false);
            $user->setDraftStatementSubmissionReminderEnabled(false);

            /*
             * user Roles needs to be wiped manually via {@link UserRoleInCustomerRepository::clearUserRoles()}
             */
            $this->invalidateCachedLoginList();

            $em->persist($user);
            $em->flush();
        } catch (Exception $e) {
            $this->logger->error('Could not wipe User '.$userId.' ', [$e]);

            return false;
        }

        return $user;
    }

    public function getFirstUserByCaseInsensitiveLogin(string $login): ?User
    {
        $users = $this->createQueryBuilder('u')
            ->where('upper(u.login) = upper(:login)')
            ->setParameter('login', $login)
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        return array_shift($users);
    }

    /**
     * Get a {@link User} in the FHHNET domain by its {@link User::$login login} string.
     * At least one matching entity must exist, otherwise an exception will be thrown.
     *
     * If multiple matching entities exist the first one will be returned.
     *
     * The matching is done case-**in**sensitive.
     */
    public function getFirstUserInFhhnetByLogin(string $loginSuffix): User
    {
        $login = User::FHHNET_PREFIX.$loginSuffix;

        $user = $this->getFirstUserByCaseInsensitiveLogin($login);
        if (!$user instanceof User) {
            throw new NoResultException();
        }

        return $user;
    }

    /**
     * @param User $user
     */
    public function upgradePassword(UserInterface|PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        // set the new encoded password on the User object
        $user->setPassword($newHashedPassword);
        $user->setAlternativeLoginPassword($newHashedPassword);

        // execute the queries on the database
        $this->getEntityManager()->flush();

        $this->invalidateCachedLoginList();
    }

    /**
     * Removes the given user from its previous organisation and adds it to the one
     * corresponding to the given $orgaId.
     *
     * Does persist both organisation entities but does <strong>not</strong> flush.
     *
     * @throws Exception
     */
    protected function moveUserToOrganization(string $orgaId, User $user): void
    {
        $previousOrga = $user->getOrga();
        if (null !== $previousOrga && $previousOrga->getId() === $orgaId) {
            // nothing to do if the user is already set to the correct orga
            return;
        }

        $orgaRepos = $this->getOrgaRepository();
        $em = $this->getEntityManager();

        $newOrga = $orgaRepos->get($orgaId);
        if (!$newOrga instanceof Orga) {
            throw OrgaNotFoundException::createFromId($orgaId);
        }

        if (null !== $previousOrga) {
            $previousOrga->removeUser($user);
            $em->persist($previousOrga);
        }

        $newOrga->addUser($user);
        $em->persist($newOrga);
    }

    protected function getOrgaRepository(): OrgaRepository
    {
        return $this->getEntityManager()->getRepository(Orga::class);
    }

    /**
     * @throws Exception
     */
    protected function addDepartmentToUser(array $data, User $user)
    {
        /** @var DepartmentRepository $departmentRepos */
        $departmentRepos = $this->getEntityManager()->getRepository(Department::class);

        // in case we have somehow more than one department, delete user from all of them
        $user->getDepartments()->map(
            static function (Department $entry) use ($departmentRepos, $user) {
                $departmentRepos->removeUser($entry->getId(), $user);
            }
        );

        $departmentRepos->addUser($data['departmentId'], $user);
    }

    /**
     * Any operation that might change the total available users
     * or a users' password needs to invalidate the cached login
     * list to trigger it's regeneration on the next login attempt.
     */
    private function invalidateCachedLoginList(): void
    {
        $this->cache->delete(self::LOGIN_LIST_CACHE_DURATION);
    }
}
