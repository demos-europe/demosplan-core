<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\AssessmentTable;

use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;

/**
 * @method string getFileNamePrefix()
 * @method        setFileNamePrefix(string $fileNamePrefix)
 * @method string getTitle()
 * @method        setTitle(string $title)
 * @method string getTemplateName()
 * @method        setTemplateName(string $templateName)
 */
class ExportTemplateData extends ValueObject
{
    /** @var string */
    protected $fileNamePrefix;
    /** @var string */
    protected $title;
    /** @var string */
    protected $templateName;
}
