<?php declare(strict_types=1);


namespace demosplan\DemosPlanCoreBundle\ValueObject;

/**
 * Used to store various information as string of (event) classes found in any directories.
 */
class EventMatch extends UnnamedEventMatch
{
    /**
     * @var array<int, string>
     */
    private array $filesOfUsage = [];

    public function __construct(
        string $filePath,
        string $nameSpace,
        protected readonly string $className,
        ?string $matchingParent,
        protected readonly bool $isEventLikeName
    ) {
        parent::__construct($filePath, $nameSpace, $matchingParent);
    }

    public function toArray(): array
    {
        $result = parent::toArray();
        $result['isEventLikeName'] = $this->isEventLikeName;
        $result['className'] = $this->className;
        $result['filesOfUsage'] = $this->getUsages();

        return $result;
    }

    public function addUsage(string $filePath): void
    {
        $this->filesOfUsage[] = str_replace(DIRECTORY_SEPARATOR, "\\", $filePath);
    }

    public function getUsages(): array
    {
        return $this->filesOfUsage;
    }

    public function getFilesOfUsage(): array
    {
        return $this->filesOfUsage;
    }

    public function __toString(): string
    {
        return parent::__toString().', '.$this->className;
    }

    public function getClassName(): string
    {
        return $this->className;
    }
}
