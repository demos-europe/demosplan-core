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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Boilerplate;
use demosplan\DemosPlanCoreBundle\Logic\Statement\BoilerplateTagSubstitutionService;
use Tests\Base\FunctionalTestCase;

class BoilerplateTagSubstitutionServiceTest extends FunctionalTestCase
{
    protected ?BoilerplateTagSubstitutionService $sut = null;

    protected function setUp(): void
    {
        parent::setUp();
        // Fetched directly from the container: BoilerplateTagSubstitutionEntityListener is
        // now a real consumer (since Step 2), so this service is no longer eliminated as
        // unused dead code the way it was when this test was first written.
        $this->sut = self::getContainer()->get(BoilerplateTagSubstitutionService::class);
    }

    public function testExtractBoilerplateIdsReturnsEmptyArrayWhenNoTagPresent(): void
    {
        $ids = $this->sut->extractBoilerplateIds('Ganz normaler Text ohne Textbaustein.');

        static::assertSame([], $ids);
    }

    public function testExtractBoilerplateIdsFindsSingleTag(): void
    {
        $embedded = 'Hallo, <dp-boilerplate boilerplate-id="1234"></dp-boilerplate> mit freundlichen Grüßen';

        $ids = $this->sut->extractBoilerplateIds($embedded);

        static::assertSame(['1234'], $ids);
    }

    public function testExtractBoilerplateIdsFindsMultipleDistinctTagsInOrder(): void
    {
        $embedded = '<dp-boilerplate boilerplate-id="1"></dp-boilerplate> und <dp-boilerplate boilerplate-id="2"></dp-boilerplate>';

        $ids = $this->sut->extractBoilerplateIds($embedded);

        static::assertSame(['1', '2'], $ids);
    }

    public function testExtractBoilerplateIdsDeduplicatesRepeatedTag(): void
    {
        $embedded = '<dp-boilerplate boilerplate-id="1"></dp-boilerplate> und noch einmal <dp-boilerplate boilerplate-id="1"></dp-boilerplate>';

        $ids = $this->sut->extractBoilerplateIds($embedded);

        static::assertSame(['1'], $ids);
    }

    public function testSubstituteReturnsTextUnchangedWhenNoTagPresent(): void
    {
        $plainText = 'Ganz normaler Text ohne Textbaustein.';

        $result = $this->sut->substitute($plainText);

        static::assertSame($plainText, $result);
    }

    public function testSubstituteReplacesTagWithLivePreloadedContent(): void
    {
        $embedded = 'Hallo, <dp-boilerplate boilerplate-id="bp-a"></dp-boilerplate> mit freundlichen Grüßen';

        $result = $this->sut->substitute($embedded, ['bp-a' => '<p>Mein TB Inhalt</p>']);

        static::assertSame('Hallo, <p>Mein TB Inhalt</p> mit freundlichen Grüßen', $result);
    }

    public function testSubstituteLoadsFromRepositoryWhenNoPreloadedMapGiven(): void
    {
        $boilerplate = BoilerplateFactory::createOne(['text' => 'Live-Inhalt des Textbausteins'])->_real();
        $embedded = "Hallo, <dp-boilerplate boilerplate-id=\"{$boilerplate->getId()}\"></dp-boilerplate> mit Grüßen";

        $result = $this->sut->substitute($embedded);

        static::assertSame('Hallo, Live-Inhalt des Textbausteins mit Grüßen', $result);
    }

    public function testSubstituteUsesLiveTextNotSnapshotAtLinkTime(): void
    {
        $boilerplate = BoilerplateFactory::createOne(['text' => 'Alter Inhalt'])->_real();
        $embedded = "<dp-boilerplate boilerplate-id=\"{$boilerplate->getId()}\"></dp-boilerplate>";

        $boilerplate->setText('Neuer Inhalt');

        $result = $this->sut->substitute($embedded);

        static::assertSame('Neuer Inhalt', $result);
    }

