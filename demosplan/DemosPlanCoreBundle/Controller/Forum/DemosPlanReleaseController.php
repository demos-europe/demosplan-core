<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Forum;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\CsvHelper;
use demosplan\DemosPlanCoreBundle\Logic\FileUploadService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\NameGenerator;
use demosplan\DemosPlanCoreBundle\Services\Breadcrumb\Breadcrumb;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Extension\EscaperExtension;

class DemosPlanReleaseController extends DemosPlanForumBaseController
{
    protected $limitForVotes = 3;

    /**
     * Index-Seite für Weiterentwicklungsbereich.
     *
     * @DplanPermissions("area_development")
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     */
    #[Route(name: 'DemosPlan_forum_development', path: '/development')]
    public function developmentIndexAction(Breadcrumb $breadcrumb, TranslatorInterface $translator)
    {
        $templateVars = [];

        // hole das aktive Release
        $storageResult = $this->forumHandler->getReleases();
        foreach ($storageResult as $release) {
            if ('voting_online' === $release['phase'] || 'voting_offline' === $release['phase']) {
                $templateVars['releaseForVoting'][] = $release;
            }
        }
        // wenn ja dann leite zu deren Detailansicht weiter
        if (!empty($templateVars['releaseForVoting'])) {
            // zeige das jüngste an
            $sumActiveReleases = count($templateVars['releaseForVoting']);
            $latestActiveReleaseKey = $sumActiveReleases - 1;

            // nehme das älteste Release (1. aus der Liste) und zeige es an
            return $this->redirectToRoute(
                'DemosPlan_forum_development_release_detail',
                ['releaseId' => $templateVars['releaseForVoting'][$latestActiveReleaseKey]['ident']]
            );
        }

        // Generiere breadcrumb items
        $title = 'forum.development';

        // Füge die kontextuelle Hilfe dazu
        $templateVars['contextualHelpBreadcrumb'] = $breadcrumb->getContextualHelp($title);

        // Ausgabe
        return $this->renderTemplate('@DemosPlanCore/DemosPlanForum/development_index.html.twig', [
            'templateVars' => $templateVars,
            'title'        => $title,
        ]);
    }

    /**
     * Ein Release anlegen.
     *
     * @DplanPermissions("feature_forum_dev_release_edit")
     *
     * @return RedirectResponse|Response
     */
    #[Route(name: 'DemosPlan_forum_development_release_new', path: '/development/release/new')]
    public function newReleaseAction(Request $request)
    {
        // Anlegen eines neuen release
        if ($request->request->has('saveNewRelease')) {
            $requestPost = $request->request->all();
            $storageResult = $this->forumHandler->newRelease($requestPost);
            if (array_key_exists('mandatoryfieldwarning', $storageResult)) {
                $this->getMessageBag()->add('error', $storageResult['mandatoryfieldwarning']['message']);
                $templateVars = $requestPost;
            }
            if (true === $storageResult['status']) {
                // Erfolgsmeldung
                $this->getMessageBag()->add('confirm', 'confirm.release.created');

                return $this->redirectToRoute(
                    'DemosPlan_forum_development_release_detail',
                    ['releaseId' => $storageResult['body']['ident']]
                );
            }
        }
        $releasePhases = $this->getReleasePhases();
        $templateVars = [];

        $templateVars['releasePhases'] = $releasePhases;

        // Ausgabe
        return $this->renderTemplate('@DemosPlanCore/DemosPlanForum/development_release_new.html.twig', [
            'templateVars' => $templateVars,
            'title'        => 'forum.development.release.new',
        ]);
    }

