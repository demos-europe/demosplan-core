<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Statement;

use DemosEurope\DemosplanAddon\Logic\ApiRequest\ResourceObject;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\UnexpectedDataStructureException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\ValueObject\ValidatableValueObject;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Class StatementFragmentUpdate.
 *
 * This class is used to update multiple StatementFragments with the same
 * data (the data in instances of this class) in the repository.
 *
 * Instances will be validated as implemented in ValidatableValueObject.
 * The meaning of 'valid' is defined in the property annotations and may
 * be limited. For example this class is not responsible to validate the
 * existence of entities for a given entity ID or to access the database for
 * other validation reasons.
 *
 * This class is final to give methods to which instances of this class are
 * given to the certainty that the values returned by its getters are valid
 * with respect to the annotations. If you need to subclass this type please
 * ensure that the validation is still executed correctly.
 *
 * @method array  getStatementFragmentIds()
 * @method string getProcedureId()
 * @method string getConsiderationAddition()
 * @method array  getPropertiesSet()
 * @method string getAssigneeId()
 */
final class StatementFragmentUpdate extends ValidatableValueObject
{
    /**
     * @Assert\All({
     *     @Assert\Uuid()
     * })
     * @Assert\Count(min=1)
     * @Assert\Valid()
     * @Assert\NotNull()
     *
     * @var string[]
     */
    protected $statementFragmentIds;

    /**
     * @Assert\Uuid()
     * @Assert\NotNull()
     *
     * @var string
     */
    protected $procedureId;

    /**
     * This value may be set in the constructor.
     *
     * Currently it must be set, as no other properties are optional and at least one optional needs to be set when
     * initializing an instance. As a result the NotBlank assertion is valid. However when more optional properties
     * are added the validation needs to differentiate between "is null meaning not set" (valid) and "is null because
     * of the request" (invalid).
     *
     * @Assert\Length(min=1)
     * @Assert\Type(type="string")
     *
     * @var string
     */
    protected $considerationAddition;

    /**
     * The properties set using the constructor. This is needed as properties
     * may be set to null to indicate a property in an StatementFragment
     * should be set to null, which would otherwise be indistinguishable from
     * their initial state.
     *
     * @var string[]
     */
    protected $propertiesSet;

    /**
     * UUID v4 if the assignee should be set, or null if it should be unset.
     *
     * Will only be used to update the StatementFragments if 'assigneeId' is set inside $propertiesSet.
     *
     * @var string
     */
    protected $assigneeId;

    /**
     * Keep this constructor private to ensure instances are created with a
     * static 'createFrom' function and validated as soon as they're created.
     *
     * @param string             $procedureId The procedure ID the statement
     *                                        fragments are assumed to be in
     * @param ResourceObject     $resource    A ResourceObject created from an
     *                                        JSON:API request. Must contain fields
     *                                        used by this class (with no additional
     *                                        fields).
     * @param ValidatorInterface $validator   the validator to use to validate
     *
     * @throws UnexpectedDataStructureException thrown in case of an invalid
     *                                          state of the given
     *                                          ResourceObject
     * @throws ViolationsException              thrown if data for expected fields didn't
     *                                          validate against the assertions
     */
    public function __construct($procedureId, ResourceObject $resource, ValidatorInterface $validator)
    {
        parent::__construct($validator);
        try {
            // get attributes
            $attributes = $resource->get('attributes');
            if (!is_array($attributes)) {
                throw new InvalidArgumentException('attributes must be an array');
            }
            $statementFragmentIds = $attributes['statementFragmentIds'] ?? null;

            if ($resource->isPresent('assignee')) {
                $this->assigneeId = $resource->get('relationships.assignee.data.id');
            }

            $this->propertiesSet = [];

            if (null !== $this->assigneeId) {
                $this->propertiesSet[] = 'assigneeId';
            }

            // remove the attributes which are handled separately and unneeded
            // information (the count is/may only needed for error messages)
            // but keep everything else to not require to adjust this function
            // for every new property
            unset($attributes['statementFragmentIds'], $attributes['markedStatementFragmentsCount']);
            $this->statementFragmentIds = $statementFragmentIds;
            $this->procedureId = $procedureId;

            // check if anything should be updated, given in attributes or relationships
            $relationships = $resource['relationships'] ?? [];
            if (!is_array($relationships)) {
                throw new InvalidArgumentException("expected 'relationships' to be an array, got $relationships");
            }

            if (0 === count($attributes) && 0 === count($relationships)) {
                throw new InvalidArgumentException('Instances must update at least one property.');
            }

            // For each key in the array a matching property name is expected.
            // The corresponding value in the array will be set as the value
            // of the property.
            foreach ($attributes as $key => $optional) {
                if ('procedureId' === $key) {
                    throw new InvalidArgumentException('procedureId must not be set using the attributes');
                }
                // check if the key is one of the properties of this class
                if (property_exists($this, $key)) {
                    $this->{$key} = $optional;
                } else {
                    throw new InvalidArgumentException(sprintf('unknown key %s', $key));
                }
            }
            $this->propertiesSet = array_merge($this->propertiesSet, array_keys($attributes));
        } catch (InvalidArgumentException $e) {
            throw new UnexpectedDataStructureException('Could not create a StatementFragmentUpdate object from the given data', 0, $e);
        }
    }
}
