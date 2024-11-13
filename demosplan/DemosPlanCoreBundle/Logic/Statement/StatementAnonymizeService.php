<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use demosplan\DemosPlanCoreBundle\Logic\StatementAttachmentService;
use Doctrine\Common\Collections\ArrayCollection;
use Exception;
use Symfony\Contracts\Translation\TranslatorInterface;

class StatementAnonymizeService extends CoreService
{
    /** @var string Tag before anonymization, it means: "this still needs to be anonymized!" */
    private const TAG = 'anonymize-text';

    public function __construct(private readonly EntityContentChangeService $entityContentChangeService, private readonly FileService $fileService, private PermissionsInterface $permissions, private readonly ReportService $reportService, private readonly StatementService $statementService, private readonly StatementAttachmentService $statementAttachmentService, private readonly TranslatorInterface $translator, private readonly DraftStatementService $draftStatementService, private readonly DraftStatementHandler $draftStatementHandler, private readonly DraftStatementFileHandler $draftStatementFileHandler)
    {
    }

    /**
     * Anonymize user data of this statement (including moved Statements) and the related reports.
     * In case of userId is given:
     * Anonymize the data of the given user from this statement, by identifying the
     * given user as author and/or submitter and anonymize author and/or submitter data.
     * In case of userId is not given:
     * Because of the current logic there is only one case in which the author and submitter
     * are different persons/users: If the statement is authored by a "Sachbearbeiter" and submitted
     * by a "Koordinator".
     * In the other cases (manual statement, authored by registered citizen,
     * authored by unregistered citizen or authored by koordinator), the author will also be the submitter.
     * Therefore the data of the author and the data of the submitter will be anonymized.
     * Will anonymize all data of given user on the given Statement or
     * recursively of the original Statement of the given Statement and all children,
     * if the parameter recursively is true.
     *
     * @param Statement $statement     statement which user data will be anonymize
     * @param bool      $recursively   if true, all copies will be anonymized, otherwise false
     * @param bool      $forceOriginal if true, the original statement (will be searched and) started with this
     * @param string    $userId        Identifies the user, which data should be anonymized on the given statement.
     *                                 In case no Id is given, author- and submitter- data will be anonymized.
     *
     * @return Statement the statement to be anonymized
     *
     * @throws CustomerNotFoundException
     * @throws InvalidDataException
     * @throws UserNotFoundException
     * @throws Exception
     */
    public function anonymizeUserDataOfStatement(
        Statement $statement,
        bool $recursively,
        bool $forceOriginal,
        string $userId,
        bool $revokeGdpr,
    ): Statement {
        $statement = $this->forceAnonymizationOfOriginal($forceOriginal, $statement);
        if (null === $statement->getGdprConsent()) {
            throw new InvalidDataException('GDPR-Consent on (original)Statement is missing.');
        }
        $this->anonymizeUserDataOfChildren($recursively, $statement, $userId, $revokeGdpr);
        $this->anonymizePlaceholderOfStatementIfItExists($statement, $userId);
        $this->anonymizeMovedStatementIfItExists($statement, $userId);
        // Order matters here: Anonymizing related report entries requires the statement, so it must be done first
        $this->reportService->anonymizeUserDataOfStatementReportEntries($statement, $userId);
        $this->overwriteUserDataOfStatement($statement, $userId);
        $this->revokeGdpr($revokeGdpr, $statement);

        // Bypass all checks of $this->updateStatement() because this is a very unusual update of an statement
        // 1. probably in most cases the current user will not be assigned or even able to be assigned
        // 2. In this case it is allowed to update an manual statement (original + manual)
        // 3. In this case it is allowed to update an original statement
        // 5. Should never happen because original STN: In this case it is allowed to update an statement which is actually a member of an cluster
        // 6. Should never happen because original STN: In this case it is allowed to update an statement which is actually a Placeholder of a cluster

        return $this->statementService->updateStatementObject($statement);
    }

    /**
     * Remove/overwrite relational address data.
     * These fields can be only filled on statements of registered or unregistered users.
     * (In case of statement by organisation, meta->submitOrgaId is used.).
     */
    public function anonymizeAddressData(Statement $statement): void
    {
        $meta = $statement->getMeta();
        if (null !== $meta) {
            if (!$this->permissions->hasPermission('feature_keep_street_on_anonymize')
                // see permission description for why the house number is checked
                || '' === $meta->getHouseNumber()) {
                $meta->setOrgaStreet('');
            }
            $meta->setHouseNumber('');
            $meta->setOrgaEmail('');
            $meta->setOrgaPostalCode('');
            $meta->setOrgaCity('');
        }
    }

