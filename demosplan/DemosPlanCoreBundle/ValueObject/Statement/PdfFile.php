<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Statement;

use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;

/**
 * @method string getName()
 * @method string getContent()
 */
class PdfFile extends ValueObject
{
    /**
     * The filename.
     *
     * @var string
     */
    protected $name;

    /**
     * PDF as string.
     *
     * @var string
     */
    protected $content;

    public function __construct(string $name, string $content)
    {
        $this->name = $name;
        $this->content = $content;
        $this->lock();
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'name'    => $this->name,
            'content' => $this->content,
        ];
    }
}
