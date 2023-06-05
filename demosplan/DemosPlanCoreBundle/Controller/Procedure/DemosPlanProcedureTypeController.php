<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Controller\Procedure;

use demosplan\DemosPlanCoreBundle\Annotation\DplanPermissions;
use demosplan\DemosPlanCoreBundle\Controller\Base\BaseController;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureBehaviorDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureType;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureUiDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\StatementFormDefinition;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\ResourceNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Form\ProcedureTypeFormType;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\EntityFetcher;
use demosplan\DemosPlanCoreBundle\Logic\EntityWrapperFactory;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureTypeService;
use demosplan\DemosPlanCoreBundle\Logic\ResourcePersister;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ProcedureBehaviorDefinitionResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ProcedureTypeResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ProcedureUiDefinitionResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementFieldDefinitionResourceType;
use demosplan\DemosPlanCoreBundle\Services\Breadcrumb\Breadcrumb;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\Query\QueryException;
use EDT\Wrapping\Contracts\AccessException;
use Exception;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class DemosPlanProcedureTypeController extends BaseController
{
    /**
     * @DplanPermissions({"area_procedure_type_edit"})
     *
     * @throws QueryException
     * @throws UserNotFoundException
     */
    #[Route(name: 'DemosPlan_procedureType_list', path: 'verfahrenstypen', methods: ['GET'])]
    public function procedureTypeListAction(
        ProcedureTypeService $procedureTypeService): Response
    {
        $procedureTypeResources = $procedureTypeService->getAllProcedureTypeResources();

        return $this->renderTemplate(
            '@DemosPlanCore/DemosPlanProcedure/administration_procedure_type_list.html.twig',
            [
                'templateVars' => [
                    'procedureTypes' => $procedureTypeResources,
                ],
                'title'        => 'procedure.types',
            ]
        );
    }

    /**
     * @DplanPermissions({"area_procedure_type_edit"})
     *
     * @throws NonUniqueResultException
     * @throws QueryException
     * @throws ResourceNotFoundException
     * @throws UserNotFoundException
     */
    #[Route(name: 'DemosPlan_procedureType_create_select', path: 'verfahrenstypen/auswahl', methods: ['GET'])]
    public function procedureTypeCreateBaseSelectAction(
        Breadcrumb $breadcrumb,
        FormFactoryInterface $formFactory,
        ProcedureTypeService $procedureTypeService,
        TranslatorInterface $translator
    ): Response {
        $template = '@DemosPlanCore/DemosPlanProcedure/administration_procedure_type_edit.html.twig';
        $procedureTypeResources = $procedureTypeService->getAllProcedureTypeResources();

        $form = $this->getForm(
            $formFactory,
            null,
            ProcedureTypeFormType::class,
            false,
            false,
            'procedureTypeCreate'
        );

        // To make it easy to quickly move back to the list of procedure types, a breadcrumb item is added.
        $breadcrumb->addItem(
            [
                'title' => $translator->trans('procedure.types', [], 'page-title'),
                'url'   => $this->generateUrl('DemosPlan_procedureType_list'),
            ]
        );

        return $this->renderTemplate(
            $template,
            [
                'templateVars' => [
                    'procedureTypes' => $procedureTypeResources,
                    'isCreate'       => true,
                ],
                'form'         => $form->createView(),
                'title'        => 'procedure.type.create',
            ]
        );
    }

    /**
     * For the moment, this method looks very much like the editAction, because it is basically the preparation step for a duplication.
     * This will be different when we have the case of actually creating new procedureTypes from scratch.
     *
     * @DplanPermissions({"area_procedure_type_edit"})
     *
     * @throws ResourceNotFoundException
     */
    #[Route(name: 'DemosPlan_procedureType_duplicate', path: 'verfahrenstypen/{procedureTypeId}/duplicate', methods: ['GET'], options: ['expose' => true])]
    public function procedureTypeCreateAction(
        Breadcrumb $breadcrumb,
        EntityFetcher $entityFetcher,
        EntityWrapperFactory $entityWrapperFactory,
        FormFactoryInterface $formFactory,
        ProcedureTypeResourceType $procedureTypeResourceType,
        ProcedureTypeService $procedureTypeService,
        string $procedureTypeId,
        TranslatorInterface $translator
    ): Response {
        if (!$procedureTypeResourceType->isAvailable()) {
            throw AccessException::typeNotAvailable($procedureTypeResourceType);
        }

        // List of ProcedureTypes
        $procedureTypeResources = $procedureTypeService->getAllProcedureTypeResources();
        /** @var ProcedureType $procedureTypeEntity */
        $procedureTypeEntity = $entityFetcher->getEntityAsReadTarget($procedureTypeResourceType, $procedureTypeId);
        $procedureTypeResource = $entityWrapperFactory->createWrapper($procedureTypeEntity, $procedureTypeResourceType);

        $template = '@DemosPlanCore/DemosPlanProcedure/administration_procedure_type_edit.html.twig';
        $form = $this->getForm(
            $formFactory,
            $procedureTypeResource,
            ProcedureTypeFormType::class,
            false,
            false,
            'procedureTypeCreate'
        );
        $form->get('name')->setData($procedureTypeEntity->getName().' (Kopie)');

        // To make it easy to quickly move back to the list of procedure types, a breadcrumb item is added.
        $breadcrumb->addItem(
            [
                'title' => $translator->trans('procedure.types', [], 'page-title'),
                'url'   => $this->generateUrl('DemosPlan_procedureType_list'),
            ]
        );

        return $this->renderTemplate(
            $template,
            [
                'templateVars' => [
                    'procedureTypeId' => $procedureTypeId,
                    'procedureTypes'  => $procedureTypeResources,
                    'isCreate'        => true,
                ],
                'form'         => $form->createView(),
                'title'        => 'procedure.type.create',
            ]
        );
    }

    /**
     * @DplanPermissions({"area_procedure_type_edit"})
     *
     * @throws NonUniqueResultException
     * @throws QueryException
     * @throws ResourceNotFoundException
     * @throws UserNotFoundException
     */
    #[Route(name: 'DemosPlan_procedureType_edit', path: 'verfahrenstypen/{procedureTypeId}/edit', methods: ['GET'], options: ['expose' => true])]
    public function procedureTypeEditAction(
        Breadcrumb $breadcrumb,
        EntityFetcher $entityFetcher,
        EntityWrapperFactory $wrapperFactory,
        FormFactoryInterface $formFactory,
        ProcedureTypeResourceType $procedureTypeResourceType,
        string $procedureTypeId,
        TranslatorInterface $translator
    ): Response {
        if (!$procedureTypeResourceType->isAvailable()) {
            throw AccessException::typeNotAvailable($procedureTypeResourceType);
        }

        $procedureTypeEntity = $entityFetcher->getEntityAsReadTarget($procedureTypeResourceType, $procedureTypeId);
        $procedureTypeResource = $wrapperFactory->createWrapper($procedureTypeEntity, $procedureTypeResourceType);

        $template = '@DemosPlanCore/DemosPlanProcedure/administration_procedure_type_edit.html.twig';
        $form = $this->getForm(
            $formFactory,
            $procedureTypeResource,
            ProcedureTypeFormType::class,
            false,
            false,
            'procedureTypeEdit'
        );

        // To make it easy to quickly move back to the list of procedure types, a breadcrumb item is added.
        $breadcrumb->addItem(
            [
                'title' => $translator->trans('procedure.types', [], 'page-title'),
                'url'   => $this->generateUrl('DemosPlan_procedureType_list'),
            ]
        );

        return $this->renderTemplate(
            $template,
            [
                'templateVars' => [
                    'procedureTypeId' => $procedureTypeId,
                ],
                'form'         => $form->createView(),
                'title'        => 'procedure.type.edit',
            ]
        );
    }

    /**
     * @DplanPermissions("area_procedure_type_edit")
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     * @throws NonUniqueResultException
     * @throws QueryException
     * @throws ResourceNotFoundException
     * @throws UserNotFoundException
     */
    #[Route(name: 'DemosPlan_procedureType_create_save', path: 'verfahrenstypen/create', methods: ['POST'], options: ['expose' => false])]
    public function procedureTypeCreateSaveAction(
        EntityFetcher $entityFetcher,
        EntityWrapperFactory $wrapperFactory,
        FormFactoryInterface $formFactory,
        ProcedureTypeResourceType $procedureTypeResourceType,
        ProcedureTypeService $procedureTypeService,
        StatementFieldDefinitionResourceType $statementFieldDefinitionResourceType,
        Request $request
    ) {
        if (!$procedureTypeResourceType->isAvailable()) {
            throw AccessException::typeNotAvailable($procedureTypeResourceType);
        }

        $procedureTypeEntity = new ProcedureType(
            '',
            '',
            new StatementFormDefinition(),
            new ProcedureBehaviorDefinition(),
            new ProcedureUiDefinition(),
        );
        $procedureTypeResource = $wrapperFactory->createWrapper($procedureTypeEntity, $procedureTypeResourceType);
        $formName = 'procedureTypeCreate';

        $form = $this->getForm(
            $formFactory,
            $procedureTypeResource,
            ProcedureTypeFormType::class,
            false,
            false,
            $formName
        );

        // adds needed field definitions, behavior definition and ID to request form
        $request = $procedureTypeService->addMissingRequestData($formName, $request);

        try {
            $form->handleRequest($request);

            if (!$form->isSubmitted()) {
                return $this->redirectToRoute('DemosPlan_procedureType_list');
            }
            if ($form->isValid()) {
                $formData = $form->getData();

                // Get all procedure type resource properties
                $procedureTypeResourceProperties = $procedureTypeService->getProcedureTypeResourceProperties(
                    $formData
                );

                // UI definition changes
                $newUiDefinitionEntity = new ProcedureUiDefinition();
                $procedureTypeService->updateProcedureUiDefinition(
                    $newUiDefinitionEntity,
                    $procedureTypeResourceProperties['procedureUiDefinitionProperties']
                );

                // behavior definition changes
                $newBehaviorDefinitionEntity = new ProcedureBehaviorDefinition();
                $procedureTypeService->updateProcedureBehaviorDefinition(
                    $newBehaviorDefinitionEntity,
                    $procedureTypeResourceProperties['procedureBehaviorDefinitionProperties']
                );

                // Form + Field Definition changes
                $newFormDefinitionEntity = new StatementFormDefinition();
                foreach ($procedureTypeResourceProperties['fieldDefinitions'] as $key => $fieldDefinition) {
                    $statementFieldDefinitionProperties = $procedureTypeService->toKeyedValues(
                        $fieldDefinition,
                        $statementFieldDefinitionResourceType->enabled,
                        $statementFieldDefinitionResourceType->required
                    );
                    $fieldDefinitionEntity = $newFormDefinitionEntity->getFieldDefinitions()->get($key);
                    $procedureTypeService->updateStatementFieldDefinition($fieldDefinitionEntity, $statementFieldDefinitionProperties);
                }

                $procedureTypeService->createProcedureType(
                    $procedureTypeResourceProperties['procedureTypeProperties']['name'],
                    $procedureTypeResourceProperties['procedureTypeProperties']['description'],
                    $newFormDefinitionEntity,
                    $newBehaviorDefinitionEntity,
                    $newUiDefinitionEntity,
                );

                $this->getMessageBag()->add('confirm', 'confirm.saved');

                return $this->redirectToRoute('DemosPlan_procedureType_list');
            }
        } catch (UniqueConstraintViolationException $constraintException) {
            $this->getMessageBag()->add('error', 'error.procedureType.duplicate.name');
            $this->logger->error($constraintException->getMessage());
        } catch (Exception $e) {
            $this->getMessageBag()->add('error', 'error.generic');
            $this->logger->error($e->getMessage());
        }

        $template = '@DemosPlanCore/DemosPlanProcedure/administration_procedure_type_edit.html.twig';
        $procedureTypes = $entityFetcher->listEntities($procedureTypeResourceType, []);

        // in case of invalid data or an exception
        return $this->renderTemplate(
            $template,
            [
                'templateVars' => [
                    'procedureTypeId' => $request->request->get('id'),
                    'procedureTypes'  => $procedureTypes,
                    'isCreate'        => true,
                ],
                'title'        => 'procedure.type.create',
                'form'         => $form->createView(),
            ]
        );
    }

    /**
     * @DplanPermissions("area_procedure_type_edit")
     *
     * @return RedirectResponse|Response
     *
     * @throws MessageBagException
     * @throws NonUniqueResultException
     * @throws QueryException
     * @throws ResourceNotFoundException
     * @throws UserNotFoundException
     */
    #[Route(name: 'DemosPlan_procedureType_edit_save', path: 'verfahrenstypen/{procedureTypeId}/edit', methods: ['POST'], options: ['expose' => false])]
    public function procedureTypeEditSaveAction(
        EntityFetcher $entityFetcher,
        EntityWrapperFactory $wrapperFactory,
        FormFactoryInterface $formFactory,
        ProcedureTypeResourceType $procedureTypeResourceType,
        ProcedureTypeService $procedureTypeService,
        ProcedureUiDefinitionResourceType $procedureUiDefinitionResourceType,
        ProcedureBehaviorDefinitionResourceType $procedureBehaviorDefinitionResourceType,
        StatementFieldDefinitionResourceType $statementFieldDefinitionResourceType,
        Request $request,
        ResourcePersister $resourcePersister,
        string $procedureTypeId
    ) {
        if (!$procedureTypeResourceType->isAvailable()) {
            throw AccessException::typeNotAvailable($procedureTypeResourceType);
        }

        $procedureTypeEntity = $entityFetcher->getEntityAsReadTarget($procedureTypeResourceType, $procedureTypeId);
        $procedureTypeResource = $wrapperFactory->createWrapper($procedureTypeEntity, $procedureTypeResourceType);
        $formName = 'procedureTypeEdit';

        $form = $this->getForm(
            $formFactory,
            $procedureTypeResource,
            ProcedureTypeFormType::class,
            false,
            false,
            $formName
        );

        // adds needed field definitions, behavior definition and ID to request form
        $request = $procedureTypeService->addMissingRequestData($formName, $request);

        try {
            $form->handleRequest($request);

            if (!$form->isSubmitted()) {
                return $this->redirectToRoute('DemosPlan_procedureType_list');
            }
            if ($form->isValid()) {
                $formData = $form->getData();

                // Get all procedure type resource properties
                $procedureTypeResourceProperties = $procedureTypeService->getProcedureTypeResourceProperties(
                    $formData
                );

                // @improve: use symfony forms mapping capabilities to map fields automatically
                $procedureTypeResourceChange = $resourcePersister->updateBackingObject(
                    $procedureTypeResourceType,
                    $procedureTypeId,
                    $procedureTypeResourceProperties['procedureTypeProperties']
                );

                // @improve: return resource instead of object and add object to entitiesToPersist property
                /** @var ProcedureType $procedureType */
                $procedureType = $procedureTypeResourceChange->getTargetResource();
                $procedureUiDefinitionResourceChange = $resourcePersister->updateBackingObject(
                    $procedureUiDefinitionResourceType,
                    $procedureType->getProcedureUiDefinition()->getId(),
                    $procedureTypeResourceProperties['procedureUiDefinitionProperties']
                );

                $procedureBehaviorDefinitionResourceChange = $resourcePersister->updateBackingObject(
                    $procedureBehaviorDefinitionResourceType,
                    $procedureType->getProcedureBehaviorDefinition()->getId(),
                    $procedureTypeResourceProperties['procedureBehaviorDefinitionProperties']
                );

                $statementFieldDefinitionResourceChanges = $procedureTypeService->calculateStatementFieldDefinitionChanges(
                    $procedureTypeResourceProperties['fieldDefinitions'],
                    $statementFieldDefinitionResourceType
                );

                $resourceChanges = array_merge(
                    [
                        $procedureTypeResourceChange,
                        $procedureUiDefinitionResourceChange,
                        $procedureBehaviorDefinitionResourceChange,
                    ],
                    $statementFieldDefinitionResourceChanges
                );

                $resourcePersister->persistResourceChanges($resourceChanges);

                $this->getMessageBag()->add('confirm', 'confirm.saved');

                return $this->redirectToRoute('DemosPlan_procedureType_list');
            }
        } catch (UniqueConstraintViolationException $constraintException) {
            $this->getMessageBag()->add('error', 'error.procedureType.duplicate.name');
            $this->logger->error($constraintException->getMessage());
        } catch (Exception $e) {
            $this->getMessageBag()->add('error', 'error.generic');
            $this->logger->error($e->getMessage());
        }

        $template = '@DemosPlanCore/DemosPlanProcedure/administration_procedure_type_edit.html.twig';

        // in case of invalid data or an exception
        return $this->renderTemplate(
            $template,
            [
                'templateVars' => ['procedureTypeId' => $procedureTypeId],
                'title'        => 'procedure.type.edit',
                'form'         => $form->createView(),
            ]
        );
    }
}
