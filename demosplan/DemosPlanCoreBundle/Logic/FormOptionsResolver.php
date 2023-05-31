<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class FormOptionsResolver
{
    public const STATEMENT_STATUS = 'statement_status';
    public const STATEMENT_FRAGMENT_ADVICE_VALUES = 'statement_fragment_advice_values';
    /**
     * @var GlobalConfigInterface
     */
    private $globalConfig;
    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(GlobalConfigInterface $globalConfig, TranslatorInterface $translator)
    {
        $this->globalConfig = $globalConfig;
        $this->translator = $translator;
    }

    public function resolve(string $type, string $key): string
    {
        $formOptions = $this->globalConfig->getFormOptions();

        return $this->translator->trans($formOptions[$type][$key] ?? $key);
    }
}
