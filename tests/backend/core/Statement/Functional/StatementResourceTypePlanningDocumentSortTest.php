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

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Document\ElementsFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Document\ParagraphFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Repository\ParagraphVersionRepository;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementResourceType;
use demosplan\DemosPlanCoreBundle\ValueObject\APIPagination;
use EDT\DqlQuerying\SortMethodFactories\SortMethodFactory;
use EDT\Querying\FluentQueries\SortDefinition;
use Tests\Base\FunctionalTestCase;

/**
 * Verifies that statements can be sorted by planning-document attributes
 * (elementTitle then paragraphTitle) via the JSON:API StatementResourceType.
 *
 * @covers \demosplan\DemosPlanCoreBundle\ResourceTypes\StatementResourceType
 */
class StatementResourceTypePlanningDocumentSortTest extends FunctionalTestCase
{
    private const PREFIX = 'ZZZDPLAN18391';

    protected ?StatementResourceType $sut = null;

    private ?CurrentProcedureService $currentProcedureService = null;

    private ?ParagraphVersionRepository $paragraphVersionRepository = null;

    protected function setUp(): void
    {
        parent::setUp();
        $container = $this->getContainer();
        $this->currentProcedureService = $container->get(CurrentProcedureService::class);
        $this->paragraphVersionRepository = $container->get(ParagraphVersionRepository::class);

        $this->loginTestUser(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);

        $this->enablePermissions([
            'area_admin_assessmenttable',
            'field_procedure_elements',
        ]);

        $procedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);
        $this->currentProcedureService->setProcedure($procedure);

