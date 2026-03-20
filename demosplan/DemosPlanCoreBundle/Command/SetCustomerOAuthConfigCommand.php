<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use demosplan\DemosPlanCoreBundle\Entity\User\CustomerOAuthConfig;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Repository\CustomerOAuthConfigRepository;
use demosplan\DemosPlanCoreBundle\Repository\CustomerRepository;
use demosplan\DemosPlanCoreBundle\Repository\OrgaRepository;
use demosplan\DemosPlanCoreBundle\Types\IdentityProviderType;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'dplan:customer:oauth-config:sync',
    description: 'Upserts per-customer Keycloak OAuth2 configuration from a JSON file or interactive prompts'
)]
class SetCustomerOAuthConfigCommand extends CoreCommand
{
    private const OPTION_CONFIG_FILE = 'config-file';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CustomerRepository $customerRepository,
        private readonly CustomerOAuthConfigRepository $configRepository,
        private readonly OrgaRepository $orgaRepository,
        ParameterBagInterface $parameterBag,
        ?string $name = null,
    ) {
        parent::__construct($parameterBag, $name);
    }

    protected function configure(): void
    {
        $this->addOption(
            self::OPTION_CONFIG_FILE,
            null,
            InputOption::VALUE_REQUIRED,
            'Path to a JSON file containing per-customer OAuth2 configurations'
        );

        $this->setHelp(<<<'HELP'
            Upserts per-customer Keycloak OAuth2 configuration.

            <info>Batch mode (JSON file):</info>

              <comment>%command.name% --config-file=/path/to/config.json</comment>

            The JSON file must be an object keyed by customer subdomain:

              {
                  "mysubdomain": {
                      "clientId":      "dplan-mysubdomain",
                      "clientSecret":  "super-secret-value",
                      "authServerUrl": "https://keycloak.example.com/auth",
                      "realm":         "dplan",
                      "logoutRoute":   "https://keycloak.example.com/auth/realms/dplan/protocol/openid-connect/logout?post_logout_redirect_uri={redirectUri}&id_token_hint={idToken}"
                  },
                  "other": {
                      "clientId":      "dplan-other",
                      "clientSecret":  "another-secret",
                      "authServerUrl": "https://keycloak.example.com/auth",
                      "realm":         "dplan"
                  }
              }

            Required fields: <comment>clientId</comment>, <comment>clientSecret</comment>, <comment>authServerUrl</comment>, <comment>realm</comment>
            Optional fields: <comment>logoutRoute</comment> (falls back to global oauth_keycloak_logout_route parameter),
                             <comment>defaultOrganisationId</comment> (organisation ID for auto-provisioning new Azure users),
                             <comment>identityProviderType</comment> ("keycloak" or "azure_entra_id", default: "keycloak"),
                             <comment>autoProvisionUsers</comment> (true/false, default: false — requires defaultOrganisationId)

            <info>Interactive mode:</info>

              <comment>%command.name%</comment>

            When called without <comment>--config-file</comment>, the command prompts for each value interactively.
            HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $configFilePath = $input->getOption(self::OPTION_CONFIG_FILE);
        if (!is_string($configFilePath) || '' === $configFilePath) {
            return $this->executeInteractive($io);
        }

        return $this->executeFromFile($io, $configFilePath);
    }

    private function executeFromFile(SymfonyStyle $io, string $configFilePath): int
    {
        if (!file_exists($configFilePath)) {
            $io->error(sprintf('Config file not found: %s', $configFilePath));

            return Command::FAILURE;
        }

        $json = file_get_contents($configFilePath);
        if (false === $json) {
            $io->error(sprintf('Failed to read config file: %s', $configFilePath));

            return Command::FAILURE;
        }

        try {
            $configs = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $io->error(sprintf('Invalid JSON in config file: %s', $e->getMessage()));

            return Command::FAILURE;
        }

        if (!is_array($configs)) {
            $io->error('Config file must contain a JSON object mapping subdomains to OAuth configs.');

            return Command::FAILURE;
        }

        $upserted = 0;
        $skipped = 0;

        foreach ($configs as $subdomain => $customerConfig) {
            try {
                $this->upsertCustomerConfig($subdomain, $customerConfig);
                $io->writeln(sprintf('  <info>✔</info> Upserted config for customer: %s', $subdomain));
                ++$upserted;
            } catch (InvalidArgumentException $e) {
                $io->warning(sprintf('Skipped customer "%s": %s', $subdomain, $e->getMessage()));
                ++$skipped;
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf('Done. Upserted: %d, Skipped: %d', $upserted, $skipped));

        return Command::SUCCESS;
    }

    private function executeInteractive(SymfonyStyle $io): int
    {
        $io->title('Interactive OAuth2 Configuration');

        $customers = $this->customerRepository->findAll();
        $subdomains = array_map(
            static fn ($c) => $c->getSubdomain(),
            $customers
        );
        sort($subdomains);

        $subdomain = $io->choice('Customer subdomain', $subdomains);

        $customer = $this->customerRepository->findOneBy(['subdomain' => $subdomain]);

        $existingConfig = $this->configRepository->findByCustomer($customer);

        $clientSecret = $this->resolveClientSecret($io, $existingConfig);
        if (null === $clientSecret) {
            $io->error('Client secret is required for new configurations.');

            return Command::FAILURE;
        }

        $existingDefaultOrgId = $existingConfig?->getDefaultOrganisation()?->getId();
        $existingIdpType = $existingConfig?->getIdentityProviderType()->value ?? IdentityProviderType::KEYCLOAK->value;
        $existingAutoProvision = $existingConfig?->isAutoProvisionUsers() ? 'true' : 'false';

        $customerConfig = [
            'clientId'               => $io->ask('Client ID', $existingConfig?->getKeycloakClientId()),
            'clientSecret'           => $clientSecret,
            'authServerUrl'          => $io->ask('Auth Server URL (e.g. https://keycloak.example.com/auth)', $existingConfig?->getKeycloakAuthServerUrl()),
            'realm'                  => $io->ask('Realm', $existingConfig?->getKeycloakRealm()),
            'logoutRoute'            => $io->ask('Logout Route (optional, press Enter to skip)', $existingConfig?->getKeycloakLogoutRoute()),
            'defaultOrganisationId'  => $io->ask('Default Organisation ID for auto-provisioning (optional)', $existingDefaultOrgId),
            'identityProviderType'   => $io->choice(
                'Identity Provider Type',
                array_column(IdentityProviderType::cases(), 'value'),
                $existingIdpType
            ),
            'autoProvisionUsers'     => $io->ask('Auto-provision users (true/false)', $existingAutoProvision),
        ];

        $io->section('Summary');
        $io->definitionList(
            ['Subdomain' => $subdomain],
            ['Client ID'              => $customerConfig['clientId']],
            ['Client Secret'          => '********'],
            ['Auth Server URL'        => $customerConfig['authServerUrl']],
            ['Realm'                  => $customerConfig['realm']],
            ['Logout Route'           => $customerConfig['logoutRoute'] ?? '(global default)'],
            ['Default Organisation'   => $customerConfig['defaultOrganisationId'] ?? '(none)'],
            ['Identity Provider'      => $customerConfig['identityProviderType']],
            ['Auto-provision Users'   => $customerConfig['autoProvisionUsers']],
        );

        if ($io->confirm('Save this configuration?')) {
            try {
                $this->upsertCustomerConfig($subdomain, $customerConfig);
                $this->entityManager->flush();
                $io->success(sprintf('OAuth config for customer "%s" saved.', $subdomain));
            } catch (InvalidArgumentException $e) {
                $io->error($e->getMessage());

                return Command::FAILURE;
            }
        } else {
            $io->warning('Aborted.');
        }

        return Command::SUCCESS;
    }

    private function resolveClientSecret(SymfonyStyle $io, ?CustomerOAuthConfig $existingConfig): ?string
    {
        $clientSecret = $io->askHidden(
            'Client Secret (input hidden)'
                .($existingConfig ? ' [leave empty to keep current]' : '')
        );

        if ('' === $clientSecret || null === $clientSecret) {
            return $existingConfig?->getKeycloakClientSecret();
        }

        return $clientSecret;
    }

    /**
     * @param array<string, string> $customerConfig
     *
     * @throws InvalidArgumentException
     */
    private function upsertCustomerConfig(string $subdomain, array $customerConfig): void
    {
        $requiredKeys = ['clientId', 'clientSecret', 'authServerUrl', 'realm'];
        foreach ($requiredKeys as $key) {
            if (!isset($customerConfig[$key]) || '' === $customerConfig[$key]) {
                throw new InvalidArgumentException(sprintf('Missing or empty required field "%s"', $key));
            }
        }

        if (!filter_var($customerConfig['authServerUrl'], FILTER_VALIDATE_URL)
            || !str_starts_with($customerConfig['authServerUrl'], 'https://')) {
            throw new InvalidArgumentException(sprintf('authServerUrl must be a valid HTTPS URL, got "%s"', $customerConfig['authServerUrl']));
        }

        $customer = $this->customerRepository->findOneBy(['subdomain' => $subdomain]);
        if (null === $customer) {
            throw new InvalidArgumentException(sprintf('No customer found with subdomain "%s"', $subdomain));
        }

        $config = $this->configRepository->findByCustomer($customer);
        if (null === $config) {
            $config = new CustomerOAuthConfig();
            $config->setCustomer($customer);
            $this->entityManager->persist($config);
        }

        $config->setKeycloakClientId($customerConfig['clientId']);
        $config->setKeycloakClientSecret($customerConfig['clientSecret']);
        $config->setKeycloakAuthServerUrl($customerConfig['authServerUrl']);
        $config->setKeycloakRealm($customerConfig['realm']);
        $config->setKeycloakLogoutRoute($customerConfig['logoutRoute'] ?? null);

        $defaultOrgId = $customerConfig['defaultOrganisationId'] ?? null;
        if (is_string($defaultOrgId) && '' !== $defaultOrgId) {
            $orga = $this->orgaRepository->get($defaultOrgId);
            if (!$orga instanceof Orga) {
                throw new InvalidArgumentException(sprintf('No organisation found with ID "%s"', $defaultOrgId));
            }
            $config->setDefaultOrganisation($orga);
        } elseif (null === $defaultOrgId || '' === $defaultOrgId) {
            $config->setDefaultOrganisation(null);
        }

        $idpTypeValue = $customerConfig['identityProviderType'] ?? null;
        if (is_string($idpTypeValue) && '' !== $idpTypeValue) {
            $idpType = IdentityProviderType::tryFrom($idpTypeValue);
            if (null === $idpType) {
                throw new InvalidArgumentException(sprintf('Invalid identityProviderType "%s". Valid values: %s', $idpTypeValue, implode(', ', array_column(IdentityProviderType::cases(), 'value'))));
            }
            $config->setIdentityProviderType($idpType);
        }

        $autoProvisionValue = $customerConfig['autoProvisionUsers'] ?? null;
        if (null !== $autoProvisionValue && '' !== $autoProvisionValue) {
            $autoProvision = filter_var($autoProvisionValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if (null === $autoProvision) {
                throw new InvalidArgumentException(sprintf('autoProvisionUsers must be "true" or "false", got "%s"', $autoProvisionValue));
            }
            if ($autoProvision && (null === $defaultOrgId || '' === $defaultOrgId)) {
                throw new InvalidArgumentException('autoProvisionUsers requires a defaultOrganisationId to be set');
            }
            if ($autoProvision && IdentityProviderType::AZURE_ENTRA_ID !== $config->getIdentityProviderType()) {
                throw new InvalidArgumentException('autoProvisionUsers is only supported for azure_entra_id identity provider type');
            }
            $config->setAutoProvisionUsers($autoProvision);
        }
    }
}
