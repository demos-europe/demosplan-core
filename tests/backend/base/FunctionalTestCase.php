<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Base;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\FileServiceInterface;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\InstitutionTagCategoryFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureBehaviorDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePerson;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureType;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureUiDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\StatementFormDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Statement\ConsultationToken;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Liip\FunctionalTestBundle\Test\WebTestCase;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Liip\TestFixturesBundle\Services\DatabaseTools\AbstractDatabaseTool;
use Liip\TestFixturesBundle\Services\FixturesLoaderFactory;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use ReflectionException;
use ReflectionObject;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Yaml\Yaml;
use Zenstruck\Foundry\Test\Factories;

class FunctionalTestCase extends WebTestCase
{
    use Factories;
    use MonoKernelTrait;
    // use resetDatabase is currently actually done by liip. In case of removing liip, its necessary to enable this or using DAMA

    /** @var object System under Test */
    protected $sut;

    /** @var AbstractDatabaseTool */
    protected $databaseTool;

    /** @var EntityManager */
    private $entityManager;

    /** @var ReferenceRepository */
    protected $fixtures;

    protected $testIndex;

    /** @var TokenStorageInterface */
    protected $tokenStorage;

    /** @var CurrentUserService */
    protected $currentUserService;

    protected function loadFixtures()
    {
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel(['environment' => 'test', 'debug' => false]);

         //$this->currentUserService = self::$container->get(CurrentUserService::class);
        //$this->entityManager = self::$container->get(EntityManagerInterface::class);
        //$this->databaseTool = self::$container->get(DatabaseToolCollection::class)->get();
        //$this->tokenStorage = self::$container->get('security.token_storage');
        //$this->fixtures = $this->databaseTool->loadAllFixtures()->getReferenceRepository();
        //$this->databaseTool->loadFixtures()
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // avoid memory leaks
        $refl = new ReflectionObject($this);

        foreach ($refl->getProperties() as $prop) {
            if (!$prop->isStatic() && 0 !== \strncmp($prop->getDeclaringClass()->getName(), 'PHPUnit_', 8)) {
                $prop->setAccessible(true);
                $prop->setValue($this, null);
            }
        }

        if ($this->entityManager instanceof EntityManager) {
            $this->entityManager->close();
            $this->entityManager = null;
        }
    }

    /**
     * Convenience method for fetching references from fixtures.
     *
     * @param string $name
     *
     * @return object
     */
    protected function getReference($name)
    {
        return $this->fixtures->getReference($name);
    }

    /**
     * Login User to be able to use TokenStorage.
     * Permissions need to be set via `$this->enablePermissions($permissions)`.
     *
     * @param User $user
     */
    protected function logIn(UserInterface $user)
    {
        $tokenMockMethods = [
            new MockMethodDefinition('getUser', $user),
            new MockMethodDefinition('getRoleNames', $user->getDplanRolesArray()),
        ];
        $token = $this->getMock(PostAuthenticationToken::class, $tokenMockMethods);

        $this->tokenStorage->setToken($token);
    }

    /**
     * Set Permissions that need to be enabled during test.
     *
     * @param array<int,string> $permissionsToEnable
     */
    protected function enablePermissions(array $permissionsToEnable): void
    {
        $this->currentUserService->getPermissions()->initPermissions(
            $this->tokenStorage->getToken()->getUser()
        );

        $this->currentUserService->getPermissions()->enablePermissions($permissionsToEnable);
    }

    /**
     * @param string[] $permissionsToDisable
     */
    protected function disablePermissions(array $permissionsToDisable): void
    {
        $this->currentUserService->getPermissions()->initPermissions(
            $this->tokenStorage->getToken()->getUser()
        );

        $this->currentUserService->getPermissions()->disablePermissions($permissionsToDisable);
    }

    public function getEntityManager(): EntityManager
    {
        return $this->entityManager;
    }

    public static function markSkippedForCIElasticsearchUnavailable(): void
    {
        self::markTestSkipped('This test was skipped because it uses elasticsearch, which is not (yet) possible. Locally you may run this test.');
    }

