<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use Closure;
use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\EntityInterface;
use DemosEurope\DemosplanAddon\Logic\ApiRequest\FluentRepository;
use demosplan\DemosPlanCoreBundle\Entity\CoreEntity;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\TransactionService;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;
use LogicException;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Illuminate\Support\Collection;

/**
 * @template T of EntityInterface
 *
 * @template-extends FluentRepository<T>
 */
abstract class CoreRepository extends FluentRepository
{
    /**
     * @var ValidatorInterface
     */
    protected $validator;

    protected $obscureTag = 'dp-obscure';

    /**
     * Please don't use `@required` for DI. It should only be used in base classes like this one.
     *
     * @return $this
     */
    #[Required]
    public function setValidator(ValidatorInterface $validator)
    {
        $this->validator = $validator;

        return $this;
    }

    /**
     * Executes a given task inside a transaction and returns the result of the task.
     * If an exception is thrown inside the task then the transaction will be rolled back
     * and the received exception will be rethrown.
     *
     * @deprecated use {@link TransactionService::executeAndFlushInTransaction()} instead
     *
     * @template TReturn
     *
     * @param callable(EntityManagerInterface): TReturn $task
     *
     * @return TReturn
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws ConnectionException
     */
    public function executeAndFlushInTransaction(callable $task)
    {
        $em = $this->getEntityManager();
        $connection = $em->getConnection();
        $connection->beginTransaction();

        try {
            $result = $task($em);
            $em->flush();
            $em->getConnection()->commit();

            return $result;
        } catch (Exception $e) {
            $em->rollback();
            throw $e;
        }
    }

    /**
     * @param int $amount
     *
     * @return T|array<T>
     */
    public function findRandom($amount = 1)
    {
        $all = $this->findAll();

        if ($amount < 1) {
            $amount = 1;
        }

        if ($amount > count($all)) {
            $amount = count($all);
        }

        if (0 === $amount) {
            return null;
        }

        $randomKeys = array_rand($all, $amount);

        if (1 === $amount) {
            return $all[$randomKeys];
        } else {
            return array_map(
                fn ($key) => $all[$key],
                $randomKeys
            );
        }
    }

    /**
     * Will use an implicit transaction, meaning in case of an failure in a later entity all previous
     * changes are reverted.
     *
     * @param CoreEntity[] $entities
     *
     * @return CoreEntity[]
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateObjects($entities)
    {
        $em = $this->getEntityManager();
        foreach ($entities as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        return $entities;
    }

    /**
     * Inserts, updates and deletes the given entities in a single transaction. From the Doctrine 2
     * documentation:
     * > For the most part, Doctrine 2 already takes care of proper transaction demarcation for
     * you: All the write operations (INSERT/UPDATE/DELETE) are queued until EntityManager#flush()
     * is invoked which wraps all of these changes in a single transaction.
     *
     * @see https://www.doctrine-project.org/projects/doctrine-orm/en/2.7/reference/transactions-and-concurrency.html
     *
     * @param object[] $toUpdate
     * @param object[] $toDelete
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function persistAndDelete(array $toUpdate, array $toDelete): void
    {
        $em = $this->getEntityManager();
        foreach ($toUpdate as $entity) {
            $em->persist($entity);
        }
        foreach ($toDelete as $entity) {
            $em->remove($entity);
        }
        $em->flush();
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function flushEverything(): void
    {
        $this->getEntityManager()->flush();
    }

    /**
     * @param array<int, object> $entities
     *
     * @throws ORMException
     */
    public function persistEntities(array $entities): void
    {
        foreach ($entities as $entity) {
            $this->getEntityManager()->persist($entity);
        }
    }

