<?php

/*
 * Reduces a cdxgen SBOM to the production dependency closure, so the JS SBOM covers the same ground
 * as the composer one, which is generated from a --no-dev install.
 *
 * cdxgen's own --required-only is not usable for this: it keeps the components it scoped as
 * "required", a narrower set that omits declared production dependencies (cesium, core-js and the
 * @uppy packages among them). The closure is therefore walked here, seeded from the dependencies
 * package.json declares and followed through the dependency graph cdxgen emitted.
 *
 * Usage: php sbom-prod-filter.php <bom.json> <package.json>
 */

declare(strict_types=1);

function fail(string $message): never
{
    fwrite(STDERR, "refusing to filter: $message\n");
    exit(1);
}

function readJson(string $path): array
{
    $contents = file_get_contents($path);

    if (false === $contents) {
        fail("cannot read $path");
    }

    return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
}

function componentName(array $component): string
{
    return '' === ($component['group'] ?? '')
        ? $component['name']
        : $component['group'].'/'.$component['name'];
}

[, $bomPath, $manifestPath] = $argv + [null, null, null];

if (null === $bomPath || null === $manifestPath) {
    fwrite(STDERR, "usage: php sbom-prod-filter.php <bom.json> <package.json>\n");
    exit(2);
}

$bom = readJson($bomPath);
$declared = array_keys(readJson($manifestPath)['dependencies'] ?? []);
$components = $bom['components'] ?? [];

$edges = [];
foreach ($bom['dependencies'] ?? [] as $entry) {
    $edges[$entry['ref']] = $entry['dependsOn'] ?? [];
}
$rootRef = $bom['metadata']['component']['bom-ref'] ?? null;

/*
 * Seed with every version of every declared production dependency, then follow the graph. The root
 * is excluded as a seed: cdxgen makes it depend on the whole tree, dev dependencies included.
 */
$keep = [];
$queue = [];
foreach ($components as $component) {
    if (in_array(componentName($component), $declared, true)) {
        $keep[$component['bom-ref']] = true;
        $queue[] = $component['bom-ref'];
    }
}
$seedCount = count($queue);

while ([] !== $queue) {
    foreach ($edges[array_pop($queue)] ?? [] as $ref) {
        if ($ref !== $rootRef && !isset($keep[$ref])) {
            $keep[$ref] = true;
            $queue[] = $ref;
        }
    }
}

$missing = array_diff($declared, array_map('componentName', $components));

if ([] !== $missing) {
    fail('declared dependencies absent from the SBOM: '.implode(', ', $missing));
}

/*
 * The closure is only as good as the graph cdxgen emitted. Rather than ship a thinned SBOM, fail if
 * that graph is missing or yielded no transitive dependency at all, which is what a change to how
 * cdxgen represents it would look like.
 */
if ([] === $edges) {
    fail('the SBOM carries no dependency graph');
}

if (count($keep) === $seedCount) {
    fail("the graph yielded no transitive dependency for $seedCount declared ones");
}

$bom['components'] = array_values(array_filter(
    $components,
    static fn (array $component): bool => isset($keep[$component['bom-ref']])
));
/*
 * Drop pruned components from the graph, and pruned refs from the entries that survive, so no
 * dependsOn points at a component that is no longer part of the document.
 */
$bom['dependencies'] = array_values(array_map(
    static function (array $entry) use ($keep): array {
        $entry['dependsOn'] = array_values(array_filter(
            $entry['dependsOn'] ?? [],
            static fn (string $ref): bool => isset($keep[$ref])
        ));

        return $entry;
    },
    array_filter(
        $bom['dependencies'] ?? [],
        static fn (array $entry): bool => $entry['ref'] === $rootRef || isset($keep[$entry['ref']])
    )
));
/*
 * Cdxgen sets no compositions on an unfiltered run. Asserting one here would either understate the
 * result ("incomplete", though no dev dependency reaches the image) or overstate it ("complete",
 * though that relies on package.json classifying its dependencies correctly).
 */
unset($bom['compositions']);

file_put_contents($bomPath, json_encode($bom, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
printf("sbom-prod-filter: kept %d of %d components\n", count($bom['components']), count($components));
