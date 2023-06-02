<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Form;

use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementFormDefinitionResourceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;

class StatementFormDefinitionFormType extends AbstractBaseResourceFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add(
                StatementFormDefinitionResourceType::startPath()->fieldDefinitions->getAsNamesInDotNotation(),
                CollectionType::class,
                [
                    'entry_type'   => StatementFieldDefinitionFormType::class,
                    'allow_add'    => false,
                    'allow_delete' => false,
                ]
            )
            ->setDataMapper($this);
    }
}
