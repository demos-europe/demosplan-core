<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command\Data;

use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Command\CoreCommand;
use demosplan\DemosPlanCoreBundle\Entity\EmailAddress;
use demosplan\DemosPlanCoreBundle\Entity\EntityContentChange;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\MailSend;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\NotificationReceiver;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureSubscription;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatementVersion;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragmentVersion;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementVersionField;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementVote;
use demosplan\DemosPlanCoreBundle\Entity\User\Address;
use demosplan\DemosPlanCoreBundle\Entity\User\AddressBookEntry;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Faker\Provider\ApproximateLengthText;
use demosplan\DemosPlanCoreBundle\Logic\Statement\DraftStatementService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

use function strlen;

/**
 * dplan:data:remove-user-data.
 */
class RemoveUserDataCommand extends CoreCommand
{
    // lazy load command
    protected static $defaultName = 'dplan:data:remove-user-data';
    protected static $defaultDescription = 'Deletes sensitive/personal data from DB.';

    /** @var UserService */
    protected $userService;

    /** @var StatementService */
    protected $statementService;

    /** @var DraftStatementService */
    protected $draftStatementService;

    /** @var bool */
    protected $removedUserDataFromUser = false;

    /** @var bool */
    protected $removedUserDataFromDepartment = false;

    /** @var bool */
    protected $removedUserDataFromOrganisation = false;

    /** @var bool */
    protected $removedUserDataFromDraftStatement = false;

    /** @var bool */
    protected $removedUserDataFromAddress = false;

    /** @var bool */
    protected $removedUserDataFromStatementMetas = false;

    /** @var OutputInterface */
    protected $output;

    /** @var Generator */
    protected $faker;

    /** @var ManagerRegistry */
    protected $doctrine;

    /** @var string[] */
    protected $mockTexts = [];

    /** @var string[] */
    protected $mapping = [];

    /** @var int */
    protected $currentGwId = 0;

    /** @var ProgressBar */
    protected $totalProgress;

    /** @var ProgressBar */
    protected $currentProgressBar;

    public function __construct(
        ParameterBagInterface $parameterBag,
        UserService $userService,
        StatementService $statementService,
        DraftStatementService $draftStatementService,
        ManagerRegistry $doctrine,
        ?string $name = null
    ) {
        $this->userService = $userService;
        $this->statementService = $statementService;
        $this->draftStatementService = $draftStatementService;
        $this->doctrine = $doctrine;
        $this->faker = Factory::create('de_DE');
        $this->faker->addProvider(new ApproximateLengthText($this->faker));

        // define static anonymous data:
        $this->map(User::ANONYMOUS_USER_ID, User::ANONYMOUS_USER_ID);
        $this->map(User::ANONYMOUS_USER_DEPARTMENT_ID, User::ANONYMOUS_USER_DEPARTMENT_ID);
        $this->map(User::ANONYMOUS_USER_DEPARTMENT_NAME, User::ANONYMOUS_USER_DEPARTMENT_NAME);
        $this->map(User::ANONYMOUS_USER_LOGIN, User::ANONYMOUS_USER_LOGIN);
        $this->map(User::ANONYMOUS_USER_ORGA_ID, User::ANONYMOUS_USER_ORGA_ID);
        $this->map(User::ANONYMOUS_USER_NAME, User::ANONYMOUS_USER_NAME);
        $this->map(User::ANONYMOUS_USER_ORGA_NAME, User::ANONYMOUS_USER_ORGA_NAME);
        $this->map(Department::DEFAULT_DEPARTMENT_NAME, Department::DEFAULT_DEPARTMENT_NAME);
        $this->map('Gesamtstellungnahme', 'Gesamtstellungnahme');
        $this->map('Fehlanzeige', 'Fehlanzeige');

        $this->currentGwId = $this->faker->numberBetween(1, 99999);
        /** @var ApproximateLengthText $textCloseToLength */
        $textCloseToLength = $this->faker;
        $this->mockTexts[50] = $textCloseToLength->textCloseToLength(50);

        parent::__construct($parameterBag, $name);
    }

    public function configure(): void
    {
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Force deletion without security question');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;
        $this->totalProgress = $this->initializeProgressBar(22, 22);

        $projectName = strtoupper($this->parameterBag->get('project_name'));
        if ('BOBHH' === $projectName || 'BOPHH' === $projectName) {
            $this->output->writeln('This command is not supported for the current project');

            // in case of this command should be workable for HH too.
            // _master_toeb
            // _master_toeb_versions
            return (int) Command::FAILURE;
        }

        // #1: independent::
        $this->removeUserDataFromUsers();
        $this->removeUserDataFromDepartments();
        $this->removeUserDataFromOrganisations();
        $this->removeUserDataFromAddresses();
        $this->removeUserDataFromEmailAddresses();
        $this->removeUserDataFromStatementMetas();
        $this->removeUserDataFromFiles();
        $this->removeUserDataFromMailSend();
        $this->removeUserDataFromStatementVersionFields();
        $this->removeUserDataFromAddressBookEntries();
        $this->removeUserDataFromNotificationReceivers();
        $this->removeUserDataFromStatementFragmentVersions();
        $this->removeUserDataFromStatements();

        // #2: depended on users:
        $this->removeUserDataFromStatementVotes();
        $this->removeUserDataFromReportEntries();
        $this->removeUserDataFromEntityContentChanges();

        // depended on address + users:
        $this->removeUserDataFromProcedureSubscriptions();

        // depended on orgas:
        $this->removeUserDataFromProcedures();

        // depended on departments:
        $this->removeUserDataFromStatementFragments();

        // depended on users + orgas + departments:
        $this->removeUserDataFromDraftStatements();

        // depended on draftstatements:
        $this->removeUserDataFromDraftStatementVersions();

        return (int) Command::SUCCESS;
    }

