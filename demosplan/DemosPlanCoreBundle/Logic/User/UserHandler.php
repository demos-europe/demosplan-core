<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use Cocur\Slugify\Slugify;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use DemosEurope\DemosplanAddon\Contracts\UserHandlerInterface;
use DemosEurope\DemosplanAddon\Logic\ApiRequest\ResourceObject;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Constraint\ValidCssVarsConstraint;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Setting;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\CouldNotDeleteAddressesOfDepartmentException;
use demosplan\DemosPlanCoreBundle\Exception\CouldNotDeleteDraftStatementsOfDepartmentException;
use demosplan\DemosPlanCoreBundle\Exception\CouldNotDetachMasterToebOfDepartmentException;
use demosplan\DemosPlanCoreBundle\Exception\CouldNotWipeDepartmentException;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\DepartmentNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\DuplicateSlugException;
use demosplan\DemosPlanCoreBundle\Exception\EmailAddressInUseException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidUserDataException;
use demosplan\DemosPlanCoreBundle\Exception\LoginNameInUseException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\ReservedSystemNameException;
use demosplan\DemosPlanCoreBundle\Exception\SendMailException;
use demosplan\DemosPlanCoreBundle\Exception\UserAlreadyExistsException;
use demosplan\DemosPlanCoreBundle\Exception\UserModificationException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\ContentService;
use demosplan\DemosPlanCoreBundle\Logic\CoreHandler;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\FlashMessageHandler;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\DraftStatementService;
use demosplan\DemosPlanCoreBundle\Types\UserFlagKey;
use demosplan\DemosPlanCoreBundle\Validator\PasswordValidator;
use demosplan\DemosPlanCoreBundle\ValueObject\Procedure\EmailAddressVO;
use demosplan\DemosPlanCoreBundle\ValueObject\SettingsFilter;
use demosplan\DemosPlanCoreBundle\ValueObject\User\CustomerResourceInterface;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;
use Illuminate\Support\Collection;
use LogicException;
use RuntimeException;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

class UserHandler extends CoreHandler implements UserHandlerInterface
{
    /**
     * @var MailService
     */
    protected $mailService;

    /**
     * @var Environment
     */
    protected $twig;

    /**
     * @var UserService
     */
    protected $userService;

    /**
     * @var DraftStatementService
     */
    protected $draftStatementService;

    /** @var ValidatorInterface */
    protected $validator;

    /** @var CustomerService */
    protected $customerService;

    /** @var OrgaService */
    protected $orgaService;

    /**
     * Since updating the orga show list is not allowed for
     * every user and not in every situation, this variable
     * is being used by `adminOrgasHandler` in conjunction with
     * `updateOrga` to determine wether the showlist should
     * be updated and to remove the data fields in question
     * if that is not the case before the update is passed on
     * to the service.
     *
     * @var bool is the showlist update allowed?
     */
    protected $canUpdateShowList = false;

    protected $splashModalVariablePrefix = 'splashModalHideVersion';

    /**
     * @var OrgaHandler
     */
    protected $orgaHandler;

    public function __construct(
        private readonly ContentService $contentService,
        CustomerService $customerService,
        DraftStatementService $draftStatementService,
        Environment $twig,
        private readonly FlashMessageHandler $flashMessageHandler,
        private readonly FileService $fileService,
        private readonly GlobalConfigInterface $globalConfig,
        MailService $mailService,
        private readonly MasterToebService $masterToebService,
        MessageBagInterface $messageBag,
        OrgaHandler $orgaHandler,
        OrgaService $orgaService,
        private readonly PasswordValidator $passwordValidator,
        private readonly PermissionsInterface $permissions,
        private readonly ProcedureService $procedureService,
        private readonly RoleHandler $roleHandler,
        private readonly TranslatorInterface $translator,
        private readonly UserHasher $userHasher,
        private readonly UserSecurityHandler $userSecurityHandler,
        UserService $userService,
        ValidatorInterface $validator,
    ) {
        parent::__construct($messageBag);
        $this->customerService = $customerService;
        $this->draftStatementService = $draftStatementService;
        $this->mailService = $mailService;
        $this->orgaHandler = $orgaHandler;
        $this->orgaService = $orgaService;
        $this->twig = $twig;
        $this->userService = $userService;
        $this->validator = $validator;
    }

    protected function getOrgaHandler(): OrgaHandler
    {
        return $this->orgaHandler;
    }

    /**
     * @return bool
     */
    public function canUpdateShowList()
    {
        return $this->canUpdateShowList;
    }

    /**
     * @param bool $canUpdateShowList
     */
    public function setCanUpdateShowList($canUpdateShowList)
    {
        $this->canUpdateShowList = $canUpdateShowList;
    }

    /**
     * Get Single Userobject.
     *
     * @param string $userId
     *
     * @return User|null
     *
     * @throws Exception
     */
    public function getSingleUser($userId): ?UserInterface
    {
        return $this->userService->getSingleUser($userId);
    }

    /**
     * @throws NoResultException
     */
    public function getFirstUserInFhhnetByLogin(string $loginSuffix): User
    {
        return $this->userService->getFirstUserInFhhnetByLogin($loginSuffix);
    }

    /**
     * @throws MessageBagException
     * @throws InvalidArgumentException
     * @throws UserAlreadyExistsException
     * @throws EmailAddressInUseException
     * @throws LoginNameInUseException
     * @throws Exception
     */
    public function createCitizen(ParameterBag $data): User
    {
        $firstname = $data->get('r_firstname');
        $lastname = $data->get('r_lastname');
        $emailAddress = $data->get('r_email');

        $fieldsExpected = 5;
        if ($data->has('r_loadtime')) {
            $fieldsExpected += 2;
        }

        if (null === $firstname
            || null === $lastname
            || null === $emailAddress
            || !is_string($firstname)
            || !is_string($lastname)
            // there are only seven values expected. Three "real" values + 1 checkbox + 1 csrf token + eventually 2 Honeypot values
            || $fieldsExpected !== $data->count()) {
            throw new InvalidArgumentException('Invalid request');
        }

        // matches a string containing
        // - any ascii control character: \x00-\x1f\x7f
        // - or one of the following characters: !"%<=>?[\]_{|}
        $nameRegex = '/[]\x00-\x1f\x7f!"%<=>?[\x5c_{|}]/';
        $firstnameConstraint = new Regex(['pattern' => $nameRegex, 'message' => $this->translator->trans('name.first.invalid'), 'match' => false]);
        $lastnameConstraint = new Regex(['pattern' => $nameRegex, 'message' => $this->translator->trans('name.last.invalid'), 'match' => false]);
        $validatableEmail = new EmailAddressVO($emailAddress);
        $validatableEmail->lock();
        $violations = $this->validator->validate($firstname, $firstnameConstraint);
        $violations->addAll($this->validator->validate($lastname, $lastnameConstraint));
        $violations->addAll($this->validator->validate($validatableEmail));
        if (0 < $violations->count()) {
            throw ViolationsException::fromConstraintViolationList($violations);
        }

        $citizenRoleArray = array_map(static fn (Role $role) => $role->getId(), $this->roleHandler->getUserRolesByCodes([Role::CITIZEN]));

        $parameterBag = new ParameterBag([
            'email'          => $emailAddress,
            'login'          => $emailAddress,
            'firstname'      => $firstname,
            'lastname'       => $lastname,
            'roles'          => $citizenRoleArray,
            'organisationId' => User::ANONYMOUS_USER_ORGA_ID,
            'departmentId'   => User::ANONYMOUS_USER_DEPARTMENT_ID,
        ]);

        $user = $this->addUser($parameterBag);
        if (!$user instanceof User) {
            throw new Exception('could not add user');
        }

        return $user;
    }

    /**
     * @throws MessageBagException
     * @throws UserAlreadyExistsException
     * @throws EmailAddressInUseException
     * @throws LoginNameInUseException
     * @throws Exception
     */
    public function addUser(ParameterBag $request): ?User
    {
        $data = $this->transformRequestVariables($request->all());
        $mandatoryErrors = $this->validateUserData($data);

        if (array_key_exists('email', $data)) {
            $emailAddress = $data['email'];
            if (!$this->userService->checkUniqueEmailAndLogin($emailAddress)) {
                $e = new EmailAddressInUseException(sprintf('The email address %s is already in use', $emailAddress));
                $e->setValue($emailAddress);
                throw $e;
            }
        }

        if (array_key_exists('login', $data)) {
            $loginName = $data['login'];
            if (!$this->userService->checkUniqueEmailAndLogin($loginName)) {
                $e = new LoginNameInUseException(sprintf('The login name %s is already in use', $loginName));
                $e->setValue($loginName);
                throw $e;
            }
        }

        if ([] !== $mandatoryErrors) {
            $this->flashMessageHandler->setFlashMessages($mandatoryErrors);
        }

        // Hole die Orga des users
        $orga = $this->getOrgaHandler()->getOrga($data['organisationId']);
        if ($orga instanceof Orga) {
            $data['organisation'] = $orga;
        } else {
            // wenn es keine orgaEntität zur Id gibt, gebe eine Fehlermeldung aus
            $this->getMessageBag()->add('error', 'error.user.organisation_not_found');

            return null;
        }

        // check if department belongs to orga and add it to user data
        if (isset($data['departmentId'])) {
            $checkID = $data['departmentId'];

            $departments = $orga->getDepartments()->filter(
                static fn (Department $department) => $department->getId() === $checkID
            );

            if (1 === $departments->count() && $departments->first() instanceof Department) {
                $data['department'] = $departments->first();
            } else {
                $this->getMessageBag()->add('error', 'error.user.department_not_found');

                return null;
            }
        }

        if (isset($data['roles'])) {
            $requestedRoleIds = $data['roles'];
            if (!is_array($requestedRoleIds)) {
                throw UserModificationException::rolesMustBeArray($requestedRoleIds);
            }

            // map role ids to role entities
            $resolvedRoles = $this->roleHandler->getRolesByIds($requestedRoleIds);
            $resolvedRoleIds = array_map(static fn (Role $role) => $role->getId(), $resolvedRoles);
            $missingRoleIds = array_diff($requestedRoleIds, $resolvedRoleIds);
            if ([] !== $missingRoleIds) {
                $this->logger->warning('Tried to add unknown roles: ', [$missingRoleIds]);
            }

            $data['roles'] = $resolvedRoles;
        }

        // add a new temporary password to user as password needs to be set
        $data['password'] = $this->userService->generateNewRandomPassword();

        return $this->userService->addUser($data);
    }

