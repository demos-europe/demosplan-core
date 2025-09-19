<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use DateTime;
use DemosEurope\DemosplanAddon\Logic\ApiRequest\FluentRepository;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ArrayInterface;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;

/**
 * @template-extends FluentRepository<File>
 */
class FileRepository extends FluentRepository implements ArrayInterface, ObjectInterface
{
    /**
     * Hole Infos zum File.
     */
    public function getFile(string $hash, ?string $procedureId = null): ?File
    {
        // Der übergebene Hash ist der Ident der Datenbank
        // Die Spalte Hash bezeichnet den Namen, unter dem die Datei auf dem
        // Dateisystem abgelegt ist

        /** @var File|null $result */
        $result = $this->findOneBy(['ident' => $hash, 'deleted' => false]);
        if (null !== $result) {
            return $result;
        }
        // As ident and hash are historically really strangely used mistakes happened
        // be kind and try to find via hash when nothing was found by ident

        // T36732 In case the same physical file is used for multiple procedures
        // There will be a fileInfo entity for every reference to a physical file.
        // They share the same hash - but not necessarily the procedure
        // - so the findOneBy method for the kindly supported hash is insufficient here.
        $fileInfos = $this->findBy(['hash' => $hash, 'deleted' => false, 'procedure' => $procedureId]);
        $fileInfosCount = count($fileInfos);
        // easy - has to be this one
        if (0 < $fileInfosCount) {
            return reset($fileInfos);
        }
        $fileInfos = $this->findBy(['hash' => $hash, 'deleted' => false, 'procedure' => null]);
        $fileInfosCount = count($fileInfos);
        // tried our best to return the correct fileInfo - but if not successful until here - just return a matching hash
        if (0 === $fileInfosCount) {
            return $this->findOneBy(['hash' => $hash, 'deleted' => false]);
        }

        return reset($fileInfos);
    }

    /**
     * @param File $entity
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function addObject($entity): File
    {
        try {
            $em = $this->getEntityManager();

            $em->persist($entity);
            $em->flush();

            return $entity;
        } catch (Exception $e) {
            $this->logger->warning('File could not be added. ', [$e]);
            throw $e;
        }
    }

    /**
     * @param File $file
     *
     * @return File
     *
     * @throws Exception
     */
    public function updateObject($file)
    {
        try {
            $em = $this->getEntityManager();
            $em->persist($file);
            $em->flush();

            return $file;
        } catch (Exception $e) {
            $this->getLogger()->warning('File could not be updated. ', [$e]);
            throw $e;
        }
    }

    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     *
     * @return File|null
     */
    public function get($entityId)
    {
        try {
            return $this->findOneBy(['ident' => $entityId]);
        } catch (Exception $e) {
            $this->logger->error('Could not find File:', [$e]);
        }

        return null;
    }

    /**
     * Add Entity to database.
     *
     * @return void
     */
    public function add(array $data)
    {
    }

    /**
     * Update Entity.
     *
     * @param string $entityId
     *
     * @return void
     */
    public function update($entityId, array $data)
    {
    }

    /**
     * Delete entity by id.
     *
     * @param string $entityId
     */
    public function delete($entityId): bool
    {
        try {
            $em = $this->getEntityManager();
            /** @var File $file */
            $file = $this->findOneBy(['ident' => $entityId]);

            if (null === $file) {
                return true;
            }

            $em->remove($file);
            $em->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->warning('FileEntity could not be removed by id. ', [$e]);

            return false;
        }
    }

    /**
     * Delete entity by hash.
     *
     * @param string $hash
     */
    public function deleteByHash($hash): bool
    {
        try {
            $em = $this->getEntityManager();
            $file = $this->findOneBy(['hash' => $hash]);
            if (null === $file) {
                return true;
            }

            $em->remove($file);
            $em->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->warning('FileEntity could not be removed by hash. ', [$e]);

            return false;
        }
    }

    /**
     * Delete Entity.
     */
    public function deleteByHashOrIdent(string $hashOrIdent): bool
    {
        try {
            // try deleting by hash
            $outputHash = $this->deleteByHash($hashOrIdent);

            // try deleting by ident
            $outputIdent = $this->delete($hashOrIdent);

            // pass on output from sub methods
            if (in_array(false, [$outputHash, $outputIdent], true)) {
                return false;
            }

            return true;
        } catch (Exception) {
            // logging is already done in sub methods

            return false;
        }
    }

    /**
     * Set Objectvalues by array
     * Set "@param" according to specific entity to get auto-completion.
     *
     * @param File $entity
     *
     * @return File
     */
    public function generateObjectValues($entity, array $data)
    {
        return $entity;
    }

    public function deleteObject($entity): never
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    /**
     * @return array<string, string>
     *
     * @throws ORMException
     */
    public function copy(string $blueprintId, string $destinationProcedureId): array
    {
        $blueprint = $this->getEntityManager()->getReference(Procedure::class, $blueprintId);
        $destinationProcedure = $this->getEntityManager()->getReference(Procedure::class, $destinationProcedureId);

        $fileStringMapping = [];

        /*The files directly related to the procedure are not the one which are related like
        procedure->element->singleDocument->document*/
        foreach ($blueprint->getFiles() as $blueprintFile) {
            $fileCopy = $this->copyFile($blueprintFile);
            $this->getEntityManager()->persist($fileCopy);
            $destinationProcedure->getFiles()->add($fileCopy);
            $fileStringMapping[$blueprintFile->getFileString()] = $fileCopy->getFileString();
        }

        $this->getEntityManager()->persist($destinationProcedure);
        $this->getEntityManager()->flush();

        return $fileStringMapping;
    }

    public function copyFile(File $fileToCopy): File
    {
        $fileCopy = new File();
        $fileCopy->setProcedure($fileToCopy->getProcedure());
        $fileCopy->setApplication($fileToCopy->getApplication());
        $fileCopy->setAuthor($fileToCopy->getAuthor());
        $fileCopy->setBlocked($fileToCopy->getBlocked());
        $fileCopy->setCreated(new DateTime());
        $fileCopy->setDeleted($fileToCopy->getDeleted());
        $fileCopy->setDescription($fileToCopy->getDescription());
        $fileCopy->setFilename($fileToCopy->getFilename());
        $fileCopy->setHash($fileToCopy->getHash());
        $fileCopy->setInfected($fileToCopy->getInfected());
        $fileCopy->setLastVScan($fileToCopy->getLastVScan());
        $fileCopy->setMimetype($fileToCopy->getMimetype());
        $fileCopy->setModified(new DateTime());
        $fileCopy->setPath($fileToCopy->getPath());
        $fileCopy->setSize($fileToCopy->getSize());
        $fileCopy->setStatDown($fileToCopy->getStatDown());
        $fileCopy->setTags($fileToCopy->getTags());
        $fileCopy->setValidUntil($fileToCopy->getValidUntil());

        return $fileCopy;
    }
}
