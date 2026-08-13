<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logger;

use DateTimeImmutable;
use DemosEurope\DemosplanAddon\Contracts\CurrentContextProviderInterface;
use demosplan\DemosPlanCoreBundle\Entity\PersonalDataLogEntry;
use Doctrine\DBAL\Exception as DBALException;
use JsonException;
use Monolog\Level;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Throwable;

/**
 * PSR-3 logger that persists the full record (incl. PII) to the {@see PersonalDataLogEntry}
 * table and forwards only a redacted reference line (UUID + content hash + non-PII
 * context) to the decorated channel logger.
 *
 * Inject **explicitly** at the call site that needs PII handling — this is not a
 * drop-in replacement for the autowired application logger.
 *
 * Recognised $context keys:
 *  - 'pii'         array<string,mixed>  confidential payload — written to DB only
 *  - 'procedureId' ?string              overrides auto-resolution from request
 *  - 'orgaId'      ?string              overrides auto-resolution from token
 *  - any other key                      treated as non-PII; forwarded to file log
 *
 * Example:
 *  $piiLogger->info('User logged in', [
 *      'pii'    => ['email' => $email, 'ip' => $ip],
 *      'action' => 'login',
 *      'outcome'=> 'success',
 *  ]);
 *
 * Failure mode: on DBAL exception the call is swallowed (logging must not break
 * business flows) and a redacted WARNING is forwarded to the decorated logger
 * so operators can see something happened without exposing PII.
 */
class PiiAwareLogger extends AbstractLogger
{
    private const CONTEXT_KEY_PII = 'pii';
    private const CONTEXT_KEY_PROCEDURE = 'procedureId';
    private const CONTEXT_KEY_ORGA = 'orgaId';

    private const DEFAULT_CHANNEL = 'pii';

    public function __construct(
        #[Autowire(service: 'monolog.logger.pii')]
        private readonly LoggerInterface $decoratedLogger,
        private readonly PiiLogWriter $writer,
        private readonly CurrentContextProviderInterface $contextProvider,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @param mixed                $level   PSR-3 string or Monolog int/Level
     * @param array<string, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $monologLevel = $this->toMonologLevel($level);
        $messageString = (string) $message;

        $piiContext = $this->extractPii($context);
        $procedureOverride = $this->extractScalar($context, self::CONTEXT_KEY_PROCEDURE);
        $orgaOverride = $this->extractScalar($context, self::CONTEXT_KEY_ORGA);
        $nonPiiContext = $context; // residue after extracts

        $piiContextJson = $this->encodeJson($piiContext);
        $nonPiiContextJson = $this->encodeJson($nonPiiContext);

        $contentHash = $this->computeHash($monologLevel->value, $messageString, $piiContextJson);

        $record = new PiiLogRecord(
            createdAt: new DateTimeImmutable(),
            level: $monologLevel->value,
            levelName: strtolower($monologLevel->getName()),
            channel: self::DEFAULT_CHANNEL,
            message: $messageString,
            piiContextJson: $piiContextJson,
            nonPiiContextJson: $nonPiiContextJson,
            contentHash: $contentHash,
            requestId: $this->resolveRequestId($this->requestStack->getCurrentRequest()),
            procedureId: $procedureOverride ?? $this->resolveProcedureId(),
            orgaId: $orgaOverride ?? $this->resolveOrgaId(),
            sourceContext: $this->resolveSourceContext(),
        );

        try {
            $rowId = $this->writer->write($record);
        } catch (DBALException) {
            $this->decoratedLogger->warning(
                '[pii WRITE_FAILED] hash={hash}',
                ['hash' => substr($contentHash, 0, 16)] + $nonPiiContext,
            );

            return;
        }

        $this->decoratedLogger->log(
            $monologLevel->toPsrLogLevel(),
            '[pii ref={ref} hash={hash}]',
            ['ref' => $rowId, 'hash' => substr($contentHash, 0, 16)] + $nonPiiContext,
        );
    }

    private function toMonologLevel(mixed $level): Level
    {
        if ($level instanceof Level) {
            return $level;
        }
        if (is_int($level)) {
            return Level::tryFrom($level) ?? Level::Info;
        }
        if (is_string($level)) {
            return match (strtolower($level)) {
                LogLevel::EMERGENCY => Level::Emergency,
                LogLevel::ALERT     => Level::Alert,
                LogLevel::CRITICAL  => Level::Critical,
                LogLevel::ERROR     => Level::Error,
                LogLevel::WARNING   => Level::Warning,
                LogLevel::NOTICE    => Level::Notice,
                LogLevel::DEBUG     => Level::Debug,
                default             => Level::Info,
            };
        }

        return Level::Info;
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>|null
     */
    private function extractPii(array &$context): ?array
    {
        if (!array_key_exists(self::CONTEXT_KEY_PII, $context)) {
            return null;
        }
        $value = $context[self::CONTEXT_KEY_PII];
        unset($context[self::CONTEXT_KEY_PII]);

        return is_array($value) ? $value : null;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function extractScalar(array &$context, string $key): ?string
    {
        if (!array_key_exists($key, $context)) {
            return null;
        }
        $value = $context[$key];
        unset($context[$key]);

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function encodeJson(?array $payload): ?string
    {
        if (null === $payload || [] === $payload) {
            return null;
        }
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        } catch (JsonException) {
            return json_encode(['__pii_encode_error' => true], JSON_PARTIAL_OUTPUT_ON_ERROR) ?: null;
        }
    }

    private function computeHash(int $level, string $message, ?string $piiJson): string
    {
        return hash('sha256', self::DEFAULT_CHANNEL."\0".$level."\0".$message."\0".($piiJson ?? ''));
    }

    private function resolveRequestId(?Request $request): ?string
    {
        if (!$request instanceof Request) {
            return null;
        }
        $rid = $request->attributes->get('request_id');

        return is_string($rid) ? $rid : null;
    }

    private function resolveProcedureId(): ?string
    {
        try {
            return $this->contextProvider->getCurrentProcedure()?->getId();
        } catch (Throwable) {
            return null;
        }
    }

    private function resolveOrgaId(): ?string
    {
        try {
            $user = $this->contextProvider->getCurrentUser();
        } catch (Throwable) {
            return null;
        }
        if (method_exists($user, 'getOrganisationId')) {
            $id = $user->getOrganisationId();

            return is_string($id) ? $id : null;
        }

        return null;
    }

    private function resolveSourceContext(): string
    {
        return 'cli' === PHP_SAPI ? PersonalDataLogEntry::CONTEXT_CLI : PersonalDataLogEntry::CONTEXT_WEB;
    }
}