    /**
     * Update of a Release.
     *
     * @DplanPermissions("feature_forum_dev_release_edit")
     *
     * @param string $releaseId
     *
     * @return RedirectResponse|Response
     */
    #[Route(name: 'DemosPlan_forum_development_release_edit', path: '/development/{releaseId}/edit')]
    public function editReleaseAction(Request $request, $releaseId)
    {
        $storageResult = [];
        // Anlegen eines neuen release
        if ($request->request->has('updateRelease')) {
            $requestPost = $request->request->all();
            $storageResult['status'] = $this->forumHandler->updateRelease($releaseId, $requestPost);
            if (array_key_exists('mandatoryfieldwarning', $storageResult)) {
                $this->getMessageBag()->add('error', $storageResult['mandatoryfieldwarning']['message']);
            }
            if (true === $storageResult['status']) {
                // Erfolgsmeldung
                $this->getMessageBag()->add('confirm', 'confirm.release.updated');

                return $this->redirectToRoute('DemosPlan_forum_development_release_detail', compact('releaseId'));
            }
        }
        $releasePhases = $this->getReleasePhases();
        $result = $this->forumHandler->getSingleRelease($releaseId);
        $tokenForDelete = $this->generateToken();

        // Übergabe an das Template
        $templateVars = [];
        $templateVars['releasePhases'] = $releasePhases;
        $templateVars['release'] = $result;
        $templateVars['token'] = $tokenForDelete;

        // Ausgabe
        return $this->renderTemplate('@DemosPlanCore/DemosPlanForum/development_release_edit.html.twig', [
            'templateVars' => $templateVars,
            'title'        => 'forum.development.release.edit',
        ]);
    }

    /**
     * Delete a release.
     *
     * @DplanPermissions("feature_forum_dev_release_edit")
     *
     * @param string $releaseId
     * @param string $token
     *
     * @return RedirectResponse
     */
    #[Route(name: 'DemosPlan_forum_development_release_delete', path: '/development/delete/{releaseId}/{token}')]
    public function deleteReleaseAction($releaseId, $token)
    {
        // Token zum Überprüfen erstellen
        $tokenToCheck = $this->generateToken();
        if ($token === $tokenToCheck) {
            try {
                $this->forumHandler->deleteRelease($releaseId);
                // Erfolgsmeldung
                $this->getMessageBag()->add('confirm', 'confirm.release.deleted');

                return $this->redirectToRoute('DemosPlan_forum_development_release_list');
                // Fehlermeldungen
            } catch (Exception $e) {
                $this->getLogger()->warning($e);
                $this->getMessageBag()->add('error', 'error.release.delete');
            }
        // Falls Berechtigung fehlt
        } else {
            $this->getMessageBag()->add('error', 'error.authorisation');
        }

        return $this->redirectToRoute('DemosPlan_forum_development_release_list');
    }

    /**
     * Get a list oof all releases.
     *
     * @DplanPermissions("feature_forum_dev_release_edit")
     *
     * @return RedirectResponse|Response
     */
    #[Route(name: 'DemosPlan_forum_development_release_list', path: '/development/release/list')]
    public function releaseListAction()
    {
        $templateVars = [];
        $storageResult = $this->forumHandler->getReleases();

        // Namen für ReleasePhasen
        $releasePhases = $this->getReleasePhases();
        foreach ($storageResult as $key => $release) {
            if ('' != $release['phase']) {
                $keyOfPhase = $release['phase'];
                $storageResult[$key]['phase'] = $releasePhases[$keyOfPhase]['name'];
            }
        }
        $templateVars['releaseList'] = $storageResult;

        // Ausgabe
        return $this->renderTemplate('@DemosPlanCore/DemosPlanForum/development_release_list.html.twig', [
            'templateVars' => $templateVars,
            'title'        => 'forum.development.release.list',
        ]);
    }

