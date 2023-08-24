<?php declare(strict_types=1);


namespace demosplan\DemosPlanCoreBundle\ValueObject;


class EventMatch
{
    /**
     * @var array<int, string>
     */
    private array $filesOfUsage = [];

    public function __construct(
        protected readonly string $filePath,
        protected readonly string $nameSpace,
        protected readonly string $className,
        protected readonly ?string $matchingParent,
        protected readonly bool $matchingName
    ) {
    }

    public function __toString(): string
    {
        return $this->nameSpace ."\\". $this->className;
//        return implode(' ', compact($this->nameSpace, $this->className, $this->matchingParent, $this->matchingName));
    }

    public function toArray(): array
    {
        return [
            'filePath'          => $this->filePath,
            'nameSpace'         => $this->nameSpace,
            'className'         => $this->className,
            'matchingParent'    => $this->matchingParent,
            'matchingName'      => $this->matchingName,
            'filesOfUsage'      => $this->getUsages(),
        ];
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function addUsage(string $filePath): void
    {
        $this->filesOfUsage[] = $filePath;
    }

    public function getUsages(): array
    {
        return $this->filesOfUsage;
    }

    public function getFilesOfUsage(): array
    {
        return $this->filesOfUsage;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

}
