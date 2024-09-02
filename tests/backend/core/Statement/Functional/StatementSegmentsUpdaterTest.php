<?php

namespace Tests\Core\Statement\Functional;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementPart;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementPartsUpdater;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use PHPUnit\Framework\TestCase;

class StatementSegmentsUpdaterTest extends TestCase
{
    private $statementService;
    private $statementPartsUpdater;
    private $givenXml;

    protected function setUp(): void
    {
        $this->statementService = $this->createMock(StatementService::class);
        $this->statementPartsUpdater = new StatementPartsUpdater($this->statementService);
        $this->givenXml = <<<XML
<dplan-statement>
<dplan-segment
     data-segment-id="d35bc463-4b89-479d-b98c-855f160b05ad"
     data-tags='[{"tag": "abc", "id": "de67acc9-6216-4910-b33f-691cea1aeeb2", "topic": {"name": "Umweltfaktoren", "id": "34567890"}}, {"tag": "keine", "id": "de67acc9-6216-4910-b33f-691cea1aeeb1", "topic": {"name": "Umweltfaktoren", "id": "34567890"}}]'>
    <p>heyho</p>
</dplan-segment>

<dplan-segment
     data-segment-id="ccd162af-fc9d-48f6-b739-76eca54a3156"
     data-tags='[{"tag": "def", "id": "de67acc9-6216-4910-b33f-691cea1aeeb3", "topic": {"name": "Umweltfaktoren", "id": "34567890"}}, {"tag": "naja", "id": "de67acc9-6216-4910-b33f-691cea1aeeb4", "topic": {"name": "Emissionen", "id": "1238864"}}]'>
    <p><b>ho</b>yo</p>
</dplan-segment>
</dplan-statement>
XML;
    }

    public function testUpdatesSegmentDataTagsAndContentWhenSegmentExists()
    {
        $statementPart = $this->createMock(StatementPart::class);
        $statement = $this->createMock(Statement::class);
        $statement->method('getMemo')->willReturn($this->givenXml);
        $statementPart->method('getStatement')->willReturn($statement);
        $expectedXml = <<<XML
<?xml version="1.0"?>
<dplan-statement>
<dplan-segment
     data-segment-id="d35bc463-4b89-479d-b98c-855f160b05ad"
     data-tags='[{"tag": "abc", "id": "de67acc9-6216-4910-b33f-691cea1aeeb2", "topic": {"name": "Umweltfaktoren", "id": "34567890"}}, {"tag": "keine", "id": "de67acc9-6216-4910-b33f-691cea1aeeb1", "topic": {"name": "Umweltfaktoren", "id": "34567890"}}]'>
    <p>heyho</p>
</dplan-segment>

<dplan-segment
    data-segment-id="ccd162af-fc9d-48f6-b739-76eca54a3156"
    data-tags='[{"tag": "updated", "id": "new-tag-id", "topic": {"name": "New Topic", "id": "new-topic-id"}}]'>
    <p><b>updated</b> content</p>
</dplan-segment>
</dplan-statement>
XML;

        $statement->expects($this->once())->method('setMemo')->with($expectedXml);

        $this->statementService->expects($this->once())->method('updateStatementObject')->with($statement);

        $result = $this->statementPartsUpdater->updateStatement($statementPart);

        $this->assertNotEmpty($result);
    }

    public function testReturnsEmptyArrayWhenHtmlIsEmpty()
    {
        $statementPart = $this->createMock(StatementPart::class);
        $statement = $this->createMock(Statement::class);
        $statement->method('getMemo')->willReturn('');
        $statementPart->method('getStatement')->willReturn($statement);

        $result = $this->statementPartsUpdater->updateStatement($statementPart);

        $this->assertEmpty($result);
    }

    public function testReturnsEmptyArrayWhenXmlIsInvalid()
    {
        $statementPart = $this->createMock(StatementPart::class);
        $statement = $this->createMock(Statement::class);
        $statement->method('getMemo')->willReturn('<invalid-xml>');
        $statementPart->method('getStatement')->willReturn($statement);

        $result = $this->statementPartsUpdater->updateStatement($statementPart);

        $this->assertEmpty($result);
    }

    public function testDoesNotUpdateWhenSegmentNotFound()
    {
        $statementPart = $this->createMock(StatementPart::class);
        $statement = $this->createMock(Statement::class);
        $statement->method('getMemo')->willReturn('<dplan-segment data-segment-id="different-id"><p>original content</p></dplan-segment>');
        $statementPart->method('getStatement')->willReturn($statement);

        $this->statementService->expects($this->never())->method('updateStatementObject');

        $result = $this->statementPartsUpdater->updateStatement($statementPart);

        $this->assertEmpty($result);
    }
}