    /**
     * Get all infos and user stories of a release.
     *
     * @DplanPermissions("area_development")
     *
     * @param string $releaseId
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_forum_development_release_detail', path: '/development/{releaseId}')]
    public function releaseDetailAction(CurrentUserInterface $currentUser, $releaseId)
    {
        $templateVars = [];
        $storageResult = $this->forumHandler->getUserStoriesForRelease($releaseId);

        // Own Votes
        foreach ($storageResult['userStories'] as $key => $userStory) {
            $votesOfUserStory = $this->forumHandler->getVotesForUserStory($userStory['ident']);
            $ownVotes = 0;
            $storageResult['userStories'][$key]['ownVotes'] = $ownVotes;
            foreach ($votesOfUserStory['votes'] as $vote) {
                if ($vote['userId'] == $currentUser->getUser()->getId()) {
                    $ownVotes += $vote['numberOfVotes'];
                }
                $storageResult['userStories'][$key]['ownVotes'] = $ownVotes;
            }
            // gesamtzahl der Votes
            $storageResult['userStories'][$key]['numVotes'] = $userStory['onlineVotes'] + $userStory['offlineVotes'];
            // Hole die Anzahl der Beiträge
            $entriesOfUserStory = $this->forumHandler->getThreadEntryList($userStory['threadId']);
            $storageResult['userStories'][$key]['numberOfEntries'] = $entriesOfUserStory['thread']['numberOfEntries'];
        }

        // Namen für ReleasePhasen
        $releasePhases = $this->getReleasePhases();
        if ('' != $storageResult['release']['phase']) {
            $keyOfPhase = $storageResult['release']['phase'];
            $storageResult['release']['phaseName'] = $releasePhases[$keyOfPhase]['name'];
        }

        $templateVars['phasePermissions'] = $this->getReleasePhasePermissions($storageResult['release']['phase']);

        $templateVars['userStoryList'] = $storageResult['userStories'];
        $templateVars['release'] = $storageResult['release'];
        $templateVars['limitForVotes'] = $this->limitForVotes;

        $title = $templateVars['release']['title'];

        // Ausgabe
        return $this->renderTemplate('@DemosPlanCore/DemosPlanForum/development_release_story_list.html.twig', [
            'templateVars' => $templateVars,
            'title'        => $title,
        ]);
    }

    /**
     * Save votes for user stories by release.
     *
     * @DplanPermissions("area_development")
     *
     * @param string $releaseId
     *
     * @return RedirectResponse
     *
     * @throws MessageBagException
     */
    #[Route(name: 'DemosPlan_forum_development_release_voting', path: '/development/{releaseId}/voting')]
    public function saveVotesForUserStoriesAction(
        Request $request,
        TranslatorInterface $translator,
        MessageBagInterface $messageBag,
        $releaseId,
    ) {
        $releaseDetails = $this->forumHandler->getSingleRelease($releaseId);
        $permissions = $this->getReleasePhasePermissions($releaseDetails['phase']);
        // Sollen OfflineVotes gespeichert werden?
        if ($request->request->has('saveVotes') || $request->request->has('resetVotes')) {
            $requestPost = $request->request->all();
            if (isset($requestPost['r_offlineVotes'])) {
                // Phasenrechte überprüfen
                if (!isset($permissions['vote_offline']) || false == $permissions['vote_offline']) {
                    $messageBag->add(
                        'warning',
                        $translator
                        ->trans('warning.phase.voting.not.possible', ['points' => 'Punkte vor Ort'])
                    );

                    return $this->redirectToRoute('DemosPlan_forum_development_release_detail', compact('releaseId'));
                }

                $storageResult = $this->forumHandler->saveOfflineVotesOfUserStories($requestPost['r_offlineVotes']);
                if (isset($storageResult['status']) && true === $storageResult['status']) {
                    // Erfolgsmeldung
                    $this->getMessageBag()->add('confirm', 'confirm.userStory.voting.offline');
                } else {
                    // Fehlermeldungen
                    $this->getMessageBag()->add('warning', 'error.userStory.voting.offline');
                }
            }
            // Sollen Online-Punkte gespeichert werden?
            if (isset($requestPost['r_onlineVotes'])) {
                // Phasenrechte überprüfen
                if (!isset($permissions['vote_online']) || false == $permissions['vote_online']) {
                    $this->getMessageBag()->add(
                        'warning',
                        'warning.phase.voting.not.possible',
                        ['points' => 'Online Punkte']
                    );

                    return $this->redirectToRoute('DemosPlan_forum_development_release_detail', compact('releaseId'));
                }

                // Überprüfe, ob Votes zurückgesetzt werden sollen
                if (array_key_exists('resetVotes', $requestPost)) {
                    // setze Punkte auf null zurück
                    foreach ($requestPost['r_onlineVotes'] as $key => $vote) {
                        $requestPost['r_onlineVotes'][$key] = 0;
                    }
                }
                $storageResult = $this->forumHandler->saveOnlineVotesOfUserStories($releaseId, $requestPost['r_onlineVotes'], $this->limitForVotes);
                if (isset($storageResult['exceededVotes'])) {
                    $this->getMessageBag()->add('warning', 'warning.exceeded.votes', ['difference' => $storageResult['exceededVotes']]);
                } elseif (isset($storageResult['status']) && true === $storageResult['status']) {
                    // Erfolgsmeldung
                    // bei Zurücksetzung der votes
                    if (array_key_exists('resetVotes', $requestPost)) {
                        $this->getMessageBag()->add('confirm', 'confirm.userStory.voting.reset');
                    } else {
                        // bei Speicherung von neuen Votes
                        $this->getMessageBag()->add('confirm', 'confirm.userStory.voting');
                    }
                } else {
                    $this->getMessageBag()->add('error', 'error.userStory.voting');
                }
            }
        }

        return $this->redirectToRoute('DemosPlan_forum_development_release_detail', compact('releaseId'));
    }

