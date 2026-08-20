<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Functional;

use DateTime;
use Tests\Base\FunctionalTestCase;
use Twig\Environment;

/**
 * Covers AC1 of the segment deadline reminders: the assignment digest mail
 * shows each assigned segment's deadline, and only when one is set.
 */
class EmailAssignedTasksTemplateTest extends FunctionalTestCase
{
    private ?Environment $twig = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->twig = self::getContainer()->get('twig');
    }

    public function testRendersDeadlineWhenSet(): void
    {
        $body = $this->renderBody(new DateTime('2026-08-03'));

        self::assertStringContainsString('M6-5', $body);
        self::assertStringContainsString('Bearbeitungsfrist: 03.08.2026', $body);
    }

    public function testOmitsDeadlineWhenNotSet(): void
    {
        $body = $this->renderBody(null);

        self::assertStringContainsString('M6-5', $body);
        self::assertStringNotContainsString('Bearbeitungsfrist', $body);
    }

    private function renderBody(?DateTime $deadline): string
    {
        return $this->twig->load('@DemosPlanCore/DemosPlanUser/email_assigned_tasks.html.twig')->renderBlock(
            'body_plain',
            [
                'templateVars' => [
                    'totalTasks' => 1,
                    'entries'    => [
                        'Test procedure' => [
                            'Test step' => [
                                'M6-5' => ['link' => 'https://example.test/segment', 'deadline' => $deadline],
                            ],
                        ],
                    ],
                ],
                'projectName' => 'Test project',
            ]
        );
    }
}
