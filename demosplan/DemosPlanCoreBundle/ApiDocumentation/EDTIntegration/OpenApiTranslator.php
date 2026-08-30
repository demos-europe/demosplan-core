<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\ApiDocumentation\EDTIntegration;

use Symfony\Contracts\Translation\TranslatorInterface;

class OpenApiTranslator implements TranslatorInterface
{
    public function __construct(
        protected readonly TranslatorInterface $translator
    ) {}

    public function trans(string $id, array $parameters = [], ?string $domain = 'openapi', ?string $locale = 'en'): string
    {
        return trim($this->translator->trans($id, $parameters, $domain, $locale));
    }

    public function getLocale(): string
    {
        return $this->translator->getLocale();
    }
}
