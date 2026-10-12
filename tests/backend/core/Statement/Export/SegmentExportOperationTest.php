<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Export;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\AbstractApiTest;
use Tests\Core\JsonApi\StatementSegment\SegmentCustomFieldTestTrait;
use Tests\Core\JsonApi\StatementSegment\StatementSegmentApiTestTrait;

/**
 * The `export` operation of the StatementSegment resource (`GET /api/3.0/StatementSegment/export.csv|xlsx`):
 * the list's query, as a file.
 */
class SegmentExportOperationTest extends AbstractApiTest
{
    use SegmentCustomFieldTestTrait;
    use StatementSegmentApiTestTrait;

    private const EXPORT_PERMISSIONS = ['feature_json_api_statement_segment', 'feature_segments_list_export_csv', 'feature_segments_list_export_xlsx'];
    private const PERMISSIONS = [...self::EXPORT_PERMISSIONS, 'field_segments_custom_fields'];

    public function testExportsCsvWithTheCustomFieldFilter(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $field = $this->createSegmentCustomField($procedure->_real(), 'Priority', ['High', 'Low']);
        [$high, $low] = $this->getOptionIds($field);
        $this->createLabeledSegment($procedure, 'SEG-HIGH', [$field->getId() => $high]);
        $this->createLabeledSegment($procedure, 'SEG-LOW', [$field->getId() => $low]);

        $response = $this->sendExport('csv', $procedure, ['customField' => [$field->getId() => [$high]]]);
        $csv = $this->getFileContent();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        self::assertStringContainsString('.csv', $response->headers->get('Content-Disposition'));
        self::assertStringContainsString('SEG-HIGH', $csv);
        self::assertStringNotContainsString('SEG-LOW', $csv);
    }

