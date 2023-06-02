<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanProcedureBundle\Form;

use demosplan\DemosPlanProcedureBundle\ValueObject\BoilerplateGroupVO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BoilerplateGroupType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add(
                'id',
                TextType::class,
                [
                    'label'         => 'group',
                    'property_path' => 'id',
                ]
            )
            ->add(
                'r_title',
                TextType::class,
                [
                    'required'      => true,
                    'label'         => 'title',
                    'property_path' => 'title',
                ]
            )
            ->setDataLocked(true);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => BoilerplateGroupVO::class]);
    }
}