    protected function removeUserDataFromUsers(): void
    {
        /** @var User[] $allUsers */
        $allUsers = $this->initializeRemovingDataForEntity(User::class);

        foreach ($allUsers as $user) {
            if (User::ANONYMOUS_USER_ID != $user->getId()) {
                $user->setGender(null);
                $user->setTitle($this->faker->title($user->getGender()));
                $user->setFirstname($this->map($user->getFirstname(), $this->faker->firstName));
                $user->setLastname($this->map($user->getLastname(), $this->faker->lastName));

                $email = random_int(1, 99999).$this->faker->lastName.$this->faker->freeEmail;
                $user->setEmail($this->map($email, $email));
                $user->setLogin($email);

                $user->setPassword($this->faker->password(8, 12));
                $user->setLanguage('de_DE');
                if (null !== $user->getGwId()) {
                    $user->setGwId((string) $this->faker->uuid);
                }
                $user->setSalt(null);
                $user->setAlternativeLoginPassword('123456');
            }
            $this->currentProgressBar->advance();
        }

        $this->currentProgressBar->finish();
        $this->updateAll(User::class, $allUsers);
        $this->removedUserDataFromUser = true;
    }

    protected function removeUserDataFromDepartments(): void
    {
        /** @var Department[] $allDepartments */
        $allDepartments = $this->initializeRemovingDataForEntity(Department::class);

        foreach ($allDepartments as $department) {
            if (User::ANONYMOUS_USER_DEPARTMENT_ID != $department->getId()) {
                $department->setName($this->map($department->getName(), $this->faker->colorName));
                $department->setCode($this->map($department->getCode(), $this->faker->numberBetween(100, 9000)));
                $department->setGwId($this->map($department->getGwId(), $this->faker->uuid));
            }
            $this->currentProgressBar->advance();
        }

        $this->currentProgressBar->finish();
        $this->updateAll(Department::class, $allDepartments);
        $this->removedUserDataFromDepartment = true;
    }

    protected function removeUserDataFromOrganisations(): void
    {
        /** @var Orga[] $allOrganisations */
        $allOrganisations = $this->initializeRemovingDataForEntity(Orga::class);

        foreach ($allOrganisations as $organisation) {
            if (User::ANONYMOUS_USER_ORGA_ID != $organisation->getId()) {
                $organisation->setName($this->map($organisation->getName(), $this->faker->company));
                $organisation->setGatewayName($this->map($organisation->getGatewayName(), $organisation->getName()));
                $organisation->setCode($this->map($organisation->getCode(), $organisation->getName()));
                $organisation->setEmail2($this->map($organisation->getEmail2(), $this->faker->companyEmail));
                $organisation->setCcEmail2($this->map($organisation->getCcEmail2(), $this->faker->email));
                $organisation->setGwId(null === $organisation->getGwId() ? null : $this->getNextUniqueGwId());
                $organisation->setCompetence($this->faker->text(99)); // ?
                $organisation->setContactPerson($this->map($organisation->getContactPerson(), $this->faker->name));
                $organisation->setEmailReviewerAdmin($this->map($organisation->getEmailReviewerAdmin(), $this->faker->email));
                $organisation->setLogo(null); // ??
                $organisation->setDataProtection(''); // ?
                $organisation->setImprint(''); // ?
            }
            $this->currentProgressBar->advance();
        }

        $this->currentProgressBar->finish();
        $this->updateAll(Orga::class, $allOrganisations);
        $this->removedUserDataFromOrganisation = true;
    }

    protected function removeUserDataFromProcedures(): void
    {
        $this->checkForAlreadyProcessedOrganisations();

        /** @var Procedure[] $allProcedures */
        $allProcedures = $this->initializeRemovingDataForEntity(Procedure::class);
        foreach ($allProcedures as $procedure) {
            $procedure->setShortUrl($this->map($procedure->getShortUrl(), $this->faker->url));
            $procedure->setOrgaName($procedure->getOrga()->getName());
            $procedure->setPublicParticipationContact(''); // ?
            $procedure->setAgencyMainEmailAddress($this->map($procedure->getAgencyMainEmailAddress(), $this->faker->freeEmail));

            $this->currentProgressBar->advance();
        }

        $this->currentProgressBar->finish();
        $this->updateAll(Procedure::class, $allProcedures);
    }

    protected function removeUserDataFromAddresses(): void
    {
        /** @var Address[] $allAddresses */
        $allAddresses = $this->initializeRemovingDataForEntity(Address::class);
        foreach ($allAddresses as $address) {
            $address->setCode(null); // ?
            $address->setStreet($this->map($address->getStreet(), $this->faker->streetName));
            $address->setStreet1($this->map($address->getStreet1(), $this->faker->streetName));
            $address->setState($this->map($address->getState(), $this->faker->country));
            $address->setPostalcode($this->map($address->getPostalcode(), $this->faker->postcode));
            $address->setCity($this->map($address->getCity(), $this->faker->city));
            $address->setRegion(''); // this->faker->domainWord
            $address->setPostofficebox($this->map($address->getPostofficebox(), $this->faker->numberBetween(1, 999)));
            $address->setPhone($this->map($address->getPhone(), $this->faker->phoneNumber));
            $address->setFax($this->map($address->getFax(), $this->faker->phoneNumber));
            $address->setEmail($this->map($address->getEmail(), $this->faker->email));
            $address->setUrl($this->map($address->getUrl(), $this->faker->url));
            $address->setHouseNumber($this->map($address->getHouseNumber(), $this->faker->buildingNumber));

            $this->currentProgressBar->advance();
        }

        $this->currentProgressBar->finish();
        $this->updateAll(Address::class, $allAddresses);
        $this->removedUserDataFromAddress = true;
    }

