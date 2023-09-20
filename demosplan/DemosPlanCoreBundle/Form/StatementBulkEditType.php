<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

class StatementBulkEditType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add(
                'procedureId',
                HiddenType::class,
                [
                    'required'      => true,
                    'property_path' => 'statementIdsInProcedure.procedureId',
                ]
            )
            ->add(
                'recommendation',
                TextareaType::class,
                [
                    'required'      => false,
                    'property_path' => 'recommendationAddition',
                ]
            )
            ->add(
                'statementIds',
                CollectionType::class,
                [
                    'entry_type'    => HiddenType::class,
                    'property_path' => 'statementIdsInProcedure.statementIds',
                    'allow_add'     => true,
                    'by_reference'  => false,
                ]
            )
            ->setDataLocked(true);
    }
}
