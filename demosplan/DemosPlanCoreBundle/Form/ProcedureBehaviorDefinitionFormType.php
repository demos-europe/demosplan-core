<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Form;

use demosplan\DemosPlanCoreBundle\Permissions\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ProcedureBehaviorDefinitionResourceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProcedureBehaviorDefinitionFormType extends AbstractBaseResourceFormType
{
    private $permissions;

    public function __construct(PermissionsInterface $permissions, TranslatorInterface $translator)
    {
        parent::__construct($translator);
        $this->permissions = $permissions;
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $procedureBehaviorDefinitionPath = ProcedureBehaviorDefinitionResourceType::startPath();
        $builder->add(
            $procedureBehaviorDefinitionPath->allowedToEnableMap->getAsNamesInDotNotation(),
            CheckboxType::class,
            [
                'label'    => $this->translator->trans('map.allow.procedure.type.activate'),
                'required' => false,
                'attr'     => [],
            ]
        );
        if ($this->permissions->hasPermission('field_statement_priority_area')) {
            $builder->add(
                $procedureBehaviorDefinitionPath->hasPriorityArea->getAsNamesInDotNotation(),
                CheckboxType::class,
                [
                    'label'    => $this->translator->trans('potential.areas.activate'),
                    'required' => false,
                    'attr'     => [],
                ]
            );
        }
        $builder->add(
            $procedureBehaviorDefinitionPath->participationGuestOnly->getAsNamesInDotNotation(),
            CheckboxType::class,
            [
                'label'    => $this->translator->trans('text.procedure.types.guests.only'),
                'required' => false,
                'attr'     => [
                    'disabled' => true,
                ],
            ]
        )
            ->setDataMapper($this);
    }
}
