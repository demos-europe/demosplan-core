<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Form;

use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementFieldDefinitionResourceType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class StatementFieldDefinitionFormType extends AbstractBaseResourceFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $statementFieldDefinitionPath = StatementFieldDefinitionResourceType::startPath();
        $builder
            ->add(
                $statementFieldDefinitionPath->name->getAsNamesInDotNotation(),
                TextType::class,
                [
                    'label' => $this->translator->trans('name'),
                    'attr'  => [
                        'disabled' => true, // the name should never be edited via the form
                    ],
                ]
            )
            ->add(
                $statementFieldDefinitionPath->enabled->getAsNamesInDotNotation(),
                CheckboxType::class,
                [
                    'label'    => $this->translator->trans('enabled'),
                    'required' => false,
                ]
            )
            ->add(
                $statementFieldDefinitionPath->required->getAsNamesInDotNotation(),
                CheckboxType::class,
                [
                    'label'    => $this->translator->trans('field.required'),
                    'required' => false,
                ]
            )
            ->setDataMapper($this);
    }
}
