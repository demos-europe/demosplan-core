<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\AssessmentTable;

use BadMethodCallException;
use Carbon\Carbon;
use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Controller\AssessmentTable\DemosPlanAssessmentTableController;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementVote;
use demosplan\DemosPlanCoreBundle\Entity\StatementAttachment;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Logic\StatementAttachmentService;
use demosplan\DemosPlanCoreBundle\Permissions\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Traits\DI\RefreshElasticsearchIndexTrait;
use demosplan\DemosPlanCoreBundle\ValueObject\BulkDeleteResult;
use demosplan\DemosPlanProcedureBundle\Logic\CurrentProcedureService;
use demosplan\DemosPlanProcedureBundle\Logic\PrepareReportFromProcedureService;
use demosplan\DemosPlanStatementBundle\Exception\CopyException;
use demosplan\DemosPlanStatementBundle\Exception\InvalidDataException;
use demosplan\DemosPlanStatementBundle\Exception\StatementElementNotFoundException;
use demosplan\DemosPlanStatementBundle\Exception\StatementNameTooLongException;
use demosplan\DemosPlanStatementBundle\Logic\StatementHandler;
use demosplan\DemosPlanStatementBundle\Logic\StatementService;
use demosplan\DemosPlanUserBundle\Logic\CurrentUserService;
use demosplan\DemosPlanUserBundle\Logic\UserService;
use Exception;
use FOS\ElasticaBundle\Index\IndexManager;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Container;

class AssessmentTableServiceStorage
{
    use RefreshElasticsearchIndexTrait;

    /** @var Container Container */
    protected $container;

    /**
     * @var StatementService
     */
    protected $statementService;

    /**
     * @var StatementHandler
     */
    protected $statementHandler;

    /**
     * @var MailService
     */
    protected $mailService;

    /**
     * @var GlobalConfigInterface
     */
    protected $config;

    /**
     * @var UserService
     */
    protected $userService;

    /**
     * @var FileService
     */
    protected $fileService;

    /**
     * @var MessageBagInterface
     */
    protected $messageBag;

    /**
     * @var ElementsService
     */
    private $elementsService;

    /**
     * @var PermissionsInterface
     */
    private $permissions;
    /**
     * @var CurrentProcedureService
     */
    private $currentProcedureService;

    /**
     * @var PrepareReportFromProcedureService
     */
    private $prepareReportFromProcedureService;
    /**
     * @var StatementAttachmentService
     */
    private $statementAttachmentService;

    /**
     * @var CurrentUserService
     */
    private $currentUser;

    public function __construct(
        CurrentProcedureService $currentProcedureService,
        CurrentUserService $currentUser,
        ElementsService $elementsService,
        GlobalConfigInterface $config,
        IndexManager $indexManager,
        MailService $mailService,
        MessageBagInterface $messageBag,
        PermissionsInterface $permissions,
        PrepareReportFromProcedureService $prepareReportFromProcedureService,
        StatementAttachmentService $statementAttachmentService,
        StatementHandler $statementHandler,
        StatementService $statementService,
        FileService $fileService,
        UserService $userService
    ) {
        $this->config = $config;
        $this->currentProcedureService = $currentProcedureService;
        $this->elementsService = $elementsService;
        $this->mailService = $mailService;
        $this->messageBag = $messageBag;
        $this->permissions = $permissions;
        $this->prepareReportFromProcedureService = $prepareReportFromProcedureService;
        $this->statementHandler = $statementHandler;
        $this->statementService = $statementService;
        $this->userService = $userService;
        $this->statementAttachmentService = $statementAttachmentService;
        $this->fileService = $fileService;
        $this->setElasticsearchIndexManager($indexManager);
        $this->currentUser = $currentUser;
    }

    protected function getMessageBag(): MessageBagInterface
    {
        return $this->messageBag;
    }

    // @improve T15851

    /**
     * @param array $statementArray
     * @param array $rParams
     * @param array $options
     *
     * @return array
     */
    protected function updateFieldInStatementArray($statementArray, $rParams, array $fieldNames, $options = [])
    {
        $rParamsKey = $fieldNames[0];
        $statementArrayKey = $fieldNames[1] ?? $fieldNames[0];

        if (array_key_exists($rParamsKey, $rParams['request'])) {
            // set statementArray key value pair
            $statementArray[$statementArrayKey] = $rParams['request'][$rParamsKey];
        } elseif (isset($options['empty'])) {
            // set empty values (overwrite files in database) if no value is set
            $statementArray[$statementArrayKey] = ('array' === $options['empty']) ? [] : '';
        }

        return $statementArray;
    }

