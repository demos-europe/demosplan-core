<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageSerializableInterface;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use Symfony\Bundle\FrameworkBundle\Translation\Translator;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Illuminate\Support\Collection;

use function collect;

class MessageBag implements MessageBagInterface
{
    public static $definedSeverities = [
        'confirm',
        'info',
        'warning',
        'error',
        'dev',
    ];

    /**
     * @var Collection
     */
    protected $messages;

    /**
     * Create an empty MessageBag.
     */
    public function __construct(private readonly TranslatorInterface $translator)
    {
        $this->messages = collect(self::$definedSeverities)
            ->flip()
            ->map(static fn () => collect());
    }

    /**
     * Get stored messages from MessageBag and delete them afterwards.
     *
     * Can optionally limit to a severity
     */
    public function get(): Collection
    {
        $messages = $this->messages;

        // Delete stored messages as we do not want to process them twice
        $this->messages = new Collection();

        return $messages;
    }

    public function getConfirm(): Collection
    {
        $confirms = $this->messages->only('confirm');

        $this->messages->put('confirm', new Collection());

        return $confirms;
    }

    public function getConfirmMessages(): Collection
    {
        $confirms = $this->messages['confirm'];
        $this->messages->put('confirm', new Collection());

        return $confirms;
    }

    public function getInfo(): Collection
    {
        $infos = $this->messages->only('info');

        $this->messages->put('info', new Collection());

        return $infos;
    }

    public function getWarning(): Collection
    {
        $warnings = $this->messages->only('warning');

        $this->messages->put('warning', new Collection());

        return $warnings;
    }

    public function getError(): Collection
    {
        $errors = $this->messages->only('error');

        $this->messages->put('error', new Collection());

        return $errors;
    }

    public function getErrorMessages(): Collection
    {
        $errors = $this->messages['error'];
        $this->messages->put('error', new Collection());

        return $errors;
    }

    /**
     * Add a message to the list.
     *
     * Messages are required to have a severity and a message text.
     * The message text should usually be a translation key. Additional
     * non-required parameters are the options for the translation string
     * and eventually the translation domain which default to empty options
     * and the system translation table.
     *
     * @param string $message   #TranslationKey
     * @param string $domain    #TranslationDomain
     * @param string $routeName #Route
     *
     * @throws MessageBagException
     */
    public function add(
        string $severity,
        string $message,
        array $params = [],
        string $domain = 'messages',
        string $routeName = '',
        array $routeParameters = [],
        string $linkText = ''
    ): void {
        if ('' === $routeName) {
            $this->addObject(MessageSerializable::createMessage($severity, $message, $params));
        } else {
            $this->addObject(
                LinkMessageSerializable::createLinkMessage(
                    $severity,
                    $message,
                    $params,
                    $routeName,
                    $routeParameters,
                    $linkText
                )
            );
        }
    }

    /**
     * @throws MessageBagException will not add a message if it already exists
     */
    public function addObject(MessageSerializableInterface $message, bool $toStart = false): void
    {
        $this->validateMessageInputData($message->getSeverity(), $message->getText());

        // translate text of message:
        $translatedText = $this->getTranslator()->trans(
            $message->getText(),
            $message->getTextParameters()
        );
        $message->setText($translatedText);

        $this->initializeMessageBagForSeverity($message->getSeverity());
        /** @var Collection $severityMessages */
        $severityMessages = $this->messages[$message->getSeverity()];
        if (!$severityMessages->contains($message)) {
            if ($toStart) {
                $severityMessages->prepend($message);
            } else {
                $severityMessages->push($message);
            }
        }
    }

    /**
     * Add a message with pluralization information to the list.
     *
     * @param string $message #TranslationKey
     * @param string $domain  #TranslationDomain
     *
     * @throws MessageBagException
     *
     * @see Translator::trans()
     * @see MessageBag::add()
     */
    public function addChoice(
        string $severity,
        string $message,
        array $params = [],
        string $domain = 'messages'
    ): void {
        $this->validateMessageInputData($severity, $message);

        $message = $this->getTranslator()->trans($message, $params, $domain);

        $this->storeParsedMessage($severity, $message);
    }

    public function addViolations(ConstraintViolationListInterface $constraintViolationList): void
    {
        collect($constraintViolationList)->each(
            function (ConstraintViolation $violation): void {
                $this->add('error', $violation->getMessage());
            }
        );
    }

    /**
     * Check for a valid severity string
     * (and do a tiny bit of sanitizing).
     */
    protected function validateSeverity(string $severity): bool
    {
        $severity = strtolower(trim($severity));

        return null !== $severity && is_string($severity) && in_array(
            $severity,
            self::$definedSeverities,
            true
        );
    }

    /**
     * Validate message input data.
     *
     * This makes sure that the severity is known and valid
     * and that the message is an actual string. It will however
     * not check wether a translation key for that string exists.
     *
     * @param string $message #TranslationKey
     *
     * @throws MessageBagException
     */
    protected function validateMessageInputData(string $severity, string $message)
    {
        if (!$this->validateSeverity($severity)) {
            throw MessageBagException::severityNotSupportedException($severity);
        }

        if (!is_string($message)) {
            throw MessageBagException::messageMustBeStringException();
        }
    }

    /**
     * Puts a message into it's bag.
     */
    protected function storeParsedMessage(string $severity, string $message)
    {
        $this->initializeMessageBagForSeverity($severity);

        $this->messages[$severity]->push(new MessageSerializable($severity, $message));
    }

    protected function initializeMessageBagForSeverity(string $severity)
    {
        if (!$this->messages->has($severity)) {
            $this->messages->put($severity, new Collection());
        }
    }

    protected function getTranslator(): TranslatorInterface
    {
        return $this->translator;
    }

    public function addViolationExceptions(ViolationsException $e): void
    {
        foreach ($e->getViolationsAsStrings() as $violation) {
            $this->add('error', $violation);
        }
    }
}
