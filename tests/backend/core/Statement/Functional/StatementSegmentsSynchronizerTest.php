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

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\EventListener\StatementSegmentsSynchronizerListener;
use Tests\Base\FunctionalTestCase;

class StatementSegmentsSynchronizerTest extends FunctionalTestCase
{
    /**
     * @var StatementSegmentsSynchronizerListener
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testSaveStatementGetSegments(): void
    {
        $listener = new StatementSegmentsSynchronizerListener();
        $statement = StatementFactory::createOne();
        self::assertCount(0, $statement->getSegmentsOfStatement());

        $segmentsHtml = <<<EOL
<dplan-statement>
<dplan-segment
     data-segment-id="d35bc463-4b89-479d-b98c-855f160b05ad"
         data-tags="[{'tag': 'abc', 'id': 'de67acc9-6216-4910-b33f-691cea1aeeb2', 'topic': {'name': 'Umweltfaktoren', 'id': '34567890'}}, {'tag': 'keine', 'id': 'de67acc9-6216-4910-b33f-691cea1aeeb1', 'topic': {'name': 'Umweltfaktoren', 'id': '34567890'}}]"
    ><p>heyho</p></dplan-segment>

<dplan-segment
           data-segment-id="ccd162af-fc9d-48f6-b739-76eca54a3156"
               data-tags="[{'tag': 'def', 'id': 'de67acc9-6216-4910-b33f-691cea1aeeb3', 'topic': {'name': 'Umweltfaktoren', 'id': '34567890'}}, {'tag': 'naja', 'id': 'de67acc9-6216-4910-b33f-691cea1aeeb4', 'topic': {'name': 'Emissionen', 'id': '1238864'}}]">
        <p><b>ho</b>yo</p>
</dplan-segment>
</dplan-statement>
EOL;
        $statement->setMemo($segmentsHtml);
        $seg = $listener->getSegmentsFromStatement($statement->_real());
        $statement->_save();
        self::assertCount(2, $statement->getSegmentsOfStatement());

    }
}