    /**
     * @throws Exception
     */
    protected function validateUserData(array $data, ?string $userId = null): array
    {
        $mandatoryErrors = [];
        $new = null === $userId;

        if (!array_key_exists('lastname', $data) || '' === trim((string) $data['lastname'])) {
            $lastName = null !== $userId ? $this->getSingleUser($userId)->getLastname() : null;
            if (null === $lastName
                || (array_key_exists('lastname', $data) && '' === trim((string) $data['lastname']))
            ) {
                $mandatoryErrors[] = [
                    'type'    => 'error',
                    'message' => $this->flashMessageHandler->createFlashMessage(
                        'mandatoryError', ['fieldLabel' => $this->translator->trans('name.last')]
                    ),
                ];
            }
        }
        if (!array_key_exists('organisationId', $data) || '' === trim((string) $data['organisationId'])) {
            // check if user has valid Orga
            // complex test to check whether User has orga and orga is not deleted
            // whith this request (aka $data has empty organisationId
            // better Ideas anyone?
            $organisationId = null !== $userId ? $this->getSingleUser($userId)->getOrganisationId() : null;
            if (null === $organisationId
                || (array_key_exists('organisationId', $data) && '' === trim((string) $data['organisationId']))
            ) {
                $mandatoryErrors[] = [
                    'type'    => 'error',
                    'message' => $this->flashMessageHandler->createFlashMessage(
                        'mandatoryError', ['fieldLabel' => $this->translator->trans('organisation')]
                    ),
                ];
            }
        }

        if (!array_key_exists('departmentId', $data) || '' === trim((string) $data['departmentId'])) {
            // check if user has valid Department
            // complex test to check whether User has department and Department is not deleted
            // whith this request (aka $data has empty departmentId
            // better Ideas anyone?
            $departmentId = null !== $userId ? $this->getSingleUser($userId)->getDepartmentId() : null;
            if (null === $departmentId
                || (array_key_exists('departmentId', $data) && '' === trim((string) $data['departmentId']))
            ) {
                $mandatoryErrors[] = [
                    'type'    => 'error',
                    'message' => $this->flashMessageHandler->createFlashMessage(
                        'mandatoryError', ['fieldLabel' => $this->translator->trans('department')]
                    ),
                ];
            }
        }

        if ($new) {
            if (!array_key_exists('login', $data) || '' === trim((string) $data['login'])) {
                $mandatoryErrors[] = [
                    'type'    => 'error',
                    'message' => $this->flashMessageHandler->createFlashMessage(
                        'mandatoryError', ['fieldLabel' => $this->translator->trans('user.name')]
                    ),
                ];
            }
            if (!array_key_exists('roles', $data) || is_null($data['roles']) || 0 === (is_countable($data['roles']) ? count($data['roles']) : 0)) {
                $mandatoryErrors[] = [
                    'type'    => 'error',
                    'message' => $this->flashMessageHandler->createFlashMessage(
                        'mandatoryError', ['fieldLabel' => $this->translator->trans('roles')]
                    ),
                ];
            }
            if (!array_key_exists('email', $data) || '' === trim((string) $data['email'])) {
                $mandatoryErrors[] = [
                    'type'    => 'error',
                    'message' => $this->flashMessageHandler->createFlashMessage(
                        'mandatoryError', ['fieldLabel' => $this->translator->trans('email')]
                    ),
                ];
            }
        }

        return $mandatoryErrors;
    }

    /**
     * Send verification email to new email address in case of user want to change current emailaddress.
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function sendChangeEmailVerificationEmail(User $user, string $newEmailAddress, string $token): User
    {
        $vars = [];
        $emailTextChangeEmail = $this->twig
            ->load('@DemosPlanCore/DemosPlanUser/email_user_change_email.html.twig')
            ->render(
                [
                    'templateVars' => [
                        'userName'    => $user->getFullname(),
                        'token'       => $token,
                        'projectName' => $this->demosplanConfig->getProjectName(),
                        'uId'         => $user->getId(),
                    ],
                ]
            );

        $scope = 'extern';
        $vars['mailbody'] = $emailTextChangeEmail;
        $vars['mailsubject'] = $this->translator->trans('email.subject.change.mail.address');

        // schicke E-Mail ab
        $this->mailService->sendMail(
            'dm_subscription',
            'de_DE',
            $newEmailAddress,
            '',
            '',
            '',
            $scope,
            $vars
        );

        // Notiere, dass mail verschickt wurde
        $this->getLogger()->info('Verification mail to change email address was sent to user', ['userId' => $user->getId()]);

        return $user;
    }

    /**
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function inviteUser(User $user, string $type = 'new'): User
    {
        $vars = [];
        $projectName = $this->demosplanConfig->getProjectName();

        $vars['mailsubject'] = match ($type) {
            'new' => $this->translator->trans(
                'email.subject.user.invited',
                ['project_name' => $projectName]
            ),
            'recover' => $this->translator->trans(
                'email.subject.user.reset_password',
                ['project_name' => $projectName]
            ),
            default => throw new LogicException("The type {$type} is not supported."),
        };

        $hash = $this->userHasher->getPasswordEditHash($user);

        $templateName = match ($type) {
            'new'     => '@DemosPlanCore/DemosPlanUser/email_user_new.html.twig',
            'recover' => '@DemosPlanCore/DemosPlanUser/email_user_recover.html.twig',
            default   => throw new RuntimeException('Unsupported invitation type: '.$type),
        };

        $emailtextInviteUser = $this->twig
            ->load($templateName)
            ->render(
                ['templateVars'   => [
                    'userName' => $user->getFullname(),
                    'token'    => $hash,
                    'uId'      => $user->getId(),
                ],
                    'projectName' => $this->demosplanConfig->getProjectName(),
                ]
            );
        $scope = 'extern';

        $vars['mailbody'] = $emailtextInviteUser;

        // schicke E-Mail ab
        $this->mailService->sendMail(
            'dm_subscription',
            'de_DE',
            $user->getEmail(),
            '',
            '',
            '',
            $scope,
            $vars
        );
        // lösche den userKey
        // Notiere, dass mail verschickt wurde
        $this->logger->info("Invitation mail of type '{$type}' was sent to user {$user->getId()}");
        if ('new' === $type) {
            $this->userService->updateUser($user->getId(), [UserFlagKey::INVITED->value => true]);
        }

        return $user;
    }

    public function adminUsersHandler(ParameterBag $requestData)
    {
        $manageUsersAction = $requestData->get('manageUsers');
        $requestData->remove('manageUsers');

        switch ($manageUsersAction) {
            case 'saveAll':
                $this->handleSaveAllUsers($requestData);
                break;

            case 'inviteSelected':
                $this->handleInviteUsers($requestData);
                break;

            case 'deleteSelected':
                return $this->handleWipeSelectedUsers($requestData);

            default:
                try {
                    [$command, $userIdent] = explode(':', (string) $manageUsersAction);

                    if ('save' === $command) {
                        return $this->handleSaveSingleUser($userIdent, $requestData);
                    }
                } catch (Exception) {
                    $this->getLogger()->warning("Tried to run an unknown user action {$manageUsersAction}");
                }
        }

        return null;
    }

    /**
     * @param ParameterBag $requestData
     */
    public function handleSaveAllUsers($requestData)
    {
        $data = $this->transformRequestVariables($requestData->all());

        foreach ($data as $ident => $user) {
            $result = $this->updateUser($ident, $user);
            if (!$result instanceof User) {
                $this->logger->error("Failed updating user {$ident}");
                $this->getMessageBag()->add('error', 'error.user.update', ['firstName' => $user['firstname'], 'lastName' => $user['lastname']]);

                return;
            }
        }

        $this->getMessageBag()->add('confirm', 'confirm.all.changes.saved');
    }

