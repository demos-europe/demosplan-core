<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanUserBundle\ValueObject;

use demosplan\DemosPlanCoreBundle\Constraint\ValidCssVarsConstraint;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceInterface;
use Symfony\Component\Validator\Constraints as Assert;

interface CustomerInterface extends ResourceInterface
{
    public const DATA_PROTECTION = 'dataProtection';
    public const IMPRINT = 'imprint';
    public const LOGO = 'logo';
    public const LOGO_DELETE = 'logoDelete';
    public const TERMS_OF_USE = 'termsOfUse';
    public const BASE_LAYER_URL = 'baseLayerUrl';
    public const BASE_LAYER_LAYERS = 'baseLayerLayers';
    public const MAP_ATTRIBUTION = 'mapAttribution';
    public const XPLANNING = 'xplanning';
    public const STYLING = 'cssvars';
    public const ACCESSIBILITY_EXPLANATION = 'accessibilityExplanation';
    public const SIGN_LANGUAGE_OVERVIEW_DESCRIPTION = 'signLanguageOverviewDescription';
    public const SIMPLE_LANGUAGE = 'simpleLanguage';
    public const SIMPLE_LANGUAGE_OVERVIEW_DESCRIPTION = 'overviewDescriptionInSimpleLanguage';

    /**
     * @Assert\Length(max=65000, groups={CustomerInterface::DATA_PROTECTION})
     */
    public function getDataProtection(): ?string;

    /**
     * @Assert\Length(max=65000, groups={CustomerInterface::IMPRINT})
     */
    public function getImprint(): ?string;

    /**
     * @Assert\Length(max=65000, groups={CustomerInterface::TERMS_OF_USE})
     */
    public function getTermsOfUse(): ?string;

    /**
     * @Assert\Length(max=65000, groups={CustomerInterface::XPLANNING})
     */
    public function getXplanning(): string;

    public function getLogo(): ?File;

    /**
     * @Assert\Length(min=0, max=4096, groups={CustomerInterface::MAP_ATTRIBUTION})
     */
    public function getMapAttribution(): ?string;

    /**
     * @Assert\Length(min=5, max=4096, groups={CustomerInterface::BASE_LAYER_URL})
     */
    public function getBaseLayerUrl(): ?string;

    /**
     * @Assert\Length(min=5, max=4096, groups={CustomerInterface::BASE_LAYER_LAYERS})
     */
    public function getBaseLayerLayers(): ?string;

    /**
     * @ValidCssVarsConstraint()
     */
    public function getCssVars(): ?string;

    /**
     * @Assert\Length(max=65000, groups={CustomerInterface::ACCESSIBILITY_EXPLANATION})
     */
    public function getAccessibilityExplanation(): string;

    public function getSignLanguageOverviewDescription(): string;

    public function getOverviewDescriptionInSimpleLanguage(): string;
}