    protected function removeUserDataFromDraftStatements(): void
    {
        $this->checkForAlreadyProcessedOrganisations();
        $this->checkForAlreadyProcessedUsers();
        $this->checkForAlreadyProcessedDepartments();

        /** @var DraftStatement[] $allDraftStatements */
        $allDraftStatements = $this->initializeRemovingDataForEntity(DraftStatement::class);

        foreach ($allDraftStatements as $draftStatement) {
            $draftStatement->setNumber($this->map($draftStatement->getNumber(), $this->faker->numberBetween(1000, 99999)));
            $draftStatement->setOName($draftStatement->getOrganisation()->getName());
            $draftStatement->setDName($draftStatement->getDepartment()->getName());
            $draftStatement->setUName($draftStatement->getUser()->getName());
            $draftStatement->setUStreet($draftStatement->getUser()->getStreet());
            $draftStatement->setUPostalCode($draftStatement->getUser()->getPostalcode());
            $draftStatement->setUCity($draftStatement->getUser()->getCity());
            $draftStatement->setUEmail($draftStatement->getUser()->getEmail());
            $draftStatement->setExternId('' !== $draftStatement->getExternId() ? (int) $draftStatement->getNumber() : '');
            $draftStatement->setRepresents('' !== $draftStatement->getRepresents() ? $this->faker->company : '');
            $draftStatement->setHouseNumber('' !== $draftStatement->getHouseNumber() ? $this->faker->buildingNumber : '');
            $draftStatement->setText($this->getTextCloseToLength(mb_strlen($draftStatement->getText())));
            if ('' !== $draftStatement->getMiscDataValue(StatementMeta::USER_ORGANISATION)) {
                $draftStatement->setMiscDataValue(StatementMeta::USER_ORGANISATION, $this->faker->company);
            }
            if ('' !== $draftStatement->getMiscDataValue(StatementMeta::USER_PHONE)) {
                $draftStatement->setMiscDataValue(StatementMeta::USER_PHONE, $this->faker->phoneNumber);
            }

            $this->currentProgressBar->advance();
        }

        $this->currentProgressBar->finish();
        $this->updateAll(DraftStatement::class, $allDraftStatements);
        $this->removedUserDataFromDraftStatement = true;
    }

    protected function removeUserDataFromDraftStatementVersions(): void
    {
        $this->checkForAlreadyProcessedDraftStatements();
        $this->checkForAlreadyProcessedUsers();

        /** @var DraftStatementVersion[] $allDraftStatementVersions */
        $allDraftStatementVersions = $this->initializeRemovingDataForEntity(DraftStatementVersion::class);

        foreach ($allDraftStatementVersions as $version) {
            $version->setNumber($this->map($version->getNumber(), $this->faker->numberBetween(1000, 99999)));
            $version->setOrganisation($version->getDraftStatement()->getOrganisation());
            $version->setOName($this->map($version->getOName(), $version->getOrganisation()->getName()));
            $version->setUName($this->map($version->getUName(), $version->getUser()->getName()));
            $version->setDName($this->map($version->getDName(), $version->getDepartment()->getName()));
            $version->setUPostalCode($this->map($version->getUPostalCode(), $version->getUser()->getPostalcode()));

            $userAddress = $this->faker->streetName;
            if (null !== $version->getUser()->getAddress()) {
                $userAddress = $version->getUser()->getAddress()->getStreet();
            }
            $version->setUStreet($this->map($version->getUStreet(), $userAddress));

            $version->setUCity($this->map($version->getUCity(), $version->getUser()->getCity()));
            $version->setUEmail($this->map($version->getUEmail(), $version->getUser()->getEmail()));
            $version->setText($version->getDraftStatement()->getText());

            $this->currentProgressBar->advance();
        }

        $this->currentProgressBar->finish();
        $this->updateAll(DraftStatementVersion::class, $allDraftStatementVersions);
    }

    protected function removeUserDataFromFiles(): void
    {
        $this->checkForAlreadyProcessedUsers();
        $this->checkForAlreadyProcessedOrganisations();

        /** @var File[] $allFiles */
        $allFiles = $this->initializeRemovingDataForEntity(File::class);
        foreach ($allFiles as $file) {
            $userId = $file->getAuthor();
            $tags = $file->getTags();

            if (null !== $tags
                && '-' !== $tags
                && '' !== $tags
                && is_string($userId)
                && 36 === strlen($userId)) {
                $relatedUser = $this->userService->getSingleUser($userId);

                $tag = $relatedUser->getFirstname().', '.$relatedUser->getLastname().', '.$relatedUser->getOrgaName();
                $file->setTags($tag);
            }

            $this->currentProgressBar->advance();
        }

        $this->currentProgressBar->finish();
        $this->updateAll(File::class, $allFiles);
    }