    public static function markSkippedForCIIntervention(): void
    {
        self::markTestSkipped('This test was skipped because of pre-existing errors. They are most likely easily fixable but prevent us from getting to a usable state of our CI.');
    }

    public static function skipTestTestingUnusedCode(): void
    {
        self::markTestSkipped('The tested code is only used in this test.');
    }

    /**
     * Tests a specific string, if this has a specific date-format.
     *
     * @param $dateAsString - string to be tested
     */
    public function checkStringDateFormat($dateAsString)
    {
        static::assertStringMatchesFormat('%d-%d-%dT%d:%d:%d+%d', $dateAsString);
    }

    /**
     * Checks a specific string, if this has the format to be an ID.
     */
    public function checkId($ident): void
    {
        static::assertIsString($ident);
        static::assertEquals(36, strlen($ident));
        static::assertStringMatchesFormat('%x-%x-%x-%x-%x', $ident);
    }

    /**
     * In case of converting one of the entities to array (legacy) the key 'id' has to be existing.
     */
    public function checkArrayIncludesId($arrayToCheck)
    {
        static::assertArrayHasKey('id', $arrayToCheck);

        $this->checkId($arrayToCheck['id']);
    }

    /**
     * Check if the given value is a timestamp of the current date.
     *
     * @param DateTime|int $timestamp Value to be checked
     *
     * @return bool - true if the given parameter a timestamp of the current date, otherwise false
     */
    public function isCurrentTimestamp($timestamp)
    {
        $currentDate = strtotime(date('Y-m-d'));
        if ($timestamp instanceof DateTime) {
            $timestamp = $timestamp->getTimestamp();
        } else {
            $timestamp = 13 === strlen($timestamp) ? $timestamp / 1000 : $timestamp;
        }
        $entityDate = strtotime(date('Y-m-d', $timestamp));

        return $this->isTimestamp($timestamp)
            && $currentDate == $entityDate;
    }

    /**
     * Check if the given value is a timestamp.
     *
     * @param int $timestamp - Value to be checked
     *
     * @return bool - true if the given parameter a timestamp, otherwise false
     */
    public function isTimestamp($timestamp)
    {
        return null !== $timestamp
                && is_numeric($timestamp)
                && !is_string($timestamp)
                && (0 < $timestamp);
    }

    /**
     * Returns the number of entries of the table, which is related to a specific entity.
     * Optional parameter can be used to filter the results, which will be count.
     *
     * @param string $entityName the name of the entity, which identify the table
     *
     * @return int - The number of entries of the table
     */
    public function countEntries(string $entityName, array $criteria = []): int
    {
        return count($this->getEntries($entityName, $criteria));
    }

    /**
     * Returns the entries of the table, which is related to a specific entity.
     * Optional parameter can be used to filter the results.
     *
     * @param class-string $entityName $entityName the name of the entity, which identify the table
     *
     * @return array - The entries of the table
     */
    public function getEntries($entityName, array $criteria = [], array $order = []): array
    {
        return $this->getEntityManager()->getRepository($entityName)->findBy($criteria, $order);
    }

    /**
     * In case of data in DB differentiate to doctrine objects, you can force doctrine to get it all fresh from DB.
     */
    public function clearEntityManager(): void
    {
        $this->getEntityManager()->clear();
    }

    /**
     * Returns the entries of the table, which is related to a specific entity.
     * Optional parameter can be used to filter the results.
     *
     * @param $entityName $entityName the name of the entity, which identify the table
     *
     * @return array - The entries of the table
     */
    public function getEntryIds($entityName, array $criteria = [], array $order = []): array
    {
        $entryIds = [];
        /** @var CoreEntity $entries */
        $entries = $this->getEntries($entityName, $criteria, $order);
        foreach ($entries as $entry) {
            $entryIds[] = $entry->getId();
        }

        return $entryIds;
    }