    /**
     * Save Userdata.
     *
     * @param string $userId
     *
     * @return array|UserInterface|bool
     *
     * @throws Exception
     */
    public function updateUser($userId, array $data)
    {
        $mandatoryErrors = $this->validateUserData($data, $userId);
        $userService = $this->userService;
        $isEmailUnique = true;
        $isLoginUnique = true;

        // T10917: on update check for unique email:
        $user = $this->getSingleUser($userId);
        if (array_key_exists('email', $data)) {
            $isEmailUnique = $userService->checkUniqueEmailAndLogin($data['email'], $user);
        }

        if (array_key_exists('login', $data)) {
            $isLoginUnique = $userService->checkUniqueEmailAndLogin($data['login'], $user);
        }

        if (!$isEmailUnique || !$isLoginUnique) {
            $this->getMessageBag()->add('error', 'error.login.or.email.not.unique');

            // do not return user object to avoid confirm message
            return false;
        }

        if ([] !== $mandatoryErrors) {
            $this->flashMessageHandler->setFlashMessages($mandatoryErrors);

            return [
                'mandatoryfieldwarning' => $mandatoryErrors,
            ];
        }
        // prüfe den status der Checkboxen

        // set Newsletter
        if (array_key_exists(UserFlagKey::SUBSCRIBED_TO_NEWSLETTER->value, $data)) {
            $data[UserFlagKey::SUBSCRIBED_TO_NEWSLETTER->value] = 'on' === $data[UserFlagKey::SUBSCRIBED_TO_NEWSLETTER->value];
        } else {
            $data[UserFlagKey::SUBSCRIBED_TO_NEWSLETTER->value] = false;
        }

        // ignore status in case an external newsletterservice is used
        if ($this->permissions->hasPermission('feature_alternative_newsletter')) {
            $data[UserFlagKey::SUBSCRIBED_TO_NEWSLETTER->value] = false;
        }

        if (array_key_exists(UserFlagKey::WANTS_FORUM_NOTIFICATIONS->value, $data)) {
            $data[UserFlagKey::WANTS_FORUM_NOTIFICATIONS->value] = 'on' === $data[UserFlagKey::WANTS_FORUM_NOTIFICATIONS->value];
        } else {
            $data[UserFlagKey::WANTS_FORUM_NOTIFICATIONS->value] = false;
        }

        if (array_key_exists(UserFlagKey::NO_USER_TRACKING->value, $data)) {
            $data[UserFlagKey::NO_USER_TRACKING->value] = 'on' === $data[UserFlagKey::NO_USER_TRACKING->value];
        } else {
            $data[UserFlagKey::NO_USER_TRACKING->value] = false;
        }

        $data[UserFlagKey::DRAFT_STATEMENT_SUBMISSION_REMINDER_ENABLED->value] =
            array_key_exists(UserFlagKey::DRAFT_STATEMENT_SUBMISSION_REMINDER_ENABLED->value, $data)
            && 'on' === $data[UserFlagKey::DRAFT_STATEMENT_SUBMISSION_REMINDER_ENABLED->value];

        // Sets notifications for newly assigned tasks
        if (array_key_exists(UserFlagKey::ASSIGNED_TASK_NOTIFICATION->value, $data)) {
            $data[UserFlagKey::ASSIGNED_TASK_NOTIFICATION->value] = 'on' === $data[UserFlagKey::ASSIGNED_TASK_NOTIFICATION->value];
        } else {
            $data[UserFlagKey::ASSIGNED_TASK_NOTIFICATION->value] = false;
        }

        // make user editing work with unset department
        if (isset($data['departmentId']) && 'Keine Abteilung' === $data['departmentId']) {
            $data['departmentId'] = null;
        }

        $userObject = $userService->updateUser($userId, $data);

        return $this->userSecurityHandler->handleUserSecurityPropertiesUpdate($userObject, $data);
    }

    /**
     * @throws InvalidUserDataException
     * @throws MessageBagException
     */
    public function handleInviteUsers(ParameterBag $requestData)
    {
        if ($requestData->has('elementsToAdminister') && 0 < (is_countable($requestData->get('elementsToAdminister')) ? count($requestData->get('elementsToAdminister')) : 0)) {
            $userIDsToInvite = $requestData->get('elementsToAdminister');

            $invitedUsersCount = 0;
            $invitationFailedList = collect();

            collect($userIDsToInvite)->each(function ($userId) use (&$invitedUsersCount, $invitationFailedList) {
                try {
                    $user = $this->getSingleUser($userId);
                    if (!$user instanceof User) {
                        throw new InvalidUserDataException("Failed to invite {$userId}");
                    }
                    $this->inviteUser($user);
                    ++$invitedUsersCount;
                } catch (SendMailException $e) {
                    $errorUser = $e->getContext();

                    if ($errorUser instanceof User) {
                        $invitationFailedList->push($errorUser);
                    }

                    throw new InvalidUserDataException("Failed to invite {$userId}");
                }
            });

            if (0 < count($invitationFailedList)) {
                $names = $invitationFailedList->map(static fn (User $user) => $user->getFullname())->implode(', ');

                $this->getMessageBag()->add(
                    'error',
                    'error.email.invitation.send.to.users',
                    ['names' => $names, 'invited' => $invitedUsersCount]
                );
            }

            $this->getMessageBag()->add('confirm', 'confirm.users.invited', ['count' => $invitedUsersCount]);
        } else {
            // wenn keine ausgewählt wurden, gebe eine info raus
            $this->getMessageBag()->add('warning', 'explanation.entries.noneselected');
        }
    }

