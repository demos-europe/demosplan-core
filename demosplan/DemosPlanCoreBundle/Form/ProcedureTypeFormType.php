<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Form;

use demosplan\DemosPlanCoreBundle\ResourceTypes\ProcedureTypeResourceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class ProcedureTypeFormType extends AbstractBaseResourceFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $procedureTypePath = ProcedureTypeResourceType::startPath();
        $builder
            ->add(
                $procedureTypePath->id->getAsNamesInDotNotation(),
                HiddenType::class,
            )
            ->add(
                $procedureTypePath->name->getAsNamesInDotNotation(),
                TextType::class,
                [
                    'label' => $this->translator->trans('text.procedures.type.name'),
                    'attr'  => [
                        'maxCharCount' => 255,
                    ],
                ]
            )
            ->add(
                $procedureTypePath->description->getAsNamesInDotNotation(),
                TextType::class,
                [
                    'label'    => $this->translator->trans('description'),
                    'required' => false,
                ]
            )
            ->add(
                $procedureTypePath->procedureUiDefinition->getAsNamesInDotNotation(),
                ProcedureUiDefinitionFormType::class
            )
            ->add(
                $procedureTypePath->procedureBehaviorDefinition->getAsNamesInDotNotation(),
                ProcedureBehaviorDefinitionFormType::class
            )
            ->add(
                $procedureTypePath->statementFormDefinition->getAsNamesInDotNotation(),
                StatementFormDefinitionFormType::class
            )
            ->setDataMapper($this);
    }
}