    /**
     * Returns a single entry by ID.
     *
     * @template T of object
     *
     * @param class-string<T> $entityName
     *
     * @return T|null
     */
    public function find(string $entityName, string $id): ?object
    {
        $entityManager = $this->getEntityManager();
        $repository = $entityManager->getRepository($entityName);

        return $repository->find($id);
    }

    public function getEntriesWhereInIds($entityClass, array $ids = [])
    {
        $result = $this->getEntityManager()->createQueryBuilder()
            ->select('entity')
            ->from($entityClass, 'entity')
            ->andWhere('entity.id IN (:ids)')
            ->setParameter('ids', $ids, ArrayParameterType::STRING)
            ->getQuery()->getResult();

        return $result;
    }

    /**
     * Returns the maximum value of a specific filed of a specific Table/Class.
     *
     * @param string $bundle      - Name of the bundle of the Class
     * @param string $entityName  - Classname/entityname that indicates the table
     * @param string $nameOfField - Fieldname of the entity that indacates the filed in the table
     *
     * @return int - Maximum value of a specific filed of a specific Table/Class
     *
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function getMaxValue($bundle, $entityName, $nameOfField = 'id'): int
    {
        $query = $this->getEntityManager()->createQueryBuilder()->select($entityName)->from($bundle.':'.$entityName, $entityName);
        $query->select('MAX('.$entityName.'.'.$nameOfField.')');
        $nextId = (int) $query->getQuery()->getSingleScalarResult();

        return $nextId;
    }

    /**
     * Converts a string to a timestamp integer.
     * The given parameter has to be in one of two specific formats:
     * 'Y-m-d H:i:s'
     * 'Y-m-dTH:i:s+100'.
     *
     * @param string $dateString date as a string
     *
     * @return bool|int - Timestamp if the given parameter in the specific or in the genereal "date as string" from, otherwise false
     */
    protected function toTimestamp($dateString)
    {
        if (!is_string($dateString)) {
            return false;
        }

        if (!$this->hasValidDateFormat($dateString)) {
            return false;
        }

        if (24 === strlen($dateString)) {
            $dateString[10] = ' ';
            $dateString = substr($dateString, 0, -5);
        } else {
            if (19 !== strlen($dateString)) {
                return false;
            }
        }

        return strtotime($dateString);
    }

    /**
     * Check if a date is the current date, with an accuracy of 30 seconds.
     * The given parameter has to be in one of two specific formats:
     * 'Y-m-d H:i:s'
     * 'Y-m-dTH:i:s+100'.
     *
     * @param string $dateString
     *
     * @return bool true if the given parameter the current date, otherwise false
     */
    public function isCurrentDateTime($dateString): bool
    {
        // todo necessary check (will be lead to failing tests because minimal time offset)? can be deletd?
        $date2 = $this->toTimestamp($dateString);
        $date = date('Y-m-d H:i:s');
        $date = strtotime($date);

        $diff = $date - $date2;

        return $diff < 30;
    }

    /**
     * Check a string, if he has one of two specific formats.
     * Examples:
     * Format1 = '2015-11-23 13:19:11';
     * Format2 = '2015-11-23T13:19:11+0456';.
     *
     * @param string $dateString - The string to check
     *
     * @return bool - true if the given string has one of the two defined formats, otherwise false
     */
    private function hasValidDateFormat($dateString): bool
    {
        $format1 = '/^[0-9]{4}[-](0[1-9]|1[012])[-](0[1-9]|[12][0-9]|3[01])[ ](0[0-9]|1[0-9]|2[0-3])[:]([0-5][0-9]|60)[:]([0-5][0-9]|60)$/';
        $format2 = '/^[0-9]{4}[-](0[1-9]|1[012])[-](0[1-9]|[12][0-9]|3[01])[T](0[0-9]|1[0-9]|2[0-3])[:]([0-5][0-9]|60)[:]([0-5][0-9]|60)[+][0-9]{4}$/';

        return preg_match($format1, $dateString) || preg_match($format2, $dateString);
    }

    /**
     * @return array
     */
    protected function getProcedurePhases()
    {
        return Yaml::parseFile(DemosPlanPath::getConfigPath('procedure/procedurephases.yml'));
    }