    protected function removeUserDataFromMailSend(): void
    {
        /** @var MailSend[] $allMailSends */
        $allMailSends = $this->initializeRemovingDataForEntity(MailSend::class);

        foreach ($allMailSends as $mailSend) {
            $mailSend->setTo($this->map($mailSend->getTo(), $this->faker->email));
            $mailSend->setFrom($this->map($mailSend->getFrom(), $this->faker->companyEmail));
            $mailSend->setCc($this->map($mailSend->getCc(), $this->faker->email));
            $mailSend->setBcc($this->map($mailSend->getBcc(), $this->faker->email));
            $mailSend->setContent($this->getTextCloseToLength(mb_strlen($mailSend->getContent())));

            $this->currentProgressBar->advance();
        }

        $this->currentProgressBar->finish();
        $this->updateAll(MailSend::class, $allMailSends);
    }

    protected function removeUserDataFromProcedureSubscriptions(): void
    {
        $this->checkForAlreadyProcessedAddresses();
        $this->checkForAlreadyProcessedUsers();

        /** @var ProcedureSubscription[] $allProcedureSubscriptions */
        $allProcedureSubscriptions = $this->initializeRemovingDataForEntity(ProcedureSubscription::class);

        foreach ($allProcedureSubscriptions as $procedureSubscription) {
            $relatedUser = $procedureSubscription->getUser();

            if (null !== $relatedUser) {
                $procedureSubscription->setPostcode($relatedUser->getPostalcode());
                $procedureSubscription->setEmail($relatedUser->getEmail());
                $procedureSubscription->setCity($relatedUser->getCity());
            } else {
                $procedureSubscription->setPostcode($this->map($procedureSubscription->getPostcode(), $this->faker->postcode));
                $procedureSubscription->setEmail($this->map($procedureSubscription->getUserEmail(), $this->faker->freeEmail));
                $procedureSubscription->setEmail($this->map($procedureSubscription->getCity(), $this->faker->city));
            }

            $procedureSubscription->setDistance($this->faker->randomElement([5, 10, 50]));
            $this->currentProgressBar->advance();
        }

        $this->currentProgressBar->finish();
        $this->updateAll(ProcedureSubscription::class, $allProcedureSubscriptions);
    }

    protected function removeUserDataFromReportEntries(): void
    {
        $this->checkForAlreadyProcessedUsers();

        $reportEntryUsers = [];
        $em = $this->doctrine->getManagerForClass(ReportEntry::class);

        /** @var ReportEntry[] $allReports */
        $allReports = $this->initializeRemovingDataForEntity(ReportEntry::class, true);

        foreach ($allReports as $report) {
            $result = $this->handleReportData($report);
            if (!array_key_exists($report->getUserId(), $reportEntryUsers)) {
                $user = $this->userService->getSingleUser($report->getUserId());
                $reportEntryUsers[$report->getUserId()] = null === $user ? '' : $user->getName();
            }
            $em->getConnection()->executeUpdate(
                'UPDATE _report_entries re SET
                re._u_name = :name,
                re._re_message = :message,
                re._re_incoming = :incoming
                WHERE re._re_id = :id',
                [
                    'name'     => $reportEntryUsers[$report->getUserId()],
                    'message'  => $result['message'],
                    'incoming' => $result['incoming'],
                    'id'       => $report->getId(),
                ]
            );

            $this->currentProgressBar->advance();
        }

        $this->currentProgressBar->finish();
    }