    /**
     * Save new user story.
     *
     * @DplanPermissions("feature_forum_dev_story_edit")
     *
     * @param string $releaseId
     *
     * @return RedirectResponse|Response
     */
    #[Route(name: 'DemosPlan_forum_development_userstory_new', path: '/development/{releaseId}/story/new')]
    public function newUserStoryAction(Request $request, $releaseId)
    {
        // Anlegen eines neuen release
        if ($request->request->has('saveNewUserStory')) {
            $requestPost = $request->request->all();
            try {
                $storageResult = $this->forumHandler->newUserStory($releaseId, $requestPost);
            } catch (Exception $e) {
                $this->getLogger()->warning($e);
                $this->getMessageBag()->add('warning', 'warning.story.exist');

                return $this->redirectToRoute('DemosPlan_forum_development_release_list');
            }
            if (true === $storageResult['status']) {
                // Erfolgsmeldung
                $this->getMessageBag()->add('confirm', 'confirm.story.created');

                return $this->redirectToRoute('DemosPlan_forum_development_release_detail', compact('releaseId'));
            }
        }

        $templateVars = [];
        $templateVars['releaseId'] = $releaseId;

        // Ausgabe
        return $this->renderTemplate('@DemosPlanCore/DemosPlanForum/development_release_story_new.html.twig', [
            'templateVars' => $templateVars,
            'title'        => 'forum.development.release.story.new',
        ]);
    }

    /**
     * Update of an user story.
     *
     * @DplanPermissions("feature_forum_dev_story_edit")
     *
     * @param string $releaseId
     * @param string $storyId
     *
     * @return RedirectResponse|Response
     */
    #[Route(name: 'DemosPlan_forum_development_userstory_edit', path: '/development/{releaseId}/story/edit/{storyId}')]
    public function editUserStoryAction(Request $request, $releaseId, $storyId)
    {
        $templateVars = [];
        // Anlegen eines neuen release
        if ($request->request->has('saveUserStory')) {
            $requestPost = $request->request->all();
            try {
                $storageResult = $this->forumHandler->editUserStory($storyId, $requestPost);
            } catch (Exception $e) {
                $this->getLogger()->warning($e);
                $this->getMessageBag()->add('warning', 'warning.entry.missing');

                return $this->redirectToRoute('DemosPlan_forum_development_release_detail', compact('releaseId'));
            }
            if (true === $storageResult['status']) {
                // Erfolgsmeldung
                $this->getMessageBag()->add('confirm', 'confirm.story.updated');

                return $this->redirectToRoute('DemosPlan_forum_development_userstory_detail', compact('storyId'));
            }
        }

        // Hole die Details zur UserStory
        $userStory = $this->forumHandler->getUserStory($storyId);
        $templateVars['userStory'] = $userStory;

        // Bestimme ein Token für die Löschfunktion
        $tokenForDelete = $this->generateToken();
        $templateVars['token'] = $tokenForDelete;

        // Hole Details zum Release für breadcrumb
        $release = $this->forumHandler->getSingleRelease($releaseId);

        // Ausgabe
        return $this->renderTemplate('@DemosPlanCore/DemosPlanForum/development_release_story_edit.html.twig', [
            'templateVars' => $templateVars,
            'title'        => 'forum.development.release.story.edit',
        ]);
    }