    /**
     * Stellungnahme Editieren.
     *
     * @param array $rParams
     *
     * @throws StatementNameTooLongException
     * @throws MessageBagException
     * @throws StatementElementNotFoundException
     */
    private function updateStatement($rParams): void
    {
        $statementArray = [];
        $statementService = $this->getStatementService();

        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['attachment_public_allowed', 'attachmentPublicAllowed']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['author_name']);
        if (array_key_exists('case_worker', $rParams)) {
            $statementArray['case_worker'] = $rParams['case_worker'];
        }
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['counties'], ['empty' => 'array']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['departmentName']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['ident']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['memo'], ['empty' => 'string']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['municipalities'], ['empty' => 'array']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['orga_city']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['orga_email']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['orga_postalcode']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['orga_street']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['paragraph', 'reason_paragraph']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['phase']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['phone']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['planningDocument', 'planning_document']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['priority'], ['empty' => 'string']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['priorityAreas'], ['empty' => 'array']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['publicVerified']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['recommendation'], ['empty' => 'string']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['publicRejectionEmail'], ['empty' => 'string']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['representationCheck']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['status']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['submit_name']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['submit_type']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['submitterEmailAddress']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['submitterType']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['tags'], ['empty' => 'array']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['text']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['votePla']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['voteStk']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['voters', 'votes'], ['empty' => 'array']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['houseNumber']);
        $statementArray = $this->updateFieldInStatementArray($statementArray, $rParams, ['userOrganisation']);

        $currentStatement = $statementService->getStatement($statementArray['ident']);

        $statementArray['replied'] = false;
        if (array_key_exists('replied', $rParams['request']) && '1' === $rParams['request']['replied']) {
            $statementArray['replied'] = true;
        }

        if (array_key_exists('file_delete', $rParams['request'])) {
            $statementArray['fileupload'] = '';
        }
        $procedureId = $this->currentProcedureService->getProcedureWithCertainty()->getId();
        $this->statementHandler->checkProcedurePublicationSetting($rParams['request'], $procedureId);

        // update multiple files
        if (array_key_exists('fileupload', $rParams)) {
            $statementArray['files'] = $rParams['fileupload'];
        }
        // create StatementAttachment for original statement
        if (array_key_exists('fileupload_'.StatementAttachment::SOURCE_STATEMENT, $rParams)) {
            // get file from FileString
            $fileId = $this->fileService->getFileIdFromUploadFile($rParams['fileupload_'.StatementAttachment::SOURCE_STATEMENT]);
            $file = $this->fileService->get($fileId);
            if ($file instanceof File && $currentStatement instanceof Statement) {
                // delete eventually existing original statement
                $this->statementAttachmentService->deleteOriginalAttachment($currentStatement);
                // create StatementAttachment to be added later on
                $statementArray['files_'.StatementAttachment::SOURCE_STATEMENT] = $this->statementAttachmentService->createOriginalAttachment(
                    $currentStatement,
                    $file
                );
            }
        }

        // Lösche ggf. Zuweisungen
        if (array_key_exists('delete_element', $rParams['request'])) {
            $element = $this->elementsService->getStatementElement($procedureId);
            $statementArray['elementId'] = $element->getId();
            $statementArray['documentId'] = '';
            $statementArray['paragraphId'] = '';
        }
        if (array_key_exists('delete_document', $rParams['request'])) {
            $statementArray['documentId'] = '';
        }
        if (array_key_exists('delete_paragraph', $rParams['request'])) {
            $statementArray['paragraphId'] = '';
        }

        if (array_key_exists('element_new', $rParams['request']) && 0 < strlen($rParams['request']['element_new'])) {
            $statementArray['elementId'] = $rParams['request']['element_new'];

            $statementArray = $this->updateFieldInStatementArray(
                $statementArray,
                $rParams,
                ['paragraph_'.$statementArray['elementId'].'_new', 'paragraphId'],
                ['empty' => 'string']
            );
            $statementArray = $this->updateFieldInStatementArray(
                $statementArray,
                $rParams,
                ['document_'.$statementArray['elementId'].'_new', 'documentId'],
                ['empty' => 'string']
            );
        }

        if (array_key_exists('delete_file', $rParams['request'])) {
            foreach ($rParams['request']['delete_file'] as $fileId) {
                $this->fileService->deleteFileContainer($fileId, $statementArray['ident']);
            }
        }

        if (array_key_exists('delete_file_'.StatementAttachment::SOURCE_STATEMENT, $rParams['request'])) {
            foreach ($rParams['request']['delete_file_'.StatementAttachment::SOURCE_STATEMENT] as $fileId) {
                $this->statementService->deleteOriginalStatementAttachmentByStatementId($statementArray['ident']);
            }
        }

        if (array_key_exists('authored_date', $rParams['request'])) {
            if ('' === $rParams['request']['authored_date']) {
                $this->getMessageBag()->add('warning', 'warning.date.authored');
            } elseif (!$this->isValidDateString($rParams['request']['authored_date'])) {
                $this->getMessageBag()->add('error', 'error.date.invalid');
            }
            $statementArray['authoredDate'] = $rParams['request']['authored_date'];
        }

        if (array_key_exists('submitted_date', $rParams['request'])) {
            if ('' === $rParams['request']['submitted_date']) {
                $this->getMessageBag()->add('warning', 'warning.date.submitted');
            } elseif (!$this->isValidDateString($rParams['request']['submitted_date'])) {
                $this->getMessageBag()->add('error', 'error.date.invalid');
            }

            // On UPDATE: Ensure hour, minute and second will stay untouched, to avoid changing of order by submitDate.
            $currentlySavedDate = Carbon::instance($currentStatement->getSubmitObject());
            $incomingDate = Carbon::createFromFormat('d.m.Y', $rParams['request']['submitted_date']);
            $incomingDate->setTime($currentlySavedDate->hour, $currentlySavedDate->minute, $currentlySavedDate->second);
            $statementArray['submittedDate'] = $incomingDate->rawFormat('d.m.Y H:i:s');
        }

        // We always get this value except it's empty string
        if (array_key_exists('voters_anonym', $rParams['request'])) {
            if (!is_numeric($rParams['request']['voters_anonym'])) {
                $this->getMessageBag()->add('error', 'error.number.invalid');

                return;
            }
            $statementArray['numberOfAnonymVotes'] = abs(intval($rParams['request']['voters_anonym']));
        } else {
            $statementArray['numberOfAnonymVotes'] = 0;
        }

        if (array_key_exists('clusterName', $rParams['request'])) {
            $statementArray['name'] = $rParams['request']['clusterName'];
            $maxLength = 200;
            $actualLength = strlen($statementArray['name']);
            if ($maxLength < $actualLength) {
                throw StatementNameTooLongException::create($actualLength, $maxLength);
            }
        }

        $oldPublicationStatus = $currentStatement->getPublicVerified();

        $ignoreCluster = false;
        if (array_key_exists('head_statement', $rParams['request'])) {
            $statementArray['headStatementId'] = $rParams['request']['head_statement'];

            // T12692:
            // Update headStatement to force creating entityContentChange of headStatement
            // Also set $ignoreCluster, to enable update of $statementArray by disable check for clustermember in this case.
            $headStatement = $statementService->getStatement($statementArray['headStatementId']);
            $headStatement->addStatement($currentStatement);
            $updatedHeadStatement = $statementService->updateStatementFromObject($headStatement);
            if ($updatedHeadStatement instanceof Statement) {
                $ignoreCluster = true;
            }
        }

        try {
            $statementArray = $this->validateStatementData($statementArray);
            $updatedStatement = $statementService->updateStatement($statementArray, false, $ignoreCluster);
        } catch (Exception $e) {
            $this->getMessageBag()->add('error', 'error.statement.update');

            return;
        }

        // display confirmation message only if statementArray has been saved successfully
        if ($updatedStatement instanceof Statement) {
            $this->detectPublicationChangeAndSendEmail(
                $oldPublicationStatus,
                $statementArray['publicVerified'] ?? $oldPublicationStatus,
                $currentStatement,
                $statementArray['publicRejectionEmail'] ?? ''
            );
            $this->getMessageBag()->add(
                'confirm',
                'confirm.statement.updated',
                ['externId' => $updatedStatement->getExternId()]
            );
        }
    }

    /**
     * Check whether date string is valid.
     *
     * @param string $string
     *
     * @return bool
     */
    protected function isValidDateString($string)
    {
        $submit = new DateTime();
        $date = $submit->createFromFormat('d.m.Y', $string);
        if ($date instanceof DateTime) {
            return true;
        }

        return false;
    }

    /**
     * T16244 T16250 If the publication status chances, send email.
     * Only send email in regular situations: pending and a decision is made, to prevent accidential emails.
     *
     * @throws Exception
     */
    protected function detectPublicationChangeAndSendEmail(string $oldStatus, string $newStatus, Statement $statement, string $reasonForRejection)
    {
        // validate that regular update situation is happening
        if ($oldStatus === $newStatus // no change
            || 'publication_pending' !== $oldStatus // only regular situation if decision is pending
            || !in_array($newStatus, ['publication_approved', 'publication_rejected']) // only regular situation if
                                                                                       // decision is made
            || $statement->isManual() // never send in case of manual statements
            || in_array($statement->getSubmitterEmailAddress(), ['', null], true) // only if email address exists
        ) {
            return;
        }

        if ($this->permissions->hasPermission(
            'feature_statements_publication_request_approval_or_rejection_notification_email'
        )) {
            $vars = $this->statementService->getStatementPublicationNotificationEmailVariables($statement);

            if ('publication_approved' === $newStatus) { // confirmation
                $template = 'statement_publication_approved_template';
            } else { // reject (will always be the case)
                $vars['reasonForRejection'] = $reasonForRejection;
                $template = 'statement_publication_rejected_template';
            }

            $this->mailService->sendMail(
                $template,
                'de_DE',
                $statement->getUser()->getEmail(),
                $vars['orgaEmail'],
                '',
                '',
                'extern',
                $vars
            );
        }
    }

    /**
     * check whether user tries to delete metadata from statement that is assigned to its fragments.
     *
     * @return mixed
     *
     * @throws MessageBagException
     */
    protected function validateStatementData(array $statementToUpdate)
    {
        $statementId = $statementToUpdate['ident'];
        /** @var Statement $currentStatement */
        $currentStatement = $this->getStatementService()->getStatement($statementId);

        if ($this->getStatementHandler()->isVoteStkReadOnly(
            $currentStatement,
            $this->currentUser->getUser()->getId()
        )) {
            $statementToUpdate['voteStk'] = $currentStatement->getVoteStk();
        }

        // is not manual statement && not public allowed
        if (false === $currentStatement->isManual() && false === $currentStatement->getPublicAllowed()) {
            // publish non manunal statement
            if (array_key_exists('publicVerified', $statementToUpdate)
                && Statement::PUBLICATION_APPROVED === $statementToUpdate['publicVerified']
            ) {
                $statementToUpdate['publicVerified'] = $currentStatement->getPublicVerified();
                $warning = 'warning.statement.not.public_allowed.or.manual.invitable_institution';
                if ($currentStatement->isSubmittedByCitizen()) {
                    $warning = 'warning.statement.not.public_allowed.or.manual.privat';
                }
                $this->getMessageBag()->add('warning', $warning);
            }
            // no votes should be added/deleted to a non manual statement that is not allowed to be published
            if (array_key_exists('votes', $statementToUpdate)) {
                if (!empty($statementToUpdate['votes'])) {
                    $this->getMessageBag()->add('warning', 'warning.statement.addVote.not.allowed');
                }
                unset($statementToUpdate['votes']);
            }
        }

        // validate data with data on fragments
        if (0 === $currentStatement->getFragments()->count()) {
            return $statementToUpdate;
        }

        $validationArray = [
            'tags'           => ['tags',           'getTagIds',          'warning.delete.statement.metadata.inconsistent.fragments.tag'],
            'counties'       => ['counties',       'getCountyIds',       'warning.delete.statement.metadata.inconsistent.fragments.county'],
            'municipalities' => ['municipalities', 'getMunicipalityIds', 'warning.delete.statement.metadata.inconsistent.fragments.municipality'],
            'priorityAreas'  => ['priorityAreas',  'getPriorityAreaIds', 'warning.delete.statement.metadata.inconsistent.fragments.priorityArea'],
        ];

        if ($this->permissions->hasPermission('feature_optional_tag_propagation')) {
            unset($validationArray['tags']);
        }

        foreach ($validationArray as $validationItem) {
            $statementToUpdate = $this->validateEntityInStatementUpdateData(
                $statementToUpdate,
                $currentStatement,
                $validationItem
            );
        }

        return $statementToUpdate;
    }

    // @improve T14469

    /**
     * @return mixed
     *
     * @throws MessageBagException
     */
    protected function validateEntityInStatementUpdateData(array $statementToUpdate, Statement $currentStatement, array $entityArray)
    {
        // get new ids
        $entityIdsWithWhichToUpdateTheStatement = array_key_exists($entityArray[0], $statementToUpdate) ? collect(
            $statementToUpdate[$entityArray[0]]
        ) : collect([]);

        // get old ids from fragments
        $fragmentCountyIds = collect($currentStatement->getFragments())
            ->transform(
                static function (StatementFragment $statementFragment) use ($entityArray) {
                    if (!method_exists($statementFragment, $entityArray[1])) {
                        throw new BadMethodCallException(sprintf('The called method %s does not exist in the class "statementFragment".', $entityArray[1]));
                    }

                    return $statementFragment->{$entityArray[1]}();
                }
            )
            ->filter(
                static function ($item) {
                    return 0 < count($item);
                }
            )
            ->flatten()
            ->unique();

        // If there are any entities in fragments, that are not at the statement, the use original entities
        if (0 !== $fragmentCountyIds->diff($entityIdsWithWhichToUpdateTheStatement)->count()) {
            $statementToUpdate[$entityArray[0]] = $fragmentCountyIds->toArray();
            $this->getMessageBag()->add('warning', $entityArray[2]);
        }

        return $statementToUpdate;
    }

    /**
     * @param string|array         $to
     * @param string|array         $from
     * @param string|array         $emailcc
     * @param array                $vars
     * @param array<string,string> $attachments
     *
     * @throws Exception
     */
    protected function sendDmSchlussmitteilung($to, $from, $emailcc, $vars, array $attachments): void
    {
        $this->mailService->sendMail(
            'dm_schlussmitteilung',
            'de_DE',
            $to,
            $from,
            $emailcc,
            '',
            'extern',
            $vars,
            $attachments
        );
    }

    /**
     * @return array<string,string> An array consisting of two keys: `name` and `content`. The
     *                              former contains the name of the file. The latter contains the
     *                              file content loaded from the file system. This format is needed
     *                              by {@link MailService::sendMail}.
     */
    protected function createSendableAttachment(string $fileString): array
    {
        $file = $this->fileService->getFileFromFileString($fileString);
        if (null === $file) {
            throw new \demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException("File not found for ID: $fileString");
        }

        return [
            'name'    => $file->getFilename(),
            'content' => $this->fileService->getContent($file),
        ];
    }

    /**
     * Mails zu Stellungnahmen versenden
     * Es gibt unterschiedliche Rückmeldevarianten bei den Bürgern und Toeb
     * bei Toeb erhält der Einreicher und die Organsiation emails sowie der Toeb-Koordinator in cc
     * bei den Bürgern bekommt nur der Einreicher eine Email, wenn er das als feedback gewünscht hat.
     * Unabhängig davon bekommen aber die Mitzeicher (voters) der SN automatisch eine Email
     * die Messages müssen dementsprechend angepasst werden.
     *
     * @param array $rParams
     *
     * @throws Exception
     */
    protected function sendStatementMail($rParams)
    {
        try {
            $error = false;
            $vars = [];
            $ident = '';
            $emailcc = [];
            $successMessageTranslationParams = [];

            if (array_key_exists('send_body', $rParams['request'])) {
                $vars['mailbody'] = $rParams['request']['send_body'];
            }

            if (array_key_exists('send_title', $rParams['request'])) {
                $vars['mailsubject'] = $rParams['request']['send_title'];
            }

            if (array_key_exists('ident', $rParams['request'])) {
                $ident = $rParams['request']['ident'];
            }

            if (array_key_exists('emailCC', $rParams)) {
                $emailcc[] = $rParams['emailCC'];
            }

            // Überprüfe, ob E-Mails im CC-Feld eingetragen wurden
            $syntaxEmailErrors = [];
            if (array_key_exists('send_emailCC', $rParams['request']) && 0 !== strlen($rParams['request']['send_emailCC'])) {
                // zerlege den string in die einzelnen E-Mail-Adressen
                $mailsCC = preg_split('/[ ]*;[ ]*|[ ]*,[ ]*/', $rParams['request']['send_emailCC']);
                // überprüfe jede dieser mails
                foreach ($mailsCC as $mail) {
                    // lösche alle Freizeichen am Anfang und Ende
                    $mailForCc = trim($mail);
                    // Überprüfe, ob die E-Mail-Adresse korrekt ist
                    if (filter_var($mailForCc, FILTER_VALIDATE_EMAIL)) {
                        // wenn ja, gebe sie weiter
                        $emailcc[] = $mailForCc;
                    } else {
                        // wennn nicht, gebe eine Fehlermeldung aus
                        $syntaxEmailErrors[] = $mailForCc;
                    }
                }
            }
            // wenn E-Mail-Adressen falsch sind, generiere eine Fehlermeldung
            if (0 < count($syntaxEmailErrors)) {
                throw new InvalidDataException('Invalid Emails provided in CC field.');
            }

            $statement = $this->statementService->getStatement($ident);

            $procedure = $this->currentProcedureService->getProcedureWithCertainty();

            $from = $procedure->getAgencyMainEmailAddress();

            if (null !== $statement) {
                $attachments = array_map([$this, 'createSendableAttachment'], $rParams['emailAttachments'] ?? []);
                $attachmentNames = array_column($attachments, 'name');
                // Bürger Stellungnahmen
                if (Statement::EXTERNAL === $statement->getPublicStatement()) {
                    if ('email' === $statement->getFeedback()) {
                        $successMessageTranslationParams['sent_to'] = 'citizen_only';
                        $this->sendDmSchlussmitteilung(
                            $statement->getMeta()->getOrgaEmail(),
                            $from,
                            $emailcc,
                            $vars,
                            $attachments
                        );
                        // wenn die Mail einmal im CC verschickt wird, muss sie es später nicht mehr
                        $emailcc = [''];
                        // speicher ab, wann die Schlussmitteilung verschickt wurde
                        $this->statementService->setSentAssessment($statement->getId());
                        $this->prepareReportFromProcedureService->addReportFinalMail(
                            $statement,
                            $rParams['request']['send_title'] ?? '',
                            $attachmentNames
                        );
                    }
                // manuell eingegebene Stellungnahme
                } elseif ('' != $statement->getMeta()->getOrgaEmail()) {
                    $successMessageTranslationParams['sent_to'] = 'institution_only';
                    $this->sendDmSchlussmitteilung(
                        $statement->getMeta()->getOrgaEmail(),
                        $from,
                        $emailcc,
                        $vars,
                        $attachments
                    );
                    // wenn die Mail einmal im CC verschickt wird, muss sie es später nicht mehr
                    $emailcc = [''];
                    // speicher ab, wann die Schlussmitteilung verschickt
                    $this->statementService->setSentAssessment($statement->getId());
                    $this->prepareReportFromProcedureService->addReportFinalMail(
                        $statement,
                        $rParams['request']['send_title'] ?? '',
                        $attachmentNames
                    );
                } else {
                    // regulär eingereichte Stellungnahme (ToeB)
                    if ('' === $statement->getUId()) {
                        throw new InvalidArgumentException('UserId must be set');
                    }

                    /** @var User $user */
                    $user = $this->userService->getSingleUser($statement->getUId());

                    // Mail an Beteiligungs-E-Mail-Adresse
                    // Die Rollen brauchen keine Mail an ihre Organisation
                    if (!$user->hasAnyOfRoles([Role::GUEST, Role::CITIZEN])) {
                        $successMessageTranslationParams['sent_to'] = 'institution_only';
                        $recipients = [];
                        if (0 < strlen($user->getOrga()->getEmail2())) {
                            $recipients[] = $user->getOrga()->getEmail2();
                        }
                        // Gibt es auch noch eingetragenede BeteiligungsEmail in CC
                        if (null !== $user->getOrga()->getCcEmail2()) {
                            $ccUsersEmail = preg_split('/[ ]*;[ ]*|[ ]*,[ ]*/', $user->getOrga()->getCcEmail2());
                            $recipients = array_merge($recipients, $ccUsersEmail);
                        }
                        $this->sendDmSchlussmitteilung(
                            $recipients,
                            $from,
                            $emailcc,
                            $vars,
                            $attachments
                        );
                        // speicher ab, wann die Schlussmitteilung verschickt wurde
                        $this->statementService->setSentAssessment($statement->getId());
                        foreach ($recipients as $email) {
                            $this->prepareReportFromProcedureService->addReportFinalMail(
                                $statement,
                                $rParams['request']['send_title'] ?? '',
                                $attachmentNames
                            );
                        }
                    }
                    // Mail an die einreichende Institutions-K, falls nicht identisch mit Einreicher*in
                    if (null !== $statement->getMeta()->getSubmitUId()) {
                        $submitUser = $this->userService->getSingleUser($statement->getMeta()->getSubmitUId());
                        $submitUserEmail = $submitUser->getEmail();
                        if (false === stripos($user->getEmail(), $submitUserEmail)) {
                            $successMessageTranslationParams['sent_to'] = 'institution_and_coordination';
                            $this->sendDmSchlussmitteilung(
                                $submitUserEmail,
                                $from,
                                '',
                                $vars,
                                $attachments
                            );
                            // speicher ab, wann die Schlussmitteilung verschickt wurde
                            $this->statementService->setSentAssessment($statement->getId());
                            $this->prepareReportFromProcedureService->addReportFinalMail(
                                $statement,
                                $rParams['request']['send_title'] ?? '',
                                $attachmentNames
                            );
                        }
                    }
                }
                if (!$statement->getVotes()->isEmpty()) {
                    /** @var StatementVote $vote */
                    foreach ($statement->getVotes() as $vote) {
                        $voteEmailAddress = $vote->getUserMail();
                        if (null !== $voteEmailAddress) {
                            $this->sendDmSchlussmitteilung(
                                $voteEmailAddress,
                                $from,
                                $emailcc,
                                $vars,
                                $attachments
                            );
                            // wenn die Mail einmal im CC verschickt wird, muss sie es später nicht mehr
                            $emailcc = '';
                            // speicher ab, wann die Schlussmitteilung verschickt wurde
                            $this->statementService->setSentAssessment($statement->getId());
                            $this->prepareReportFromProcedureService->addReportFinalMail(
                                $statement,
                                $rParams['request']['send_title'] ?? '',
                                $attachmentNames
                            );
                        }
                    }

                    $successMessageTranslationParams['voters_count'] = count($statement->getVotes());
                    if (Statement::EXTERNAL === $statement->getPublicStatement() && 'email' === $statement->getFeedback()) {
                        $successMessageTranslationParams['sent_to'] = 'citizen_and_voters';
                    } else {
                        $successMessageTranslationParams['sent_to'] = 'voters_only';
                    }
                }
            } else {
                $error = true;
            }
        } catch (InvalidArgumentException $e) {
            $this->getMessageBag()->add('error', 'error.statement.final.send.noemail');

            return;
        }

        if (true === $error) {
            $this->getMessageBag()->add('error', 'error.statement.final.send');

            return;
        }

        $this->getMessageBag()->add('confirm', 'confirm.statement.final.sent', $successMessageTranslationParams);
        $this->getMessageBag()->add('confirm', 'confirm.statement.final.sent.emailCC');
    }

    /**
     * Stellungnahme kopieren.
     */
    private function copyStatements(array $items)
    {
        $error = false;
        $successful = 0;
        $unsuccessful = 0;

        if (0 === count($items)) {
            $this->getMessageBag()->add('warning', 'warning.entries.no.selected');

            return;
        }

        try {
            foreach ($items as $item) {
                $updatedStatement = $this->statementService->copyStatementWithinProcedure($item);
                if ($updatedStatement instanceof Statement) {
                    ++$successful;
                } else {
                    ++$unsuccessful;
                }
            }
        } catch (CopyException $e) {
            // do nothing, as Message is already set
        } catch (Exception $e) {
            $error = true;
        }

        // Elasticsearch wartet 1 Sekunde mit dem Ausführen. Warten, damit die Ergebnisse korrekt dargestellt werden
        $this->refreshElasticsearchIndexes();

        if ($unsuccessful > 0) {
            $this->getMessageBag()->addChoice('error', 'error.statement.copied', ['count' => $unsuccessful]);
        }

        if ($successful > 0) {
            $this->getMessageBag()->addChoice('confirm', 'confirm.statement.copied', ['count' => $successful]);
        }

        if ($error) {
            $this->getMessageBag()->add('error', 'error.copy');

            return;
        }
    }

    /**
     * Stellungnahmen löschen.
     *
     * @param array $items
     *
     * @throws MessageBagException
     */
    private function deleteStatements($items)
    {
        if (0 === count($items)) {
            $this->getMessageBag()->add('warning', 'warning.entries.no.selected');

            return;
        }

        $deleteResults = $this->deleteStatementsCheck($items);

        // Elasticsearch wartet 1 Sekunde mit dem Ausführen. Warten, damit die Ergebnisse korrekt dargestellt werden
        $this->refreshElasticsearchIndexes();

        if ($deleteResults->getCountUnsuccessful() > 0) {
            $this->getMessageBag()->addChoice('error', 'error.statement.delete', ['count' => $deleteResults->getCountUnsuccessful()]);
        }

        if ($deleteResults->getCountSuccessful() > 0) {
            $this->getMessageBag()->addChoice('confirm', 'confirm.statement.delete', ['count' => $deleteResults->getCountSuccessful()]);
        }
        if ($deleteResults->getCountNotFound() > 0) {
            $this->getMessageBag()->addChoice('warning', 'warning.statement.delete', ['count' => $deleteResults->getCountNotFound()]);
        }

        if ($deleteResults->getIsErroneous()) {
            $this->getMessageBag()->add('error', 'error.delete');
        }
    }

    /**
     * @param array<int, string> $items
     */
    private function deleteStatementsCheck(array $items): BulkDeleteResult
    {
        $error = false;
        $successful = 0;
        $unsuccessful = 0;
        $notfound = 0;

        try {
            $statementHandler = $this->statementHandler;
            foreach ($items as $item) {
                $statement = $statementHandler->getStatement($item);
                if (!($statement instanceof Statement)) {
                    // statement with ID of $item not found
                    ++$notfound;
                    continue;
                }

                if ($statement->isClusterStatement()) {
                    $statementHandler->resolveCluster($statement);
                } else {
                    $success = $this->statementService->deleteStatement($item);
                    if ($success) {
                        ++$successful;
                    } else {
                        ++$unsuccessful;
                    }
                }
            }
        } catch (Exception $e) {
            $error = true;
        }
        $result = new BulkDeleteResult($successful, $unsuccessful, $notfound, $error);

        return $result->lock();
    }

    /**
     * This is only called from {@link AssessmentTableServiceOutput::getStatementListHandler()} and only in case
     * that it originates from {@link DemosPlanAssessmentTableController::viewTableAction()}
     * or {@link DemosPlanAssessmentTableController::viewOriginalTableAction()} will there be an 'action'-parameter.
     * That parameter can only be 'copy' or 'delete' for the viewTableAction and only 'copy' for the viewOriginalTableAction.
     *
     * @throws MessageBagException
     */
    public function executeAdditionalTableAction(array $rParams): void
    {
        if (!array_key_exists('action', $rParams['request'])) {
            return;
        }

        $prepareAction = $this->prepareAction($rParams);

        if ('copy' === $prepareAction['action']) {
            $this->copyStatements($prepareAction['items']);
        }
        if ('delete' === $prepareAction['action']) {
            $this->deleteStatements($prepareAction['items']);
        }
    }

    /**
     * This is only called from {@link AssessmentTableServiceOutput::singleStatementHandler()} which is only used in
     * {@link DemosPlanAssessmentTableController::viewSingleAction()}.
     * Possible values for 'action': 'send', 'update'.
     *
     * @throws MessageBagException
     * @throws StatementElementNotFoundException
     */
    public function executeAdditionalSingleViewAction(array $rParams): void
    {
        if (!array_key_exists('action', $rParams['request'])) {
            return;
        }

        $prepareAction = $this->prepareAction($rParams);

        if ('send' === $prepareAction['action']) {
            $this->sendStatementMail($rParams);
        }
        if ('update' === $prepareAction['action']) {
            $this->updateStatement($rParams);
        }
    }

    /**
     * Formulardaten vorbereiten.
     */
    private function prepareAction(array $rParams): array
    {
        $prepareAction['action'] = $rParams['request']['action'];

        if (array_key_exists('items', $rParams)) {
            $prepareAction['items'] = $rParams['items'];
        }

        if (array_key_exists('text', $rParams['request'])) {
            $prepareAction['text'] = $rParams['request']['text'];
        }

        if (array_key_exists('procedure', $rParams)) {
            $prepareAction['procedure'] = $rParams['procedure'];
        }

        return $prepareAction;
    }

    /**
     * @throws Exception
     */
    protected function getStatementHandler(): StatementHandler
    {
        return $this->statementHandler;
    }

    protected function getStatementService(): StatementService
    {
        return $this->statementService;
    }
}
