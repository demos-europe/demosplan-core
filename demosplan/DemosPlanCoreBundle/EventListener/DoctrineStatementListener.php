<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventListener;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use Exception;
use Symfony\Contracts\Translation\TranslatorInterface;

class DoctrineStatementListener
{
    /**
     * @var FileService
     */
    protected $fileService;

    /**
     * @var array
     */
    protected $formOptions;

    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(
        FileService $fileService,
        GlobalConfigInterface $globalConfig,
        TranslatorInterface $translator)
    {
        $this->fileService = $fileService;
        $this->formOptions = $globalConfig->getFormOptions();
        $this->translator = $translator;
    }

    public function postLoad(Statement $statement)
    {
        try {
            $files = $this->fileService->getEntityFileString(Statement::class, $statement->getId(), 'file');
            // add files to statement Entity
            $statement->setFiles($files);
            // translate Values
            $transKey = $this->formOptions['statement_submit_types']['values'][$statement->getSubmitType()] ?? '';
            $statement->setSubmitTypeTranslated($this->translator->trans($transKey));
        } catch (Exception $e) {
            // bad luck :-(
        }
    }
}
