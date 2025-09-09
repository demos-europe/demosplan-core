<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Forum;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\CoreHandler;
use demosplan\DemosPlanCoreBundle\Logic\FlashMessageHandler;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use Exception;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class ForumHandler extends CoreHandler
{
    /**
     * @var ForumService
     */
    protected $forumService;

    /**
     * @var UserService
     */
    protected $userService;

    /**
     * @var MailService
     */
    protected $mailService;

    /**
     * @var Environment
     */
    protected $twig;

    public function __construct(
        Environment $twig,
        private readonly FlashMessageHandler $flashMessageHandler,
        ForumService $forumService,
        MailService $mailService,
        MessageBagInterface $messageBag,
        private readonly OrgaService $orgaService,
        private readonly TranslatorInterface $translator,
        UserService $userService
    ) {
        parent::__construct($messageBag);
        $this->forumService = $forumService;
        $this->mailService = $mailService;
        $this->twig = $twig;
        $this->userService = $userService;
    }

    /**
     * Hole alle Beiträge zu einem Thread.
     *
     * @param string $threadId
     *
     * @return array|mixed
     */
    public function getThreadEntryList($threadId)
    {
        // Liste holen
        $result = $this->forumService->getThreadEntryList($threadId);

        return $result;
    }

    /**
     * Verarbeitet das Edit-Formular vom ThreadEntry.
     *
     * @param array $threadEntry
     * @param array $data
     *
     * @return array
     */
    public function threadEntryEdit($threadEntry, $data)
    {
        $threadEntryUpdated = [];

        // Überprüfe Pflichtfelder
        if (!array_key_exists('r_text', $data) || '' === trim((string) $data['r_text'])) {
            $mandatoryError = [
                'type'    => 'error',
                'message' => $this->flashMessageHandler->createFlashMessage('mandatoryError', [
                    'fieldLabel' => $this->translator->trans('text'),
                ]),
            ];

            return [
                'mandatoryfieldwarning' => $mandatoryError,
            ];
        }
        if (array_key_exists('r_text', $data)) {
            $threadEntryUpdated['text'] = $data['r_text'];
        }
        if (array_key_exists('r_files', $data) && !empty($data['r_files'])) {
            // falls nur ein File hochgeladen wurde, kommt nur ein string vom import zurück,
            // der noch in einen array umgewandelt werden muss
            if (!is_array($data['r_files'])) {
                $data['r_files'] = [$data['r_files']];
            }
            $threadEntryUpdated['files'] = $data['r_files'];
        }

        $result = $this->forumService->updateThreadEntry($threadEntry['ident'], $threadEntryUpdated);

        if (true == $result['status']) {
            // Schicke eine Benachrichtigungsemail
            $dataForEmail = $result['body'];
            $dataForEmail['isNew'] = false;
            // Falls es ein Eintrag zur User Story ist, gebe die Daten dazu mit
            if (isset($data['userStory'])) {
                $dataForEmail['userStory'] = $data['userStory'];
            }
            try {
                $this->sentNotificationEmail($dataForEmail);
            } catch (Exception $e) {
                $this->logger->warning('Get Sending Notification Email failed: Responsecode: ', [$e]);
            }
        }

        return $result;
    }

    /**
     * Verarbeitet das NewThreadEntry-Formular aus dem Forum und Weiterentwicklungsbereich.
     *
     * @param string $threadId
     * @param array  $data
     *
     * @return array
     */
    public function threadEntryNew($threadId, $data)
    {
        $threadEntry = [];

        // Überprüfe Pflichtfelder
        if (!array_key_exists('r_text', $data) || '' === trim((string) $data['r_text'])) {
            $mandatoryError = [
                'type'    => 'error',
                'message' => $this->flashMessageHandler->createFlashMessage('mandatoryError', [
                    'fieldLabel' => $this->translator->trans('text'),
                ]),
            ];

            return [
                'mandatoryfieldwarning' => $mandatoryError,
            ];
        }
        if (array_key_exists('r_text', $data)) {
            $threadEntry['text'] = $data['r_text'];
        }
        if (array_key_exists('r_files', $data) && !empty($data['r_files'])) {
            // falls nur ein File hochgeladen wurde, kommt nur ein string vom import zurück,
            // der noch in einen array umgewandelt werden muss
            if (!is_array($data['r_files'])) {
                $data['r_files'] = [$data['r_files']];
            }
            $threadEntry['files'] = $data['r_files'];
        }
        if (array_key_exists('isFirstEntry', $data) && (true == $data['isFirstEntry'])) {
            $threadEntry['initialEntry'] = true;
        }

        $result = $this->forumService->addThreadEntry($threadId, $threadEntry);

        // Wenn erfolgreich, dann schicke eine Benachrichtigungsemail
        if (true === $result['status']) {
            $dataForEmail = $result['body'];
            $dataForEmail['isNew'] = true;
            // Falls es ein Eintrag zur User Story ist, gebe die Daten dazu mit
            if (isset($data['userStory'])) {
                $dataForEmail['userStory'] = $data['userStory'];
            }
            try {
                $this->sentNotificationEmail($dataForEmail);
            } catch (Exception $e) {
                $this->logger->warning('Get Sending Notification Email failed: Responsecode: ', [$e]);
            }
        }

        return $result;
    }

    /**
     * Replace the content of a threadEntry with Placeholder.
     *
     * @param array $threadEntry
     *
     * @return array
     */
    public function threadEntryUpdateWithDeletedPlaceholder($threadEntry)
    {
        // wenn nicht dann lösche die Inhalte des Beitrags und setze die delete-Flag
        $data = [];
        $data['anonymise'] = true;

        if (true == $threadEntry['editableByUser']) {
            // falls Autor, dann gebe Platzhaltertext für Autor aus
            $data['text'] = 'Verfasser';
        } else {
            // ansonsten  Platzhaltertext für Moderation
            $data['text'] = 'Moderator';
        }

        // die Beiträge  werden mit leeren Variablen überschrieben um einen Platzhalter zu generieren
        return $this->forumService->updateThreadEntry($threadEntry['ident'], $data);
    }

    /**
     * Lösche die Dateien zu einem Forumsbeitrag.
     *
     * @param array $files
     *
     * @return bool|string
     */
    public function deleteForumFiles($files)
    {
        $result = '';
        foreach ($files as $file) {
            $result = $this->forumService->deleteForumFile($file);
        }

        return $result;
    }

    /**
     * Holt die Daten zu einem Beitrag.
     *
     * @param string $threadEntryId
     *
     * @return array
     */
    public function getSingleThreadEntry($threadEntryId)
    {
        return $this->forumService->getThreadEntry($threadEntryId);
    }

    /**
     * Save a new release.
     *
     * @param array $data
     *
     * @return array
     */
    public function newRelease($data)
    {
        $release = [];
        $mandatoryErrors = [];

        // Überprüfe Pflichtfelder
        if (!array_key_exists('r_title', $data) || '' === trim((string) $data['r_title'])) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->flashMessageHandler->createFlashMessage('mandatoryError', [
                    'fieldLabel' => $this->translator->trans('title'),
                ]),
            ];
        }
        if (!array_key_exists('r_phase', $data) || '' === trim((string) $data['r_phase'])) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->flashMessageHandler->createFlashMessage('mandatoryError', [
                    'fieldLabel' => $this->translator->trans('phase'),
                ]),
            ];
        }
        if (0 < count($mandatoryErrors)) {
            return [
                'mandatoryfieldwarning' => $mandatoryErrors,
            ];
        }

        if (array_key_exists('r_title', $data) && 0 < strlen((string) $data['r_title'])) {
            $release['title'] = $data['r_title'];
        }
        if (array_key_exists('r_phase', $data) && 0 < strlen((string) $data['r_phase'])) {
            $release['phase'] = $data['r_phase'];
        }

        if (array_key_exists('r_description', $data) && 0 < strlen((string) $data['r_description'])) {
            $release['description'] = $data['r_description'];
        }
        if (array_key_exists('r_startdate', $data)) {
            if ('' != $data['r_startdate']) {
                $release['startDate'] = strtotime((string) $data['r_startdate']);
            }
        }
        if (array_key_exists('r_enddate', $data)) {
            if ('' != $data['r_enddate']) {
                $release['endDate'] = strtotime((string) $data['r_enddate']);
            }
        }

        return $this->forumService->newRelease($release);
    }

    /**
     * Update of a release.
     *
     * @param array $data
     *
     * @return array|bool
     */
    public function updateRelease($releaseId, $data)
    {
        $release = [];
        $mandatoryErrors = [];

        // Überprüfe Pflichtfelder
        if (!array_key_exists('r_title', $data) || '' === trim((string) $data['r_title'])) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->flashMessageHandler->createFlashMessage('mandatoryError', [
                    'fieldLabel' => $this->translator->trans('title'),
                ]),
            ];
        }
        if (!array_key_exists('r_phase', $data) || '' === trim((string) $data['r_phase'])) {
            $mandatoryErrors[] = [
                'type'    => 'error',
                'message' => $this->flashMessageHandler->createFlashMessage('mandatoryError', [
                    'fieldLabel' => $this->translator->trans('phase'),
                ]),
            ];
        }
        if (0 < count($mandatoryErrors)) {
            return [
                'mandatoryfieldwarning' => $mandatoryErrors,
            ];
        }

        if (array_key_exists('r_title', $data) && 0 < strlen((string) $data['r_title'])) {
            $release['title'] = $data['r_title'];
        }
        if (array_key_exists('r_phase', $data) && 0 < strlen((string) $data['r_phase'])) {
            $release['phase'] = $data['r_phase'];
        }

        if (array_key_exists('r_description', $data) && 0 < strlen((string) $data['r_description'])) {
            $release['description'] = $data['r_description'];
        }
        if (array_key_exists('r_startdate', $data)) {
            if ('' != $data['r_startdate']) {
                $release['startDate'] = strtotime((string) $data['r_startdate']);
            }
        }
        if (array_key_exists('r_enddate', $data)) {
            if ('' != $data['r_enddate']) {
                $release['endDate'] = strtotime((string) $data['r_enddate']);
            }
        }

        return $this->forumService->updateRelease($releaseId, $release);
    }

    /**
     * Delete a release.
     *
     * @param string $releaseId
     */
    public function deleteRelease($releaseId): void
    {
        $this->forumService->deleteRelease($releaseId);
    }

    /**
     * Process votes for different user stories of a release.
     *
     * @param string $releaseId
     * @param array  $data
     * @param int    $limitForVotes
     */
    public function saveOnlineVotesOfUserStories($releaseId, $data, $limitForVotes)
    {
        $votes = [];
        $userStories = $data;
        $sumVotes = 0;

        foreach ($userStories as $key => $userStory) {
            $sumVotes += $userStory;
            if (0 <= intval($userStory)) {
                $votes[] = ['userStoryId' => $key, 'numberOfVotes' => intval($userStory)];
            }
        }
        // Überprüfe, ob das Limit eingehalten wird, wenn nicht gebe Meldung zurück
        if ($sumVotes > $limitForVotes) {
            $exceededVotes = $sumVotes - $limitForVotes;

            return ['exceededVotes' => $exceededVotes];
        }

        return $this->forumService->saveVotes($releaseId, $votes);
    }

    /**
     * Process offlineVotes foreach User Story.
     *
     * @param array $offlineVotes
     *
     * @return array|mixed
     */
    public function saveOfflineVotesOfUserStories($offlineVotes)
    {
        $result = [];
        $errorMessages = [];
        // Übergebe dem Service für jede userStory die offlineVotes
        $userStories = $offlineVotes;
        foreach ($userStories as $key => $userStory) {
            $data['offlineVotes'] = $userStory;
            try {
                $result = $this->forumService->updateUserStory($key, $data);
            } catch (Exception $e) {
                $errorMessages[] = [
                    'type'    => 'error',
                    'message' => 'Offline-Punkte konnten für Story mit ID:'.$key.' gespeichert werden',
                ];
                $this->logger->warning(sprintf('Fehler beim Speichern der offlineVotes für eine Usertory. EntryId: %s Exceptionmessage: %s', $key, $e->getMessage()));
            }
            if (0 < count($errorMessages)) {
                return [
                    'errorMessages' => $errorMessages,
                ];
            }
        }

        return $result;
    }

    /**
     * Get a list of all releases.
     */
    public function getReleases()
    {
        return $this->forumService->getReleases();
    }

    /**
     * Get all info of one release.
     *
     * @param string $releaseId
     */
    public function getSingleRelease($releaseId)
    {
        return $this->forumService->getRelease($releaseId);
    }

    /**
     * Process variables for a new user story.
     *
     * @param string $releaseId
     * @param array  $data
     *
     * @return array|bool
     */
    public function newUserStory($releaseId, $data)
    {
        $userStory = [];

        // Überprüfe Pflichtfelder
        if (!array_key_exists('r_title', $data) || '' === trim((string) $data['r_title'])) {
            $mandatoryError = [
                'type'    => 'error',
                'message' => $this->flashMessageHandler->createFlashMessage('mandatoryError', [
                    'fieldLabel' => $this->translator->trans('title'),
                ]),
            ];

            return [
                'mandatoryfieldwarning' => $mandatoryError,
            ];
        }

        if (array_key_exists('r_title', $data) && 0 < strlen((string) $data['r_title'])) {
            $userStory['title'] = $data['r_title'];
        }

        if (array_key_exists('r_description', $data) && 0 < strlen((string) $data['r_description'])) {
            $userStory['description'] = $data['r_description'];
        }
        if (array_key_exists('r_onlineVotes', $data)) {
            $userStory['onlineVotes'] = $data['r_onlineVotes'];
        }
        if (array_key_exists('r_offlineVotes', $data)) {
            $userStory['offlineVotes'] = $data['r_offlineVotes'];
        }

        return $this->forumService->newUserStory($releaseId, $userStory);
    }

    /**
     * process variables for an update of a user story.
     *
     * @param string $storyId
     * @param array  $data
     *
     * @return array|mixed
     */
    public function editUserStory($storyId, $data)
    {
        $userStory = [];

        // Überprüfe Pflichtfelder
        if (!array_key_exists('r_title', $data) || '' === trim((string) $data['r_title'])) {
            $mandatoryError = [
                'type'    => 'error',
                'message' => $this->flashMessageHandler->createFlashMessage('mandatoryError', [
                    'fieldLabel' => $this->translator->trans('title'),
                ]),
            ];

            return [
                'mandatoryfieldwarning' => $mandatoryError,
            ];
        }

        if (array_key_exists('r_title', $data) && 0 < strlen((string) $data['r_title'])) {
            $userStory['title'] = $data['r_title'];
        }

        if (array_key_exists('r_description', $data) && 0 < strlen((string) $data['r_description'])) {
            $userStory['description'] = $data['r_description'];
        }

        if (array_key_exists('r_offlineVotes', $data)) {
            $userStory['offlineVotes'] = $data['r_offlineVotes'];
        }

        return $this->forumService->updateUserStory($storyId, $userStory);
    }

    /**
     * Delete a single user story.
     *
     * @param string $storyId
     *
     * @return bool
     */
    public function deleteUserStory($storyId)
    {
        return $this->forumService->deleteUserStory($storyId);
    }

    /**
     * Get all info and user stories of one release.
     *
     * @param string $releaseId
     */
    public function getUserStoriesForRelease($releaseId)
    {
        return $this->forumService->getUserStories($releaseId);
    }

    /**
     * Get all info about one user story.
     *
     * @param string $storyId
     */
    public function getUserStory($storyId)
    {
        return $this->forumService->getUserStory($storyId);
    }

    public function getVotesForUserStory($storyId)
    {
        return $this->forumService->getVotes($storyId);
    }

    /**
     * Process all variables for a notification email.
     *
     * @param array $data
     *
     * @throws Exception
     */
    protected function sentNotificationEmail($data)
    {
        $vars = [];
        // hole alle User mit der Rolle Moderator
        $role = Role::BOARD_MODERATOR;
        $allUsersWithRole = $this->userService->getUsersOfRole($role);

        // Flag für Mail an Author of starterEntry(Thread)
        $notificationForAuthor = false;

        // wenn es sich nicht um einen ersten Beitrag handelt, hole ihn
        if (true != $data['initialEntry'] && !isset($data['userStory'])) {
            // hole den ersten Beitrag
            $entryListForThread = $this->forumService->getThread($data['threadId']);
            $firstEntry = $entryListForThread['starterEntry'];

            // Generiere eine Teaser vom ersten Beitragstext
            // bereinige die Textvariable von Tags
            $entryTextNoTags = strip_tags((string) $firstEntry['text']);
            if (0 < strlen($entryTextNoTags)) {
                // Kürze den Text und speicher das Ergebnis in der MailVariable
                $shortText = substr($entryTextNoTags, 0, 150).'...';
                $data['firstEntryText'] = $shortText;
            }
            // Prüfe, ob Autor des starterEntry ungleich des Autors vom neuen Threadentry ist, wenn ja dann setze flag auf true
            if ($entryListForThread['starterEntry']['user']['ident'] != $data['user']['ident']) {
                $notificationForAuthor = true;
                $data['starterEntryAuthor'] = $entryListForThread['starterEntry']['user'];
            }
        }

        // generiere eine Teaser vom Beitragstext
        // bereinige die Textvariable von Tags
        $entryTextNoTags = strip_tags((string) $data['text']);
        if (0 < strlen($entryTextNoTags)) {
            // Kürze den Text und speicher das Ergebnis in der MailVariable
            $shortText = substr($entryTextNoTags, 0, 150).'...';
            $data['text'] = $shortText;
        }

        // Setze die Mailvariablen
        $mailTemplateVars = $data;
        $vars['mailsubject'] =
          $this->translator->trans('email.subject.forum.notification');
        // Schicke eine Email an die Moderatoren, dass im Forum/Weiterentwicklung Beiträge veröffentlicht/aktualisiert wurden
        $this->sendNotificationEmailToModerator(
            $mailTemplateVars,
            $vars,
            $allUsersWithRole
        );

        // Schicke eine Email an den Autor des StarterEntry, wenn er Benachrichtigungen aktiviert hat und wenn Beitrag nicht zu einer UserStory geschrieben wurde
        if (isset($data['starterEntryAuthor']) && true == $notificationForAuthor && !isset($data['userStory'])) {
            $this->sendNotificationEmailToAuthor($data, $mailTemplateVars, $vars);
        }
    }

    /**
     * @param string $orgaId
     *
     * @return Orga
     *
     * @throws Exception
     */
    public function getOrgaOfVote($orgaId)
    {
        return $this->orgaService->getOrga($orgaId);
    }

    /**
     * Verschickt an alle Moderatoren eine Benachrichtigungsemail.
     *
     * @param array  $mailTemplateVars
     * @param array  $vars
     * @param User[] $allUsersWithRole
     */
    protected function sendNotificationEmailToModerator(
        $mailTemplateVars,
        $vars,
        $allUsersWithRole
    ) {
        // Wenn ein Bezug zur User Story übergeben wird,  hole dieses template
        if (isset($mailTemplateVars['userStory'])) {
            $vars['mailbody'] = $this->twig
                ->load(
                    '@DemosPlanCore/DemosPlanForum/development_send_moderator_notification_email.html.twig'
                )->renderBlock(
                    'body_plain',
                    ['templateVars' => $mailTemplateVars]
                );
        } else {
            // Ansonsten, handelt es sich um einen Beitrag aus dem Forum, dann hole ein dieses template
            $vars['mailbody'] = $this->twig
            ->load(
                '@DemosPlanCore/DemosPlanForum/forum_send_moderator_notification_email.html.twig'
            )->renderBlock(
                'body_plain',
                ['templateVars' => $mailTemplateVars]
            );
        }
        // Schicke die Email an alle Moderatoren
        foreach ($allUsersWithRole as $user) {
            // wenn das Flag gesetzt ist, dass er keine Emails haben möchte, eben nicht
            if (!$user->getForumNotification()) {
                continue;
            }

            try {
                $this->mailService->sendMail(
                    'dm_forum_notification',
                    'de_DE',
                    $user->getEmail(),
                    '',
                    '',
                    '',
                    'extern',
                    $vars
                );
            } catch (Exception) {
                $this->getLogger()->warning(sprintf('Email konnte nicht an den Moderator %s verschickt werden', $user['email']));
            }
        }
    }

    /**
     * Verschickt Benachrichtigungsemail an den Autor eines StarterEntry.
     *
     * @param array $data
     * @param array $mailTemplateVars
     * @param array $vars
     *
     * @deprecated in development forum there is no starterEntryAuthor
     */
    protected function sendNotificationEmailToAuthor(
        $data,
        $mailTemplateVars,
        $vars
    ) {
        // Besorge dir auch die Flags des Users
        /** @var User $starterEntryAuthor */
        $starterEntryAuthor = $this->userService->getSingleUser($data['starterEntryAuthor']['ident']);
        // wenn das Flag gesetzt ist, dass er keine Emails haben möchte, eben nicht
        if (!$starterEntryAuthor->getForumNotification()) {
            return;
        }

        // hole das template für die Email an den Author
        $vars['mailbody'] = $this->twig
            ->load(
                '@DemosPlanCore/DemosPlanForum/forum_send_author_notification_email.html.twig'
            )->renderBlock(
                'body_plain',
                ['templateVars' => $mailTemplateVars]
            );
        try {
            $this->mailService->sendMail(
                'dm_forum_notification',
                'de_DE',
                $data['starterEntryAuthor']['uemail'],
                '',
                '',
                '',
                'extern',
                $vars
            );
        } catch (Exception) {
            $this->getLogger()->warning(sprintf('Email konnte nicht an den Autor %s verschickt werden', $data['starterEntryAuthor']['uemail']));
        }
    }

    /**
     * @param array $threadEntry
     *
     * @return array
     */
    public function checkPermission($threadEntry, User $user, PermissionsInterface $permissions)
    {
        // Rechte zum Editieren/Löschen überprüfen
        // Darf er den Beitrag noch(zeitl. Limit) bearbeiten und ist er auch der Autor?
        $threadEntry['limitToEdit'] = $threadEntry['createDate'] / 1000 + (60 * 60);
        // Bearbeitungsmodus-Flag
        $threadEntry['editableByUser'] = false;
        $timeNow = time();
        if ($threadEntry['limitToEdit'] > $timeNow && ($threadEntry['user']['ident'] == $user->getId())) {
            $threadEntry['editableByUser'] = true;
        }
        // ist der Thread geschlossen, verfällt für user das recht zum editieren
        if (true == $threadEntry['threadClosed']) {
            $threadEntry['editableByUser'] = false;
        }
        // oder hat der User das generelle Recht (Moderator)
        $threadEntry['editableByModerator'] = $permissions->hasPermission('feature_forum_dev_release_edit');

        return $threadEntry;
    }
}
