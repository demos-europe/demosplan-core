<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Form;

use demosplan\DemosPlanCoreBundle\ResourceTypes\ProcedureUiDefinitionResourceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class ProcedureUiDefinitionFormType extends AbstractBaseResourceFormType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $procedureUiDefinitionPath = ProcedureUiDefinitionResourceType::startPath();
        $builder->add(
            $procedureUiDefinitionPath->statementFormHintStatement->getAsNamesInDotNotation(),
            TextType::class,
            [
                'label'      => $this->translator->trans('statement.form.hint.statement'),
                'required'   => false,
                'empty_data' => '',
                'attr'       => [
                    'linkButton'       => true,
                    'listButtons'      => false,
                    'fullscreenButton' => false,
                    'hint'             => $this->translator->trans('text.visible.to.loggedin.users'),
                ],
            ]
        )
            ->add(
                $procedureUiDefinitionPath->statementFormHintPersonalData->getAsNamesInDotNotation(),
                TextType::class,
                [
                    'label'      => $this->translator->trans('statement.form.hint.personal.data'),
                    'required'   => false,
                    'empty_data' => '',
                    'attr'       => [
                        'linkButton'       => true,
                        'listButtons'      => false,
                        'fullscreenButton' => false,
                        'hint'             => $this->translator->trans('text.visible.to.loggedin.users'),
                    ],
                ]
            )
            ->add(
                $procedureUiDefinitionPath->statementFormHintRecheck->getAsNamesInDotNotation(),
                TextType::class,
                [
                    'label'      => $this->translator->trans('statement.form.hint.recheck'),
                    'required'   => false,
                    'empty_data' => '',
                    'attr'       => [
                        'linkButton'       => true,
                        'listButtons'      => false,
                        'fullscreenButton' => false,
                        'hint'             => $this->translator->trans('text.visible.to.loggedin.users'),
                    ],
                ]
            )->add(
                $procedureUiDefinitionPath->mapHintDefault->getAsNamesInDotNotation(),
                TextType::class,
                [
                    'label'      => $this->translator->trans('map.hint'),
                    'required'   => false,
                    'empty_data' => '',
                    'attr'       => [
                        'hint'       => $this->translator->trans('map.hint.edit.explanation').'<br><br>'.$this->translator->trans('map.hint.warning.tooshort', ['minLength' => 50, 'maxLength' => 2000]),
                        'tooltip'    => $this->translator->trans('map.hint.edit.contextual.help'),
                        'attributes' => [
                            'minlength=50',
                            'maxlength=2000',
                        ],
                    ],
                ]
            )->add(
                $procedureUiDefinitionPath->statementPublicSubmitConfirmationText->getAsNamesInDotNotation(),
                TextType::class,
                [
                    'label'      => $this->translator->trans('edit.statement.confirm.info.headline'),
                    'required'   => false,
                    'empty_data' => '',
                    'attr'       => [
                        'hint'       => $this->translator->trans('edit.statement.confirm.info.explanation').'<br><br>',
                        'attributes' => [
                            'maxlength=500',
                        ],
                    ],
                ],
            )
            ->setDataMapper($this);
    }
}