    /**
     * Will delete the text-history of given Statement and all children.
     * In case of $forceOriginal ist true, the original STN will be used as startpoint of
     * recursively deletion of text-history of all children.
     * Will delete the text-history of given Statement and all children.
     *
     * @param bool $forceOriginal In case of true, the original STN will be used as start point of
     *                            recursively deletion of text-history of all children. Hence, this should
     *                            usually be true.
     *
     * @throws UserNotFoundException
     * @throws CustomerNotFoundException
     * @throws Exception
     */
    public function deleteHistoryOfTextsRecursively(Statement $statement, bool $forceOriginal = true, bool $createReport = true): void
    {
        $relatedEntityIds = [];
        if ($forceOriginal) {
            $statement = $statement->isOriginal() ? $statement : $statement->getOriginal();
        }
        $relatedEntityIds[] = $statement->getId();

        /** @var Statement $childStatement */
        foreach ($statement->getChildren() as $childStatement) {
            $this->deleteHistoryOfTextsRecursively($childStatement, false, false);
            $relatedEntityIds[] = $childStatement->getId();
        }

        $this->entityContentChangeService->deleteByEntityIdsAndField($relatedEntityIds, 'text');
    }

    /**
     * @throws InvalidDataException
     * @throws Exception
     */
    public function anonymizeTextOfStatement(Statement $statement, string $anonymizedTextWithTags): void
    {
        $this->validateAnonymizedText($statement->getText(), $anonymizedTextWithTags);
        $anonymizedText = $this->anonymizeText($anonymizedTextWithTags);
        $statement->setText($anonymizedText);
        $this->statementService->updateStatementObject($statement);
    }

    /**
     * @throws Exception
     */
    public function deleteAttachments(Statement $statement): void
    {
        foreach ($statement->getChildren() as $childStatement) {
            $this->deleteAttachments($childStatement);
        }

        $attachments = $statement->getAttachments()->getValues();
        $this->statementAttachmentService->deleteStatementAttachments($attachments);

        /** @var string $fileString */
        foreach ($statement->getFiles() as $fileString) {
            $fileStringParts = explode(':', $fileString);
            $this->fileService->deleteFileContainer($fileStringParts[1], $statement->getId());
            $draftStatements = $this->draftStatementFileHandler->getDraftStatementRelatedToThisFile($fileString);

            if (null === $draftStatements) {
                $this->fileService->deleteFileFromFileString($fileString); //
                // we cannot delete the file if it belongs to a draft statement because it means it belongs to a priveate user
                // this is the line that trigger the error
            }
        }
        $statement->setFile('');
        $statement->setFiles([]);
        $statement->setAttachments(new ArrayCollection());
        $this->statementService->updateStatementObject($statement);
    }

    private function anonymizeText(string $anonymizedTextWithTags): string
    {
        $blackSharpie = '<span class="anonymized">***</span>';

        /*
         * (.+?) means:
         * - a group: ()
         * - of any possible symbol: .
         * - 1 or more times: +
         * - once or more, but be greedy about it: ?
         */
        return preg_replace(
            '/<'.self::TAG.'>(.+?)<\/'.self::TAG.'>/i',
            $blackSharpie,
            $anonymizedTextWithTags
        );
    }

    /**
     * Overwrite user data containing fields of a Statement, depending on whether user is submitter and/or author.
     *
     * @param Statement $statement statement, which have to be anonymized
     * @param string    $userId    Identifies the user, whose data should be anonymized on the given statement.
     *                             In case no Id is given, author- and submitter- data will be anonymized.
     *
     * @throws NotYetImplementedException will thrown in case of no userId is given but the submitter is not the author
     */
    private function overwriteUserDataOfStatement(Statement $statement, string $userId): void
    {
        $anonymizeSubmitterData = true;
        $anonymizeAuthorData = true;

        if (User::ANONYMOUS_USER_ID !== $userId && null !== $userId) {
            $anonymizeSubmitterData = $statement->isSubmitter($userId);
            $anonymizeAuthorData = $statement->isAuthor($userId);

            // anonymize data of user is more or less deprecated
            // instead via UI only submitter can revoke -> submitterdata as well as authordata will be anonymized
            if ($statement->isSubmitter($userId)
                && $statement->hasBeenAuthoredByInstitutionSachbearbeiterAndSubmittedByInstitutionKoordinator()) {
                // Means koordiantor is revoking GDPR-Consent, also anonyimize data of Sachbearbeiter:
                $anonymizeAuthorData = true;
            }
        }

        if ($anonymizeSubmitterData) {
            $this->anonymizeSubmitUserData($statement);
        }
        if ($anonymizeAuthorData) {
            $this->anonymizeAuthorUserData($statement);
        }
        if ($anonymizeAuthorData || $anonymizeSubmitterData) {
            $this->anonymizeAddressData($statement);
        }
    }

    /**
     * Remove/overwrite relational data of the author from this statement.
     */
    private function anonymizeAuthorUserData(Statement $statement): void
    {
        $statement->setUser(null);
        if (null !== $statement->getMeta()) {
            $statement->getMeta()->setAuthorName($this->translator->trans('anonymized'));
        }
    }

