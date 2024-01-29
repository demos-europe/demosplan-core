<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Form;

use demosplan\DemosPlanCoreBundle\ValueObject\Procedure\BoilerplateVO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Test\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BoilerplateType extends AbstractType implements DataMapperInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add(
                'action',
                TextType::class
            )
            ->add(
                'r_ident',
                TextType::class
            )
            ->add(
                'r_title',
                TextType::class,
                [
                    'required' => true,
                    'label'    => 'title',
                ]
            )
            ->add(
                'r_boilerplateCategory',
                CollectionType::class,
                [
                    'entry_type'   => BoilerplateCategoryType::class,
                    'allow_add'    => true,
                    'allow_delete' => true,
                    'by_reference' => false,
                    'label'        => 'category',
                ]
            )
            ->add(
                'r_text',
                TextType::class,
                [
                    'required' => true,
                    'label'    => 'text',
                ]
            )
            ->add(
                'r_boilerplateGroup',
                BoilerplateGroupType::class,
                [
                    'required' => false,
                    'label'    => 'group',
                ]
            )
            ->add(
                'saveBoilerplate',
                SubmitType::class,
                ['label' => 'save']
            )
            ->setDataMapper($this)
            ->setDataLocked(true);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(['data_class' => BoilerplateVO::class]);
    }

    /**
     * @param BoilerplateVO $data
     */
    public function mapDataToForms($data, iterable $forms)
    {
        $forms = \iterator_to_array($forms);
        /* @var FormInterface[] $forms */
        $forms['r_ident']->setData($data ? $data->getId() : '');
        $forms['r_title']->setData($data ? $data->getTitle() : '');
        $forms['r_boilerplateCategory']->setData($data ? $data->getCategories() : '');
        $forms['r_boilerplateGroup']->setData($data ? $data->getGroup() : '');
        $forms['r_text']->setData($data ? $data->getText() : '');
    }

    /**
     * @param BoilerplateVO $boilerplateVO
     */
    public function mapFormsToData(iterable $forms, &$boilerplateVO)
    {
        $forms = \iterator_to_array($forms);
        /** @var FormInterface[] $forms */
        $boilerplateVO = new BoilerplateVO();
        $boilerplateVO->setId($forms['r_ident']->getData());
        $boilerplateVO->setTitle($forms['r_title']->getData());
        foreach ($forms['r_boilerplateCategory']->getData() as $category) {
            $boilerplateVO->addCategory($category);
        }
        $boilerplateVO->setGroup($forms['r_boilerplateGroup']->getData());
        $boilerplateVO->setText($forms['r_text']->getData());
        $boilerplateVO->lock();
    }
}