    /**
     * Lösche eine User Story.
     *
     * @DplanPermissions("feature_forum_dev_story_edit")
     *
     * @param string $releaseId
     * @param string $storyId
     * @param string $token
     *
     * @return RedirectResponse
     */
    #[Route(name: 'DemosPlan_forum_development_userstory_delete', path: '/development/{releaseId}/story/delete/{storyId}/{token}')]
    public function deleteUserStoryAction($releaseId, $storyId, $token)
    {
        // Token zum Überprüfen erstellen
        $tokenToCheck = $this->generateToken();
        // sind beide Token gleich, dann gehe weiter zum löschen
        if ($tokenToCheck === $token) {
            $storageResult = $this->forumHandler->deleteUserStory($storyId);
            if (true === $storageResult) {
                $this->getMessageBag()->add('confirm', 'confirm.story.deleted');

                return $this->redirectToRoute('DemosPlan_forum_development_release_detail', compact('releaseId'));
            }
        } else {
            $this->getMessageBag()->add('error', 'error.delete');
        }

        return $this->redirectToRoute('DemosPlan_forum_development_userstory_detail', compact('storyId'));
    }

    /**
     * Gebe die Details zu einer UserStory aus.
     *
     * @DplanPermissions("area_development")
     *
     * @param string $storyId
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_forum_development_userstory_detail', path: '/development/story/{storyId}')]
    public function userStoryDetailAction(CurrentUserInterface $currentUser, $storyId)
    {
        $templateVars = [];
        $userId = $currentUser->getUser()->getId();

        // Get all info about user story and its voting
        try {
            $storageResultStory = $this->forumHandler->getVotesForUserStory($storyId);
            // Get the orgaDetails foreach vote and save its name
            foreach ($storageResultStory['votes'] as $key => $vote) {
                $orgaOfVote = $this->forumHandler->getOrgaOfVote($vote['orgaId']);
                $storageResultStory['votes'][$key]['orgaName'] = $orgaOfVote->getName();
            }
        } catch (Exception $e) {
            $this->getLogger()->warning($e);
            $this->getMessageBag()->add('warning', 'warning.story.missing');

            return $this->redirectToRoute('DemosPlan_forum_development_release_list');
        }

        // Get info about Release
        $storageResultRelease = $this->forumHandler->getSingleRelease($storageResultStory['userStory']['releaseId']);

        // Get all entries of userstory
        $storageResultEntries = $this->forumHandler->getThreadEntryList($storageResultStory['userStory']['threadId']);
        foreach ($storageResultEntries['entryList'] as $key => $threadEntry) {
            // Dateien in Bilder und PDFs aufteilen
            if (isset($threadEntry['files']) && !empty($threadEntry['files'])) {
                $imagesAndDocuments = $this->generateImagesAndDocuments($threadEntry['files']);
                $threadEntry['images'] = $imagesAndDocuments['images'];
                $threadEntry['documents'] = $imagesAndDocuments['documents'];
            }
            // Speicher den Rollen-String in einem array ab
            if (isset($threadEntry['userRoles'])) {
                $threadEntry['userRoles'] = explode(',', (string) $threadEntry['userRoles']);
            }

            $threadEntry['editable'] = false;
            if (isset($threadEntry['user'])) {
                // Bearbeitungslimit errechnen
                $threadEntry['limitToEdit'] = $threadEntry['createDate'] / 1000 + (60 * 60);
                // Bearbeitungsmodus weitergeben
                $timeNow = time();
                // Zeit und User vergleichen
                if ($threadEntry['limitToEdit'] > $timeNow && ($threadEntry['user']['ident'] == $userId)) {
                    $threadEntry['editable'] = true;
                }
            }
            $storageResultEntries['entryList'][$key] = $threadEntry;
        }
        $templateVars['votes'] = $storageResultStory['votes'];
        $templateVars['userStory'] = $storageResultStory['userStory'];
        // Sum of online and offline votes
        $templateVars['userStory']['sumVotes'] = $templateVars['userStory']['onlineVotes'] + $templateVars['userStory']['offlineVotes'];
        $templateVars['release'] = $storageResultRelease;
        // phasePermissions
        $templateVars['phasePermissions'] = $this->getReleasePhasePermissions($templateVars['release']['phase']);
        $templateVars['entries'] = $storageResultEntries;

        // Ausgabe
        return $this->renderTemplate('@DemosPlanCore/DemosPlanForum/development_release_story_threadentry_list.html.twig', [
            'templateVars' => $templateVars,
            'title'        => 'forum.development.release.story',
        ]);
    }

    /**
     * Save a new threadEntry for an userStory.
     *
     * @DplanPermissions("area_development")
     *
     * @param string $storyId
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_forum_development_userstory_threadentry_new', path: '/development/{storyId}/entry/new')]
    public function newThreadEntryForUserStoryAction(
        Request $request,
        TranslatorInterface $translator,
        FileUploadService $fileUploadService,
        $storyId,
    ) {
        $templateVars = [];
        $storageResultStory = $this->forumHandler->getUserStory($storyId);

        // Phasenrechte überprüfen
        $storageResultRelease = $this->forumHandler->getSingleRelease($storageResultStory['releaseId']);
        $permission = $this->getReleasePhasePermissions($storageResultRelease['phase']);

        if (false === $permission['new_threadEntry']) {
            $this->getMessageBag()->add('warning', 'warning.new.entry.not.possible');

            return $this->redirectToRoute('DemosPlan_forum_development_userstory_detail', compact('storyId'));
        }

        if ($request->request->has('saveNewEntryForUserStory')) {
            $requestPost = $request->request->all();
            $files = $fileUploadService->prepareFilesUpload($request, 'r_files');
            $requestPost['r_files'] = $files;
            // Übergebe mit die Details zur User Story
            $requestPost['userStory'] = $storageResultStory;
            $storageResult = $this->forumHandler->threadEntryNew($storageResultStory['threadId'], $requestPost);
            // Erfolgsmeldung
            if (true === $storageResult['status']) {
                $this->getMessageBag()->add('confirm', 'confirm.thread.created');

                return $this->redirectToRoute('DemosPlan_forum_development_userstory_detail', compact('storyId'));
            }
            // Fehlermeldungen
            if (array_key_exists('mandatoryfieldwarning', $storageResult)) {
                $this->getMessageBag()->add('error', $storageResult['mandatoryfieldwarning']['message']);
                $templateVars = $requestPost;
            } else {
                $this->getMessageBag()->add('error', 'error.save');
            }
        }

        $templateVars['story'] = $storageResultStory;

        // Ausgabe
        return $this->renderTemplate('@DemosPlanCore/DemosPlanForum/development_release_story_threadentry_new.html.twig', [
            'templateVars' => $templateVars,
            'title'        => 'forum.development.story.threadentry.new',
        ]);
    }

    /**
     * update a threadEntry of user story thread.
     *
     * @DplanPermissions("area_development")
     *
     * @param string $storyId
     * @param string $threadEntryId
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_forum_development_userstory_threadentry_edit', path: '/development/{storyId}/entry/{threadEntryId}/edit')]
    public function editThreadEntryOfUserStoryAction(
        CurrentUserInterface $currentUser,
        FileUploadService $fileUploadService,
        PermissionsInterface $permissions,
        Request $request,
        $storyId,
        $threadEntryId,
    ) {
        $storageResultStory = $this->forumHandler->getUserStory($storyId);
        // Hole die Details zum Beitrag
        try {
            $storageResult = $this->forumHandler->getSingleThreadEntry($threadEntryId);
            $threadEntry = $storageResult;
            // Falls es diesen Beitrags-Id nicht mehr gibt, leite zur Liste zurück
        } catch (Exception $e) {
            $this->getLogger()->warning($e);
            $this->getMessageBag()->add('warning', 'warning.entry.missing');

            return $this->redirectToRoute('DemosPlan_forum_development_userstory_detail', compact('storyId'));
        }

        // Update des Beitrags
        if ($request->request->has('updateThreadEntryForUserStory')) {
            // überprüfe die Rechte zum Editieren
            $threadEntry = $this->forumHandler->checkPermission($threadEntry, $currentUser->getUser(), $permissions);
            if ($threadEntry['editableByUser'] || $threadEntry['editableByModerator']) {
                $requestPost = $request->request->all();
                if (array_key_exists('delete_file', $requestPost)) {
                    try {
                        $this->forumHandler->deleteForumFiles($requestPost['delete_file']);
                    } catch (Exception $e) {
                        $this->getLogger()->warning($e);
                        $this->getMessageBag()->add('warning', 'warning.file.missing');

                        return $this->redirectToRoute(
                            'DemosPlan_forum_development_userstory_detail',
                            compact('storyId')
                        );
                    }
                }
                $files = $fileUploadService->prepareFilesUpload($request, 'r_files');
                $requestPost['r_files'] = $files;
                try {
                    // Übergebe mit die Details zur User Story
                    $requestPost['userStory'] = $storageResultStory;
                    $storageResult = $this->forumHandler->threadEntryEdit($threadEntry, $requestPost);
                    // Fehlermeldungen
                    if (array_key_exists('mandatoryfieldwarning', $storageResult)) {
                        $this->getMessageBag()->add('error', $storageResult['mandatoryfieldwarning']['message']);
                    }
                    // Erfolgsmeldung, leite zur Übersichtsliste der userStory weiter
                    if (isset($storageResult['status']) && true === $storageResult['status']) {
                        $this->getMessageBag()->add('confirm', 'confirm.thread.updated');

                        return $this->redirectToRoute('DemosPlan_forum_development_userstory_detail', compact('storyId'));
                    }
                    // Falls es diese Beitrags-Id nicht mehr gibt, leite zur Liste zurück
                } catch (Exception $e) {
                    $this->getLogger()->warning($e);
                    $this->getMessageBag()->add('warning', 'warning.entry.missing');

                    return $this->redirectToRoute('DemosPlan_forum_development_userstory_detail', compact('storyId'));
                }
            }
        }
        // Dateien in Bilder und PDFs aufteilen
        if (isset($threadEntry['files']) && !empty($threadEntry['files'])) {
            $imagesAndDocuments = $this->generateImagesAndDocuments($threadEntry['files']);
            $threadEntry['images'] = $imagesAndDocuments['images'];
            $threadEntry['documents'] = $imagesAndDocuments['documents'];
        }
        // Bestimme ein Token für die Löschfunktion
        $tokenForDelete = $this->generateToken();

        $templateVars = [];
        $templateVars['threadEntry'] = $threadEntry;
        $templateVars['token'] = $tokenForDelete;
        $templateVars['storyId'] = $storyId;

        // Hole details zum release für die breadcrumb
        $release = $this->forumHandler->getSingleRelease($storageResultStory['releaseId']);

        // Ausgabe
        return $this->renderTemplate('@DemosPlanCore/DemosPlanForum/development_release_story_threadentry_edit.html.twig', [
            'templateVars' => $templateVars,
            'title'        => 'forum.development.story.threadentry.edit',
        ]);
    }

    /**
     * Delete a threadEntry of an user story thread.
     *
     * @DplanPermissions("area_development")
     *
     * @param string $storyId
     * @param string $threadEntryId
     * @param string $token
     *
     * @return RedirectResponse
     */
    #[Route(name: 'DemosPlan_forum_development_userstory_threadentry_delete', path: '/development/{storyId}/entry/{threadEntryId}/delete/{token}')]
    public function deleteThreadEntryOfUserStoryAction(CurrentUserInterface $currentUser, PermissionsInterface $permissions, $storyId, $threadEntryId, $token)
    {
        $storageResult = [];
        try {
            $storageResult = $this->forumHandler->getSingleThreadEntry($threadEntryId);
        } catch (Exception $e) {
            $this->getLogger()->warning($e);
            $noEntry = true;
        }
        if (isset($noEntry) || !isset($storageResult['user'])) {
            $this->getMessageBag()->add('warning', 'warning.entry.missing');

            return $this->redirectToRoute('DemosPlan_forum_development_userstory_detail', compact('storyId'));
        }

        $threadEntry = $storageResult;
        $threadEntry = $this->forumHandler->checkPermission($threadEntry, $currentUser->getUser(), $permissions);

        // Token zum Überprüfen erstellen
        $tokenToCheck = $this->generateToken();

        // sind beide Token gleich und die User berechtigt, dann gehe weiter zum löschen
        if ($tokenToCheck === $token && (true == $threadEntry['editableByUser'] || true == $threadEntry['editableByModerator'])) {
            $storageResult = $this->forumHandler->threadEntryUpdateWithDeletedPlaceholder($threadEntry);
            if (true === $storageResult['status']) {
                $this->getMessageBag()->add('confirm', 'confirm.thread.deleted');

                return $this->redirectToRoute('DemosPlan_forum_development_userstory_detail', compact('storyId'));
            } else {
                $this->getMessageBag()->add('error', 'error.delete');

                return $this->redirectToRoute('DemosPlan_forum_development_userstory_detail', compact('storyId'));
            }
        }

        $this->getMessageBag()->add('error', 'error.authorisation');

        return $this->redirectToRoute(
            'DemosPlan_forum_development_userstory_threadentry_edit',
            compact('storyId', 'threadEntryId')
        );
    }

