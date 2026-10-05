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

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\BoilerplateFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use Doctrine\ORM\EntityManagerInterface;
use FOS\ElasticaBundle\Transformer\ModelToElasticaTransformerInterface;
use Tests\Base\FunctionalTestCase;

/**
 * DPLAN-18271, step 8: verifies the Elasticsearch indexing pipeline reads
 * `recommendation` through the substituting accessor, not a raw property/reflection
 * read. The `statementSegments` FOSElastica index is configured with a custom
 * `model_to_elastica_transformer` (`path_based_model_to_elastica_transformer`, see
 * config/services.yml), which is still {@see \FOS\ElasticaBundle\Transformer\ModelToElasticaAutoTransformer}
 * under the hood, just wired with a custom {@see \demosplan\DemosPlanCoreBundle\Logic\NullablePropertyAccessor}.
 * Symfony's PropertyAccessor resolves a "recommendation" path via the `getRecommendation()`
 * getter method, not raw reflection — this test proves that resolution actually happens,
 * rather than trusting the PropertyAccessor's documented behavior on faith.
 *
 * Transformation is a pure in-memory operation ({@see ModelToElasticaTransformerInterface::transform()}
 * returns an {@see \Elastica\Document}) — no live Elasticsearch cluster is needed, which
 * sidesteps this environment's ES-unavailability issue affecting other tests in this suite.
 */
class BoilerplateElasticsearchIndexingTest extends FunctionalTestCase
{
    protected ?ModelToElasticaTransformerInterface $sut = null;
    protected ?EntityManagerInterface $entityManager = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = self::getContainer()->get('path_based_model_to_elastica_transformer');
        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
    }

    public function testTransformUsesSubstitutingRecommendationAccessor(): void
    {
        $boilerplate = BoilerplateFactory::createOne(['text' => 'Aktueller Textbausteininhalt'])->_real();
        $segment = SegmentFactory::createOne([
            'procedure'      => $boilerplate->getProcedure(),
            'recommendation' => "Hallo, <dp-boilerplate boilerplate-id=\"{$boilerplate->getId()}\"></dp-boilerplate> mit Grüßen",
        ])->_real();
        $this->entityManager->refresh($segment);

        $document = $this->sut->transform($segment, ['recommendation' => []]);

        static::assertSame('Hallo, Aktueller Textbausteininhalt mit Grüßen', $document->get('recommendation'));
        static::assertStringNotContainsString('dp-boilerplate', (string) $document->get('recommendation'));
    }
}