    /**
     * Converts Input given via Interface to a valid DateTime object if possible.
     *
     * @param string $string
     * @param string $time   Formatted Time to be generated
     *
     * @return DateTime|null
     */
    public function convertUserInputDate($string, $time = '02:00:00')
    {
        $date = null;
        // php date parameter
        $day = 'd';
        $month = 'm';
        $year = 'Y';

        // Nur Strings können hier verarbeitet werden
        if (!is_string($string)) {
            return $date;
        }

        // Das eingegebene Datum sollte 3 Stellen mit Punkt getrennt haben
        $parts = explode('.', $string);
        if (3 !== count($parts)) {
            return $date;
        }

        // Ist der Tag nur mit einer Stelle angegeben?
        if (1 === strlen($parts[0])) {
            $day = 'j';
        }

        // Ist der Monat nur mit einer Stelle angegeben?
        if (1 === strlen($parts[1])) {
            $month = 'n';
        }

        // Ist das Jahr nur mit zwei Stellen angegeben?
        if (2 === strlen($parts[2])) {
            $year = 'y';
        }

        // generiere das Datum
        $date = DateTime::createFromFormat($day.'.'.$month.'.'.$year.' H:i:s', $string.' '.$time);
        // Returnvalue should be null on fault
        $date = false === $date ? null : $date;

        return $date;
    }

    /**
     * Copy properties from SourceObject to TargetObject.
     *
     * @param object $copyToEntity
     * @param object $copyFromEntity
     * @param array  $excludeProperties
     *
     * @return object
     *
     * @throws ReflectionException
     */
    protected function generateObjectValuesFromObject($copyToEntity, $copyFromEntity, $excludeProperties = [])
    {
        $reflect = new ReflectionClass($copyFromEntity);
        $properties = $reflect->getProperties(
            ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PRIVATE
        );

        foreach ($properties as $singleprop) {
            $key = $singleprop->getName();

            if (in_array($key, $excludeProperties)) {
                continue;
            }

            $setter = 'set'.ucfirst($key);
            if (!method_exists($copyToEntity, $setter)) {
                continue;
            }

            $method = 'get'.ucfirst($key);
            if (method_exists($copyFromEntity, $method)) {
                $copyToEntity->$setter($copyFromEntity->$method());
                continue;
            }

            $methodIs = 'is'.ucfirst($key);
            if (method_exists($copyFromEntity, $methodIs)) {
                $copyToEntity->$setter($copyFromEntity->$methodIs());
                continue;
            }

            $methodKey = ucfirst($key);
            if (method_exists($copyFromEntity, $methodKey)) {
                $copyToEntity->$setter($copyFromEntity->$methodKey());
                continue;
            }
        }

        return $copyToEntity;
    }

    /**
     * Returns a closure to set field names on an entity based form input data.
     *
     * @param CoreEntity|User $entity
     *
     * @return Closure
     *
     * @throws LogicException
     */
    protected function setEntityFieldFromData($entity, array $data)
    {
        if (!is_a($entity, CoreEntity::class) && !is_a($entity, User::class)) {
            throw new LogicException('This method only supports CoreEntity and User. Got '.$entity::class);
        }

        return function ($fieldName) use ($entity, $data) {
            if (array_key_exists($fieldName, $data)) {
                $fieldSetterMethod = sprintf('set%s', ucfirst($fieldName));

                if (!method_exists($entity, $fieldSetterMethod)) {
                    throw new LogicException("Cannot set field value on unknown field {$fieldName}");
                }

                $entity->$fieldSetterMethod($data[$fieldName]);
            }
        };
    }

