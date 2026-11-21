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

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagTopicFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use Tests\Base\FunctionalTestCase;

class StatementHandlerTagDescriptionTest extends FunctionalTestCase
{
    private const TAG_TITLE = 'Alter Titel';
    private const NEW_TAG_TITLE = 'Neuer Titel';
    private const TAG_DESCRIPTION = 'Fachliche Beschreibung';
    private const NEW_TAG_DESCRIPTION = 'Geänderte Beschreibung';
    private const MAX_DESCRIPTION_LENGTH = 1000;

    private ?StatementHandler $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(StatementHandler::class);
    }

    public function testDescriptionIsSet(): void
    {
        $tag = $this->createTag(null);

        $this->handle($tag, ['description' => self::NEW_TAG_DESCRIPTION]);

        self::assertSame(self::NEW_TAG_DESCRIPTION, $this->reloadTag($tag)->getDescription());
    }

    public function testEmptyDescriptionRemovesDescription(): void
    {
        $tag = $this->createTag(self::TAG_DESCRIPTION);

        $this->handle($tag, ['description' => '']);

        self::assertNull($this->reloadTag($tag)->getDescription());
    }

    public function testMissingDescriptionKeepsDescription(): void
    {
        $tag = $this->createTag(self::TAG_DESCRIPTION);

        $this->handle($tag, ['description' => null, 'tagTitle' => self::NEW_TAG_TITLE]);

        $reloadedTag = $this->reloadTag($tag);
        self::assertSame(self::TAG_DESCRIPTION, $reloadedTag->getDescription());
        self::assertSame(self::NEW_TAG_TITLE, $reloadedTag->getTitle());
    }

    public function testTooLongDescriptionIsRejectedWithoutChangingOtherFields(): void
    {
        $tag = $this->createTag(self::TAG_DESCRIPTION);

        try {
            $this->handle($tag, [
                'description' => str_repeat('a', self::MAX_DESCRIPTION_LENGTH + 1),
                'tagTitle'    => self::NEW_TAG_TITLE,
            ]);
            self::fail('Expected a ViolationsException for a description that is too long.');
        } catch (ViolationsException) {
            $reloadedTag = $this->reloadTag($tag);
            self::assertSame(self::TAG_DESCRIPTION, $reloadedTag->getDescription());
            self::assertSame(self::TAG_TITLE, $reloadedTag->getTitle());
        }
    }

    private function createTag(?string $description): Tag
    {
        $topic = TagTopicFactory::createOne();

        return TagFactory::createOne([
            'topic'       => $topic,
            'title'       => self::TAG_TITLE,
            'description' => $description,
        ])->_real();
    }

    /**
     * @param array<string, string|null> $data
     */
    private function handle(Tag $tag, array $data): void
    {
        $this->sut->handleTagBoilerplate(
            $tag->getId(),
            array_merge(
                [
                    'action'            => null,
                    'tagTitle'          => null,
                    'boilerplateTitle'  => null,
                    'boilerplateText'   => null,
                    'boilerplateId'     => null,
                    'defaultAssigneeId' => null,
                    'description'       => null,
                ],
                $data
            ),
            $tag->getTopic()->getProcedure()->getId()
        );
    }

    private function reloadTag(Tag $tag): Tag
    {
        $this->getEntityManager()->clear();

        return $this->getEntityManager()->find(Tag::class, $tag->getId());
    }
}
