<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Segment\Functional;

use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagTopicFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Transformers\Segment\StatementToDraftsInfoTransformer;
use Ramsey\Uuid\Uuid;
use Tests\Base\FunctionalTestCase;

class StatementToDraftsInfoTransformerTest extends FunctionalTestCase
{
    private const TAG_TITLE = 'Baulärm';

    /** @var StatementToDraftsInfoTransformer|null */
    protected $sut;

    private ?Procedure $procedure = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(StatementToDraftsInfoTransformer::class);
        $this->procedure = ProcedureFactory::createOne()->_real();
    }

    public function testStoredTagIdIsKeptWhenTitleExistsInTwoTopics(): void
    {
        $tagId = $this->createTag('Lärm', self::TAG_TITLE);
        $this->createTag('Baustelle', self::TAG_TITLE);

        $tagIds = $this->transformWithTag($tagId, self::TAG_TITLE);

        self::assertSame([$tagId], $tagIds);
    }

    public function testUnknownTagIdIsReplacedByTagWithUniqueTitle(): void
    {
        $tagId = $this->createTag('Lärm', self::TAG_TITLE);

        $tagIds = $this->transformWithTag(Uuid::uuid4()->toString(), self::TAG_TITLE);

        self::assertSame([$tagId], $tagIds);
    }

    private function createTag(string $topicTitle, string $tagTitle): string
    {
        $topic = TagTopicFactory::createOne(['procedure' => $this->procedure, 'title' => $topicTitle]);

        return TagFactory::createOne(['topic' => $topic, 'title' => $tagTitle])->getId();
    }

    /**
     * @return list<string> the tag IDs of the transformed draft segment
     */
    private function transformWithTag(string $tagId, string $tagName): array
    {
        $text = 'Der Baulärm ist zu laut.';
        $statement = StatementFactory::createOne(['procedure' => $this->procedure, 'text' => $text]);
        $statement->_real()->setDraftsListJson(Json::encode([
            'data' => [
                'id'         => Uuid::uuid4()->toString(),
                'type'       => 'slicing transaction',
                'attributes' => [
                    'statementId'      => $statement->getId(),
                    'procedureId'      => $this->procedure->getId(),
                    'textualReference' => $text,
                    'segments'         => [[
                        'id'        => Uuid::uuid4()->toString(),
                        'charStart' => 0,
                        'charEnd'   => mb_strlen($text),
                        'tags'      => [['id' => $tagId, 'tagName' => $tagName]],
                    ]],
                ],
            ],
        ]));
        $statement->_save();

        $draftsInfo = Json::decodeToArray($this->sut->transform($statement->getId()));

        return array_column($draftsInfo['data']['attributes']['segments'][0]['tags'], 'id');
    }
}
