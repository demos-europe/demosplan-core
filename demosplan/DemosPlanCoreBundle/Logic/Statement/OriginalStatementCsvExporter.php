<?php

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use League\Csv\Writer;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;

class OriginalStatementCsvExporter extends CoreService
{
    public function export(array $statements): string
    {
        $csv = Writer::createFromString('');
        $csv->setDelimiter(',');
        $csv->setEnclosure('"');
        $csv->setEscape('\\');

        // Define headers
        $headers = ['ID', 'Title', 'Submitter',/* more fields as
   needed */];
        $csv->insertOne($headers);

        // Add statement dat
        foreach ($statements as $statement) {
            /** @var Statement $statement */
            $csv->insertOne([
                $statement->getId(),
                $statement->getTitle(),
                $statement->getSubmitterName(),
                // Add more fields as needed
            ]);
        }

        return $csv->toString();
    }
}

