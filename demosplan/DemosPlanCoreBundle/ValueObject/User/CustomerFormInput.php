<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\User;

use demosplan\DemosPlanCoreBundle\Entity\File;
use Symfony\Component\HttpFoundation\Request;

class CustomerFormInput implements CustomerResourceInterface
{
    /** @var string|null */
    private $id;
    /** @var string|null */
    private $dataProtection;
    /** @var string|null */
    private $imprint;
    /** @var string|null */
    private $termsOfUse;
    /** @var File|null */
    private $logo;
    /** @var string|null */
    private $mapAttribution;
    /** @var string|null */
    private $baseLayerUrl;
    /** @var string|null */
    private $baseLayerLayers;
    /**
     * @var string
     */
    private $xplanning = '';
    /**
     * @var string
     */
    private $cssVars = '';
    /**
     * @var string
     */
    private $accessibilityExplanation = '';
    /** @var array */
    private $activeGetters = [];

    /**
     * @var string|null
     */
    private $signLanguageOverviewDescription;

    /**
     * @var string|null
     */
    private $overviewDescriptionInSimpleLanguage;

    /**
     * @param File|null $logo
     */
    public static function createFromFormRequest(Request $request, $logo): self
    {
        $customerFormInput = new self();
        $requestParameter = $request->request;

        $logoDeleteParameterKey = 'r_'.self::LOGO_DELETE;
        $imprintParameterKey = 'r_'.self::IMPRINT;
        $dataProtectionParameterKey = 'r_'.self::DATA_PROTECTION;
        $termsOfUseParameterKey = 'r_'.self::TERMS_OF_USE;
        $mapAttributionParameterKey = 'r_'.self::MAP_ATTRIBUTION;
        $baseLayerParameterKey = 'r_'.self::BASE_LAYER_URL;
        $baseLayerLayersParameterKey = 'r_'.self::BASE_LAYER_LAYERS;
        $xplanningParameterKey = 'r_'.self::XPLANNING;
        $stylingParameterKey = 'r_'.self::STYLING;
        $accessibilityExplanationParameterKey = 'r_'.self::ACCESSIBILITY_EXPLANATION;
        $signLanguageOverviewDescriptionParameterKey = 'r_'.self::SIGN_LANGUAGE_OVERVIEW_DESCRIPTION;
        $simpleLanguageKey = 'r_'.self::SIMPLE_LANGUAGE;

        if ($requestParameter->has($imprintParameterKey)) {
            $customerFormInput->imprint = $requestParameter->get($imprintParameterKey);
            $customerFormInput->activeGetters[self::IMPRINT] = 'getImprint';
        }
        if ($requestParameter->has($dataProtectionParameterKey)) {
            $customerFormInput->dataProtection = $requestParameter->get($dataProtectionParameterKey);
            $customerFormInput->activeGetters[self::DATA_PROTECTION] = 'getDataProtection';
        }
        if ($requestParameter->has($termsOfUseParameterKey)) {
            $customerFormInput->termsOfUse = $requestParameter->get($termsOfUseParameterKey);
            $customerFormInput->activeGetters[self::TERMS_OF_USE] = 'getTermsOfUse';
        }
        if ($requestParameter->has($mapAttributionParameterKey)) {
            $customerFormInput->mapAttribution = $requestParameter->get($mapAttributionParameterKey);
            $customerFormInput->activeGetters[self::MAP_ATTRIBUTION] = 'getMapAttribution';
        }
        if ($requestParameter->has($baseLayerParameterKey)) {
            $customerFormInput->baseLayerUrl = $requestParameter->get($baseLayerParameterKey);
            $customerFormInput->activeGetters[self::BASE_LAYER_URL] = 'getBaseLayerUrl';
        }
        if ($requestParameter->has($baseLayerLayersParameterKey)) {
            $customerFormInput->baseLayerLayers = $requestParameter->get($baseLayerLayersParameterKey);
            $customerFormInput->activeGetters[self::BASE_LAYER_LAYERS] = 'getBaseLayerLayers';
        }
        if ($requestParameter->has($xplanningParameterKey)) {
            $customerFormInput->xplanning = $requestParameter->get($xplanningParameterKey);
            $customerFormInput->activeGetters[self::XPLANNING] = 'getXplanning';
        }
        if ($requestParameter->has($stylingParameterKey)) {
            $customerFormInput->cssVars = $requestParameter->get($stylingParameterKey);
            $customerFormInput->activeGetters[self::STYLING] = 'getCssvars';
        }
        if (null !== $logo) {
            $customerFormInput->logo = $logo;
            $customerFormInput->activeGetters[self::LOGO] = 'getLogo';
        } elseif ($requestParameter->has($logoDeleteParameterKey)) {
            $customerFormInput->logo = null;
            $customerFormInput->activeGetters[self::LOGO] = 'getLogo';
        }
        if ($requestParameter->has($accessibilityExplanationParameterKey)) {
            $customerFormInput->accessibilityExplanation = $requestParameter->get($accessibilityExplanationParameterKey);
            $customerFormInput->activeGetters[self::ACCESSIBILITY_EXPLANATION] = 'getAcessibilityExplanation';
        }
        if ($requestParameter->has($signLanguageOverviewDescriptionParameterKey)) {
            $customerFormInput->signLanguageOverviewDescription = $requestParameter->get($signLanguageOverviewDescriptionParameterKey);
            $customerFormInput->activeGetters[self::SIGN_LANGUAGE_OVERVIEW_DESCRIPTION] = 'getSignLanguageOverviewDescription';
        }
        if ($requestParameter->has($simpleLanguageKey)) {
            $customerFormInput->overviewDescriptionInSimpleLanguage = $requestParameter->get($simpleLanguageKey);
            $customerFormInput->activeGetters[self::SIMPLE_LANGUAGE_OVERVIEW_DESCRIPTION] = 'getOverviewDescriptionInSimpleLanguage';
        }

        // Add more properties here, don't forget to add the methods, constants and validation
        // in the interface. Do not remove this comment after adding a property.

        return $customerFormInput;
    }

    public function getDataProtection(): ?string
    {
        return $this->dataProtection;
    }

    public function getImprint(): ?string
    {
        return $this->imprint;
    }

    public function getTermsOfUse(): ?string
    {
        return $this->termsOfUse;
    }

    public function getXplanning(): string
    {
        return $this->xplanning;
    }

    public function getLogo(): ?File
    {
        return $this->logo;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getMapAttribution(): ?string
    {
        return $this->mapAttribution;
    }

    public function getBaseLayerUrl(): ?string
    {
        return $this->baseLayerUrl;
    }

    public function getBaseLayerLayers(): ?string
    {
        return $this->baseLayerLayers;
    }

    public function getCssVars(): ?string
    {
        return $this->cssVars;
    }

    public function getSignLanguageOverviewDescription(): string
    {
        return $this->signLanguageOverviewDescription;
    }

    public function getActiveGetters(): array
    {
        return $this->activeGetters;
    }

    public function getAccessibilityExplanation(): string
    {
        return $this->accessibilityExplanation;
    }

    public function getOverviewDescriptionInSimpleLanguage(): string
    {
        return $this->overviewDescriptionInSimpleLanguage;
    }
}