    protected function removeUserDataFromStatementMetas(): void
    {
        $this->checkForAlreadyProcessedUsers();
        $em = $this->doctrine->getManagerForClass(StatementMeta::class);

        /** @var StatementMeta[] $allStatementMetas */
        $allStatementMetas = $this->initializeRemovingDataForEntity(StatementMeta::class, true);

        foreach ($allStatementMetas as $meta) {
            // use dql as it is by far faster than using units of work
            $q = $em->createQuery('update '.StatementMeta::class.' sm set
                sm.authorName = :authorName,
                sm.submitName = :submitName,
                sm.orgaDepartmentName = :orgaDepartmentName,
                sm.orgaName = :orgaName,
                sm.caseWorkerName = :caseWorkerName,
                sm.orgaStreet = :orgaStreet,
                sm.orgaPostalCode = :orgaPostalCode,
                sm.orgaCity = :orgaCity,
                sm.orgaEmail = :orgaEmail,
                sm.houseNumber = :houseNumber,
                sm.miscData = :miscData
                WHERE sm.id = :id
            ')
                ->setParameters([
                    'authorName'         => $this->map($meta->getAuthorName(), $this->faker->name),
                    'submitName'         => $this->map($meta->getSubmitName(), $this->faker->name),
                    'orgaDepartmentName' => $this->map($meta->getOrgaDepartmentName(), $this->faker->colorName),
                    'orgaName'           => $this->map($meta->getOrgaName(), $this->faker->company),
                    'caseWorkerName'     => $this->map($meta->getCaseWorkerName(), $this->faker->name),
                    'orgaStreet'         => $this->map($meta->getOrgaStreet(), $this->faker->streetName),
                    'orgaPostalCode'     => $this->map($meta->getOrgaPostalCode(), $this->faker->postcode),
                    'orgaCity'           => $this->map($meta->getOrgaCity(), $this->faker->city),
                    'orgaEmail'          => $this->map($meta->getOrgaEmail(), $this->faker->companyEmail),
                    'houseNumber'        => $this->map($meta->getHouseNumber(), $this->faker->buildingNumber),
                    'miscData'           => $this->anonymizeStatementMiscData($meta->getMiscData()),
                    'id'                 => $meta->getId(),
                ]);
            $q->execute();
            $this->currentProgressBar->advance();
        }

        $this->currentProgressBar->finish();
        $this->removedUserDataFromStatementMetas = true;
    }

    protected function anonymizeStatementMiscData(?array $miscData): ?string
    {
        if (0 === count((array) $miscData)) {
            return null;
        }

        if (array_key_exists(StatementMeta::USER_ORGANISATION, $miscData)) {
            $miscData[StatementMeta::USER_ORGANISATION] = $this->faker->company;
        }
        if (array_key_exists(StatementMeta::USER_PHONE, $miscData)) {
            $miscData[StatementMeta::USER_PHONE] = $this->faker->phoneNumber;
        }

        return serialize($miscData);
    }

    protected function removeUserDataFromStatementVersionFields(): void
    {
        /** @var StatementVersionField[] $allStatementVersionFields */
        $allStatementVersionFields = $this->initializeRemovingDataForEntity(StatementVersionField::class);

        foreach ($allStatementVersionFields as $versionField) {
            try {
                $parts = explode(', ', $versionField->getUserName(), 3);

                $userName = $this->map($parts[0], $this->faker->name);
                $organisationName = $this->map($parts[1], $this->faker->company);
                $userRole = $this->map($parts[2], 'FachplanerAdmin');
            } catch (Exception) {
                $userName = $this->faker->name;
                $organisationName = $this->faker->company;
                $userRole = 'FachplanerAdmin';
            }

            $versionField->setUserName($userName.', '.$organisationName.', '.$userRole);
            $this->currentProgressBar->advance();
        }

        $this->currentProgressBar->finish();
        $this->updateAll(Statement::class, $allStatementVersionFields);
    }

    protected function removeUserDataFromStatements(): void
    {
        /** @var Statement[] $allStatements */
        $allStatements = $this->initializeRemovingDataForEntity(Statement::class);

        foreach ($allStatements as $statement) {
            if (null !== $statement->getRepresents() && '' !== $statement->getRepresents()) {
                $statement->setRepresents($this->map($statement->getRepresents(), $this->faker->company));
            }
            $this->currentProgressBar->advance();
        }

        $this->currentProgressBar->finish();
        $this->updateAll(Statement::class, $allStatements);
    }

    protected function removeUserDataFromStatementVotes(): void
    {
        $this->checkForAlreadyProcessedUsers();

        $em = $this->doctrine->getManagerForClass(StatementVote::class);
        /** @var StatementVote[] $allStatementVotes */
        $allStatementVotes = $this->initializeRemovingDataForEntity(StatementVote::class, true);

        foreach ($allStatementVotes as $vote) {
            $relatedUser = $vote->getUser();
            // use dql as it is by far faster than using units of work
            if (null !== $relatedUser) {
                $q = $em->createQuery('update '.StatementVote::class.' sv set
                    sv.userName = :userName,
                    sv.firstName = :firstName ,
                    sv.lastName = :lastName,
                    sv.organisationName = :organisationName,
                    sv.departmentName = :departmentName,
                    sv.userMail = :userMail,
                    sv.userCity = :userCity,
                    sv.userPostcode = :userPostcode
                    WHERE sv.id = :id
                ')->setParameters([
                    'userName'         => $relatedUser->getName(),
                    'firstName'        => $relatedUser->getFirstname(),
                    'lastName'         => $relatedUser->getLastname(),
                    'organisationName' => $relatedUser->getOrgaName(),
                    'departmentName'   => $relatedUser->getDepartmentNameLegal(),
                    'userMail'         => $relatedUser->getEmail(),
                    'userCity'         => $relatedUser->getCity(),
                    'userPostcode'     => $relatedUser->getPostalcode(),
                    'id'               => $vote->getId(),
                ]);
            } else {
                $q = $em->createQuery('update '.StatementVote::class.' sv set
                    sv.userName = :userName,
                    sv.firstName = :firstName,
                    sv.lastName = :lastName,
                    sv.organisationName = :organisationName,
                    sv.departmentName = :departmentName,
                    sv.userMail = :userMail,
                    sv.userCity = :userCity,
                    sv.userPostcode = :userPostcode
                    WHERE sv.id = :id
                ')->setParameters([
                    'userName'         => $this->map($vote->getFirstName(), $this->faker->firstName).' '.$this->map($vote->getLastName(), $this->faker->lastName),
                    'firstName'        => $this->map($vote->getFirstName(), $this->faker->firstName),
                    'lastName'         => $this->map($vote->getLastName(), $this->faker->lastName),
                    'organisationName' => $this->map($vote->getOrganisationName(), $this->faker->company),
                    'departmentName'   => $this->map($vote->getDepartmentName(), $this->faker->colorName),
                    'userMail'         => $this->map($vote->getUserMail(), $this->faker->freeEmail),
                    'userCity'         => $this->map($vote->getUserCity(), $this->faker->city),
                    'userPostcode'     => $this->map($vote->getUserPostcode(), $this->faker->postcode),
                    'id'               => $vote->getId(),
                ]);
            }

            $q->execute();

            $this->currentProgressBar->advance();
        }