    public function testExportsXlsxWithTheCustomFieldFilter(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $field = $this->createSegmentCustomField($procedure->_real(), 'Priority', ['High', 'Low']);
        [$high, $low] = $this->getOptionIds($field);
        $this->createLabeledSegment($procedure, 'SEG-HIGH', [$field->getId() => $high]);
        $this->createLabeledSegment($procedure, 'SEG-LOW', [$field->getId() => $low]);

        $response = $this->sendExport('xlsx', $procedure, ['customField' => [$field->getId() => [$high]]]);
        $cells = $this->getCellsOfXlsx($this->getFileContent());

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('spreadsheetml.sheet', $response->headers->get('Content-Type'));
        self::assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));
        self::assertContains('SEG-HIGH', $cells);
        self::assertNotContains('SEG-LOW', $cells);
    }

    /**
     * The API provider parses the real request, so filters with a dot in their name work without extra care.
     */
    public function testFiltersWithADotInTheirNameWork(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $statementA = StatementFactory::createOne(['procedure' => $procedure->_real(), 'externId' => 'STMT-AAA'])->_real();
        $statementB = StatementFactory::createOne(['procedure' => $procedure->_real(), 'externId' => 'STMT-BBB'])->_real();
        SegmentFactory::createOne(['externId' => 'SEG-OF-A', 'procedure' => $procedure, 'parentStatementOfSegment' => $statementA]);
        SegmentFactory::createOne(['externId' => 'SEG-OF-B', 'procedure' => $procedure, 'parentStatementOfSegment' => $statementB]);

        $this->sendExport('csv', $procedure, ['parentStatementOfSegment.externId' => 'STMT-AAA']);
        $csv = $this->getFileContent();

        self::assertStringContainsString('SEG-OF-A', $csv);
        self::assertStringNotContainsString('SEG-OF-B', $csv);
    }

    public function testExportsMoreSegmentsThanFitOnOnePage(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $statement = StatementFactory::createOne(['procedure' => $procedure->_real()])->_real();
        for ($i = 1; $i <= 101; ++$i) {
            SegmentFactory::createOne(['externId' => "BULK-$i", 'procedure' => $procedure, 'parentStatementOfSegment' => $statement]);
        }

        $this->sendExport('csv', $procedure, []);

        self::assertSame(101, substr_count($this->getFileContent(), 'BULK-'));
    }

    public function testExportsInTheRequestedOrder(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $this->createSegmentInProcedure($procedure, ['externId' => 'SEG-FIRST', 'orderInProcedure' => 1]);
        $this->createSegmentInProcedure($procedure, ['externId' => 'SEG-SECOND', 'orderInProcedure' => 2]);

        $this->sendExport('csv', $procedure, ['order' => ['orderInProcedure' => 'asc']]);
        $ascending = $this->getFileContent();
        $this->sendExport('csv', $procedure, ['order' => ['orderInProcedure' => 'desc']]);
        $descending = $this->getFileContent();

        self::assertLessThan(strpos($ascending, 'SEG-SECOND'), strpos($ascending, 'SEG-FIRST'));
        self::assertGreaterThan(strpos($descending, 'SEG-SECOND'), strpos($descending, 'SEG-FIRST'));
    }

    /**
     * The order the FE sends: keys with a dot in their name, as the list request uses them.
     */
    public function testAcceptsTheDefaultOrderOfTheList(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $this->createLabeledSegment($procedure, 'SEG-ONE', null);
        $this->createLabeledSegment($procedure, 'SEG-TWO', null);

        $response = $this->sendExport('csv', $procedure, ['order' => [
            'parentStatementOfSegment.submit'   => 'asc',
            'parentStatementOfSegment.externId' => 'asc',
            'orderInProcedure'                  => 'asc',
        ]]);
        $csv = $this->getFileContent();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('SEG-ONE', $csv);
        self::assertStringContainsString('SEG-TWO', $csv);
    }

    public function testTheFileNameShowsThatTheExportIsFiltered(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $field = $this->createSegmentCustomField($procedure->_real(), 'Priority', ['High']);
        [$high] = $this->getOptionIds($field);
        $this->createLabeledSegment($procedure, 'SEG-HIGH', [$field->getId() => $high]);

        $unfiltered = $this->sendExport('csv', $procedure, [])->headers->get('Content-Disposition');
        $filtered = $this->sendExport('csv', $procedure, ['customField' => [$field->getId() => [$high]]])->headers->get('Content-Disposition');

        self::assertNotSame($unfiltered, $filtered);
    }

    public function testRequiresTheProcedure(): void
    {
        $this->client->setServerParameter('HTTP_ACCEPT', 'text/html,application/xhtml+xml,*/*;q=0.8');
        $response = $this->sendSegmentRequest(
            self::SEGMENT_COLLECTION_ROUTE.'/export.csv?columns=externId',
            null,
            self::PERMISSIONS
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
    }

    public function testRequiresThePermissionOfTheFormat(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();

        $response = $this->sendExport('csv', $procedure, [], ['feature_json_api_statement_segment']);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testTheListStillAnswersWithJson(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $this->createLabeledSegment($procedure, 'SEG-ONE', null);

        $this->client->setServerParameter('HTTP_ACCEPT', 'application/vnd.api+json');
        $response = $this->sendSegmentRequest(
            self::SEGMENT_COLLECTION_ROUTE.'?parentStatementOfSegment.procedure.id='.$procedure->getId(),
            $procedure,
            self::PERMISSIONS
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertCount(1, $this->getResponseIds($response));
    }

    /**
     * @param array<string, string|list<string>>|null $valuesByFieldId
     */
    private function createLabeledSegment(Procedure $procedure, string $externId, ?array $valuesByFieldId): void
    {
        $attributes = ['externId' => $externId];
        if (null !== $valuesByFieldId) {
            $attributes['customFields'] = $this->buildCustomFieldValues($valuesByFieldId);
        }

        $this->createSegmentInProcedure($procedure, $attributes);
    }

    /**
     * @param array<string, mixed> $query       the list's query parameters
     * @param list<string>         $permissions
     */
    private function sendExport(string $format, Procedure $procedure, array $query, array $permissions = self::PERMISSIONS): Response
    {
        $queryString = http_build_query($query + [
            'procedureId' => $procedure->getId(),
            'columns'     => 'externId',
        ]);

        // A browser asks for html: the format comes from the URL suffix.
        $this->client->setServerParameter('HTTP_ACCEPT', 'text/html,application/xhtml+xml,*/*;q=0.8');

        return $this->sendSegmentRequest(
            self::SEGMENT_COLLECTION_ROUTE."/export.$format?$queryString",
            null,
            $permissions
        );
    }

    /**
     * The export is a streamed response, whose content only the browser's internal response holds.
     */
    private function getFileContent(): string
    {
        return $this->client->getInternalResponse()->getContent();
    }

    /**
     * @return list<string> the text of all cells of all sheets
     */
    private function getCellsOfXlsx(string $content): array
    {
        $file = tempnam(sys_get_temp_dir(), 'segment-export');
        file_put_contents($file, $content);

        try {
            $cells = [];
            foreach (IOFactory::load($file)->getAllSheets() as $sheet) {
                foreach ($sheet->toArray() as $row) {
                    array_push($cells, ...array_map('strval', $row));
                }
            }

            return $cells;
        } finally {
            unlink($file);
        }
    }
}
