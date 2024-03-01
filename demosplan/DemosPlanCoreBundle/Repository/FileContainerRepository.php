<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use DemosEurope\DemosplanAddon\Logic\ApiRequest\FluentRepository;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\FileContainer;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Exception;

use function collect;

/**
 * @template-extends FluentRepository<FileContainer>
 */
class FileContainerRepository extends FluentRepository implements ObjectInterface
{
    /**
     * Get Files from entity.
     *
     * @return File[]
     *
     * @throws Exception
     */
    public function getFiles(string $entityClass, string $id, string $field): array
    {
        try {
            /** @var FileContainer|null $files */
            $files = $this->findBy(['entityId' => $id, 'entityClass' => $entityClass, 'entityField' => $field]);
            if (null !== $files) {
                $fileEntities = [];
                collect($files)->each(static function (FileContainer $fileContainer) use (&$fileEntities) {
                    $fileEntities[] = $fileContainer->getFile();
                }
                );

                return $fileEntities;
            }

            return [];
        } catch (Exception $e) {
            $this->logger->warning('Could not get FileContainer ', [$e]);
            throw $e;
        }
    }

    /**
     * @return array<int, FileContainer>
     *
     * @throws Exception
     */
    public function getStatementFileContainers($id): array
    {
        try {
            $files = $this->findBy(['entityId' => $id, 'entityClass' => Statement::class, 'entityField' => 'file']);
            if (null !== $files) {
                return $files;
            }

            return [];
        } catch (Exception $e) {
            $this->logger->warning('Could not get FileContainer ', [$e]);
            throw $e;
        }
    }

    /**
     * Get Filesstrings from entity.
     *
     * @param string $entityClass
     * @param string $id
     * @param string $field
     *
     * @return string[]
     *
     * @throws Exception
     */
    public function getFileStrings($entityClass, $id, $field): array
    {
        try {
            /** @var FileContainer|null $files */
            $files = $this->findBy(['entityId' => $id, 'entityClass' => $entityClass, 'entityField' => $field]);
            if (null !== $files) {
                return collect($files)
                    ->map(static fn ($item, $key) => $item->getFileString())->toArray();
            }

            return [];
        } catch (Exception $e) {
            $this->logger->warning('Could not get FileContainer ', [$e]);
            throw $e;
        }
    }

    /**
     * @param FileContainer $entity
     */
    public function addObject($entity, bool $flush = true): FileContainer
    {
        try {
            $em = $this->getEntityManager();
            $em->persist($entity);
            if ($flush) {
                $em->flush();
            }
        } catch (Exception $e) {
            $this->logger->error('Add FileContainer failed: ', [$e]);
        }

        return $entity;
    }

    /**
     * @param FileContainer $entity
     *
     * @return FileContainer
     *
     * @throws Exception
     */
    public function updateObject($entity)
    {
        try {
            $em = $this->getEntityManager();
            $em->persist($entity);
            $em->flush();

            return $entity;
        } catch (Exception $e) {
            $this->logger->warning('Update Statement failed. Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     *
     * @return void
     */
    public function get($entityId)
    {
    }

    /**
     * Get a FileContainer by the pair of FileId and EntityId
     * Filecontainers are pairing files and arbitrary entities, thus the naming
     * of this method.
     *
     * @param string $file     the id of the file
     * @param string $entityId the id of the entity the file is attached to
     *
     * @return FileContainer|null the resulting file container or nothing
     */
    public function getByPairing($file, $entityId): ?FileContainer
    {
        try {
            return $this->findOneBy(['file' => $file, 'entityId' => $entityId]);
        } catch (Exception $e) {
            $this->logger->error('Could not determine FileContainer by pairing: ', [$e]);

            return null;
        }
    }

    /**
     * Delete Entity.
     *
     * @param string $entityId
     */
    public function delete($entityId): bool
    {
        try {
            $em = $this->getEntityManager();
            /** @var FileContainer $fileContainer */
            $fileContainer = $this->findOneBy(['id' => $entityId]);

            if (null === $fileContainer) {
                return false;
            }

            $em->remove($fileContainer);
            $em->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->warning('FileEntity could not be removed. ', [$e]);

            return false;
        }
    }

    /**
     * @param FileContainer $entity
     */
    public function deleteObject($entity): bool
    {
        return $this->delete($entity->getId());
    }
}
