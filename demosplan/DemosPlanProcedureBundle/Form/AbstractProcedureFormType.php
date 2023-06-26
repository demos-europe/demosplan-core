<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanProcedureBundle\Form;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanProcedureBundle\Logic\CurrentProcedureService;
use demosplan\DemosPlanProcedureBundle\Logic\ProcedureService;
use demosplan\DemosPlanProcedureBundle\ValueObject\ProcedureFormData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Valid;

/**
 * Intended to be used to create and edit settings for a procedure.
 *
 * Old fields in the procedure settings page should be migrated into this form, so that the
 * manual data handling in a form POST request is no longer necessary at some point in time.
 *
 * This class is reserved for procedure settings only. Do not use for procedure template forms!
 * Instead create a separate form class if needed.
 */
abstract class AbstractProcedureFormType extends AbstractType
{
    public function __construct(private readonly CustomerService $customerService, private readonly CurrentProcedureService $currentProcedure, private readonly CurrentUserInterface $currentUser, private readonly PermissionsInterface $permissions, private readonly ProcedureService $procedureService)
    {
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        // We can't simply disable the email address fields as a whole because this would require
        // adjustments in the FE. Instead, we simply do not validate to avoid errors when the
        // addresses are not sent.
        $constraints = [];
        if ($this->currentUser->hasPermission('feature_procedure_agency_email_addresses')) {
            $constraints[] = new Valid();
        }

        $builder
            ->add(
                'agencyMainEmailAddress',
                EmailAddressType::class,
                [
                    'required'    => !$this->isProcedureTemplate(),
                    'constraints' => $constraints,
                ]
            )
            ->add(
                'agencyExtraEmailAddresses',
                CollectionType::class,
                [
                    'entry_type'   => EmailAddressType::class,
                    'allow_add'    => true,
                    'allow_delete' => true,
                    'prototype'    => true,
                    'by_reference' => false,
                    'constraints'  => $constraints,
                ]
            );

        if ($this->permissions->hasPermission('feature_segment_access_expansion')) {
            $builder->addEventListener(
                FormEvents::PRE_SET_DATA,
                function (FormEvent $event): void {
                    $canEdit = $this->permissions->hasPermission(
                        'feature_segment_access_expansion_edit'
                    );
                    $choices = $this->generateChoices();

                    $event->getForm()->add(
                        'allowedSegmentAccessProcedureIds',
                        ChoiceType::class,
                        [
                            'choices'  => $choices,
                            'multiple' => true,
                            'disabled' => !$canEdit,
                        ]
                    );
                }
            );
        }

        $builder->setDataLocked(true);
    }

    /**
     * Returns all administratable procedures.
     *
     * @return array<string,string> procedure name as key and procedure ID as value
     */
    private function generateChoices(): array
    {
        $procedureIdToExclude = $this->currentProcedure->getProcedureWithCertainty()->getId();
        $orgaCustomerId = $this->customerService->getCurrentCustomer()->getId();
        $currentUser = $this->currentUser->getUser();
        $allowableSegmentAccessProcedures = $this->procedureService->getProcedureAdminList(
            [
                'procedureIdToExclude' => $procedureIdToExclude,
                'orgaCustomerId'       => $orgaCustomerId,
            ],
            null,
            $currentUser,
            null,
            false,
            false
        );

        return collect($allowableSegmentAccessProcedures)
            ->mapWithKeys(
                static fn (Procedure $allowedProcedure): array => [$allowedProcedure->getName() => $allowedProcedure->getId()]
            )
            ->all();
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'data_class' => ProcedureFormData::class,
            ]
        );
    }

    abstract protected function isProcedureTemplate(): bool;
}
