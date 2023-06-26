<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiDocumentation;

use const ARRAY_FILTER_USE_KEY;

use function array_key_exists;
use function array_pop;
use function array_reduce;

use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Reference;
use cebe\openapi\SpecObjectInterface;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use EDT\JsonApi\Schema\ContentField;

/**
 * Generate a JS File with resource definitions for demosplans' {json:api}.
 *
 * To make lookup and usage of EDT-based {json:api} Resources
 * in the FE JS/Vue code base easier, a dictionary of all resources
 * with their names, type names and relationship information
 * is exported during frontend asset builds.
 */
final class JsApiResourceDefinitionBuilder
{
    public function build(OpenApi $openApiSpec, string $filename): void
    {
        $resourceInfoList = $this->parseSpec($openApiSpec);

        $resourceTypeNames = array_map(static function (string $type): string {
            return sprintf('  %sResource', $type);
        }, array_keys($resourceInfoList));

        // declare all available resource type objects
        $resourceDeclarations = array_reduce(
            $resourceTypeNames,
            static function (string $list, string $typeName): string {
                return $list.sprintf("let %s\n", trim($typeName));
            },
            ''
        );

        // freeze available resource information into the objects
        $resourceMap = collect($resourceInfoList)->map(static function (array $resourceInfo): string {
            $resourceName = $resourceInfo['name'];
            $constantName = sprintf('%sResource', $resourceName);
            $moduleName = lcfirst($resourceName);

            $relationships = 'null';
            if (0 < count($resourceInfo[ContentField::RELATIONSHIPS])) {
                $relationships = "{\n";

                // format relationship info into readable js objects.
                $relationships .= collect($resourceInfo[ContentField::RELATIONSHIPS])
                    ->map(static function (string $relationshipType, string $relationshipName): string {
                        return sprintf(
                            "        %s: { name: '%s', type: %sResource }",
                            $relationshipName,
                            $relationshipName,
                            $relationshipType
                        );
                    })
                    ->implode(",\n");

                $relationships .= "\n    }";
            }

            return sprintf(
                <<<RESOURCE_INFO
%s = Object.freeze({
    type: '%s',
    module: '%s',
    relationships: %s
})

RESOURCE_INFO
                ,
                $constantName,
                $resourceName,
                $moduleName,
                $relationships
            );
        })->implode(PHP_EOL);

        // add an export block for all generated objects
        $resourceMap .= sprintf(
            "\nexport default {\n%s\n}\n",
            implode(
                ",\n",
                $resourceTypeNames
            )
        );

        $classname = self::class;
        $resourceMapHeader = <<<RESOURCE_MAP_HEADER
/**
 * This file is auto-generated during the webpack build process.
 *
 * **NEVER COMMIT THIS FILE!**
 *
 * The contents of this file can be adjusted in {$classname}.
 */


RESOURCE_MAP_HEADER;

        file_put_contents(
            DemosPlanPath::getRootPath(
                $filename
            ),
            $resourceMapHeader.$resourceDeclarations.PHP_EOL.$resourceMap.PHP_EOL
        );
    }

    /**
     * Parse a dictionary of resource names mapped to their relationships out of an OpenApi Spec.
     *
     * @return array<string,mixed>
     */
    private function parseSpec(OpenApi $openApiSpec): array
    {
        $resourceInfoList = [];

        // skip all prefixed schema types as those aren't Resources
        $typeSchemas = array_filter(
            $openApiSpec->components->schemas,
            static function (string $type): bool {
                return -1 > strpos($type, ':');
            },
            ARRAY_FILTER_USE_KEY
        );

        foreach ($typeSchemas as $type => $schema) {
            $resourceInfo = ['name' => $type];

            $resourceInfo[ContentField::RELATIONSHIPS] = collect($schema->properties)
                ->filter(static function (SpecObjectInterface $property) {
                    return $property instanceof Reference;
                })
                ->mapWithKeys(static function (Reference $relationship, string $relationshipName): array {
                    $referencePath = $relationship->getJsonReference()->getJsonPointer()->getPath();

                    return [$relationshipName => array_pop($referencePath)];
                })
                ->all();

            if (array_key_exists($type, $resourceInfoList)) {
                $resourceInfo = array_merge($resourceInfoList[$type], $resourceInfo);
            }

            $resourceInfoList[$type] = $resourceInfo;
        }

        return $resourceInfoList;
    }
}