    /**
     * Checks each attribute of given $object if it can be found in given $objectAsArray,
     * expecting the given $attributesToCheck.
     */
    protected function checkIfArrayHasEqualDataToObject(
        array $objectAsArray,
        CoreEntity $object,
        array $attributesToSkip = []
    ): void {
        $class = get_class($object);

        /** @var string $methodName */
        foreach (get_class_methods($class) as $methodName) {
            $length = strlen('get');
            if (0 === strpos($methodName, 'get')) {
                $attributeName = lcfirst(substr_replace($methodName, '', 0, $length));
                if (property_exists($class, $attributeName) && !in_array($attributeName, $attributesToSkip, true)) {
                    if ($object->$methodName() instanceof DateTime
                        && is_int($objectAsArray[$attributeName])
                    ) {
                        static::assertEquals(
                            $object->$methodName()->getTimestamp() * 1000, // duplicate logic from coreService::convertDatesToLegacy()
                            $objectAsArray[$attributeName],
                            'Returned value of '.$methodName.' does not match value of key '.$attributeName
                        );
                    } else {
                        static::assertEquals(
                            $object->$methodName(),
                            $objectAsArray[$attributeName],
                            'Returned value of '.$methodName.' does not match value of key '.$attributeName
                        );
                    }
                }
            }
        }
    }

    /**
     * Will not check the number of key-value pairs in given $arrayToCheck.
     */
    public function checkArrayHasGivenInput(
        array $arrayToCheck,
        array $inputValues,
        array $allowedMissingInputValueKeys = []): void
    {
        foreach ($inputValues as $key => $value) {
            if (!in_array($key, $allowedMissingInputValueKeys, true)) {
                static::assertArrayHasKey($key, $arrayToCheck);
                static::assertEquals($arrayToCheck[$key], $value);
            }
        }
    }

    /**
     * @param string $uuid the string to test if it is a valid UUID
     *
     * @return bool true if the given $uuid is a valid UUID, false otherwise
     */
    public function hasValidUUIDFormat($uuid)
    {
        // taken from XBauleitplanung Specification Version 0.79
        $format = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/';

        return 1 === preg_match($format, $uuid);
    }

    /**
     * @template T of object
     *
     * @param class-string<T>        $classToMock
     * @param MockMethodDefinition[] $definitions
     *
     * @return T
     */
    public function getMock(string $classToMock, array $definitions = []): MockObject
    {
        $mock = $this->getMockBuilder($classToMock)
            ->disableOriginalConstructor()
            ->getMock();

        foreach ($definitions as $mockMethodDefinition) {
            $returnValue = $mockMethodDefinition->getReturnValue();
            $invocationMock = $mock->method($mockMethodDefinition->getMethod());
            if ($mockMethodDefinition->isReturnValueCallback()) {
                $invocationMock->willReturnCallback($returnValue);
            } else {
                $invocationMock->willReturn($returnValue);
            }
            $property = $mockMethodDefinition->getPropertyName();
            if (null !== $property) {
                $mockReflection = new ReflectionClass($classToMock);
                $propertyReflection = $mockReflection->getProperty($property);
                $propertyReflection->setAccessible(true);
                $propertyReflection->setValue($mock, $returnValue);
            }
        }

        return $mock;
    }

    /**
     * Set up a mock session.
     */
    protected function setUpMockSession(string $userReferenceName = LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY): Session
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('sessionId', 'fakeSession');
        $session->set('userId', $this->fixtures->getReference($userReferenceName)->getId());

