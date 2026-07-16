<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Statement;

use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;
use PhpOffice\PhpWord\Writer\WriterInterface;

/**
 * @method string          getFilename()
 * @method WriterInterface getWriter()
 * @method string[]        getStatementIds()
 */
class DocxExportResult extends ValueObject
{
    /** @var string */
    protected $filename;
    /** @var WriterInterface */
    protected $writer;
    /**
     * Ids of the statements contained in the document. Allows consumers (e.g.
     * the procedure export collecting statement attachments) to reuse the
     * already fetched result instead of re-querying Elasticsearch.
     *
     * @var string[]
     */
    protected $statementIds;

    public function __construct(string $filename, WriterInterface $writer, array $statementIds = [])
    {
        $this->filename = $filename;
        $this->writer = $writer;
        $this->statementIds = $statementIds;
        $this->lock();
    }
}