    /**
     * Determines if the given user is the only user with the permission "area_admin_procedures".
     *
     * @throws Exception
     */
    protected function isUserOnlyAdminOfItsOrganisation(string $userId): bool
    {
        // roles with permission area_admin_procedures: todo: load dynamical
        $rolesOfAreaAdminProcedures = [
            Role::PLANNING_AGENCY_ADMIN,
            Role::PRIVATE_PLANNING_AGENCY,
            Role::PLANNING_AGENCY_WORKER,
            Role::HEARING_AUTHORITY_ADMIN,
            Role::HEARING_AUTHORITY_WORKER,
        ];
        $user = $this->getSingleUser($userId);
        $usersOfOrganisation = $user->getOrga()->getUsers();

        /** @var User $user */
        foreach ($usersOfOrganisation as $user) {
            if ($user->getId() != $userId) {
                $roles = $user->getDplanRolesArray();
                foreach ($rolesOfAreaAdminProcedures as $adminRole) {
                    if (in_array($adminRole, $roles)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Returns procedures of the given organisations, which are not deleted.
     *
     * @return Collection
     */
    protected function getUnerasedProceduresOfOrganisation(Orga $organisation)
    {
        $allProceduresOfOrga = $organisation->getProcedures();
        $openProcedures = collect([]);

        // Collect undeleted Procedure of Orga
        /** @var Procedure $procedure */
        foreach ($allProceduresOfOrga as $procedure) {
            if (!$procedure->isDeleted()) {
                $openProcedures->push($procedure);
            }
        }

        return $openProcedures;
    }

    /**
     * @return string|null
     *
     * @throws Exception
     * @throws MessageBagException
     */
    public function handleWipeSelectedUsers(ParameterBag $requestData)
    {
        if ($requestData->has('elementsToAdminister')
            && 0 < (is_countable($requestData->get('elementsToAdminister')) ? count($requestData->get('elementsToAdminister')) : 0)) {
            $usersToDelete = $requestData->get('elementsToAdminister');

            foreach ($usersToDelete as $userId) {
                $organisation = $this->getSingleUser($userId)->getOrga();
                $numberOfOpenProcedures = $this->getUnerasedProceduresOfOrganisation($organisation)->count();

                if ($this->isUserOnlyAdminOfItsOrganisation($userId) && $numberOfOpenProcedures > 0) {
                    $this->logger->error("Failed to delete user with id {$userId}, because of user is the only administrator of organisation and there are open procedures.");

                    $this->getMessageBag()->add(
                        'error',
                        'error.delete.last.admin.user.of.orga.with.open.procedures',
                        [
                            'organisationName'        => $organisation->getName(),
                            '%numberOfOpenProcedures' => $numberOfOpenProcedures,
                        ]
                    );
                } else {
                    $result = $this->wipeUserData($userId);

                    if (!$result instanceof User) {
                        $this->logger->error("Failed to delete user with id {$userId}");
                        $this->getMessageBag()->add('error', 'error.delete.user');

                        return $userId;
                    }

                    $this->getMessageBag()->add('confirm', 'confirm.entries.marked.deleted');
                }
            }
        } else {
            // wenn keine ausgewählt wurden, gebe eine info raus
            $this->getMessageBag()->add('warning', 'explanation.entries.noneselected');
        }

        return null;
    }

    /**
     * Overrides all relevant data field of the given user with default values.
     * Also deletes addresses and unreleased and not submitted draftStatements of the user.
     * This method does not actually delete the user entity.
     *
     * @param string $userId indicates the user whose data will be wiped
     *
     * @return User|bool - The wiped User if all operations are successful, otherwise false
     */
    public function wipeUserData($userId)
    {
        $resultOfRemoveSettings = $this->contentService->removeSettingsOfUser($userId);
        $resultOfDeleteAddresses = $this->userService->deleteAddressesOfUser($userId);
        $resultOfDeleteDraftStatements = $this->userService->deleteDraftStatementsOfUser($userId);
        $resultOfClearedVotes = $this->userService->clearStatementVotes($userId);

        $wipedUser = $this->userService->wipeUser($userId);

        if ($resultOfRemoveSettings
            && $resultOfDeleteAddresses
            && $resultOfDeleteDraftStatements
            && $resultOfClearedVotes
            && ($wipedUser instanceof User)) {
            return $wipedUser;
        }

        return false;
    }

    /**
     * @param string $userIdent user id
     *
     * @return User|bool|array
     *
     * @throws MessageBagException
     * @throws Exception
     */
    public function handleSaveSingleUser($userIdent, ParameterBag $requestData)
    {
        $data = $this->transformRequestVariables($requestData->all());
        $result = $this->updateUser($userIdent, $data[$userIdent]);

        if ($result instanceof User) {
            $this->getMessageBag()->add('confirm', 'confirm.all.changes.saved');
        }

        return $result;
    }

    /**
     * Update Orga.
     *
     * @return Orga|array|null
     *
     * @throws Exception
     */
    public function addOrga(array $data)
    {
        $mandatoryErrors = $this->getOrgaHandler()->validateOrgaData($data);

        if ([] !== $mandatoryErrors) {
            $this->flashMessageHandler->setFlashMessages($mandatoryErrors);

            return [
                'mandatoryfieldwarning' => $mandatoryErrors,
            ];
        }
        $data = $this->validateActivationInfo($data);
        $data = $this->handleOrgaCustomers($data);
        $data = $this->userService->handleBrandingByCreate($data);
        $newOrga = $this->orgaService->addOrga($data);

        // Add a default Department (= Keine Abteilung)
        $this->addDefaultDepartment($newOrga->getid());
        $this->getMessageBag()->add(
            'confirm',
            $this->translator->trans(
                'confirm.orga.created',
                ['orgaName' => $newOrga->getName()]
            )
        );

        return $newOrga;
    }

    /**
     * Temporary solution to map ResourceObject to old Array structure.
     *
     * @return array
     */
    public function getOrgaArrayFromResourceObject(ResourceObject $resourceObject)
    {
        $orgaData = [];
        $addressFields = [
            'street',
            'city',
            'phone',
            'fax',
            'state',
        ];
        $attributes = $resourceObject['attributes'] ?? [];
        foreach ($attributes as $key => $value) {
            // map special cases
            switch ($key) {
                case in_array($key, $addressFields, true):
                    $key = 'address_'.$key;
                    break;
                case 'code':
                    $key = 'address_postalcode';
                    break;
                case 'copy':
                    $key = 'paperCopy';
                    break;
                case 'copySpec':
                    $key = 'paperCopySpec';
            }
            $orgaData[$key] = $value;
        }

        return $orgaData;
    }

    /**
     * Sets in data the Customer Entity/ies for given id/s.
     *
     * @throws InvalidArgumentException
     */
    private function handleOrgaCustomers(array $data): array
    {
        if (isset($data['customerId']) && is_string($data['customerId'])) {
            $customer = $this->customerService->findCustomerById($data['customerId']);
            if (null === $customer) {
                throw new InvalidArgumentException('No Customer with id: '.$data['customerId']);
            }
            unset($data['customerId']);
            $data['customer'] = $customer;
        } elseif (isset($data['customerIds']) && is_array(Json::decodeToArray($data['customerIds']))) {
            $customerIds = Json::decodeToMatchingType($data['customerIds']);
            $customers = $this->customerService->findCustomersByIds($customerIds);
            if (count($customers) !== (is_countable($customerIds) ? count($customerIds) : 0)) {
                throw new InvalidArgumentException('Wrong ids for Customers '.$data['customerIds']);
            }
            unset($data['customerIds']);
            $data['customers'] = $customers;
        } elseif (isset($data['customerSubdomain'])) {
            $customer = $this->customerService->findCustomerBySubdomain($data['customerSubdomain']);
            if (null === $customer) {
                throw new InvalidArgumentException('No Customer with subdomain: '.$data['customerSubdomain']);
            }
            unset($data['customerSubdomain']);
            $data['customer'] = $customer;
        } else {
            throw new InvalidArgumentException('Missing required info for Customer.');
        }

        return $data;
    }

    /**
     * Fügt der orga ein Defaultdepartment hinzu.
     *
     * @param string $orgaId
     *
     * @throws Exception
     */
    protected function addDefaultDepartment($orgaId)
    {
        $data = [];
        $data['name'] = 'Keine Abteilung';
        $this->userService->addDepartment($data, $orgaId);
    }

    // @improve T15377

    /**
     * Identicates if incoming request is a save or delete action of organisations
     * and call the appropriate methods.
     *
     * @return mixed|string|void|null
     */
    public function adminOrgasHandler(ParameterBag $requestData)
    {
        $manageOrgasAction = $requestData->get('manageOrgas');
        $requestData->remove('manageOrgas');

        // teile dem Model explizit mit, dass die Anzeige in der Töbliste verändert werden darf
        $this->setCanUpdateShowList(true);

        switch ($manageOrgasAction) {
            case 'saveAll':
                return $this->handleSaveAllOrgas($requestData);

            case 'deleteSelected':
                return $this->handleWipeSelectedOrgas($requestData);

            default:
                try {
                    [$command, $ident] = explode(':', (string) $manageOrgasAction);

                    if ('save' === $command) {
                        return $this->handleSaveSingleOrga($ident, $requestData);
                    }
                } catch (Exception) {
                    $this->logger->critical('Undefined orga update action: '.$manageOrgasAction);

                    $this->getMessageBag()->add('error', 'error.undefined');

                    return;
                }

                throw new \InvalidArgumentException("Undefined or unknown requestData['manageOrgasAction']: {$manageOrgasAction}");
        }
    }

    /**
     * Given an array with orgas info, checks whether among them there is a repeated slug.
     * If so, returns the id of the orga with the already exisitng slug.
     * Otherwise returns null.
     *
     * @return mixed|null
     *
     * @throws MessageBagException
     */
    protected function checkDuplicateSlugs(array $orgas)
    {
        $slugify = new Slugify();
        // Do not use array_columns because array keys will be lost
        $orgaSlugs = array_map(static fn ($item) => $slugify->slugify($item['slug']), $orgas);
        $uniqueOrgaSlugs = array_unique($orgaSlugs);
        if (count($uniqueOrgaSlugs) !== count($orgaSlugs)) {
            $this->getMessageBag()->add('error', 'error.save.organisation.slug.bulk');
            $duplicatedSlugs = array_diff(array_keys($orgaSlugs), array_keys($uniqueOrgaSlugs));

            return collect($duplicatedSlugs)->first();
        }

        return null;
    }

    /**
     * @return int|mixed|string|null
     *
     * @throws MessageBagException
     */
    protected function handleSaveAllOrgas(ParameterBag $requestData)
    {
        $data = $this->transformRequestVariables($requestData->all());

        $duplicatedOrgaId = $this->checkDuplicateSlugs($data);
        if (null !== $duplicatedOrgaId) {
            return $duplicatedOrgaId;
        }

        foreach ($data as $ident => $orgaEntry) {
            $result = $this->updateOrga($ident, $orgaEntry);
            if (!$result instanceof Orga) {
                $this->getLogger()->error("Failed updating orga {$ident}");

                $this->getMessageBag()->add('error', 'error.save.organisation', ['organisationName' => $orgaEntry['name']]);

                return $ident;
            }
        }

        // Erfolgsmeldung
        $this->getMessageBag()->add('confirm', 'confirm.all.changes.saved');

        return null;
    }

    // @improve T14548

    /**
     * Update Orga.
     *
     * @param string $orgaId
     * @param array  $data   array with key value pairs where each key is the field to be updated
     *
     * @return Orga|null
     *
     * @throws MessageBagException
     */
    public function updateOrga($orgaId, $data)
    {
        try {
            // the following methods partly validate, partly check permissions, partly do some mapping. It's a bit
            // chaotic. However, it does make sense to keep logical units together. When using, just make sure to
            // consider ALL of the following methods.
            $currentOrga = $this->getOrgaHandler()->getOrga($orgaId);
            $data = $this->adaptDataAttributesInfo($data);
            $data = $this->checkUpdatePermissions($data);
            $this->validateCssVars($data);
            $mandatoryErrors = $this->checkMandatoryFieldsOrga($data, $currentOrga);
            if (0 < $mandatoryErrors) {
                $this->getMessageBag()->add('error', 'error.mandatoryfields');

                return null;
            }
            $data = $this->validateActivationInfo($data);

            return $this->userService->updateOrga($orgaId, $data, true);
        } catch (DuplicateSlugException $e) {
            $this->getMessageBag()->add('error', 'error.organisation.duplicated.slug', ['slug' => $e->getDuplicatedSlug()]);
        } catch (ViolationsException) {
            $this->getMessageBag()->add('error', 'error.organisation.cssvars.invalid');
        } catch (Exception $e) {
            $this->getMessageBag()->add('error', 'error.save');
            $this->logger->error('orga mit orgaId '.$orgaId.' konnte nicht geupdated werden! ('.$e->getMessage().')');
        }

        return null;
    }

    /**
     * @param array<string, string> $data
     *
     * @throws ViolationsException
     */
    private function validateCssVars(array $data): void
    {
        if (array_key_exists(CustomerResourceInterface::STYLING, $data)) {
            $constraintViolationList = $this->validator->validate($data[CustomerResourceInterface::STYLING], new ValidCssVarsConstraint());
            if (0 !== $constraintViolationList->count()) {
                throw ViolationsException::fromConstraintViolationList($constraintViolationList);
            }
        }
    }

    /**
     * Converts file string to File object.
     *
     * @param File|string $file
     *
     * @throws Exception
     */
    public function handleLogoFileMapping($file): File
    {
        // if it's a file string
        if (!$file instanceof File) {
            $fileService = $this->fileService;

            $fileInfo = $fileService->getFileInfoFromFileString($file);
            $file = $fileService->get($fileInfo->getHash());
        }

        return $file;
    }

    /**
     * @return string|null
     *
     * @throws MessageBagException
     */
    protected function handleWipeSelectedOrgas(ParameterBag $requestData)
    {
        if ($requestData->has('elementsToAdminister') && 0 < (is_countable($requestData->get('elementsToAdminister')) ? count($requestData->get('elementsToAdminister')) : 0)) {
            $orgaIdsToDelete = $requestData->get('elementsToAdminister');

            foreach ($orgaIdsToDelete as $orgaId) {
                $result = $this->wipeOrganisationData($orgaId);
                if (!$result instanceof Orga) {
                    $this->logger->error('Orga mit OrgaId: '.$orgaId.' could not been deleted!');

                    return $orgaId;
                }
            }

            $this->getMessageBag()->add('confirm', 'confirm.entries.marked.deleted');
        } else {
            $this->getMessageBag()->add('error', 'error.delete');
        }

        return null;
    }

    protected function handleSaveSingleOrga($ident, ParameterBag $data)
    {
        try {
            $transformedData = $this->transformRequestVariables($data->all());
            $updatedOrga = $this->updateOrga($ident, $transformedData[$ident]);

            if (null !== $updatedOrga) {
                $this->getMessageBag()->add('confirm', 'confirm.orga.updated', ['orgaName' => $updatedOrga->getName()]);

                return null;
            }
        } catch (Exception) {
            $this->logger->error("Failed to update orga with id {$ident}!");
        }

        return $ident;
    }

    /**
     * @return array|mixed|void|null
     *
     * @throws MessageBagException
     */
    public function adminDepartmentsHandler(ParameterBag $requestData)
    {
        $manageDepartmentsAction = $requestData->get('manageDepartments');
        $requestData->remove('manageDepartments');

        switch ($manageDepartmentsAction) {
            case 'saveAll':
                return $this->handleSaveAllDepartments($requestData);

            case 'deleteSelected':
                return $this->handleWipeSelectedDepartments($requestData);

            default:
                try {
                    [$command, $ident] = explode(':', (string) $manageDepartmentsAction);

                    if ('save' === $command) {
                        return $this->handleSaveSingleDepartment($ident, $requestData);
                    }
                } catch (Exception) {
                    $this->logger->critical("Undefined department update action: {$manageDepartmentsAction}.");

                    $this->getMessageBag()->add('error', 'error.generic');
                }
        }

        return null;
    }

    /**
     * @return array
     */
    protected function handleSaveAllDepartments(ParameterBag $requestData)
    {
        $data = $this->transformRequestVariables($requestData->all());

        foreach ($data as $ident => $department) {
            try {
                if ('_token' !== $ident) {
                    $result = $this->updateDepartment($ident, $department);
                    if (is_array($result) && array_key_exists('mandatoryfieldwarning', $result)) {
                        return $this->getSession()->getFlashBag()->get('error.mandatoryfields', 'error');
                    }
                }
            } catch (Exception) {
                $this->logger->error("Failed updating Department {$ident}.");

                return $this->getSession()->getFlashBag()->set(
                    'error',
                    'Die Abteilung konnte nicht aktualisiert werden!'
                );
            }
        }

        return $this->getSession()->getFlashBag()->set(
            'confirm',
            $this->translator->trans('confirm.all.changes.saved')
        );
    }

    /**
     * @param string $departmentId
     * @param array  $data
     *
     * @return array|Department
     *
     * @throws Exception
     */
    public function updateDepartment($departmentId, $data)
    {
        try {
            $mandatoryErrors = $this->validateDepartmentData($data);

            if ([] !== $mandatoryErrors) {
                $this->flashMessageHandler->setFlashMessages($mandatoryErrors);

                return [
                    'mandatoryfieldwarning' => $mandatoryErrors,
                ];
            }

            return $this->userService->updateDepartment($departmentId, $data);
        } catch (Exception $e) {
            $this->getMessageBag()->add(
                'error',
                $this->translator->trans('error.save')
            );
            $this->logger->error('orga mit orgaId '.$departmentId.' konnte nicht geupdated werden!');
            throw $e;
        }
    }

    /**
     * @param array $data
     *
     * @return array
     */
    protected function validateDepartmentData($data)
    {
        $mandatoryErrors = [];

        // Überprüfe Pflichtfeld
        if (!array_key_exists('name', $data) || '' === trim((string) $data['name'])) {
            $mandatoryErrors = [
                'type'    => 'error',
                'message' => $this->flashMessageHandler->createFlashMessage(
                    'mandatoryError',
                    [
                        'fieldLabel' => $this->translator->trans('name'),
                    ]
                ),
            ];
        }

        return $mandatoryErrors;
    }

    /**
     * @return string|void
     *
     * @throws MessageBagException
     */
    protected function handleWipeSelectedDepartments(ParameterBag $requestData)
    {
        if ($requestData->has('elementsToAdminister') && 0 < (is_countable($requestData->get('elementsToAdminister')) ? count($requestData->get('elementsToAdminister')) : 0)) {
            $itemIdsToDelete = $requestData->get('elementsToAdminister');
            foreach ($itemIdsToDelete as $departmentId) {
                try {
                    $result = $this->wipeDepartmentDataById($departmentId);
                    if (!$result instanceof Department) {
                        return $departmentId;
                    }
                } catch (Exception) {
                    $this->logger->error(
                        'Department mit DepartmentId: '.$requestData->get(
                            'elementsToAdminister'
                        ).' could not been deleted!'
                    );

                    $this->getMessageBag()->add('error', 'error.delete.department');
                }
            }

            $this->getMessageBag()->add('confirm', 'confirm.entries.marked.deleted');
        } else {
            $this->getMessageBag()->add('error', 'error.delete');
        }
    }

    /**
     * @param string $ident
     *
     * @return void|null
     *
     * @throws MessageBagException
     */
    protected function handleSaveSingleDepartment($ident, ParameterBag $data)
    {
        try {
            $transformedData = $this->transformRequestVariables($data->all());
            $result = $this->updateDepartment($ident, $transformedData[$ident]);

            if (array_key_exists('mandatoryfieldwarning', $result)) {
                $this->getMessageBag()->add('error', 'error.mandatoryfields');
            }

            $this->getMessageBag()->add('confirm', 'confirm.department.updated');
        } catch (Exception) {
            $this->logger->error(
                'Department mit DepartmentId: '.$ident.' could not been updated!'
            );

            $this->getMessageBag()->add('error', 'error.update.department');
        } finally {
            return null;
        }
    }

    /**
     * @param string $orgaId
     * @param array  $data
     *
     * @return array|Department
     *
     * @throws MessageBagException
     * @throws ReservedSystemNameException
     */
    public function addDepartment($orgaId, $data)
    {
        $mandatoryErrors = $this->validateDepartmentData($data);

        if ([] !== $mandatoryErrors) {
            return [
                'mandatoryfieldwarning' => $mandatoryErrors,
            ];
        }
        if (strtolower(trim((string) $data['name'])) === strtolower(trim((string) Department::DEFAULT_DEPARTMENT_NAME))) {
            throw ReservedSystemNameException::createFromName($data['name']);
        }

        // Überprüfe, ob dazugehörige Orga existiert
        $orga = $this->getOrgaHandler()->getOrga($orgaId);
        if ($orga instanceof Orga) {
            try {
                // wenn ja, übergebe sie im Datenarray
                $data['organisation'] = $orga;

                return $this->userService->addDepartment($data, $orgaId);
            } catch (Exception) {
                $this->logger->error('Department could not been added!');
                $this->getMessageBag()->add('error', 'error.department.create');
            }
        } else {
            $this->logger->error('Selected Organisation for creating department could not been found!');

            $this->getMessageBag()->add('error', 'error.organisation.not.existent');
        }

        throw new Exception('Department creation failed miserably.');
    }

    /**
     * @param array $data [userId, password_old, password_new, password_new_2
     */
    public function changePasswordHandler(array $data)
    {
        $mandatoryErrors = $this->validateChangePasswordData($data);

        if ([] !== $mandatoryErrors) {
            $this->flashMessageHandler->setFlashMessages($mandatoryErrors);

            return;
        }

        $userId = $data['userId'];
        $oldPassword = trim((string) $data['password_old']);
        $newPassword = trim((string) $data['password_new']);

        try {
            $this->userService->changePassword($userId, $oldPassword, $newPassword);
            $this->getMessageBag()->add('confirm', 'confirm.password.changed');
        } catch (Exception $e) {
            $this->logger->error('User password change exited with an error', [$e]);
            $this->getMessageBag()->add('error', 'error.password.change');
        }
    }

    /**
     * @return array
     *
     * @throws Exception
     */
    protected function validateChangePasswordData(array $data)
    {
        $mandatoryErrors = [];

        // Daten überprüfen
        if (!array_key_exists('userId', $data) || '' === trim((string) $data['userId'])) {
            throw new Exception('No userId given');
        }
        if (!array_key_exists('password_old', $data) || '' === trim((string) $data['password_old'])) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->flashMessageHandler->createFlashMessage(
                    'mandatoryError',
                    [
                        'fieldLabel' => $this->translator->trans('password.old'),
                    ]
                ),
            ];
        }

        $mandatoryErrors = $this->checkMandatoryErrorsNewPasswordFields($data, $mandatoryErrors);

        $mandatoryErrors = $this->checkMandatoryErrorsPasswordEquals($data, $mandatoryErrors);

        return $this->checkMandatoryErrorsPasswordStrength($data['password_new'], $mandatoryErrors);
    }

    /**
     * Preparation for changing email address of user.
     *
     * @param string $userId
     * @param string $password
     * @param string $newEmailAddress
     *
     * @return bool
     */
    public function requestEmailChange($userId, $password, $newEmailAddress, PasswordHasherFactoryInterface $hasherFactory)
    {
        try {
            $user = $this->getSingleUser($userId);
            if (!$user instanceof User) {
                $this->getLogger()->info(
                    'Wrong user on request of email change.',
                    ['userId' => $userId]
                );
                throw new \InvalidArgumentException('Could not find User');
            }
            $this->getLogger()->info(
                'Request change of email',
                ['userId' => $userId, 'from' => $user->getEmail(), 'to' => $newEmailAddress]
            );

            // check PW before send mail
            if (!$hasherFactory->getPasswordHasher($user)->verify($user->getPassword() ?? '', $password)) {
                $this->getLogger()->info(
                    'Wrong password on request of email change.',
                    ['userId' => $userId]
                );
                $this->getMessageBag()->add('error', 'error.wrong.password');
                throw new \InvalidArgumentException("This is either not the user's password or the user does not exist");
            }

            $newEmailAddress = trim($newEmailAddress);
            // Check if a valid email was entered and email is unique
            if (!filter_var($newEmailAddress, FILTER_VALIDATE_EMAIL)) {
                $this->getLogger()->error(
                    'Given Email has invalid format.',
                    ['userId' => $userId, 'newEmailAddress' => $newEmailAddress]
                );
                $this->getMessageBag()->add('error', 'error.email.invalid');

                return false;
            }
            $this->getLogger()->info(
                'Incoming Email address on request email change of user has valid format.',
                ['userId' => $userId, 'newEmailAddress' => $newEmailAddress]);

            // given email has to be unique in email as well as in login to avoid setting existing login as new email
            if (!$this->userService->checkUniqueEmailAndLogin($newEmailAddress, $user)) {
                $this->getMessageBag()->add('error', 'error.login.or.email.not.unique');

                return false;
            }
            $this->getLogger()->info('Incoming Email is unique.');

            $successful = $this->userService->storeNewEmail($user->getId(), $newEmailAddress);
            if (!$successful) {
                $this->getMessageBag()->add('error', 'error.save.email');

                return false;
            }
            $this->getLogger()->info('Successfully stored incoming Email in settings.');

            $hash = $this->userHasher->getChangeEmailHash($user, $newEmailAddress);
            $user = $this->sendChangeEmailVerificationEmail($user, $newEmailAddress, $hash);
            if ($user instanceof User) {
                $this->getMessageBag()->add('confirm', 'confirm.user.request.change.email',
                    ['email' => $newEmailAddress]);

                return true;
            }

            return false;
        } catch (Exception) {
            $this->getLogger()->error('User password could not be changed!');

            return false;
        }
    }

    /**
     * Execute a change of the email-address of a specific user by set previously by user defined email-address.
     * Incoming key identifies a UserKey, which holds a specific email-address and a specific user.
     * After successfully change email-address of user, the Setting entry as well as the UserKey will be deleted.
     *
     * @return bool|User - User in case of successfully set email, otherwise false
     *
     * @throws Exception
     */
    public function changeEmailValidate(User $user, string $token)
    {
        try {
            $userService = $this->userService;

            $setting = $this->contentService->getSettings(
                'changeEmail',
                SettingsFilter::whereUser($user)->lock(),
                false
            );

            if (!is_array($setting) || 1 !== count($setting) || !($setting[0] instanceof Setting)) {
                $this->getLogger()->error('Too many Entries (Settings) found!');
                throw new \InvalidArgumentException('Too many Entries (Settings) found!');
            }

            $setting = $setting[0];
            $newEmail = $setting->getContent();

            if (!$this->userHasher->isValidChangeEmailHash($user, $newEmail, $token)) {
                $this->getLogger()->warning('Angefragter Key ist nicht gültig', ['key' => $token, 'uId' => $user->getId()]);
                $this->getMessageBag()->add('error', 'error.user.invalidkey');
                throw new \InvalidArgumentException('Invalid key');
            }

            $this->getLogger()->info('Confirmed email change of user ', ['userId' => $user->getId()]);

            $user = $userService->setEmailOfUser($user, $newEmail);

            if ($user instanceof User) {
                $this->contentService->deleteSetting($setting->getId());
            }

            return $user;
        } catch (Exception $e) {
            $this->getLogger()->error('Fehler bei der Abfrage: ', [$e]);
            throw $e;
        }
    }

    public function recoverPasswordHandler(User $user): bool
    {
        $email = $user->getEmail();
        try {
            $email = trim($email);

            if ('' === $email) {
                return false;
            }

            $this->getMessageBag()->add('confirm', 'user.password.recovery_vague', ['email' => $email]);

            // Check if a valid email was entered
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return false;
            }

            $this->inviteUser($user, 'recover');

            return true;
        } catch (Exception) {
            $this->logger->error('User password could not be changed!');

            return false;
        }
    }

    /**
     * Setze für den Nutzer den Status, dass sein Konto bestätigt wurde.
     *
     * @throws Exception
     */
    public function setAccessConfirmed(User $user): User
    {
        $user->setAccessConfirmed(true);

        return $this->userService->updateUserObject($user);
    }

    /**
     * Fetch  all departments of a given orga.
     *
     * @return Department[] Contains departments as legacy array format
     *
     * @throws Exception
     */
    public function getDepartments(Orga $orga): array
    {
        return $this->orgaService->getDepartments($orga);
    }

    /**
     * @return array[]
     *
     * @throws Exception
     */
    public function getSortedLegacyDepartmentsWithoutDefaultDepartment(Orga $orga): array
    {
        return $this->userService->getSortedLegacyDepartmentsWithoutDefaultDepartment($orga);
    }

    /**
     * @param array $data
     * @param Orga  $currentOrga
     *
     * @return int
     */
    protected function checkMandatoryFieldsOrga($data, $currentOrga)
    {
        $mandatoryErrors = 0;

        // if support changes visibility of toeb in toeblist, a reason must be given
        $showList = array_key_exists('showlist', $data) ? filter_var($data['showlist'], FILTER_VALIDATE_BOOLEAN) : false;
        if ($this->canUpdateShowList() && $showList !== $currentOrga->getShowlist() && (!array_key_exists('showlistChangeReason', $data)
            || '' === trim((string) $data['showlistChangeReason']))) {
            $this->getMessageBag()->add('error', 'reason.change');
            ++$mandatoryErrors;
        }

        if (array_key_exists('email2', $data)) {
            $mandatoryErrors = $this->validateEmailAddress($currentOrga, $data['email2'], $mandatoryErrors);
        }

        if (array_key_exists('slug', $data)) {
            $mandatoryErrors = $this->validateSlugName($data['slug'], $mandatoryErrors);
        }

        return $mandatoryErrors;
    }

    /**
     * Overrides all relevant data field of the given organisation with default values.
     * Will wipe related Departments, if there are no user in there.
     * This method actually do not delete the organisation entity.
     *
     * @param string $organisationId Indicates the organisation whose data will be wiped
     *
     * @return Orga|array The wiped organisation if all operations are successful, otherwise errors
     *
     * @throws MessageBagException
     */
    public function wipeOrganisationData(string $organisationId)
    {
        $errors = [];
        $requiredRelationsAreSolved = true;

        $organisation = $this->orgaHandler->getOrga($organisationId);
        if (!$organisation instanceof Orga) {
            $requiredRelationsAreSolved = false;
            $errors[] = 'error.delete.organisation.not.found';
        }
        if ($organisation instanceof Orga) {
            // related Entities, which have do be solved, before wiping organisation:
            if (false === $organisation->getProcedures()->isEmpty()) {
                $requiredRelationsAreSolved = false;
                $errors[] = 'error.delete.organisation.related.procedure';
            }

            if (!$organisation->getUsers()->isEmpty()) {
                $requiredRelationsAreSolved = false;
                $errors[] = 'error.delete.organisation.related.user';
            }

            // if one of the related departments have a user, return false
            /** @var Department[] $departments */
            $departments = $organisation->getDepartments();
            foreach ($departments as $department) {
                if ($requiredRelationsAreSolved && !$department->getUsers()->isEmpty()) {
                    $requiredRelationsAreSolved = false;
                    $errors[] = 'error.delete.organisation.related.departments';
                }
            }

            if ($requiredRelationsAreSolved) {
                $successfulDeletedDepartments = $this->wipeDepartmentsOfOrga($organisation);
                $successfulDeletedAddresses = $this->orgaService->deleteAddressesOfOrga($organisationId);
                $successfulDeletedDraftStatements = $this->draftStatementService->deleteDraftStatementsOfOrga($organisationId);
                $successfulRemovedSettings = $this->contentService->deleteSettingsOfOrga($organisationId);
                $successfulDeletedMasterToebs = $this->masterToebService->detachMasterToebOfOrga($organisationId);
                $successfulDeletedMasterToebMail = $this->procedureService->deleteInstitutionMailOfOrga($organisationId);

                if ($successfulRemovedSettings
                    && $successfulDeletedAddresses
                    && $successfulDeletedDraftStatements
                    && $successfulDeletedMasterToebs
                    && $successfulDeletedMasterToebMail
                    && $successfulDeletedDepartments) {
                    $result = $this->orgaService->wipeOrganisation($organisationId);
                    if ($result instanceof Orga) {
                        return $result;
                    }
                    $errors[] = 'error.delete.organisation.not.found';
                }
                $errors[] = 'error.organisation.not.deleted';
            }
        }

        return $errors;
    }

    /**
     * Ignores departments with the name {@link Department::DEFAULT_DEPARTMENT_NAME}.
     *
     * @return bool true if all departments were successfully deleted
     *
     * @throws Exception
     */
    public function wipeDepartmentsOfOrga(Orga $orga): bool
    {
        $departmentsToWipe = $this->getDepartments($orga);

        return collect($departmentsToWipe)
            // keep only non-default departments
            ->filter(static fn (Department $department) => Department::DEFAULT_DEPARTMENT_NAME !== $department->getName())
            // execute wipe for every remaining department
            // (aborts on the first failing wipe)
            ->every(fn (Department $department) => $this->wipeDepartmentData($department));
    }

    /**
     * Overrides all relevant data field of the given department with default values.
     * This method actually do not delete the department entity.
     *
     * @return bool|Department
     *
     * @throws DepartmentNotFoundException
     * @throws Exception
     */
    public function wipeDepartmentDataById(string $departmentId)
    {
        $department = $this->userService->getDepartment($departmentId);

        if (!$department instanceof Department) {
            throw DepartmentNotFoundException::createFromId($departmentId);
        }

        return $this->wipeDepartmentData($department);
    }

    /**
     * Overrides all relevant data field of the given department with default values.
     * This method actually do not delete the department entity.
     *
     * @return bool|Department
     */
    public function wipeDepartmentData(Department $department)
    {
        try {
            if ($department->getUsers()->isEmpty()) {
                $this->userService->deleteAddressesOfDepartment($department);
                $this->userService->deleteDraftStatementsOfDepartment($department);
                $this->userService->detachMasterToebOfDepartment($department);

                return $this->userService->wipeDepartment($department);
            }

            $this->getMessageBag()->add('error', 'error.delete.department.related.users');
        } catch (CouldNotDeleteAddressesOfDepartmentException $e) {
            $this->logger->error('Fehler beim Löschen der Adressen: ', [$e]);
        } catch (CouldNotDeleteDraftStatementsOfDepartmentException $e) {
            $this->logger->error('Fehler beim Löschen der draftStatements: ', [$e]);
        } catch (CouldNotDetachMasterToebOfDepartmentException $e) {
            $this->logger->error('Fehler beim Lösen des Departments vom masterToeb Eintrage: ', [$e]);
        } catch (CouldNotWipeDepartmentException $e) {
            $this->logger->error('Fehler beim Löschen der Abteilung: ', [$e]);
        } catch (Exception $e) {
            $this->logger->error('wipeDepartmentData failed', [$e]);
        }

        return false;
    }

    /**
     * @throws MessageBagException
     */
    public function createUserFromResourceObject(ResourceObject $resourceObject): ?User
    {
        $parameterBag = new ParameterBag(
            [
                'firstname'      => $resourceObject['firstname'],
                'lastname'       => $resourceObject['lastname'],
                'email'          => $resourceObject['email'],
                'login'          => $resourceObject['email'],
                'organisationId' => data_get($resourceObject, 'relationships.orga.data.id'),
                'departmentId'   => data_get($resourceObject, 'relationships.department.data.id'),
                'roles'          => collect(
                    data_get($resourceObject, 'relationships.roles.data')
                )->map(
                    static fn ($relationship) => $relationship['id']
                )->toArray(),
            ]
        );

        return $this->addUser($parameterBag);
    }

    public function findPublicAffairsAgenciesIdsByCustomer(Customer $customer): array
    {
        return $this->orgaService->findPublicAffairsAgenciesIdsByCustomer($customer);
    }

    public function isOrgaType(string $orgaId, string $orgaType): bool
    {
        return $this->orgaService->isOrgaType($orgaId, $orgaType);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws Exception
     */
    public function addCustomerToPublicAffairsAgencyByIds(string $customerId, string $publicAffairsAgencyId)
    {
        $this->orgaService->addCustomerToPublicAffairsAgencyByIds($customerId, $publicAffairsAgencyId);
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function removeCustomerFromPublicAffairsAgencyByIds(string $customerId, string $publicAffairsAgencyId)
    {
        $this->orgaService->removeCustomerFromPublicAffairsAgencyByIds($customerId, $publicAffairsAgencyId);
    }

    /**
     * If there is some registration status for a subdomain different from current and the user has no permission to
     * modify other domains' orgas, then the registration status info gets removed from the $data array.
     *
     * @throws CustomerNotFoundException
     */
    private function validateActivationInfo(array $data): array
    {
        $currentSubdomain = $this->customerService->getCurrentCustomer()->getSubdomain();
        $canManageAllOrgas = $this->permissions->hasPermission('area_manage_orgas_all');
        $registrationStatuses = $this->orgaService->getActivationChanges($data);
        $registrationStatuses = array_filter($registrationStatuses,
            fn ($registrationStatus) => $registrationStatus['subdomain'] === $currentSubdomain || $canManageAllOrgas
        );

        $registrationStatuses = array_map(
            function ($registrationStatus) {
                $subdomain = $registrationStatus['subdomain'];
                $registrationStatus['customer'] = $this->customerService->findCustomerBySubdomain($subdomain);

                return $registrationStatus;
            },
            $registrationStatuses
        );

        $data['registrationStatuses'] = $registrationStatuses;

        return $data;
    }

    /**
     * Given a freshly updated Orga, an Orga Type and their original activation statuses checks whether there has been
     * any change in the statuses for the given customer. If so, sends the expected notifications.
     *
     * If customer null they all will be treated.
     *
     * @param Customer|null $currentCustomer
     *
     * @throws Exception
     */
    public function manageStatusChangeNotifications(Orga $orga, string $orgaTypeName, array $customersPendingActivation, $currentCustomer)
    {
        $customersAccepted = $this->getCustomersWithRecentActivationChanges(
            $orga,
            $orgaTypeName,
            OrgaStatusInCustomer::STATUS_ACCEPTED,
            $customersPendingActivation,
            $currentCustomer
        );
        $customersRejected = $this->getCustomersWithRecentActivationChanges(
            $orga,
            $orgaTypeName,
            OrgaStatusInCustomer::STATUS_REJECTED,
            $customersPendingActivation,
            $currentCustomer
        );

        $this->notifyAcceptedOrga($orga, $orgaTypeName, $customersAccepted);
        $this->notifyRejectedOrga($orga, $orgaTypeName, $customersRejected);
    }

    public function manageMinimalRoles(Orga $orga, string $orgaTypeName, array $customersPendingActivation, ?Customer $currentCustomer)
    {
        $customersAccepted = $this->getCustomersWithRecentActivationChanges(
            $orga,
            $orgaTypeName,
            OrgaStatusInCustomer::STATUS_ACCEPTED,
            $customersPendingActivation,
            $currentCustomer
        );

        $this->ensureMinimalRoles($orga, $orgaTypeName, $customersAccepted);
    }

    private function getCustomersWithRecentActivationChanges(
        Orga $orga,
        string $orgaTypeName,
        string $activationStatus,
        array $customersPendingActivation,
        ?Customer $currentCustomer,
    ): array {
        $customers = $orga->getCustomersByActivationStatus($orgaTypeName, $activationStatus);

        // If parameter $currentCustomer is not null then we will exclude other customers/subdomains
        if (null !== $currentCustomer) {
            $customers = array_filter(
                $customers,
                static fn (Customer $customer) => $customer->getSubdomain() === $currentCustomer->getSubdomain()
            );
        }

        return array_intersect($customersPendingActivation, $customers);
    }

    /**
     * @param Customer[] $customers
     *
     * @throws Exception
     */
    private function notifyAcceptedOrga(Orga $orga, string $orgaTypeName, array $customers)
    {
        foreach ($customers as $customer) {
            $masterUser = $orga->getMasterUser($customer->getSubdomain());
            if (null !== $masterUser) {
                if (empty($masterUser->getPassword())) {
                    $this->inviteUser($masterUser);
                }
                $to = $masterUser->getEmail();
                $from = $this->globalConfig->getEmailSystem();
                $customerName = $customer->getName();
                $userFirstName = $masterUser->getFirstname();
                $userLastName = $masterUser->getLastname();
                $orgaName = $orga->getName();

                $orgaTypeLabel = $this->orgaService->transformOrgaTypeNameToLabel($orgaTypeName);

                $this->orgaService->sendRegistrationAccepted($from, $to, $orgaTypeLabel, $customerName,
                    $userFirstName, $userLastName, $orgaName);
            } else {
                $this->logger->error('Orga # '.$orga->getId().' has no masteruser.');
            }
        }
    }

    /**
     * @param Customer[] $customers
     *
     * @throws Exception
     */
    private function notifyRejectedOrga(Orga $orga, string $orgaTypeName, array $customers)
    {
        foreach ($customers as $customer) {
            $masterUser = $orga->getMasterUser($customer);
            if (null !== $masterUser) {
                $to = $masterUser->getEmail();
                $from = $this->globalConfig->getEmailSystem();
                $customerName = $customer->getName();
                $userFirstName = $masterUser->getFirstname();
                $userLastName = $masterUser->getLastname();
                $orgaName = $orga->getName();

                $orgaTypeLabel = $this->orgaService->transformOrgaTypeNameToLabel($orgaTypeName);

                $this->orgaService->sendRegistrationRejected($from, $to, $orgaTypeLabel, $customerName,
                    $userFirstName, $userLastName, $orgaName);
            } else {
                $this->logger->error('Orga # '.$orga->getId().' has no masteruser.');
            }
        }
    }

    /**
     * @param Customer[] $customers
     *
     * @throws Exception
     */
    public function ensureMinimalRoles(Orga $orga, string $orgaTypeName, array $customers)
    {
        $minimumRoles = [
            OrgaType::MUNICIPALITY             => Role::PLANNING_AGENCY_ADMIN,
            OrgaType::PUBLIC_AGENCY            => Role::PUBLIC_AGENCY_COORDINATION,
            OrgaType::PLANNING_AGENCY          => Role::PRIVATE_PLANNING_AGENCY,
            OrgaType::HEARING_AUTHORITY_AGENCY => Role::HEARING_AUTHORITY_ADMIN,
        ];

        if (!array_key_exists($orgaTypeName, $minimumRoles)) {
            return;
        }

        foreach ($customers as $customer) {
            // check whether at least one user already has minimal required role
            $usersWithMinimalRole = $orga->getUsers()->filter(static fn (User $user) => in_array($minimumRoles[$orgaTypeName], $user->getDplanRolesArray($customer), true));
            if (0 < $usersWithMinimalRole->count() || 0 === $orga->getUsers()->count()) {
                continue;
            }

            // if no user could be found, grant master user minimal required role
            $masterUser = $orga->getMasterUser($customer->getSubdomain());
            if (null !== $masterUser) {
                $roles = $this->roleHandler->getUserRolesByCodes([$minimumRoles[$orgaTypeName]]);
                $masterUser->addDplanrole($roles[0], $customer);
                $this->userService->updateUserObject($masterUser);
                $this->logger->info('Added minimal role to masterUser',
                    [
                        'orga'     => $orga->getName(),
                        'user'     => $masterUser->getLogin(),
                        'role'     => $roles[0]->getCode(),
                        'customer' => $customer->getName(),
                    ]
                );
            } else {
                $this->logger->info('No masterUser found',
                    ['orga' => $orga->getName(), 'orgaId' => $orga->getId(), 'customer' => $customer->getName()]
                );
            }
        }
    }

    /**
     * Takes care of some changes in the $data array that might be necessary.
     *
     * @param array $data
     *
     * @throws Exception
     */
    protected function adaptDataAttributesInfo($data): array
    {
        if (isset($data['attributes'])) {
            $keyArray = [
                'city', 'competence', 'copy', 'copySpec', 'cssvars', 'email2', 'emailNotificationEndingPhase',
                'emailNotificationNewStatement', 'houseNumber', 'name', 'phone', 'postalcode', 'registrationStatuses',
                'showlist', 'showlistChangeReason', 'showname', 'street', 'url', 'imprint', 'dataProtection',
            ];
            foreach ($keyArray as $key) {
                if (isset($data['attributes'][$key])) {
                    $data[$key] = $data['attributes'][$key];
                    unset($data['attributes'][$key]);
                }
            }
        }

        unset($data['attributes']);

        // set or reset logo file
        if (array_key_exists('logo', $data) && null !== $data['logo']) {
            if (0 !== strlen((string) $data['logo'])) {
                $data['logo'] = $this->handleLogoFileMapping($data['logo']);
            } else {
                $data['logo'] = null; // this deletes the logo
            }
        }

        return $data;
    }

    /**
     * Check permissions for update.
     *
     * @param array $data
     *
     * @throws Exception
     */
    protected function checkUpdatePermissions($data): array
    {
        $dataPermissionChecked = [];

        if ($this->canUpdateShowList()) {
            // also set $data['updateShowlist'] as it might be overwritten later
            // within feature_orga_edit_all check
            $data['updateShowlist'] = true;
            $dataPermissionChecked['updateShowlist'] = true;
            $dataPermissionChecked['showlist'] = $data['showlist'];
            $dataPermissionChecked['showlistChangeReason'] = $data['showlistChangeReason'] ?? '';
        }

        if ($this->permissions->hasPermission('field_data_protection_text_customized_edit_orga')
            && isset($data['data_protection'])
        ) {
            // also set $data['dataProtection'] as it might be overwritten later
            // within feature_orga_edit_all check
            $data['dataProtection'] = $data['data_protection'];
            $dataPermissionChecked['dataProtection'] = $data['data_protection'];
        }

        if ($this->permissions->hasPermission('field_imprint_text_customized_edit_orga')
            && isset($data['imprint'])
        ) {
            $dataPermissionChecked['imprint'] = $data['imprint'];
        }

        if ($this->permissions->hasPermission('area_organisations_applications_manage')
            && isset($data['registrationStatuses'])
        ) {
            $dataPermissionChecked['registrationStatuses'] = $data['registrationStatuses'];
        }

        if ($this->permissions->hasPermission('feature_orga_branding_edit')
            && isset($data['styling'])
        ) {
            $dataPermissionChecked['styling'] = $data['styling'];
        }

        // if the user may edit all fields, then assume that all non-explicitly checked changes are allowed
        if (in_array(true, [
            $this->permissions->hasPermission('feature_orga_edit_all_fields'),
            $this->permissions->hasPermission('area_manage_orgadata'),
        ], true)) {
            $dataPermissionChecked = $data;
        }

        return $dataPermissionChecked;
    }

    /**
     * If current orga is a planning agency, a valid email address is required.
     * In other cases the incoming email address can be blank or has to be a valid email address.
     *
     * @return int $mandatoryErrors
     *
     * @throws CustomerNotFoundException
     * @throws MessageBagException
     */
    private function validateEmailAddress(Orga $currentOrga, string $emailAddress, int $mandatoryErrors): int
    {
        $currentSubdomain = $this->customerService->getCurrentCustomer()->getSubdomain();
        $validator = Validation::createValidatorBuilder()->enableAnnotationMapping()->getValidator();

        if ($currentOrga->hasType(OrgaType::PUBLIC_AGENCY, $currentSubdomain) && 0 < $validator->validate($emailAddress, [new NotBlank()])->count()) {
            $this->getMessageBag()->add('error', 'error.missing.emailAddress');
            ++$mandatoryErrors;
        }

        if (0 < $validator->validate($emailAddress, [new Email()])->count()) {
            $this->getMessageBag()->add('error', 'error.email.invalid');
            ++$mandatoryErrors;
        }

        return $mandatoryErrors;
    }

    /**
     * @throws MessageBagException
     */
    private function validateSlugName(string $slugName, int $mandatoryErrors): int
    {
        $validator = Validation::createValidatorBuilder()->enableAnnotationMapping()->getValidator();
        if (0 < $validator->validate($slugName, [new Length(['min' => 0, 'max' => 250])])->count()) {
            $this->getMessageBag()->add('error', 'error.length.slugName', ['count' => 250]);
            ++$mandatoryErrors;
        }

        return $mandatoryErrors;
    }

    public function checkMandatoryErrorsNewPasswordFields(array $data, array $mandatoryErrors): array
    {
        $newPasswordFieldMissing = null;

        if (!array_key_exists('password_new', $data) || '' === trim((string) $data['password_new'])) {
            $newPasswordFieldMissing = [
                'type'    => 'error',
                'message' => $this->flashMessageHandler->createFlashMessage(
                    'mandatoryError',
                    [
                        'fieldLabel' => $this->translator->trans('password.new'),
                    ]
                ),
            ];
        }

        if ((!array_key_exists('password_new_2', $data) || '' === trim((string) $data['password_new_2']))
            && !is_array($newPasswordFieldMissing)) {
            $newPasswordFieldMissing = [
                'type'    => 'error',
                'message' => $this->flashMessageHandler->createFlashMessage(
                    'mandatoryError',
                    [
                        'fieldLabel' => $this->translator->trans('password.new'),
                    ]
                ),
            ];
        }

        if (is_array($newPasswordFieldMissing)) {
            $mandatoryErrors[] = $newPasswordFieldMissing;
        }

        return $mandatoryErrors;
    }

    public function checkMandatoryErrorsPasswordEquals(array $data, array $mandatoryErrors): array
    {
        if (0 != strcmp((string) $data['password_new'], (string) $data['password_new_2'])) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->translator->trans('warning.password.repeat.not.equal'),
            ];
        }

        return $mandatoryErrors;
    }

    public function checkMandatoryErrorsPasswordStrength(string $password_new, array $mandatoryErrors = []): array
    {
        // check password strength
        $violations = $this->passwordValidator->validate($password_new);
        if (0 < $violations->count()) {
            /** @var ConstraintViolationInterface $error */
            foreach ($violations as $error) {
                $mandatoryErrors[] = [
                    'type'    => 'error',
                    'message' => $error->getMessage(),
                ];
            }
        }

        return $mandatoryErrors;
    }
}
