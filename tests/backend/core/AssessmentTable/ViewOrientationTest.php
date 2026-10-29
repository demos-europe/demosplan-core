<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\AssessmentTable;

use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\ViewOrientation;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter\Enum\ExportTemplate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Base\UnitTestCase;

class ViewOrientationTest extends UnitTestCase
{
    #[DataProvider('landscapeTemplatesProvider')]
    public function testCreateForTemplateReturnsLandscape(string $template): void
    {
        $result = ViewOrientation::createForTemplate($template);

        self::assertTrue($result->isLandscape());
    }

    #[DataProvider('portraitTemplatesProvider')]
    public function testCreateForTemplateReturnsPortrait(string $template): void
    {
        $result = ViewOrientation::createForTemplate($template);

        self::assertTrue($result->isPortrait());
    }

    public static function landscapeTemplatesProvider(): array
    {
        return [
            'landscape'                    => [ExportTemplate::LANDSCAPE->value],
            'landscape with fragments'     => [ExportTemplate::LANDSCAPE_WITH_FRAGMENTS->value],
            'portrait with prioritization' => [ExportTemplate::PORTRAIT_WITH_PRIORITIZATION->value],
        ];
    }

    public static function portraitTemplatesProvider(): array
    {
        return [
            'portrait'                => [ExportTemplate::PORTRAIT->value],
            'portrait with fragments' => [ExportTemplate::PORTRAIT_WITH_FRAGMENTS->value],
            'condensed'               => [ExportTemplate::CONDENSED->value],
        ];
    }
}
