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
use demosplan\DemosPlanCoreBundle\Entity\MailAttachment;
use demosplan\DemosPlanCoreBundle\Entity\MailSend;
use demosplan\DemosPlanCoreBundle\Entity\MailTemplate;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ImmutableArrayInterface;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ImmutableObjectInterface;
use Exception;

/**
 * @template-extends FluentRepository<MailSend>
 */
class MailRepository extends FluentRepository implements ImmutableArrayInterface, ImmutableObjectInterface
{
    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     *
     * @return MailSend
     */
    public function get($entityId)
    {
        return $this->getEntityManager()
            ->getRepository(MailSend::class)
            ->findOneBy(['id' => $entityId]);
    }

    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     */
    public function getTemplate($entityId): ?MailTemplate
    {
        return $this->getEntityManager()
            ->getRepository(MailTemplate::class)
            ->findOneBy(['label' => $entityId]);
    }

    /**
     * Add Entity to database.
     *
     * @return MailSend
     *
     * @throws Exception
     *
     * @deprecated Use {@link MailRepository::addObject()} instead. A full text search for
     * `getRepository(MailSend` shows no call of this method anymore and ideally it can be
     * removed.
     */
    public function add(array $data)
    {
        try {
            $mailSend = $this->generateObjectValues(new MailSend(), $data);
            // add prefix to subject if defined
            if (isset($data['subjectPrefix'])) {
                $mailSend->setTitle($data['subjectPrefix'].$mailSend->getTitle());
            }

            return $mailSend;
        } catch (Exception $e) {
            $this->logger->warning(
                'Create Mail failed Message: '.$e
            );
            throw $e;
        }
    }

    /**
     * Create an Attachment for an Email.
     *
     * @param string $filename
     * @param bool   $deleteOnSent
     *
     * @return MailAttachment
     */
    public function createAttachment($filename, $deleteOnSent = true)
    {
        try {
            $attachment = new MailAttachment();
            $attachment->setFilename($filename)
                       ->setDeleteOnSent($deleteOnSent);

            return $attachment;
        } catch (Exception $e) {
            $this->logger->warning(
                "Attachment $filename could not be created: ", [$e]
            );
            throw $e;
        }
    }

    /**
     * Set Objectvalues by array
     * Set "@param" according to specific entity to get autocompletion.
     *
     * @param MailSend $entity
     *
     * @return MailSend
     */
    public function generateObjectValues($entity, array $data)
    {
        $mailTemplate = $this->getTemplate($data['template']);
        if (array_key_exists('template', $data)) {
            if (!is_null($mailTemplate)) {
                $entity->setTemplate($mailTemplate->getLabel());
            }
        }

        if (!is_null($mailTemplate)) {
            $mailTitle = $mailTemplate->getTitle();
            $mailContent = $mailTemplate->getContent();
            if (array_key_exists('vars', $data)) {
                $mailTitle = $this->replacePlaceholder($mailTitle, $data['vars']);
                $mailContent = $this->replacePlaceholder($mailContent, $data['vars']);
            }
            $entity->setTitle($mailTitle);
            $entity->setContent($mailContent);
            $entity->setTemplate($mailTemplate->getLabel());
        }
        if (array_key_exists('to', $data)) {
            $entity->setTo($data['to']);
        }
        if (array_key_exists('cc', $data)) {
            $entity->setCc($data['cc']);
        }
        if (array_key_exists('bcc', $data)) {
            $entity->setBcc($data['bcc']);
        }
        if (array_key_exists('from', $data)) {
            $entity->setFrom($data['from']);
        }
        if (array_key_exists('scope', $data)) {
            $entity->setScope($data['scope']);
        }

        return $entity;
    }

    /**
     * Replace placeholder.
     *
     * @param string $string
     */
    public function replacePlaceholder($string, array $placeholder)
    {
        foreach ($placeholder as $toReplace => $value) {
            $string = preg_replace('/\$\{'.$toReplace.'\}/', (string) $value, $string);
        }

        return $string;
    }

    public function deleteAfterDays(int $days): int
    {
        try {
            $query = $this->getEntityManager()->createQueryBuilder()
                ->delete(MailSend::class, 'm')
                ->andWhere("m.sendDate < DATE_SUB(CURRENT_DATE(), :days, 'DAY')")
                ->setParameter('days', $days)
                ->getQuery();

            return $query->execute();
        } catch (Exception $e) {
            $this->logger->warning('Could not delete MailSend by date', [$e]);
            throw $e;
        }
    }

    /**
     * @param MailSend $entity
     *
     * @throws Exception
     */
    public function addObject($entity): ?MailSend
    {
        try {
            // Mark this email als ready to send.
            $this->getEntityManager()->persist($entity);
            $this->getEntityManager()->flush();

            return $entity;
        } catch (Exception $e) {
            $this->logger->warning('Create Mail failed Message: '.$e);
            throw $e;
        }
    }
}
