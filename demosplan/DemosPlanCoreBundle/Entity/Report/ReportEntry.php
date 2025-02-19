<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Entity\Report;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\ReportEntryInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use DemosEurope\DemosplanAddon\Exception\JsonException;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Gedmo\Mapping\Annotation as Gedmo;
use Seld\JsonLint\JsonParser;
use Seld\JsonLint\ParsingException;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table(name="_report_entries",indexes={
 *
 *     @ORM\Index(columns={"_re_category"}),
 *     @ORM\Index(columns={"_re_group"}),
 *     @ORM\Index(columns={"_re_identifier_type"}),
 *     @ORM\Index(columns={"_re_identifier"}),
 * })
 *
 * @ORM\Entity(repositoryClass="demosplan\DemosPlanCoreBundle\Repository\ReportRepository")
 */
class ReportEntry extends CoreEntity implements UuidEntityInterface, ReportEntryInterface
{
    final public const GROUP_PROCEDURE = 'procedure';
    final public const GROUP_DOCUMENT = 'document';
    final public const GROUP_STATEMENT = 'statement';
    final public const GROUP_MASTER_PUBLIC_AGENCY = 'mastertoeb';
    final public const GROUP_ORGA = 'orga';

    final public const CATEGORY_ADD = 'add';
    final public const CATEGORY_ANONYMIZE_META = 'anonymizeMeta';
    final public const CATEGORY_ANONYMIZE_TEXT = 'anonymizeText';
    final public const CATEGORY_CHANGE_PHASES = 'changePhases';
    final public const CATEGORY_COPY = 'copy';
    final public const CATEGORY_DELETE = 'delete';
    final public const CATEGORY_DELETE_ATTACHMENTS = 'deleteAttachments';
    final public const CATEGORY_DELETE_TEXT_FIELD_HISTORY = 'deleteTextFieldHistory';
    final public const CATEGORY_FINAL_MAIL = 'finalMail';
    final public const CATEGORY_INVITATION = 'invitation';
    final public const CATEGORY_MERGE = 'merge';
    final public const CATEGORY_MOVE = 'move';
    final public const CATEGORY_ORGA_SHOWLIST_CHANGE = 'orgaShowlistChange';
    final public const CATEGORY_REGISTER_INVITATION = 'register_invitation';
    final public const CATEGORY_STATEMENT_SYNC_INSOURCE = 'syncStatementSourceCategory';
    final public const CATEGORY_STATEMENT_SYNC_INTARGET = 'syncStatementTargetCategory';
    final public const CATEGORY_UPDATE = 'update';
    final public const CATEGORY_VIEW = 'view';

    final public const LEVEL_INFO = 'INFO';

    final public const IDENTIFIER_TYPE_PROCEDURE = 'procedure';
    final public const IDENTIFIER_TYPE_STATEMENT = 'statement';
    final public const IDENTIFIER_TYPE_FINAL_MAIL = 'finalMail';
    final public const IDENTIFIER_TYPE_MASTER_PUBLIC_AGENCY = 'masterToeb';
    final public const IDENTIFIER_TYPE_ORGANISATION = 'orga';

    /**
     * @var string|null
     *
     * @ORM\Column(name="_re_id", type="string", length=36, options={"fixed":true})
     *
     * @ORM\Id
     *
     * @ORM\GeneratedValue(strategy="CUSTOM")
     *
     * @ORM\CustomIdGenerator(class="\demosplan\DemosPlanCoreBundle\Doctrine\Generator\UuidV4Generator")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="_re_category", type="string", length=100, nullable=false, options={"fixed":true})
     */
    #[Assert\NotBlank]
    protected $category;

    /**
     * @var string
     *
     * @ORM\Column(name="_re_group", type="string", length=100, nullable=false, options={"fixed":true})
     */
    #[Assert\NotBlank]
    protected $group;

    /**
     * @var string
     *
     * @ORM\Column(name="_re_level", type="string", length=255, nullable=false, options={"fixed":true})
     */
    protected $level = 'INFO';

    /**
     * @var string
     *
     * @ORM\Column(name="_u_id", type="string", length=36, options={"fixed":true}, nullable=false)
     */
    protected $userId;

