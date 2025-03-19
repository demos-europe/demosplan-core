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
use demosplan\DemosPlanCoreBundle\Exception\DuplicatedTagTitleException;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\FileUploadService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Traits\CanTransformRequestVariablesTrait;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_key_exists;

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
    #[Route(name: 'DemosPlan_statement_administration_tag', path: '/verfahren/{procedure}/tag/{tag}', defaults: ['master' => false], options: ['expose' => true])]
    public function tagViewAction(
        ProcedureService $procedureService,
        Request $request,
        StatementHandler $statementHandler,
        TranslatorInterface $translator,
        string $procedure,
        string $tag,
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
        if (\array_key_exists('r_boilerplateId', $requestPost)) {
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
        TranslatorInterface $translator,
        CurrentProcedureService $currentProcedureService,
        string $procedure,
    ): Response {
        $templateVars = [];

        $templateVars['procedure'] = $procedure;
        $title = $translator->trans('tag.administration');
        $templateVars['procedureTemplate'] = $currentProcedureService->getProcedure()?->getMaster() ?? false;

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
     * Creates a list of Tags for the given procedure by
     * parsing a csv import file.
     *
     * @DplanPermissions("area_admin_statements_tag")
     *
     * @return RedirectResponse
     *
     * @throws Exception
     */
    #[Route(
        path: '/verfahren/{procedureId}/schlagworte/import/csv',
        name: 'DemosPlan_statement_administration_tags_csv_import',
        options: ['expose' => true],
        defaults: ['master' => false]
    )]
    public function tagListCsvImportAction(
        FileService $fileService,
        FileUploadService $fileUploadService,
        Request $request,
        StatementHandler $statementHandler,
        string $procedureId,
    ): Response {
        $anchor = '';
        $requestPost = $this->transformRequestVariables($request->request->all());
        $requestPost['r_importCsv'] = $fileUploadService->prepareFilesUpload($request, 'r_importCsv');

        // Check if we need to import tags
        if (array_key_exists('r_import', $requestPost)) {
            if ('' !== $requestPost['r_importCsv']) {
                try {
                    $fileInfo = $fileService->getFileInfoFromFileString($requestPost['r_importCsv']);
                    $statementHandler->importTags($procedureId, $fileService->getFileContentStream($fileInfo));
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
                ['procedure' => $procedureId]
            ).'#'.$anchor
        );
    }
}