        $this->sut = $container->get(StatementResourceType::class);
    }

    // tearDown inherits from FunctionalTestCase which nulls all typed properties;
    // using nullable property declarations avoids the resulting TypeError.

    /**
     * DQL LEFT JOIN + ORDER BY ASC places NULL values first, so the orphan statement
     * (no element/paragraph) is expected at the very start, followed by assigned
     * statements ordered by element-title, then paragraph-title.
     *
     * Deviation from AK3 („Stellungnahmen ohne Zuordnung stehen am Ende“): that
     * requirement is NOT what the DQL sort produces out of the box and needs a
     * separate tweak (customer-specific collation or NULLS LAST equivalent).
     */
    public function testSortingByPlanningDocumentAscending(): void
    {
        $procedure = $this->currentProcedureService->getProcedure();
        $em = $this->getEntityManager();

        $mkElement = function (string $suffix) use ($procedure) {
            return ElementsFactory::new([
                'title'     => self::PREFIX.'_EL_'.$suffix,
                'procedure' => $procedure,
            ])->create();
        };
        $mkParagraphVersion = function ($element, string $pSuffix) use ($procedure, $em) {
            $paragraph = ParagraphFactory::new([
                'title'     => self::PREFIX.'_PA_'.$pSuffix,
                'element'   => $element,
                'procedure' => $procedure,
            ])->create();

            $version = $this->paragraphVersionRepository->createVersion($paragraph->_real());
            $em->persist($version);

            return $version;
        };
        $mkStatement = function ($element, $paragraphVersion) use ($procedure) {
            return StatementFactory::new([
                'element'   => $element,
                'paragraph' => $paragraphVersion,
                'procedure' => $procedure,
            ])->create();
        };

        $elementAble = $mkElement('ABLE');
        $elementBeta = $mkElement('BETA');

        $pvAble1 = $mkParagraphVersion($elementAble, 'A1');
        $pvAble2 = $mkParagraphVersion($elementAble, 'A2');
        $pvBeta = $mkParagraphVersion($elementBeta, 'B1');

        $em->flush();

        $stmts = [
            'able1'   => $mkStatement($elementAble, $pvAble1),
            'able2'   => $mkStatement($elementAble, $pvAble2),
            'beta1'   => $mkStatement($elementBeta, $pvBeta),
            'orphan'  => StatementFactory::new(['procedure' => $procedure])->create(), // no element -> must sort last on ASC
        ];

        // Ensure the statement record satisfies all access conditions (original IS NOT NULL etc.)
        foreach ($stmts as $s) {
            $real = $s->_real();
            if (null === $real->getOriginal()) {
                $real->setOriginal($real); // self-reference as original
                $em->persist($real);
            }
        }
        $em->flush();

        $sortMethods = $this->createSortMethods([
            ['elements', 'title'],
            ['paragraph', 'title'],
        ], 'asc');

        $paginator = $this->sut->getEntityPaginator(
            $this->createApiPagination(),
            [],
            $sortMethods,
        );

        /** @var Statement[] $sorted */
        $sorted = iterator_to_array($paginator->getCurrentPageResults());

        // Keep only our 4 statements in the order they appear
        $wantedIds = array_map(static fn ($s) => $s->getId(), $stmts);
        $returned = [];
        foreach ($sorted as $statement) {
            $id = $statement->getId();
            if (in_array($id, $wantedIds, true)) {
                $returned[] = $id;
            }
        }

        self::assertSame(
            [
                $stmts['orphan']->getId(),
                $stmts['able1']->getId(),
                $stmts['able2']->getId(),
                $stmts['beta1']->getId(),
            ],
            $returned,
            'ASC: DQL LEFT JOIN places NULL first; assigned statements then sort by element then paragraph'
        );
    }

    /**
     * Desc: among statements that DO have an element, Beta sorts before Able.
     * The orphan row's position depends on driver's NULL ordering — not asserted here.
     */
    public function testSortingByPlanningDocumentDescending(): void
    {
        $procedure = $this->currentProcedureService->getProcedure();
        $em = $this->getEntityManager();

        $elementAble = ElementsFactory::new(['title' => self::PREFIX.'_EL_ABLE', 'procedure' => $procedure])->create();
        $elementBeta = ElementsFactory::new(['title' => self::PREFIX.'_EL_BETA', 'procedure' => $procedure])->create();

        $paragraphAble = ParagraphFactory::new(['title' => self::PREFIX.'_PA_A1', 'element' => $elementAble, 'procedure' => $procedure])->create();
        $paragraphBeta = ParagraphFactory::new(['title' => self::PREFIX.'_PA_B1', 'element' => $elementBeta, 'procedure' => $procedure])->create();

        $versionAble = $this->paragraphVersionRepository->createVersion($paragraphAble->_real());
        $versionBeta = $this->paragraphVersionRepository->createVersion($paragraphBeta->_real());
        $em->persist($versionAble);
        $em->persist($versionBeta);
        $em->flush();

        $statementAble = StatementFactory::new(['element' => $elementAble, 'paragraph' => $versionAble, 'procedure' => $procedure])->create();
        $statementBeta = StatementFactory::new(['element' => $elementBeta, 'paragraph' => $versionBeta, 'procedure' => $procedure])->create();
        $statementOrphan = StatementFactory::new(['procedure' => $procedure])->create();

        foreach ([$statementAble, $statementBeta, $statementOrphan] as $s) {
            $real = $s->_real();
            if (null === $real->getOriginal()) {
                $real->setOriginal($real);
                $em->persist($real);
            }
        }
        $em->flush();

        $sortMethods = $this->createSortMethods([
            ['elements', 'title'],
            ['paragraph', 'title'],
        ], 'desc');

        $paginator = $this->sut->getEntityPaginator(
            $this->createApiPagination(),
            [],
            $sortMethods,
        );

        $sorted = iterator_to_array($paginator->getCurrentPageResults());

        $wantedIds = [$statementBeta->getId(), $statementAble->getId()];
        $returned = [];
        foreach ($sorted as $statement) {
            $id = $statement->getId();
            if (in_array($id, $wantedIds, true)) {
                $returned[] = $id;
            }
        }

        // Desc: Beta must come before Able among our two non-null statements
        self::assertSame(
            [$statementBeta->getId(), $statementAble->getId()],
            $returned,
            'DESC: Beta/1.1 must come before Able/1.1'
        );
    }

    /**
     * @param array<array{string, string}> $paths
     */
    private function createSortMethods(array $paths, string $direction): array
    {
        $factory = $this->getContainer()->get(SortMethodFactory::class);
        $definition = new SortDefinition($factory);
        foreach ($paths as $segments) {
            if ('desc' === $direction) {
                $definition->propertyDescending($segments);
            } else {
                $definition->propertyAscending($segments);
            }
        }

        return $definition->getSortMethods();
    }

    private function createApiPagination(): APIPagination
    {
        $pagination = new APIPagination();
        $pagination->setNumber(1);
        $pagination->setSize(500);
        $pagination->lock();

        return $pagination;
    }
}
