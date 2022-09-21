<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\Entity\EntityInterface;

/**
 * Even simple tasks to be executed in the backend -- e.g. updating an entity field value or
 * deleting or creating an entity -- may affect more than one entity due to (potentially
 * bidirectional) relationships and side effects. Other tasks, e.g. bulk edits, affect multiple
 * entities by their very nature.
 *
 * {@link ResourceChange} instances can be used to collect entity instances that were affected by
 * an executed task, to update the database and search index accordingly in a single go. Executing
 * the actual database/search index update is not done via this class. It is merely a collector of
 * the entities that were affected and provides getters to retrieve those.
 *
 * Instances can be passed around at leisure when and where they are deemed useful. E.g. via
 * method parameters, returns or via events to add entities in plugins.
 */
interface EntityChangeInterface
{
    /**
     * Add an entity instance that should be persisted via Doctrine before flushing.
     *
     * Please note that it is **not necessary** to use this method if your entity instance is
     * already managed by Doctrine (i.e. fetched via Doctrine from the database). Changes to
     * already persisted entities will be flushed into the database without the need to add the
     * entity instance to the {@link EntityChangeInterface} instance.
     */
    public function addEntityToPersist(EntityInterface $entity): void;

    /**
     * Add multiple entity instances that should be persisted via Doctrine before flushing.
     *
     * Please note that it is **not necessary** to use this method if your entity instance is
     * already managed by Doctrine (i.e. fetched via Doctrine from the database). Changes to
     * already persisted entities will be flushed into the database without the need to add the
     * entity instances to the {@link EntityChangeInterface} instance.
     *
     * @param array<int, EntityInterface> $entities
     */
    public function addEntitiesToPersist(array $entities): void;

    /**
     * @return array<int,EntityInterface>
     */
    public function getEntitiesToPersist(): array;

    public function addEntityToDelete(EntityInterface $entity): void;

    /**
     * @return array<int,EntityInterface>
     */
    public function getEntitiesToDelete(): array;

    /**
     * This method is only needed in very special cases, attempted to be explained below:.
     *
     * Within the `fos_elastica.yml` file you can define a listener via `persistence.listener`
     * in each type. The default is "`listener: ~`" meaning that the FoS Elastica Bundle
     * listens on doctrine events (e.g. an update) and automatically updates the index.
     *
     * In some cases a custom listener needs to be used instead of the default one (currently the
     * case for {@link Statement} and {@link StatementFragment} entities). In this case you need
     * to set `listener: { enabled: false }` and implement your own listener like
     * {@link UpdateElasticaStatementPostListener} and configure it as an appropriately
     * tagged service, so it will become active at the relevant events.
     *
     * *If* for some reason these custom listeners do not update the index with all data of the
     * entity, a manual call to {@link SearchIndexTaskService::addIndexTask()} is needed. Adding
     * the IDs and corresponding class of entities via this method expresses the desire that
     * these will be manually updated at some point.
     *
     * Currently, it is needed for {@link Statement} entities only, so their attributes are updated
     * in the Elasticsearch index, to avoid discrepancies between the database and the index.
     *
     * @param class-string $class
     */
    public function addEntityToUpdateInIndex(string $class, string $entityId): void;

    /**
     * @return array<class-string,array<int,string>>
     */
    public function getEntityIdsToUpdateInIndex(): array;
}
