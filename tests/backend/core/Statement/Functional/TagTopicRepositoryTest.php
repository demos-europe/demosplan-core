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

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagTopicFactory;
use demosplan\DemosPlanCoreBundle\Repository\TagTopicRepository;
use Tests\Base\FunctionalTestCase;

class TagTopicRepositoryTest extends FunctionalTestCase
{
    private const TAG_TITLE_WITH_DESCRIPTION = 'Mit Beschreibung';
    private const TAG_TITLE_WITHOUT_DESCRIPTION = 'Ohne Beschreibung';
    private const TAG_DESCRIPTION = 'Fachliche Beschreibung';

    private ?TagTopicRepository $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(TagTopicRepository::class);
    }

    public function testCopyKeepsTagDescriptions(): void
    {
        $procedureTemplate = ProcedureFactory::new()->withDefaultSettings()->create();
        $topic = TagTopicFactory::createOne(['procedure' => $procedureTemplate]);
        TagFactory::createOne([
            'topic'       => $topic,
            'title'       => self::TAG_TITLE_WITH_DESCRIPTION,
            'description' => self::TAG_DESCRIPTION,
        ]);
        TagFactory::createOne(['topic' => $topic, 'title' => self::TAG_TITLE_WITHOUT_DESCRIPTION]);
        $newProcedure = ProcedureFactory::new()->withDefaultSettings()->create();

        $this->sut->copy($procedureTemplate->getId(), $newProcedure->_real());

        $descriptionsByTitle = [];
        foreach ($newProcedure->_real()->getTags() as $copiedTag) {
            $descriptionsByTitle[$copiedTag->getTitle()] = $copiedTag->getDescription();
        }
        ksort($descriptionsByTitle);
        self::assertSame(
            [
                self::TAG_TITLE_WITH_DESCRIPTION    => self::TAG_DESCRIPTION,
                self::TAG_TITLE_WITHOUT_DESCRIPTION => null,
            ],
            $descriptionsByTitle
        );
    }
}