    /**
     * TODO: This should be refactored and made cleaner, very dirty solution.
     *
     * @throws InvalidDataException
     */
    private function validateAnonymizedText(string $originalText, string $anonymizedText): void
    {
        // replace ascii non breaking spaces from original text as they kill comparability
        $originalText = str_replace("\xc2\xa0", ' ', $originalText);
        $texts = [$originalText, $anonymizedText];
        $tagArray = [
            'a',
            'abbr',
            'b',
            'del',
            'dp-obscure',
            'em',
            'i',
            'img',
            'ins',
            'li',
            'mark',
            'ol',
            'p',
            's',
            'span',
            'strike',
            'strong',
            'sup',
            'table',
            'td',
            'th',
            'thead',
            'tr',
            'u',
            'ul',
        ];

        for ($i = 0; $i < 2; ++$i) {
            // remove previously anonymized text tags
            $texts[$i] = str_replace(
                '<span class="anonymized">***</span>',
                '***',
                $texts[$i]
            );

            // remove ascii issue, refs: T18761
            $texts[$i] = str_replace(
                [
                    '&nbsp;',
                    ' ',

                    '<br>',
                    '<br />',
                    '<br/>',

                    "\n",
                    "\r",
                    "\t",

                    '&quot;',
                    '&lt;',
                    '&gt;',
                ],
                [
                    '', '',
                    '', '', '',
                    '', '', '',
                    '"', '<', '>',
                ],
                $texts[$i]
            );

            // remove problem that anonymization tags exit formatting tags around them by recursively removing the
            // surrounding exit formatting tags
            $sumTagArray = count($tagArray);
            for ($k = 0; $k < $sumTagArray; ++$k) {
                // the for loop ensures that the replacement happens n times for each tag, to account for nested tags
                foreach ($tagArray as $tag) {
                    $texts[$i] = preg_replace(
                        [
                            '/<\/'.$tag.'[^>]*><'.self::TAG.'><'.$tag.'>/',
                            '/<\/'.$tag.'[^>]*><\/'.self::TAG.'><'.$tag.'>/',
                        ],
                        [
                            '<'.self::TAG.'>',
                            '</'.self::TAG.'>',
                        ],
                        $texts[$i]
                    );
                }
            }

            // actually remove anonymization tags to enable identical comparison
            $texts[$i] = str_replace(
                ['<'.self::TAG.'>', '</'.self::TAG.'>'],
                ['', ''],
                $texts[$i]
            );
        }

        if ($texts[0] !== $texts[1]) {
            $message = 'The provided string does not match up with the original text minus the tags.';
            throw new InvalidDataException($message);
        }
    }

    /**
     * Remove/overwrite relational data of the submitter from this statement.
     */
    private function anonymizeSubmitUserData(Statement $statement): void
    {
        if (null !== $statement->getMeta()) {
            $statement->getMeta()->setSubmitUId(null);
            $statement->getMeta()->setSubmitName($this->translator->trans('anonymized'));
        }
    }

    public function getPermissions(): PermissionsInterface
    {
        return $this->permissions;
    }

    /**
     * @param PermissionsInterface $permissions
     */
    public function setPermissions($permissions)
    {
        $this->permissions = $permissions;
    }

    private function anonymizePlaceholderOfStatementIfItExists(Statement $statement, string $userId): void
    {
        if (null !== $statement->getPlaceholderStatement()) {
            $this->overwriteUserDataOfStatement($statement->getPlaceholderStatement(), $userId);
        }
    }

    private function anonymizeMovedStatementIfItExists(Statement $statement, string $userId): void
    {
        if (null !== $statement->getMovedStatement()) {
            $this->overwriteUserDataOfStatement($statement->getMovedStatement(), $userId);
        }
    }

    private function revokeGdpr(bool $revokeGDPR, Statement $statement): void
    {
        if ($revokeGDPR) {
            $statement->getGdprConsent()->setConsentRevoked(true);
            $statement->getGdprConsent()->setConsentRevokedDate(new DateTime());
        }
    }

    /**
     * @throws CustomerNotFoundException
     * @throws InvalidDataException
     * @throws UserNotFoundException
     */
    private function anonymizeUserDataOfChildren(
        bool $recursively,
        Statement $statement,
        string $userId,
        bool $revokeGdpr,
    ): void {
        if ($recursively) {
            /** @var Statement[] $children */
            $children = $statement->getChildren();
            foreach ($children as $child) {
                $this->anonymizeUserDataOfStatement($child, $recursively, false, $userId, $revokeGdpr);
            }
        }
    }

    /**
     * This method allows to start with the original. This then enables the code to go down the three and find and
     * anonymize all children.
     *
     * @param bool $forceOriginal If this is set to true, the anonymization happens on the original, not a child.
     *                            If it already is the original, then the anonymization simply proceeds.
     */
    private function forceAnonymizationOfOriginal(bool $forceOriginal, Statement $statement): Statement
    {
        if ($forceOriginal && !$statement->isOriginal()) {
            $statement = $statement->getOriginal();
        }

        return $statement;
    }
}