    /**
     * @var string
     *
     * @ORM\Column(name="_u_name", type="string", length=255, nullable=false, options={"fixed":true})
     */
    #[Assert\NotBlank]
    protected $userName;

    /**
     * @var string
     *
     * @ORM\Column(name="_s_id", type="string", length=36, options={"fixed":true}, nullable=false)
     *
     * @deprecated this property doesn't seem to be in use anymore
     */
    protected $sessionId = '';

    /**
     * @var string
     *
     * @ORM\Column(name="_re_identifier_type", type="string", length=50, nullable=false)
     */
    #[Assert\NotBlank]
    protected $identifierType;

    /**
     * @var string
     *
     * @ORM\Column(name="_re_identifier", type="string", length=36, options={"fixed":true}, nullable=false)
     */
    #[Assert\NotBlank]
    protected $identifier;

    /**
     * @var string
     *
     * @ORM\Column(name="_re_message_mime_type", type="string", length=255, nullable=false)
     *
     * @deprecated this property doesn't seem to be in use anymore, {@link $message} is always JSON
     */
    protected $mimeType = '';

    /**
     * @var string always in JSON format (a simple string is considered valid JSON)
     *
     * @ORM\Column(name="_re_message", type="text", nullable=false, length=15000000)
     */
    #[Assert\NotBlank]
    protected $message;

    /**
     * @var string
     *
     * @ORM\Column(name="_re_incoming", type="text", nullable=false, length=15000000)
     */
    protected $incoming = '';

    /**
     * @var DateTime
     *
     * @Gedmo\Timestampable(on="create"), updateable = true
     *
     * @ORM\Column(name="_re_created_date", type="datetime", nullable=false)
     */
    protected $createDate;

    /**
     * @var Customer
     *
     * @ORM\ManyToOne(targetEntity="demosplan\DemosPlanCoreBundle\Entity\User\Customer")
     *
     * @ORM\JoinColumn(name="_c_id", referencedColumnName="_c_id", nullable=false)
     */
    #[Assert\NotBlank]
    protected $customer;

    /**
     * Tells Elasticsearch whether Entity should be indexed.
     *
     * @return bool
     */
    public function shouldBeIndexed()
    {
        try {
            if ('statement' === $this->getGroup() && 'access' === $this->getCategory()) {
                return false;
            }

            return true;
        } catch (Exception) {
            return false;
        }
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Set category.
     *
     * @param string $category
     *
     * @return ReportEntry
     */
    public function setCategory($category)
    {
        $this->category = $category;

        return $this;
    }

    /**
     * Get category.
     *
     * @return string
     */
    public function getCategory()
    {
        return $this->category;
    }

    /**
     * Set group.
     *
     * @param string $group
     *
     * @return ReportEntry
     */
    public function setGroup($group)
    {
        $this->group = $group;

        return $this;
    }

    /**
     * Get group.
     *
     * @return string
     */
    public function getGroup()
    {
        return $this->group;
    }

    /**
     * Set level.
     *
     * @param string $level
     *
     * @return ReportEntry
     */
    public function setLevel($level)
    {
        $this->level = $level;

        return $this;
    }

    /**
     * Get level.
     *
     * @return string
     */
    public function getlevel()
    {
        return $this->level;
    }

    /**
     * Set userId.
     *
     * @param string $userId
     *
     * @return ReportEntry
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;

        return $this;
    }

    /**
     * Sets UserId and UserName from UserObject.
     *
     * @param User|null $user
     *
     * @return $this
     */
    public function setUser($user)
    {
        $this->setUserId('');
        $this->setUserName('');
        if ($user instanceof User) {
            $this->setUserId($user->getId());
            $this->setUserName($user->getFullname());
        }

        return $this;
    }

    /**
     * Get userId.
     *
     * @return string
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * Set userName.
     *
     * @param string $userName
     *
     * @return ReportEntry
     */
    public function setUserName($userName)
    {
        $this->userName = $userName;

        return $this;
    }

    /**
     * Get userName.
     *
     * @return string
     */
    public function getUserName()
    {
        return $this->userName;
    }

    /**
     * Set sessionId.
     *
     * @param string $sessionId
     *
     * @return ReportEntry
     *
     * @deprecated this property doesn't seem to be in use anymore
     */
    public function setSessionId($sessionId)
    {
        $this->sessionId = $sessionId;

        return $this;
    }

    /**
     * Get sessionId.
     *
     * @return string
     *
     * @deprecated this property doesn't seem to be in use anymore
     */
    public function getSessionId()
    {
        return $this->sessionId;
    }

    /**
     * Set identifierType.
     *
     * @param string $identifierType
     *
     * @return ReportEntry
     */
    public function setIdentifierType($identifierType)
    {
        $this->identifierType = $identifierType;

        return $this;
    }

    /**
     * Get identifierType.
     *
     * @return string
     */
    public function getIdentifierType()
    {
        return $this->identifierType;
    }

    /**
     * Set identifier.
     *
     * @param string $identifier
     *
     * @return ReportEntry
     */
    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;

        return $this;
    }

