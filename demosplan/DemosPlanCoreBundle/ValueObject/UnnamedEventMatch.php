<?php declare(strict_types=1);


namespace demosplan\DemosPlanCoreBundle\ValueObject;


class UnnamedEventMatch
{
    public function __construct(
        protected readonly string $filePath,
        protected readonly string $nameSpace,
        protected readonly ?string $matchingParent,
    ) {
    }

    public function toArray(): array
    {
        return [
            'filePath'          => $this->filePath,
            'nameSpace'         => $this->nameSpace,
            'matchingParent'    => $this->matchingParent,
        ];
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function __toString(): string
    {
        return $this->nameSpace;
    }

}
