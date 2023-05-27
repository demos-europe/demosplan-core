<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Faq;

use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\Faq;
use demosplan\DemosPlanCoreBundle\Entity\FaqCategory;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\FaqNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\Faq\FaqHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use demosplan\DemosPlanCoreBundle\Services\Breadcrumb\Breadcrumb;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Ausgabe Faqeiten.
 */
class FaqController extends BaseController
{
    /**
     * Displays a list of Faq Articles visible to the current user (all categories).
     *
     * @throws Exception
     *
     *
     * @DplanPermissions("area_demosplan")
     */
    #[Route(path: '/faq', name: 'DemosPlan_faq', options: ['expose' => true])]
    #[Route(path: '/haeufigefragen', name: 'DemosPlan_haeufigefragen')]
    public function faqListAction(
        Breadcrumb $breadcrumb,
        CurrentUserInterface $currentUser,
        FaqHandler $faqHandler,
        TranslatorInterface $translator
    ): Response {
        $categories = $faqHandler->getCustomFaqCategoriesByNamesOrCustom(FaqCategory::FAQ_CATEGORY_TYPES_MANDATORY);
        $templateVars = [
            'list' => $faqHandler->convertIntoTwigFormat($categories, $currentUser->getUser()),
        ];

        // generate breadcrumb items
        $breadcrumb->addItem(
            [
                'title' => $translator->trans('misc.information', [], 'page-title'),
                'url'   => $this->generateUrl('DemosPlan_misccontent_static_information'),
            ]
        );

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanFaq/faqlist.html.twig',
            [
                'templateVars' => $templateVars,
                'procedure'    => null,
                'title'        => 'faq.list',
            ]
        );
    }

    /**
     * Displays a list of Faq Articles visible to the current user (only one category, based on route).
     *
     *
     * @DplanPermissions("area_demosplan")
     *
     * @param string $type
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     */
    #[Route(path: '/faq/bauleitplanung', name: 'DemosPlan_faq_public_planning', defaults: ['type' => 'oeb_bauleitplanung'])]
    #[Route(path: '/faq/projekt', name: 'DemosPlan_faq_public_project', defaults: ['type' => 'oeb_bob'])]
    public function faqPublicListAction(
        CurrentUserService $currentUserService,
        FaqHandler $faqHandler,
        $type
    ) {
        $user = $currentUserService->getUser();

        $categoryTypeName = $type;
        $faqCategory = $faqHandler->findFaqCategoryByType($categoryTypeName);
        $faqList = $faqHandler->getEnabledFaqList($faqCategory, $user);

        $templateCategories = [];
        if (0 !== count($faqList)) {
            $templateCategories[$categoryTypeName] = [
                'faqlist' => $faqList,
                'label'   => $faqList[0]->getCategory()->getTitle(),
            ];
        }

        $templateVars['list'] = $templateCategories;

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanFaq/public_faqlist.html.twig',
            [
                'templateVars' => $templateVars,
                'procedure'    => null,
                'title'        => 'faq.public',
            ]
        );
    }

    /**
     * Admin list of FAQs.
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     *
     *
     * @DplanPermissions("area_admin_faq")
     */
    #[Route(path: '/faq/verwalten', name: 'DemosPlan_faq_administration_faq', options: ['expose' => true])]
    public function faqAdminListAction(
        Request $request,
        FaqHandler $faqHandler,
        GlobalConfig $globalConfig,
        string $procedure = null
    ) {
        $requestPost = $request->request->all();

        if (array_key_exists('faq_delete', $requestPost)) {
            try {
                $faqId = $requestPost['faq_delete'];
                $faqToDelete = $faqHandler->getFaq($faqId);
                if (null === $faqToDelete) {
                    throw FaqNotFoundException::createFromId($faqId);
                }
                $faqHandler->deleteFaq($faqToDelete);
                $this->getMessageBag()->add('confirm', 'confirm.entries.marked.deleted');
            } catch (Exception $e) {
                $this->logger->log('error', 'Failed to delete FAQ', [$e]);
                $this->getMessageBag()->add('error', 'error.delete');
            }
        }

        if (array_key_exists('manualsort', $requestPost)) {
            $storageResult = $faqHandler->setManualSort(
                $requestPost['categoryId'],
                $requestPost['manualsort']
            );

            if ($storageResult) {
                $this->getMessageBag()->add('confirm', 'confirm.sort.saved');
            }
        }

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanFaq/faq_admin_list.html.twig',
            [
                'procedure'                 => $procedure,
                'title'                     => 'faq.admin',
                'roleGroupsFaqVisibility'   => $globalConfig->getRoleGroupsFaqVisibility(),
            ]
        );
    }

    /**
     * Gib das Editformular der Faq aus.
     *
     * @return RedirectResponse|Response
     *
     * @throws Exception
     *
     *
     * @DplanPermissions("area_admin_faq")
     */
    #[Route(path: '/faq/{faqID}/edit', name: 'DemosPlan_faq_administration_faq_edit', options: ['expose' => true])]
    public function faqAdminEditAction(
        Breadcrumb $breadcrumb,
        GlobalConfig $globalConfig,
        Request $request,
        string $faqID,
        TranslatorInterface $translator,
        FaqHandler $faqHandler
    ) {
        $faq = $faqHandler->getFaq($faqID);
        if (!$faq instanceof Faq) {
            $this->getMessageBag()->add('error', 'error.faq.not.found');

            return $this->redirectToRoute('DemosPlan_faq_administration_faq');
        }

        $requestPost = $request->request->all();

        if (false === empty($requestPost['action']) && 'faqedit' === $requestPost['action']) {
            $inData = $this->prepareIncomingData($request, 'faq_edit');
            // Wenn Gast ausgewählt wurde, sollen es auch gleichzeitig Bürger sehen
            if (isset($inData['r_group_code']) && in_array(Role::GGUEST, $inData['r_group_code'])) {
                $inData['r_group_code'][] = Role::GCITIZ;
            }

            if (null !== $inData) {
                $updatedFaq = $faqHandler->addOrUpdateFaq($inData, $faq);

                if ($updatedFaq instanceof Faq) {
                    $this->getMessageBag()->add('confirm', 'confirm.faq.updated');

                    return $this->redirectToRoute('DemosPlan_faq_administration_faq');
                }
            }
        }

        $breadcrumb->addItem(
            [
                'title' => $translator->trans('faq.list', [], 'page-title'),
                'url'   => $this->generateUrl('DemosPlan_faq'),
            ]
        );

        $categoryTypeNames = FaqCategory::FAQ_CATEGORY_TYPES_MANDATORY;

        $templateVars = [
            'categories'                => $faqHandler->getCustomFaqCategoriesByNamesOrCustom($categoryTypeNames),
            'faq'                       => $faq,
            'roleGroupsFaqVisibility'   => $globalConfig->getRoleGroupsFaqVisibility(),
        ];

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanFaq/faq_admin_edit.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'faq.admin.edit',
            ]
        );
    }

    /**
     * Gib das Faq anlegen formular aus.
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     * @throws CustomerNotFoundException
     *
     *
     * @DplanPermissions("area_admin_faq")
     */
    #[Route(path: '/faq/neu', name: 'DemosPlan_faq_administration_faq_new', options: ['expose' => true])]
    public function faqAdminNewAction(
        Breadcrumb $breadcrumb,
        FaqHandler $faqHandler,
        GlobalConfig $globalConfig,
        Request $request,
        TranslatorInterface $translator,
        string $procedure = null
    ) {
        $templateVars['procedure'] = $procedure;
        $requestPost = $request->request->all();

        if (!empty($requestPost['action']) && 'faqnew' === $requestPost['action']) {
            $inData = $this->prepareIncomingData($request, 'faq_new');
            // Wenn Gast ausgewählt wurde, sollen es auch gleichzeitig Bürger sehen
            if (isset($inData['r_group_code']) &&
                in_array(Role::GGUEST, $inData['r_group_code'], true)
            ) {
                $inData['r_group_code'][] = Role::GCITIZ;
            }
            // Storage Formulardaten übergeben
            if (null !== $inData) {
                $newFaq = $faqHandler->addOrUpdateFaq($inData);

                // Wenn Storage erfolgreich: zurueck zur Liste
                if ($newFaq instanceof Faq) {
                    $this->getMessageBag()->add('confirm', 'faq.created');

                    return $this->redirectToRoute('DemosPlan_faq_administration_faq');
                } else {
                    return $this->redirectToRoute('DemosPlan_faq_administration_faq_new');
                }
            }
        }

        // reichere die breadcrumb mit extraItem an
        $breadcrumb->addItem(
            [
                'title' => $translator->trans('faq.list', [], 'page-title'),
                'url'   => $this->generateUrl('DemosPlan_faq'),
            ]
        );

        $categoryTypeNames = FaqCategory::FAQ_CATEGORY_TYPES_MANDATORY;

        $templateVars = [
            'categories'                => $faqHandler->getCustomFaqCategoriesByNamesOrCustom($categoryTypeNames),
            'roleGroupsFaqVisibility'   => $globalConfig->getRoleGroupsFaqVisibility(),
        ];

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanFaq/faq_admin_edit.html.twig',
            [
                'templateVars' => $templateVars,
                'procedure'    => $procedure,
                'title'        => 'faq.admin.new',
            ]
        );
    }

    /**
     * Bereite die einkommenden Daten vor.
     *
     * @param string $action
     */
    private function prepareIncomingData(Request $request, $action): array
    {
        $result = [];

        $incomingFields = [
            'faq_new'    => [
                'action',
                'r_title',
                'r_text',
                'r_enable',
                'r_group_code',
                'r_category_id',
            ],
            'faq_delete' => [
                'action',
                'faq_delete',
            ],
            'faq_edit'   => [
                'action',
                'r_ident',
                'r_title',
                'r_text',
                'r_enable',
                'r_group_code',
                'r_category_id',
            ],
            'show'       => [
                'action',
                'r_category_title',
            ],
            'delete'     => [
                'action',
            ],
        ];

        $request = $request->request->all();

        foreach ($incomingFields[$action] as $key) {
            if (array_key_exists($key, $request)) {
                $result[$key] = $request[$key];
            }
        }

        return $result;
    }

    /**
     * @DplanPermissions("area_admin_faq")
     *
     *
     * @param string $categoryId
     * @param string $action
     *
     * @return RedirectResponse|Response
     *
     * @throws CustomerNotFoundException
     * @throws MessageBagException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    #[Route(path: '/category/new', name: 'DemosPlan_faq_administration_category_new', options: ['expose' => true])]
    #[Route(path: '/category/{categoryId}/edit', name: 'DemosPlan_faq_administration_category_edit', options: ['expose' => true])]
    public function faqCategoryEditAction(
        Breadcrumb $breadcrumb,
        FaqHandler $faqHandler,
        Request $request,
        TranslatorInterface $translator,
        $categoryId = '',
        $action = 'show'
    ) {
        $administrateFaq = 'DemosPlan_faq_administration_faq';
        $templateVars = ['category' => new FaqCategory()];
        $inData = $this->prepareIncomingData($request, $action);
        $dataGiven = (false === empty($inData));
        $isIdGiven = ('' != $categoryId);

        if ($dataGiven) {
            $action = $isIdGiven ? 'update' : 'create';
        }
        $faqCategory = '';
        if ($isIdGiven) {
            $faqCategory = $faqHandler->getFaqCategory($categoryId);
        }

        switch ($action) {
            case 'show':
                $category = $isIdGiven ? $faqCategory : ['title' => '', 'id' => ''];
                $templateVars = ['category' => $category];
                break;

            case 'create':
                $resultCategory = $faqHandler->createFaqCategory($inData);
                if ($resultCategory instanceof FaqCategory) {
                    $this->getMessageBag()->add('confirm', 'confirm.category.created');

                    return $this->redirectToRoute($administrateFaq);
                }
                break;

            case 'update':
                $inData['id'] = $categoryId;
                $faqCategory->setTitle($inData['r_category_title']);
                $resultCategory = $faqHandler->updateFaqCategory($faqCategory);
                if ($resultCategory instanceof FaqCategory) {
                    $this->getMessageBag()->add('confirm', 'confirm.category.updated');

                    return $this->redirectToRoute($administrateFaq);
                }
                break;

            default:
                // showEmpty:
                $templateVars = ['category' => ['title' => '', 'id' => '']];
        }

        $breadcrumb->addItem(
            [
                'title' => $translator->trans('faq.list', [], 'page-title'),
                'url'   => $this->generateUrl('DemosPlan_faq'),
            ]
        );

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanFaq/faq_admin_category_edit.html.twig',
            [
                'templateVars' => $templateVars,
                'title'        => 'category.admin',
            ]
        );
    }

    /**
     * @DplanPermissions("area_admin_faq")
     *
     *
     * @return RedirectResponse|Response
     * @throws Exception
     */
    #[Route(path: '/category/{categoryId}/delete', name: 'DemosPlan_faq_administration_category_delete', options: ['expose' => true, 'action' => 'delete'])]
    public function faqCategoryDeleteAction(FaqHandler $faqHandler, string $categoryId): Response
    {
        $administrateFaq = 'DemosPlan_faq_administration_faq';

        $faqCategory = $faqHandler->getFaqCategory($categoryId);
        if (!$faqCategory instanceof FaqCategory) {
            $this->getMessageBag()->add('error', 'category.not.found');

            return $this->redirectToRoute($administrateFaq);
        }
        $faqHandler->deleteFaqCategory($faqCategory);

        return $this->redirectToRoute($administrateFaq);
    }
}
