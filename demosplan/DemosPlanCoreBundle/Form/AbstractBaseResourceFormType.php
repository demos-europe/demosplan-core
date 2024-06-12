<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Form;

use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\TwigableWrapperObject;
use Doctrine\ORM\Query\QueryException;
use EDT\Wrapping\WrapperFactories\WrapperObject;
use ReflectionException;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;
use Traversable;

abstract class AbstractBaseResourceFormType extends AbstractType implements DataMapperInterface
{
    /**
     * @var TranslatorInterface
     */
    protected $translator;

    public function __construct(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Fills all fields of the form structure with the corresponding data from the default data object.
     * Existing forms are based on the definition in the extending form type.
     *
     * @param WrapperObject               $viewData
     * @param FormInterface[]|Traversable $forms
     *
     * @throws QueryException
     * @throws ReflectionException
     * @throws UserNotFoundException
     */
    public function mapDataToForms($viewData, iterable $forms): void
    {
        if (null === $viewData) {
            return;
        }

        if (!$viewData instanceof TwigableWrapperObject) {
            throw new UnexpectedTypeException($viewData, WrapperObject::class);
        }

        /** @var FormInterface[] $forms */
        $forms = iterator_to_array($forms);
        foreach ($forms as $form) {
            $propertyName = $form->getName();
            $propertyValue = $viewData->__get($propertyName);
            $forms[$propertyName]->setData($propertyValue);
        }
    }

    /**
     * @param FormInterface[]|Traversable $forms
     * @param WrapperObject               $viewData
     *
     * @throws QueryException
     * @throws ReflectionException
     * @throws UserNotFoundException
     */
    public function mapFormsToData(iterable $forms, &$viewData): void
    {
        /** @var FormInterface[] $forms */
        $forms = iterator_to_array($forms);
        if ($viewData instanceof WrapperObject) {
            $id = $viewData->__get('id');
        }

        $viewData = [];
        $viewData['id'] = $id;
        foreach ($forms as $form) {
            $propertyName = $form->getName();
            $viewData[$propertyName] = $form->getData();
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('empty_data', null);
    }

    #[Required]
    public function setTranslator(TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }
}
