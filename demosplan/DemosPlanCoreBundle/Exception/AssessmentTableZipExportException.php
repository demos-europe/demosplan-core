<?php
declare(strict_types=1);


namespace demosplan\DemosPlanCoreBundle\Exception;

class AssessmentTableZipExportException extends DemosException
{
    public function __construct(private readonly string $level, string $userMsg, string $logMsg = '', int $code = 0)
    {
        parent::__construct($userMsg, $logMsg, $code);
    }

    public function getLevel(): string
    {
        return $this->level;
    }
}