    /**
     * DPLAN-18271 step 8 ("populate caching"): avoids one DB lookup per tag occurrence
     * during bulk operations (Elasticsearch populate) that substitute many recommendations
     * referencing the same handful of boilerplates. Safe per Trap 7 ("per-request caching
     * is safe; long-running processes cache per-run only") — this service is a shared,
     * singleton-per-container Symfony service, so the cache's lifetime is exactly one
     * request or one long-running command run.
     *
     * Proven here by removing the boilerplate row directly (bypassing the normal deletion
     * flow) between two substitute() calls for the same id: without caching, the second
     * call would fall back to "" (Trap 8's residual-race behavior); with caching, it still
     * returns the value resolved on the first call.
     */
    public function testSubstituteCachesResolvedTextAcrossCallsWithinTheSameInstance(): void
    {
        $boilerplate = BoilerplateFactory::createOne(['text' => 'Zwischengespeicherter Inhalt'])->_real();
        $boilerplateId = $boilerplate->getId();
        $embedded = "<dp-boilerplate boilerplate-id=\"{$boilerplateId}\"></dp-boilerplate>";

        $firstResult = $this->sut->substitute($embedded);
        static::assertSame('Zwischengespeicherter Inhalt', $firstResult);

        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $entityManager->remove($entityManager->getReference(Boilerplate::class, $boilerplateId));
        $entityManager->flush();

        $secondResult = $this->sut->substitute($embedded);
        static::assertSame('Zwischengespeicherter Inhalt', $secondResult);
    }

    public function testSubstituteFallsBackToEmptyStringForNonExistentBoilerplate(): void
    {
        $embedded = 'Hallo, <dp-boilerplate boilerplate-id="does-not-exist"></dp-boilerplate> mit Grüßen';

        $result = $this->sut->substitute($embedded);

        static::assertSame('Hallo,  mit Grüßen', $result);
    }

    public function testSubstitutePreservesUmlautsAndSharpS(): void
    {
        $embedded = '<dp-boilerplate boilerplate-id="bp-a"></dp-boilerplate>';

        $result = $this->sut->substitute($embedded, ['bp-a' => 'Grüße, Straße, Löwenzahn, ÄÖÜ']);

        static::assertSame('Grüße, Straße, Löwenzahn, ÄÖÜ', $result);
    }

    public function testSubstituteHandlesMultipleDistinctTagsIndependently(): void
    {
        $embedded = 'A: <dp-boilerplate boilerplate-id="bp-a"></dp-boilerplate>, B: <dp-boilerplate boilerplate-id="bp-b"></dp-boilerplate>';

        $result = $this->sut->substitute($embedded, [
            'bp-a' => 'Erster Inhalt',
            'bp-b' => 'Zweiter Inhalt',
        ]);

        static::assertSame('A: Erster Inhalt, B: Zweiter Inhalt', $result);
    }

    public function testMaterializeBoilerplateReplacesOnlyTheTargetedTag(): void
    {
        // DPLAN-18271 delete-time materialization: deleting bp-a must leave bp-b's tag
        // completely untouched (still a live tag, not substituted), since bp-b is not
        // being deleted.
        $embedded = 'A: <dp-boilerplate boilerplate-id="bp-a"></dp-boilerplate>, B: <dp-boilerplate boilerplate-id="bp-b"></dp-boilerplate>';

        $result = $this->sut->materializeBoilerplate($embedded, 'bp-a', 'Erster Inhalt');

        static::assertSame(
            'A: Erster Inhalt, B: <dp-boilerplate boilerplate-id="bp-b"></dp-boilerplate>',
            $result
        );
    }

    public function testMaterializeBoilerplateReplacesEveryOccurrenceOfTheSameId(): void
    {
        $embedded = '<dp-boilerplate boilerplate-id="bp-a"></dp-boilerplate> und noch einmal <dp-boilerplate boilerplate-id="bp-a"></dp-boilerplate>';

        $result = $this->sut->materializeBoilerplate($embedded, 'bp-a', 'Inhalt');

        static::assertSame('Inhalt und noch einmal Inhalt', $result);
    }

    public function testMaterializeBoilerplateRemovesTagWhenReplacementTextIsEmpty(): void
    {
        $embedded = 'Hallo, <dp-boilerplate boilerplate-id="bp-a"></dp-boilerplate> mit Grüßen';

        $result = $this->sut->materializeBoilerplate($embedded, 'bp-a', '');

        static::assertSame('Hallo,  mit Grüßen', $result);
    }

    public function testMaterializeBoilerplateReturnsTextUnchangedWhenNoTagPresent(): void
    {
        $plainText = 'Ganz normaler Text ohne Textbaustein.';

        $result = $this->sut->materializeBoilerplate($plainText, 'bp-a', 'Inhalt');

        static::assertSame($plainText, $result);
    }
}
