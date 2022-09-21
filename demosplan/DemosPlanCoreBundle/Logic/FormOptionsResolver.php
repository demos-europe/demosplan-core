<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfigInterface;
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