        $this->currentProgressBar->finish();
    }

    protected function removeUserDataFromAddressBookEntries(): void
    {
        /** @var AddressBookEntry[] $allAddressBookEntries */
        $allAddressBookEntries = $this->initializeRemovingDataForEntity(AddressBookEntry::class);

        foreach ($allAddressBookEntries as $addressBookEntry) {
            $addressBookEntry->setName($this->map($addressBookEntry->getName(), $this->faker->name)); // use just some unrelated faker name + address
            $addressBookEntry->setEmailAddress($this->map($addressBookEntry->getEmailAddress(), $this->faker->freeEmail));

            $this->currentProgressBar->advance();
        }

        $this->currentProgressBar->finish();
        $this->updateAll(AddressBookEntry::class, $allAddressBookEntries);
    }

    protected function removeUserDataFromEmailAddresses(): void
    {
        /** @var EmailAddress[] $allEmailAddresses */
        $allEmailAddresses = $this->initializeRemovingDataForEntity(EmailAddress::class);

        foreach ($allEmailAddresses as $emailAddress) {
            $emailAddress->setFullAddress($this->map($emailAddress->getFullAddress(), $this->faker->freeEmail));

            $this->currentProgressBar->advance();
        }

        $this->currentProgressBar->finish();
        $this->updateAll(EmailAddress::class, $allEmailAddresses);
    }

    protected function removeUserDataFromEntityContentChanges(): void
    {
        $this->checkForAlreadyProcessedUsers();

        /** @var EntityContentChange[] $allEntityContentChanges */
        $allEntityContentChanges = $this->initializeRemovingDataForEntity(EntityContentChange::class);

        foreach ($allEntityContentChanges as $entityContentChange) {
            $nameToUse = $this->map($entityContentChange->getUserName(), $this->faker->name);

            if (null !== $entityContentChange->getUserId()) {
                $userOfChange = $this->userService->getSingleUser($entityContentChange->getUserId());
                if ($userOfChange instanceof User) {
                    $nameToUse = $userOfChange->getUserIdentifier();
                }
            }

            $entityContentChange->setUserName($nameToUse);

            $this->currentProgressBar->advance();
        }

        $this->currentProgressBar->finish();
        $this->updateAll(EntityContentChange::class, $allEntityContentChanges);
    }

    protected function removeUserDataFromNotificationReceivers(): void
    {
        /** @var NotificationReceiver[] $allNotificationReceivers */
        $allNotificationReceivers = $this->initializeRemovingDataForEntity(NotificationReceiver::class);

        foreach ($allNotificationReceivers as $notificationReceiver) {
            $notificationReceiver->setEmail($this->map($notificationReceiver->getEmail(), $this->faker->email));

            $this->currentProgressBar->advance();
        }

        $this->currentProgressBar->finish();
        $this->updateAll(NotificationReceiver::class, $allNotificationReceivers);
    }

    protected function removeUserDataFromStatementFragments(): void
    {
        $this->checkForAlreadyProcessedDepartments();

        /** @var StatementFragment[] $allStatementFragments */
        $allStatementFragments = $this->initializeRemovingDataForEntity(StatementFragment::class);

        foreach ($allStatementFragments as $statementFragment) {
            $statementFragment->setArchivedOrgaName(null);
            $statementFragment->setArchivedDepartmentName(null);

            try {
                if ($statementFragment->getDepartment() instanceof Department) {
                    $statementFragment->setArchivedDepartmentName($statementFragment->getDepartment()->getName());
                    $statementFragment->setArchivedOrgaName($statementFragment->getDepartment()->getOrgaName());
                } else {
                    $statementFragment->setArchivedOrgaName($this->map($statementFragment->getArchivedOrgaName(), $this->faker->company));
                    $statementFragment->setArchivedDepartmentName($this->map($statementFragment->getArchivedDepartmentName(), $this->faker->colorName));
                }
            } catch (Exception) {
                $relatedDepartment = null; // related Department is not existing getDepartment() leads to exception
            }

            $statementFragment->setArchivedVoteUserName(null); // this may be effect the testability
            $this->currentProgressBar->advance();
        }

        $this->currentProgressBar->finish();
        $this->updateAll(StatementFragment::class, $allStatementFragments);
    }

    protected function removeUserDataFromStatementFragmentVersions(): void
    {
        $this->checkForAlreadyProcessedDepartments();
        $this->checkForAlreadyProcessedOrganisations();
        $this->checkForAlreadyProcessedUsers();

        /** @var StatementFragmentVersion[] $allStatementFragmentVersions */
        $allStatementFragmentVersions = $this->initializeRemovingDataForEntity(StatementFragmentVersion::class);

        foreach ($allStatementFragmentVersions as $version) {
            $version->setArchivedDepartmentName($this->map($version->getArchivedDepartmentName(), $this->faker->colorName));
            $version->setArchivedOrgaName($this->map($version->getArchivedOrgaName(), $this->faker->company));
            $version->setArchivedVoteUserName($this->map($version->getArchivedVoteUserName(), $this->faker->name));
            $version->setOrgaName($this->map($version->getOrgaName(), $this->faker->company));
            $version->setDepartmentName($this->map($version->getDepartmentName(), $this->faker->colorName));

            $this->currentProgressBar->advance();
        }
        $this->currentProgressBar->finish();
        $this->updateAll(StatementFragmentVersion::class, $allStatementFragmentVersions);
    }

    protected function checkForAlreadyProcessedUsers(): void
    {
        if (!$this->removedUserDataFromUser) {
            $this->removeUserDataFromUsers();
        }
    }

    protected function checkForAlreadyProcessedOrganisations(): void
    {
        if (!$this->removedUserDataFromOrganisation) {
            $this->removeUserDataFromOrganisations();
        }
    }

    protected function checkForAlreadyProcessedDepartments(): void
    {
        if (!$this->removedUserDataFromDepartment) {
            $this->removeUserDataFromDepartments();
        }
    }

    protected function checkForAlreadyProcessedDraftStatements(): void
    {
        if (!$this->removedUserDataFromDraftStatement) {
            $this->removeUserDataFromDraftStatements();
        }
    }

    protected function checkForAlreadyProcessedAddresses(): void
    {
        if (!$this->removedUserDataFromAddress) {
            $this->removeUserDataFromAddresses();
        }
    }

    /**
     * Contains logic for mapping.
     * Will return null in case of given key is null.
     * Will return empty string in case of given key is empty string.
     * Will create new mapping if given key not existing and return the new created value.
     *
     * @param string|null $keyToGetValue
     * @param string|null $valueIfKeyNotExists
     */
    public function map($keyToGetValue, $valueIfKeyNotExists): ?string
    {
        if (null === $keyToGetValue) {
            return null;
        }

        if ('' === $keyToGetValue) {
            return '';
        }

        if (!array_key_exists($keyToGetValue, $this->mapping)) {
            $this->mapping[$keyToGetValue] = $valueIfKeyNotExists;
        }

        return $this->mapping[$keyToGetValue];
    }

    protected function checkForAlreadyProcessedStatementMetas(): void
    {
        // because of statement meta will be cascading-updated via statement!

        if (!$this->removedUserDataFromStatementMetas) {
            $this->output->writeln('Userdata of StatementMetas are not removed.');
            throw new Exception('Userdata of StatementMetas are not removed.');
        }
    }

    /**
     * @param int $totalNumber
     */
    protected function initializeProgressBar($totalNumber, int $barWidth = 100): ProgressBar
    {
        $progressBar = new ProgressBar($this->output, $totalNumber);
        $progressBar->setBarCharacter('█');
        $progressBar->setEmptyBarCharacter(' ');
        $progressBar->setProgressCharacter('_');
        $progressBar->setRedrawFrequency(0.01);
        $progressBar->setOverwrite(true);
        $progressBar->setBarWidth($barWidth);

        return $progressBar;
    }

    protected function updateAll(string $classString, array $objectsToUpdate): array
    {
        $this->output->writeln("\n updating:");
        $progressBar = $this->initializeProgressBar(count($objectsToUpdate));

        $manager = $this->doctrine->getManagerForClass($classString);
        foreach ($objectsToUpdate as $entity) {
            $manager->persist($entity);
            $progressBar->advance();
        }
        $progressBar->finish();

        $this->output->writeln("\n flushing...");
        $manager->flush();
        $manager->clear(); // Detaches all objects from Doctrine!
        $this->output->write(' > flushed');

        return $objectsToUpdate;
    }

    protected function handleReportData(ReportEntry $report): array
    {
        $incomingEncoded = true;
        $incoming = $report->getIncomingDecoded(false);
        if ([] === $incoming || !is_array($incoming)) {
            $incoming = $report->getIncoming();
            $incomingEncoded = false;
        }

        $messageEncoded = true;
        $message = $report->getMessageDecoded(false);
        if ([] === $message) {
            $message = $report->getMessage();
            $messageEncoded = false;
        }

        // use list of keys to overwrite to map to methodNames of faker:
        $keysToOverwrite = [
            'address_city'                => 'city',
            'address_fax'                 => 'phoneNumber',
            'address_houseNumber'         => 'buildingNumber',
            'address_phone'               => 'phoneNumber',
            'address_postalcode'          => 'postcode',
            'address_street'              => 'streetName',
            'addressBookEntries'          => '-', // ??
            'addresses'                   => '-', // ??
            'agencyExtraEmailAddresses'   => '-', // ??
            'agencyMainEmailAddress'      => 'companyEmail',
            'assignee'                    => 'name',
            'authorizedUsers'             => '-', // ??
            'authorName'                  => 'name',
            'caseWorkerName'              => 'name',
            'ccEmail2'                    => 'email',
            'city'                        => 'city',
            'competence'                  => 'name',
            'contactPerson'               => 'name',
            'county'                      => 'city', // ??
            'currentSlug'                 => 'url',
            'customer'                    => '-', // ?? //company??
            'customers'                   => '-', // ??
            'dataInputOrganisations'      => '-', // ??
            'dataProtection'              => '-', // ??
            'departments'                 => '-', // ??
            'desc'                        => 'sentence',
            'dName'                       => 'colorName',
            'email'                       => 'email',
            'email2'                      => 'companyEmail',
            'emailCc'                     => 'email',
            'emailReviewerAdmin'          => 'email',
            'emailText'                   => 'text',
            'emailTitle'                  => 'text', // ??
            'externalDesc'                => 'sentence',
            'externalName'                => '-', // ??
            'feedback'                    => 'text',
            'gatewayName'                 => '-', // ??
            'houseNumber'                 => 'buildingNumber',
            'informationUrl'              => 'url',
            'legalNotice'                 => '-', // ??
            'locationName'                => 'city',
            'locationPostCode'            => 'postcode',
            'lockReason'                  => 'sentence',
            'mailBody'                    => 'text',
            'mailSubject'                 => 'text',
            'memo'                        => 'text',
            'meta'                        => '-', // ??
            'municipalCode'               => '-', // ??
            'name'                        => 'name',
            'newAuthorizedUsers'          => '-', // comma separated string (names) //??
            'newName'                     => '-', // ??
            'newPublicName'               => '-', // ??
            'notificationReceivers'       => '-', // ??
            'notifications'               => '-', // ??
            'oldAuthorizedUsers'          => '-', // comma separated string (names)//??
            'oldName'                     => '-', // ??
            'oldPublicName'               => '-', // ??
            'oName'                       => 'company',
            'orgaCity'                    => 'city',
            'orgaDepartmentName'          => 'colorName',
            'orgaEmail'                   => 'companyEmail',
            'orgaName'                    => 'company',
            'organisations'               => '-', // ??
            'orgaPostalCode'              => 'postcode',
            'orgaStreet'                  => 'streetName',
            'phone'                       => 'phoneNumber',
            'planDrawText'                => 'text',
            'planningOffices'             => '-', // ??
            'planText'                    => 'text',
            'postalcode'                  => 'postcode',
            'publicParticipationContact'  => 'name',
            'recommendation'              => 'text',
            'recommendationShort'         => 'text',
            'represents'                  => 'company',
            'shortUrl'                    => 'url',
            'slugs'                       => '-', // ??
            'street'                      => 'streetName',
            'subDomain'                   => '-', // ??
            'submit'                      => '-', // ??
            'submitName'                  => 'name',
            'submitterEmailAddress'       => 'email',
            'sumbmit'                     => '-', // ??
            'text'                        => 'text',
            'textShort'                   => 'text',
            'title'                       => 'text',
            'uName'                       => 'name',
            'url'                         => 'url',
            'user'                        => 'name',
            'users'                       => '-', // ??
            'version'                     => '-', // ??
            'versions'                    => '-', // ??
        ];

        $message = $this->anonymizeNestedArray($message, $keysToOverwrite);
        $incoming = $this->anonymizeNestedArray($incoming, $keysToOverwrite);

        $message = $messageEncoded ? Json::encode($message, JSON_UNESCAPED_UNICODE) : $message;
        $incoming = $incomingEncoded ? Json::encode($incoming, JSON_UNESCAPED_UNICODE) : $incoming;

        return [
            'message'  => $message,
            'incoming' => $incoming,
        ];
    }

    /**
     * Returns an anonymized version of incoming messageContentToAnonymize.
     *
     * @param string $messageContentToAnonymize
     */
    protected function anonymize($messageContentToAnonymize, string $type): ?string
    {
        if ('' === $messageContentToAnonymize) {
            return '';
        }

        if ('-' === $messageContentToAnonymize) {
            return '-';
        }

        if ('-' === $type) {
            return 'Anonymisiert';
        }

        if ('text' === $type) {
            $length = strlen($messageContentToAnonymize);

            return $this->getTextCloseToLength($length);
        }

        if (!is_array($messageContentToAnonymize)) {
            return $this->map($messageContentToAnonymize, $this->faker->$type);
        }

        return $this->faker->$type;
    }

    /**
     * Get Faker Texts close to some given length. Use cache to reuse
     * already calculated text lengths.
     */
    protected function getTextCloseToLength(int $length): string
    {
        if ($length > 50) {
            $this->mockTexts[$length] = $this->mockTexts[50];
            do {
                $this->mockTexts[$length] .= ' '.$this->mockTexts[50];
            } while (strlen($this->mockTexts[$length]) < $length);

            return $this->mockTexts[$length];
        }

        $roundedLength = (int) round($length, -2);

        if (!array_key_exists($roundedLength, $this->mockTexts)) {
            /** @var ApproximateLengthText $textCloseToLength */
            $textCloseToLength = $this->faker;
            $this->mockTexts[$roundedLength] = $textCloseToLength->textCloseToLength($length < 10 ? 10 : $length);
        }

        return $this->mockTexts[$roundedLength];
    }

    protected function getNextUniqueGwId(): int
    {
        $this->currentGwId += 44;

        $orgaWithCurrentGwId =
            $this->doctrine->getRepository(Orga::class)
                ->findOneBy(['gwId' => $this->currentGwId]);

        if (null !== $orgaWithCurrentGwId) {
            $this->getNextUniqueGwId();
        }

        return $this->currentGwId;
    }

    /**
     * Contains extracted logic, which is necessary for each entity.
     * Additionally rendering the progressbar of the total process
     * as well as information and progressbar of the current entity-process.
     *
     * @param string $classname        name of the class of entity to initialize removing data
     * @param bool   $includesUpdating flag to determine if updating and executing changes to the database will happen in one step (DQL)
     *
     * @return array All entities of given class
     */
    protected function initializeRemovingDataForEntity(string $classname, bool $includesUpdating = false): array
    {
        $this->output->write("\n\n Total Progress: \n");
        $this->totalProgress->advance();

        $this->output->write("\n".$classname."\n");
        $this->output->write('fetching all...');
        $allEntries = $this->doctrine->getRepository($classname)->findAll();
        if ($includesUpdating) {
            $this->output->writeln(" > fetched \n overwriting and updating:");
        } else {
            $this->output->writeln(" > fetched \n overwriting:");
        }

        $this->currentProgressBar = $this->initializeProgressBar(count($allEntries));

        return $allEntries;
    }

    /**
     * Anonymize certain keys in a nested array using the $keysToOverwrite.
     */
    private function anonymizeNestedArray(array|string $message, array $keysToOverwrite): array|string
    {
        if (is_array($message)) {
            $message = $this->anonymizeArray($keysToOverwrite, $message);

            foreach ($message as $subArray) {
                if (is_array($subArray) && !empty($subArray)) {
                    $message = $this->anonymizeArray($keysToOverwrite, $subArray);
                }
            }
        }

        return $message;
    }

    private function anonymizeArray(array $keysToOverwrite, array $message): array
    {
        foreach ($keysToOverwrite as $key => $value) {
            if (array_key_exists($key, $message)) {
                $message[$key] = $this->anonymize($message[$key], $value);
            }
        }

        return $message;
    }
}