    /**
     * Exportiere alle Infos zu einem Release im CSV-Format.
     *
     * @DplanPermissions("feature_forum_dev_release_edit")
     *
     * @return RedirectResponse|Response
     */
    #[Route(name: 'DemosPlan_forum_development_release_export', path: '/development/{releaseId}/export')]
    public function exportReleaseAction(
        CsvHelper $csvHelper,
        Environment $twig,
        NameGenerator $nameGenerator,
        string $releaseId,
    ) {
        $storageResult = $this->forumHandler->getUserStoriesForRelease($releaseId);

        // Namen für ReleasePhasen
        $releasePhases = $this->getReleasePhases();
        if ('' != $storageResult['release']['phase']) {
            $keyOfPhase = $storageResult['release']['phase'];
            $storageResult['release']['phaseName'] = $releasePhases[$keyOfPhase]['name'];
        }

        foreach ($storageResult['userStories'] as $key => $userStory) {
            // bereinige die Variablen von html-Tags
            if (isset($userStory['description'])) {
                $userStory['description'] = strip_tags((string) $userStory['description']);
                $storageResult['userStories'][$key]['description'] = $userStory['description'];
            }
            // Variable für Gesamtsumme der Votes
            $storageResult['userStories'][$key]['voteSum'] = $userStory['onlineVotes'] + $userStory['offlineVotes'];
            $votesOfUserStory['votes'] = [];
            if (isset($userStory['onlineVotes']) && (0 < $userStory['onlineVotes'])) {
                $votesOfUserStory = $this->forumHandler->getVotesForUserStory($userStory['ident']);
                // Get the orgaDetails foreach vote and save its name
                foreach ($votesOfUserStory['votes'] as $keyVote => $vote) {
                    $orgaOfVote = $this->forumHandler->getOrgaOfVote($vote['orgaId']);
                    $votesOfUserStory['votes'][$keyVote]['orgaName'] = $orgaOfVote->getName();
                }
            }
            $storageResult['userStories'][$key]['votes'] = $votesOfUserStory['votes'];
        }

        $part = $storageResult['release']['title'];
        $templateVars = $storageResult;
        $templateVars['exportDate'] = date('d.m.Y');

        // set csv Escaper
        $twig->getExtension(EscaperExtension::class)->setEscaper(
            'csv',
            fn ($twigEnv, $string, $charset) => str_replace('"', '""', (string) $string)
        );

        $response = $this->renderTemplate('@DemosPlanCore/DemosPlanForum/development_release_export.csv.twig', [
            'templateVars' => $templateVars,
            'title'        => 'forum.development.story.threadentry.delete',
        ]);

        return $csvHelper->prepareCsvResponse($response, $part, $nameGenerator);
    }
}
