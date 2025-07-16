<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Twig\Extension;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Illuminate\Support\Collection;
use Twig\TwigFunction;

/**
 * Several Twig Helper functions.
 */
class TwigToolsExtension extends ExtensionBase
{
    /**
     * @var array
     */
    protected $formOptions;

    private string $loginPath = '';
    private int $displayOrder = 0;

    public function __construct(
        ContainerInterface $container,
        ParameterBagInterface $parameterBag,
        private readonly TranslatorInterface $translator
    ) {
        parent::__construct($container);
        $this->formOptions = $parameterBag->get('form_options');
    }

    /**
     * @return array|TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('getFormOption', $this->getFormOption(...)),
            new TwigFunction('arraysHasSameValues', $this->arraysHasSameValues(...)),

            new TwigFunction('setLoginPath', $this->setLoginPath(...)),
            new TwigFunction('getLoginPath', $this->getLoginPath(...)),
            new TwigFunction('setDisplayOrder', $this->setDisplayOrder(...)),
            new TwigFunction('getDisplayOrder', $this->getDisplayOrder(...)),
        ];
    }

    /**
     * Get a key from the form options.
     *
     * Works with subkeys in the Twig-typical dot-notation
     *
     * @param string $key
     * @param bool   $translate     Translate option values
     * @param string $sortDirection
     *
     * @return mixed
     */
    public function getFormOption($key = null, $translate = false, $sortDirection = 'ASC')
    {
        $options = data_get($this->formOptions, $key, null);

        if (null === $options) {
            return null;
        }

        $options = collect($options);

        if ($translate) {
            $options = $this->translateValuesOfMultiDimensionalCollection($options);
        }

        if ('asc' === strtolower($sortDirection)) {
            $options = $options->sort(
                fn($val1, $val2) => strcasecmp((string) $val1, (string) $val2)
            );
        }

        return $options->toArray();
    }

    /**
     * @return Collection
     */
    protected function translateValuesOfMultiDimensionalCollection(Collection $collection)
    {
        return $collection->map(
            function ($value) {
                if ($value instanceof Collection || is_array($value)) {
                    // we need to go deeper:
                    $collection2 = collect($value);

                    return $this->translateValuesOfMultiDimensionalCollection($collection2);
                }
                if (is_string($value)) {
                    return $this->translator->trans($value);
                }
                // default: just return the value
                return $value;
            }
        );
    }

    public function setLoginPath($value): void
    {
        $this->loginPath = $value;
    }

    public function getLoginPath(): string
    {
        return $this->loginPath;
    }

    public function setDisplayOrder($value): void
    {
        $this->displayOrder = $value;
    }

    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }

    public function arraysHasSameValues(array $array1 = [], array $array2 = []): bool
    {
        $arrayDiff = array_diff($array1, $array2);

        return [] === $arrayDiff && count($array1) === count($array2);
    }
}
