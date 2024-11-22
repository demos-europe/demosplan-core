<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Statement;

use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Exception\DuplicatedTagTitleException;
use demosplan\DemosPlanCoreBundle\Exception\DuplicatedTagTopicTitleException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\TagTopicNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\FileUploadService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\TagService;
use demosplan\DemosPlanCoreBundle\Traits\CanTransformRequestVariablesTrait;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use function array_key_exists;
use function strlen;
use function trim;
use function is_array;

class DemosPlanStatementTagController extends DemosPlanStatementController
{
    use CanTransformRequestVariablesTrait;

    /**
     * Renders the admin view of a single tag.
     *
     * @DplanPermissions("area_admin_statements_tag")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_statement_administration_tag', path: '/verfahren/{procedure}/tag/{tag}', defaults: ['master' => false])]
    public function tagViewAction(
        ProcedureService $procedureService,
        Request $request,
        StatementHandler $statementHandler,
        TranslatorInterface $translator,
        string $procedure,
        string $tag
    ): Response {
        $requestPost = $request->request->all();
        $data = [];

        $data['action'] = null;
        if (array_key_exists('r_attachmode', $requestPost)) {
            $data['action'] = $requestPost['r_attachmode'];
        }
        $data['tagTitle'] = null;
        if (array_key_exists('r_tagTitle', $requestPost)) {
            $data['tagTitle'] = $requestPost['r_tagTitle'];
        }
        $data['boilerplateTitle'] = null;
        if (array_key_exists('r_boilerplateTitle', $requestPost)) {
            $data['boilerplateTitle'] = $requestPost['r_boilerplateTitle'];
        }
        $data['boilerplateText'] = null;
        if (array_key_exists('r_boilerplateText', $requestPost)) {
            $data['boilerplateText'] = $requestPost['r_boilerplateText'];
        }
        $data['boilerplateId'] = null;
        if (array_key_exists('r_boilerplateId', $requestPost)) {
            $data['boilerplateId'] = $requestPost['r_boilerplateId'];
        }
        $statementHandler->handleTagBoilerplate($tag, $data, $procedure);
        $templateVars = [];
        $tagEntity = $statementHandler->getTag($tag);
        if (null === $tagEntity) {
            $message = $translator->trans('error.tag.notfound');
            $this->logger->warning($message, ['tagId' => $tag]);
            $this->getMessageBag()->add('error', $message);

            return $this->redirectToRoute('DemosPlan_statement_administration_tags', ['procedure' => $procedure]);
        }
        $templateVars['tag'] = $tagEntity;
        $templateVars['boilerplates'] = $procedureService->getBoilerplateList($procedure);
        if (null !== $data['action']) {
            $this->getMessageBag()->add('confirm', 'confirm.tag.edited');

            return $this->redirectToRoute('DemosPlan_statement_administration_tags', ['procedure' => $procedure]);
        }

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanStatement/edit_tag.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'tag.administration_single',
                'procedure'    => $procedure,
                'tag'          => $tag,
            ]
        );
    }

    /**
     * List of Tags that are being used in this procedures.
     *
     * @DplanPermissions("area_admin_statements_tag")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_statement_administration_tags', path: '/verfahren/{procedure}/schlagworte', defaults: ['master' => false], options: ['expose' => true])]
    public function tagListAction(
        StatementHandler $statementHandler,
        TranslatorInterface $translator,
        string $procedure
    ): Response {
        $templateVars = [];
        $topics = $statementHandler->getTopicsByProcedure($procedure);

        $templateVars['topics'] = $topics;
        $templateVars['procedure'] = $procedure;
        $title = $translator->trans('tag.administration');

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanStatement/list_tags.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => $title,
                'procedure'    => $procedure,
            ]
        );
    }

    /**
     * Edit Tags and topics that are being used in this procedures.
     *
     * @DplanPermissions("area_admin_statements_tag")
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(name: 'DemosPlan_statement_administration_tags_edit', path: '/verfahren/{procedure}/schlagworte/edit', defaults: ['master' => false])]
    public function tagListEditAction(
        FileUploadService $fileUploadService,
        Request $request,
        StatementHandler $statementHandler,
        TagService $tagservivce,
        string $procedure
    ): Response {
        $anchor = '';
        $requestPost = $this->transformRequestVariables($request->request->all());
        $requestPost['r_importCsv'] = $fileUploadService->prepareFilesUpload($request, 'r_importCsv');

        // Check if we triggered creating a new Topic
        if (array_key_exists('r_create', $requestPost)
            && array_key_exists('r_newTopic', $requestPost)
        ) {
            if (0 < strlen(trim((string) $requestPost['r_newTopic']))) {
                try {
                    $result = $statementHandler->createTopic($requestPost['r_newTopic'], $procedure);
                    $this->getMessageBag()->add('confirm', 'confirm.topic.created');
                    $anchor = $result->getId();
                } catch (DuplicatedTagTopicTitleException) {
                    $this->getMessageBag()->add('warning', 'topic.create.duplicated.title');
                }
            } else {
                $this->getMessageBag()->add('warning', 'warning.topic.empty');
            }
        }

        // Check if we need to create some Tags
        if (array_key_exists('r_createtags', $requestPost)
            && array_key_exists($requestPost['r_createtags'], $requestPost)
            && array_key_exists('r_newtags', $requestPost[$requestPost['r_createtags']])
        ) {
            $newTag = $requestPost[$requestPost['r_createtags']]['r_newtags'];
            if (0 < strlen(trim((string) $newTag))) {
                try {
                    $result = $statementHandler->createTagFromTopicId($requestPost['r_createtags'], $newTag, $procedure);
                    $this->getMessageBag()->add('confirm', 'confirm.tag.created');
                    $anchor = $result->getId();
                } catch (DuplicatedTagTitleException $e) {
                    $this->getMessageBag()->add('error', 'error.import.tag.name.taken', ['tagTitle' => $e->getTagTitle(), 'topicName' => $e->getTopic()->getTitle()]);
                } catch (TagTopicNotFoundException $e) {
                    $this->getMessageBag()->add('error', 'error.topic.notfound');
                    $this->logger->error('error.topic.notfound', [$e]);
                } catch (Exception $e) {
                    $this->getMessageBag()->add('error', 'error.tag.add');
                    $this->logger->error('error.tag.add', [$e]);
                }
            } else {
                $this->getMessageBag()->add('warning', 'warning.tag.empty');
            }
        }

        // Check if we triggered a rename-action
        if (array_key_exists('r_renametopic', $requestPost)
            && array_key_exists($requestPost['r_renametopic'], $requestPost)
            && array_key_exists('r_rename', $requestPost[$requestPost['r_renametopic']])) {
            $result = $statementHandler->renameTopic(
                $requestPost['r_renametopic'],
                $requestPost[$requestPost['r_renametopic']]['r_rename']
            );
            if ($result instanceof TagTopic) {
                $this->getMessageBag()->add('confirm', 'confirm.topic.renamed');
                $anchor = $result->getId();
            } else {
                $this->getMessageBag()->add('warning', 'warning.topic.renamed');
            }
        }

        // Check if the checkbox for the topicalTag has been checked
        $tagToCheck = $requestPost[$requestPost['r_topicalTag']] ?? null;
        if (null !== $tagToCheck) {
            $isTopicalTag = (bool) ($tagToCheck['r_tag_changeTopicalTag'] ?? false);
            $tagId = $requestPost['r_topicalTag'];
            try {
                $tagservivce->updateTagTopicalTag($tagId, $isTopicalTag);
                $this->getMessageBag()->add('confirm', 'Checkbox erfolgreich aktualisiert');
            } catch (InvalidArgumentException $e) {
                $this->getMessageBag()->add('warning', 'Fehler beim updaten der Checkbox');
                $this->logger->error('An error occurred trying to update a isTopical checkbox for a Tag', [$e]);
            }
        }


        if (array_key_exists('r_topicalTag', $requestPost)
            && array_key_exists($requestPost['r_topicalTag'], $requestPost)
            && array_key_exists('r_tag_changeTopicalTag', $requestPost[$requestPost['r_topicalTag']])
        ) {
            $tagname = $requestPost[$requestPost['r_tag_changeTopicalTag']]['r_topicalTag'] ?? '';
            $result = $tagservivce->updateTagTopicalTag($requestPost['r_topicalTag'], $requestPost[$requestPost['r_tag_changeTopicalTag']]['r_topicalTag']);
            if ($result instanceof Tag) {
                $this->getMessageBag()->add('confirm', 'confirm.tag.topicalTag.update', ['title' => $tagname]);
                $anchor = $result->getId();
            } else {
                $this->getMessageBag()->add('warning', 'warning.tag.topicalTag.update', ['title' => $tagname]);
            }
        }

        // Check if we triggered a rename-tag-action
        if (array_key_exists('r_renametag', $requestPost)
            && array_key_exists($requestPost['r_renametag'], $requestPost)
            && array_key_exists('r_tag_newname', $requestPost[$requestPost['r_renametag']])
            && 0 < strlen(trim((string) $requestPost[$requestPost['r_renametag']]['r_tag_newname']))
        ) {
            $result = $statementHandler->renameTag($requestPost['r_renametag'], $requestPost[$requestPost['r_renametag']]['r_tag_newname']);
            if ($result instanceof Tag) {
                $this->getMessageBag()->add('confirm', 'confirm.tag.renamed');
                $anchor = $result->getId();
            } else {
                $this->getMessageBag()->add('warning', 'warning.tag.renamed');
            }
        }

        // Check if a delete-action has been triggered
        if (array_key_exists('r_deletetag', $requestPost)
            && array_key_exists($requestPost['r_deletetag'], $requestPost)) {
            $result = false;
            $tagname = $requestPost[$requestPost['r_deletetag']]['r_tag_newname'] ?? '';

            try {
                if ($statementHandler->isTagInUse($requestPost['r_deletetag'])) {
                    $this->getMessageBag()->add('warning', 'warning.tag.in.use', ['tagname' => $tagname]);
                } else {
                    $result = $statementHandler->deleteTag($requestPost['r_deletetag']);
                }
            } catch (Exception) {
                $this->getMessageBag()->add('error', 'warning.tag.deleted', ['tagname' => $tagname]);
            }

            if (true === $result) {
                $this->getMessageBag()->add('confirm', 'confirm.tag.deleted', ['title' => $tagname]);
            } else {
                $this->getMessageBag()->add('warning', 'warning.tag.deleted', ['tagname' => $tagname]);
            }
        }
        // delete Tags by checkbox
        if (array_key_exists('r_delete', $requestPost)) {
            $success = true;
            $selected = 0;
            foreach ($requestPost as $key => $val) {
                if (is_array($val) && array_key_exists('r_selected', $val)) {
                    $result = false;
                    if (array_key_exists('r_itemtype', $val) && 'tag' === $val['r_itemtype']) {
                        try {
                            if ($statementHandler->isTagInUse($key)) {
                                $this->getMessageBag()->add('warning', 'warning.tag.in.use', ['tagname' => $val['r_tag_newname']]);
                            } else {
                                $result = $statementHandler->deleteTag($key);
                            }
                        } catch (InvalidArgumentException) {
                            // Tag may be deleted already if topic is deleted before
                            $result = true;
                        } catch (Exception) {
                            $this->getMessageBag()->add('error', 'warning.tag.deleted', ['tagname' => $val['r_tag_newname']]);
                            $success = false;
                            continue;
                        }
                    } elseif (array_key_exists('r_itemtype', $val) && 'topic' === $val['r_itemtype']) {
                        try {
                            if ($statementHandler->isTopicInUse($key)) {
                                $this->getMessageBag()->add('warning', 'warning.topic.in.use', ['topicname' => $val['r_rename']]);
                            } else {
                                $result = $statementHandler->deleteTopic($key);
                            }
                        } catch (Exception) {
                            $this->getMessageBag()->add('error', 'warning.topic.deleted', ['topicname' => $val['r_rename']]);
                            $success = false;
                            continue;
                        }
                    }
                    if (false === $result) {
                        $success = false;
                    }
                    ++$selected;
                }
            }
            if (true === $success && 0 < $selected) {
                $this->getMessageBag()->add('confirm', 'confirm.entries.deleted');
            } elseif (0 === $selected) {
                $this->getMessageBag()->add('warning', 'explanation.entries.noneselected');
            } else {
                $this->getMessageBag()->add('error', 'warning.entries.deleted');
            }
        }

        // Check if a move-action has been triggered
        if (array_key_exists('r_move', $requestPost)
            && array_key_exists($requestPost['r_move'], $requestPost)
            && array_key_exists('r_moveto', $requestPost[$requestPost['r_move']])) {
            $result = $statementHandler->moveTagToTopic($requestPost[$requestPost['r_move']]['r_moveto'], $requestPost['r_move']);
            if (true === $result) {
                $this->getMessageBag()->add('confirm', 'confirm.tag.moved');
            } else {
                $this->getMessageBag()->add('warning', 'warning.tag.moved');
            }
        }

        // Check if we need to delete a topic
        if (array_key_exists('r_deletetopic', $requestPost) && array_key_exists($requestPost['r_deletetopic'], $requestPost)) {
            $topicname = $requestPost[$requestPost['r_deletetopic']]['r_rename'];
            $result = $statementHandler->deleteTopic($requestPost['r_deletetopic']);
            if (true === $result) {
                $this->getMessageBag()->add('confirm', 'confirm.topic.deleted');
            } else {
                $this->getMessageBag()->add('warning', 'warning.topic.deleted', ['topicname' => $topicname]);
            }
        }

        // Check if we need to import tags
        if (array_key_exists('r_import', $requestPost)) {
            if (array_key_exists('r_importCsv', $requestPost) && '' != $requestPost['r_importCsv']) {
                try {
                    $statementHandler->importTags($procedure, $requestPost['r_importCsv']);
                    $this->getMessageBag()->add('confirm', 'explanation.import.topicsAndTags');
                } catch (DuplicatedTagTitleException $e) {
                    $this->getMessageBag()->add('error', 'error.import.tag.name.taken', ['tagTitle' => $e->getTagTitle(), 'topicName' => $e->getTopic()->getTitle()]);
                } catch (Exception $e) {
                    $this->getMessageBag()->add('error', 'error.tag.add');
                    $this->logger->error('error.tag.add', [$e]);
                }
            } else {
                $this->getMessageBag()->add('warning', 'explanation.file.noupload');
            }
        }

        return $this->redirect(
            $this->generateUrl(
                'DemosPlan_statement_administration_tags',
                ['procedure' => $procedure]
            ).'#'.$anchor
        );
    }
}