    /**
     * Returns a closure to set field names for flag fields on an entity based form input data
     * In addition to `setEntityFlagFieldFromData` this makes sure to only save booleans (intval) to
     * the database as we store flags as tinyint fields.
     *
     * @param CoreEntity|User $entity
     *
     * @return Closure
     *
     * @throws LogicException
     */
    protected function setEntityFlagFieldFromData($entity, array $data)
    {
        if (!is_a($entity, CoreEntity::class) && !is_a($entity, User::class)) {
            throw new LogicException('This method only supports CoreEntity and User. Got '.$entity::class);
        }

        return function ($fieldName) use ($entity, $data) {
            $fieldSetterMethod = sprintf('set%s', ucfirst($fieldName));

            if (!method_exists($entity, $fieldSetterMethod)) {
                throw new LogicException("Cannot set field value on unknown field {$fieldName}");
            }

            // Es dürfen nur Werte verändert werden, die auch übergeben werden!
            // => Werte, die nicht übergeben werden, dürfen nicht verändert werden
            if (array_key_exists($fieldName, $data)) {
                $entity->$fieldSetterMethod($data[$fieldName]);
                if ('' === $data[$fieldName] && !is_bool($data[$fieldName])) {
                    $this->getLogger()->warning(
                        'Property should have default value. Property: '.$fieldName.' Entity '.$entity::class
                    );
                }
            }
        };
    }

    /**
     * Convenience method to call `setEntityFieldFromData` on multiple fields.
     *
     * @param CoreEntity|User $entity
     *
     * @throws LogicException
     *
     * @see CoreRepository::setEntityFieldFromData()
     */
    protected function setEntityFieldsOnFieldCollection(Collection $fields, $entity, array $data)
    {
        if (!is_a($entity, CoreEntity::class) && !is_a($entity, User::class)) {
            throw new LogicException('This method only supports CoreEntity and User. Got '.$entity::class);
        }

        $fields->each($this->setEntityFieldFromData($entity, $data));
    }

    /**
     * Convenience method to call `setEntityFlagFieldFromData` on multiple fields.
     *
     * @param CoreEntity|User $entity
     *
     * @see CoreRepository::setEntityFlagFieldFromData()
     *
     * @throws LogicException
     */
    protected function setEntityFlagFieldsOnFlagFieldCollection(Collection $fields, $entity, array $data)
    {
        if (!is_a($entity, CoreEntity::class) && !is_a($entity, User::class)) {
            throw new LogicException('This method only supports CoreEntity and User. Got '.$entity::class);
        }

        $fields->each($this->setEntityFlagFieldFromData($entity, $data));
    }

    /**
     * Removes not all HTML-Tags, except the allowed Tags and the additionalAllowedTags.
     * Allowed Tags are defined in the used function: wysiwygFilter().
     *
     * @param string $stringToSanitize
     * @param array  $additionalAllowedTags
     *
     * @return string
     */
    protected function sanitize($stringToSanitize, $additionalAllowedTags = [])
    {
        return $this->wysiwygFilter($stringToSanitize, $additionalAllowedTags);
    }

    /**
     * HTML-Filter fuer Eingaben aus dem WYSIWYG-Editor.
     *
     * @param string $text
     * @param array  $additionalAllowedTags
     *
     * @return string
     */
    public function wysiwygFilter($text, $additionalAllowedTags = [])
    {
        // sort alphabetically:
        $allowedTags = collect(
            [
                'a',
                'abbr',
                'b',
                'br',
                'del',
                'em',
                'i',
                'img',
                'ins',
                'li',
                'mark',
                'ol',
                'p',
                's',
                'span',
                'strike',
                'strong',
                'sup',
                'table',
                'td',
                'th',
                'thead',
                'tr',
                'u',
                'ul',
            ]
        )->merge($additionalAllowedTags)
            ->flatMap(
                fn ($tagName) => ["<{$tagName}>", "</{$tagName}>"]
            )->implode('');

        return strip_tags($text, $allowedTags);
    }

    /**
     * Getting "original" data from DB, excluding the non-persisted data.
     *
     * @retrun array<string, mixed> an empty array if no original data was found, otherwise a
     *                              mapping from property names to values
     */
    public function getOriginalEntityData(CoreEntity $entity): array
    {
        return $this->getEntityManager()->getUnitOfWork()->getOriginalEntityData($entity);
    }

    protected function validate(object $entityToValidate): void
    {
        $violations = $this->validator->validate($entityToValidate);
        if (0 !== $violations->count()) {
            throw ViolationsException::fromConstraintViolationList($violations);
        }
    }
}