    /**
     * Get identifier.
     *
     * @return string
     */
    public function getIdentifier()
    {
        return $this->identifier;
    }

    /**
     * Set messageMimeType.
     *
     * @param string $mimeType
     *
     * @return ReportEntry
     *
     * @deprecated this property doesn't seem to be in use anymore
     */
    public function setMimeType($mimeType)
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    /**
     * Get messageMimeType.
     *
     * @return string
     *
     * @deprecated this property doesn't seem to be in use anymore
     */
    public function getMimeType()
    {
        return $this->mimeType;
    }

    /**
     * Set message.
     *
     * @param string|array $message
     *
     * @return ReportEntry
     */
    public function setMessage($message)
    {
        if (!is_string($message)) {
            $message = Json::encode($message, JSON_UNESCAPED_UNICODE);
        }
        $this->message = $message;

        return $this;
    }

    /**
     * Get message.
     *
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Get message field as decoded array.
     */
    public function getMessageDecoded(bool $fixOrigMessage): array
    {
        return $this->getDecoded($this->message, $fixOrigMessage);
    }

    /**
     * Set incoming.
     *
     * @param string $incoming
     *
     * @return ReportEntry
     */
    public function setIncoming($incoming)
    {
        if (!is_string($incoming)) {
            $incoming = Json::encode($incoming, JSON_UNESCAPED_UNICODE);
        }
        $this->incoming = $incoming;

        return $this;
    }

    /**
     * Get incoming.
     *
     * @return string
     */
    public function getIncoming()
    {
        return $this->incoming;
    }

    /**
     * Get incoming field as decoded array.
     */
    public function getIncomingDecoded(bool $fixOrigMessage): array
    {
        return $this->getDecoded($this->incoming, $fixOrigMessage);
    }

    /**
     * Set createDate.
     *
     * @param DateTime $createDate
     *
     * @return ReportEntry
     */
    public function setCreateDate($createDate)
    {
        $this->createDate = $createDate;

        return $this;
    }

    /**
     * Get createDate.
     *
     * @return DateTime
     */
    public function getCreateDate()
    {
        return $this->createDate;
    }

    /**
     * Get createDate.
     *
     * @return DateTime
     */
    public function getCreated()
    {
        return $this->createDate;
    }

    /**
     * Fix some known issues with old messages.
     *
     * @param string $message
     */
    protected function fixOrigMessageFormat($message): string
    {
        $parser = new JsonParser();
        $parsingException = $parser->lint($message);
        if (null === $parsingException) {
            return $message;
        }

        if ($parsingException instanceof ParsingException) {
            // try to fix some known issues
            $message = preg_replace('*\\\/|\\\\|(\\\)*', '\\\\\\\\', $message);
        }

        // If message is yet invalid skip message
        $parsingException = $parser->lint($message);
        if (null !== $parsingException) {
            return '';
        }

        return $message;
    }

    /**
     * @param string $inputMessage
     */
    protected function getDecoded($inputMessage, bool $fixOrigMessage): array
    {
        $message = $fixOrigMessage ? $this->fixOrigMessageFormat($inputMessage) : $inputMessage;

        try {
            $decoded = Json::decodeToArray($message);
        } catch (JsonException) {
            $decoded = [];
        }

        return $decoded;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): void
    {
        $this->customer = $customer;
    }
}