        return $session;
    }

    public function getEntityManagerMock(): EntityManager
    {
        $mock = $this->getMockBuilder(EntityManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(
                [
                    'getConnection',
                    'getClassMetadata',
                    'close',
                ]
            )
            ->getMock();

        $connectionMock = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(
                [
                    'beginTransaction',
                    'commit',
                    'rollback',
                ]
            )
            ->getMock();

        $mock
            ->method('getConnection')
            ->willReturn($connectionMock);

        return $mock;
    }

    protected function getConsultationTokenReference(string $name): ConsultationToken
    {
        return $this->getReference($name);
    }

    protected function getOrgaReference(string $name): Orga
    {
        return $this->getReference($name);
    }

    protected function getElementReference(string $name): Elements
    {
        return $this->getReference($name);
    }

    protected function getPlaceReference(string $name): Place
    {
        return $this->getReference($name);
    }

    protected function getTagReference(string $name): Tag
    {
        return $this->getReference($name);
    }

    protected function getTagTopicReference(string $name): TagTopic
    {
        return $this->getReference($name);
    }

    protected function getStatementReference(string $name): Statement
    {
        return $this->getReference($name);
    }

    protected function getSegmentReference(string $name): Segment
    {
        return $this->getReference($name);
    }

    protected function getStatementFragmentReference(string $name): StatementFragment
    {
        return $this->getReference($name);
    }

    protected function getStatementFormDefinitionReference(string $name): StatementFormDefinition
    {
        return $this->getReference($name);
    }

    protected function getProcedureBehaviorDefinitionReference(string $name): ProcedureBehaviorDefinition
    {
        return $this->getReference($name);
    }

    protected function getProcedurePersonReference(string $name): ProcedurePerson
    {
        return $this->getReference($name);
    }

    protected function getProcedureUiDefinitionReference(string $name): ProcedureUiDefinition
    {
        return $this->getReference($name);
    }

    protected function getProcedureTypeReference(string $name): ProcedureType
    {
        return $this->getReference($name);
    }

    protected function getUserReference(string $name): User
    {
        return $this->getReference($name);
    }

    protected function getProcedureReference(string $name): Procedure
    {
        return $this->getReference($name);
    }

    protected function getFileReference(string $name): File
    {
        return $this->getReference($name);
    }

    protected function getDraftStatementReference(string $name): DraftStatement
    {
        return $this->getReference($name);
    }

    protected function getCustomerReference(string $name): Customer
    {
        return $this->getReference($name);
    }

    /**
     * Make a current user available.
     */
    protected function loginTestUser(string $reference = LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY): User
    {
        /** @var User $testUser */
        $testUser = $this->fixtures->getReference($reference);
        $this->logIn($testUser);

        return $testUser;
    }

    public function getMasterTemplate(): Procedure
    {
        return $this->getEntries(Procedure::class, ['masterTemplate' => true])[0];
    }

    /**
     * This helper method may be used to test protected methods when really needed.
     * Please note that testing a protected method may be a code smell hint. Probably it might be better
     * to extract the method to a class itself as a public method that could be unit tested by itself
     * (Method Object Pattern).
     * Private methods should not be tested, as they are an implementation detail that shoudld be tested
     * indirectly via public methods.
     *
     * @param array{class-string|object,string} $classAndMethod
     *
     * @throws ReflectionException
     */
    protected function invokeProtectedMethod(array $classAndMethod, ...$args)
    {
        [$class, $methodName] = $classAndMethod;
        $class = new ReflectionClass($class);
        $method = $class->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invoke($this->sut, ...$args);
    }

    protected function getFile($testPath, $filename, $contentType, $procedure): ?FileInfo
    {
        $fileService = $this->getContainer()->get(FileServiceInterface::class);
        $finder = Finder::create();
        $currentDirectoryPath = DemosPlanPath::getTestPath($testPath);
        $finder->files()->in($currentDirectoryPath)->name($filename);

        if ($finder->hasResults()) {
            /** @var SplFileInfo $file */
            foreach ($finder as $file) {
                if ($filename === $file->getFilename()) {
                    //                    echo var_dump($file->getFilename());

                    $fileInfo = new FileInfo(
                        $fileService->createHash(),
                        $file->getFilename(),
                        $file->getSize(),
                        $contentType,
                        $file->getPath(),
                        $file->getRealPath(),
                        $procedure
                    );

                    return $fileInfo;
                }
            }
        }

        return null;
    }
}
