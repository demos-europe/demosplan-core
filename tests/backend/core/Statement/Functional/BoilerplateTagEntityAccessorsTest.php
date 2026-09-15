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
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Repository\RecommendationVersionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Tests\Base\FunctionalTestCase;

/**
 * Covers the entity-level accessor split introduced for DPLAN-18271
 * (getRecommendation() substitutes, getRecommendationEmbedded() stays raw), and the
 * Trap 2 fix for addRecommendationParagraph() (must not destroy a tag by operating on
 * the substituted form).
 */
class BoilerplateTagEntityAccessorsTest extends FunctionalTestCase
{
    protected ?EntityManagerInterface $entityManager = null;
    protected ?RecommendationVersionRepository $recommendationVersionRepository = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->recommendationVersionRepository = self::getContainer()->get(RecommendationVersionRepository::class);
    }

    public function testGetRecommendationSubstitutesTagAfterRealDatabaseLoad(): void
    {
        $boilerplate = BoilerplateFactory::createOne(['text' => 'Aktueller Textbausteininhalt'])->_real();
        $segment = SegmentFactory::createOne([
            'recommendation' => "Hallo, <dp-boilerplate boilerplate-id=\"{$boilerplate->getId()}\"></dp-boilerplate> mit Grüßen",
        ])->_real();

        // Force a real reload from the database so postLoad listeners fire, matching
        // how the entity is actually hydrated outside of this test (Foundry's freshly
        // created object already has the service injected in-process; refreshing proves
        // the listener wiring itself, not just the object graph Foundry built).
        $this->entityManager->refresh($segment);

        static::assertSame('Hallo, Aktueller Textbausteininhalt mit Grüßen', $segment->getRecommendation());
        static::assertSame(
            "Hallo, <dp-boilerplate boilerplate-id=\"{$boilerplate->getId()}\"></dp-boilerplate> mit Grüßen",
            $segment->getRecommendationEmbedded()
        );
    }

    public function testGetRecommendationShortNeverContainsATag(): void
    {
        $boilerplate = BoilerplateFactory::createOne(['text' => 'Kurzer Inhalt'])->_real();
        $segment = SegmentFactory::createOne([
            'recommendation' => "<dp-boilerplate boilerplate-id=\"{$boilerplate->getId()}\"></dp-boilerplate>",
        ])->_real();
        $this->entityManager->refresh($segment);

        $short = $segment->getRecommendationShort();

        static::assertStringNotContainsString('dp-boilerplate', $short);
        static::assertStringContainsString('Kurzer Inhalt', $short);
    }

    public function testAddRecommendationParagraphPreservesTag(): void
    {
        $boilerplate = BoilerplateFactory::createOne(['text' => 'Textbausteininhalt'])->_real();
        $segment = SegmentFactory::createOne([
            'recommendation' => "<dp-boilerplate boilerplate-id=\"{$boilerplate->getId()}\"></dp-boilerplate>",
        ])->_real();
        $this->entityManager->refresh($segment);

        $segment->addRecommendationParagraph('<p>Zusätzlicher Absatz</p>');

        static::assertSame(
            "<dp-boilerplate boilerplate-id=\"{$boilerplate->getId()}\"></dp-boilerplate><p>Zusätzlicher Absatz</p>",
            $segment->getRecommendationEmbedded()
        );
        static::assertSame(
            'Textbausteininhalt<p>Zusätzlicher Absatz</p>',
            $segment->getRecommendation()
        );
    }

    public function testVersionHistoryNeverContainsATag(): void
    {
        // Trap 5 (DPLAN-18271 plan): recordVersion() must receive substituted old/new
        // values, not the raw tag form, or version history — already exposed via
        // RecommendationVersionResourceType's API — starts leaking markers.
        $boilerplate = BoilerplateFactory::createOne(['text' => 'Alter Textbausteininhalt'])->_real();
        $segment = SegmentFactory::createOne([
            'procedure'      => $boilerplate->getProcedure(),
            'recommendation' => '',
        ])->_real();
        $this->entityManager->refresh($segment);
        $segment->setRecommendation("<dp-boilerplate boilerplate-id=\"{$boilerplate->getId()}\"></dp-boilerplate>");
        $this->entityManager->flush();

        $segment->setRecommendation('Komplett neuer Text ohne Textbaustein');
        $this->entityManager->flush();

        $versions = $this->recommendationVersionRepository->findByStatementId($segment->getId());
        static::assertCount(1, $versions);
        static::assertSame('Alter Textbausteininhalt', $versions[0]->getRecommendationText());
        static::assertStringNotContainsString('dp-boilerplate', $versions[0]->getRecommendationText());
    }

    public function testVersionRecordedOnUnlinkEvenWhenSubstitutedTextStaysIdentical(): void
    {
        // Clarified Decision 10 (DPLAN-18271 plan): unlink is always part of a
        // text-change intent, even when the materialized text happens to be
        // byte-identical to what was already being substituted a moment before — the
        // *tag form* changed (tag -> plain text), and that alone must still produce a
        // version entry. Change detection must happen on the raw form, never the
        // substituted form, or this exact case would be silently skipped.
        $boilerplate = BoilerplateFactory::createOne(['text' => 'Aktueller Textbausteininhalt'])->_real();
        $segment = SegmentFactory::createOne([
            'procedure'      => $boilerplate->getProcedure(),
            'recommendation' => '',
        ])->_real();
        $this->entityManager->refresh($segment);
        $segment->setRecommendation("<dp-boilerplate boilerplate-id=\"{$boilerplate->getId()}\"></dp-boilerplate>");
        $this->entityManager->flush();

        // Simulates the FE's "unlink" flow: replace the tag with the exact materialized
        // content already being rendered (getRecommendation() substitutes the tag to
        // this very text) — substituted-form-identical, but the raw/tag form genuinely
        // changed.
        $segment->setRecommendation('Aktueller Textbausteininhalt');
        $this->entityManager->flush();

        $versions = $this->recommendationVersionRepository->findByStatementId($segment->getId());
        static::assertCount(1, $versions);
        static::assertSame('Aktueller Textbausteininhalt', $versions[0]->getRecommendationText());
    }

    public function testGetRecommendationFallsBackToRawValueWithoutInjectedService(): void
    {
        $segment = new Segment();
        $segment->setRecommendation('Text ohne Textbaustein');

        // Never loaded from the database, so no postLoad listener ran — documents the
        // known limitation rather than asserting it as desirable (see
        // BoilerplateTagSubstitutionEntityListener docblock).
        static::assertSame('Text ohne Textbaustein', $segment->getRecommendation());
        static::assertSame('Text ohne Textbaustein', $segment->getRecommendationEmbedded());
    }
}
