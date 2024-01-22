<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Form;

use demosplan\DemosPlanCoreBundle\ValueObject\Procedure\PreparationMailVO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;

class PreparationMailType extends AbstractType implements DataMapperInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add(
                'r_email_subject',
                TextType::class,
                [
                    'required' => true,
                ]
            )
            ->add(
                'r_email_body',
                TextareaType::class,
                [
                    'required' => true,
                ]
            )
            ->add(
                'r_email_address',
                CheckboxType::class,
                [
                    'required' => true,
                ]
            )
            ->add(
                'send',
                ButtonType::class
            )
            ->setDataMapper($this);
    }

    public function mapDataToForms($data, iterable $forms)
    {
        $forms = iterator_to_array($forms);
        /* @var FormInterface[] $forms */
        $forms['r_email_subject']->setData($data ? $data->getMailSubject() : '');
        $forms['r_email_body']->setData($data ? $data->getMailBody() : '');
        $forms['r_email_address']->setData($data ? $data->getSendMail() : true);
    }

    public function mapFormsToData(iterable $forms, &$data)
    {
        $forms = iterator_to_array($forms);
        /** @var FormInterface[] $forms */
        $data = new PreparationMailVO();
        $data->setMailSubject($forms['r_email_subject']->getData());
        $data->setMailBody($forms['r_email_body']->getData());
        $data->setSendMail($forms['r_email_address']->getData());
        $data->lock();
    }
}
