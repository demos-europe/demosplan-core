<?php

namespace demosplan\DemosPlanCoreBundle\Api\EmailAddress;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use demosplan\DemosPlanCoreBundle\Entity\EmailAddress;
use demosplan\DemosPlanCoreBundle\Repository\EmailAddressRepository;
use Exception;
use Psr\Log\LoggerInterface;
use Webmozart\Assert\Assert;

class EmailAddressProvider implements ProviderInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private EmailAddressRepository $emailAddressRepository,
    ) {

    }
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|null
    {
        try {
            Assert::same($operation->getClass(), EmailAddress::class);

            if (isset($uriVariables['id'])) {
                return $this->provideSingle($uriVariables['id']);
            }
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage());
        }
        return null;
    }

    private function provideSingle(string $id): ?EmailAddressResource
    {
        try {
            $emailAddress = $this->emailAddressRepository->find($id);
            Assert::isInstanceOf($emailAddress, EmailAddress::class);

            return EmailAddressResource::fromEntity($emailAddress);
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage());

            return null;
        }

    }
}
