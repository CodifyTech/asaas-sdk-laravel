<?php

/**
 * Refreshes tools/asaas-openapi.json from the official docs and reports what changed against the endpoint map.
 *
 * php tools/sync-spec.php [--out=file] [--pages-out=file] [--local=dir]
 *
 *   --out        snapshot to write (default: tools/asaas-openapi.json)
 *   --pages-out  "METHOD path" => doc page slug index (default: tools/doc-pages.json), used for the @see links
 *   --local  read <dir>/ref-asaas.txt and <dir>/pages/<slug>.md instead of the network (offline runs and tests)
 *
 * Each endpoint page of docs.asaas.com embeds its own OpenAPI document in a ```json block; the snapshot is the
 * union of their paths and of every components.* section. Same-named components that differ keep the first one.
 * After a sync: edit tools/endpoint-map.json for added/removed operations, then run tools/generator/generate.php.
 */

const INDEX_URL = 'https://docs.asaas.com/reference/asaas/llms.txt';

$options = ['out' => __DIR__ . '/asaas-openapi.json', 'pages-out' => __DIR__ . '/doc-pages.json', 'local' => ''];
foreach (array_slice($argv, 1) as $arg) {
    if (! preg_match('/^--(out|pages-out|local)=(.+)$/', $arg, $m)) {
        fwrite(STDERR, "Unknown argument: $arg\n");
        exit(2);
    }
    $options[$m[1]] = $m[2];
}

/** Some slugs have accents and PHP sends raw bytes (rejected by the server), so the last segment is percent-encoded. Idempotent. */
function normalizeUrl(string $url): string
{
    $cut = (int) strrpos($url, '/');
    return substr($url, 0, $cut + 1) . rawurlencode(rawurldecode(substr($url, $cut + 1)));
}

function fetchText(string $url, string $local): ?string
{
    if ($local !== '') {
        $file = $local . ($url === INDEX_URL ? '/ref-asaas.txt' : '/pages/' . rawurldecode(basename($url)));
        return is_file($file) ? (string) file_get_contents($file) : null;
    }
    $context = stream_context_create(['http' => ['timeout' => 30, 'follow_location' => 1]]);
    for ($try = 0; $try < 5; $try++) {
        $body = @file_get_contents(normalizeUrl($url), false, $context);
        if ($body !== false && $body !== '') {
            return $body;
        }
        sleep($try + 1);
    }
    return null;
}

/** @return list<string> endpoint page URLs: the indented links under each category */
function endpointPages(string $index): array
{
    preg_match_all('/^\s{2,}- \[[^\]]+\]\((https:\/\/docs\.asaas\.com\/reference\/[^)]+\.md)\)/m', $index, $m);
    return $m[1];
}

/** @return object|null the OpenAPI document of a page (the ```json block that has "paths") */
function pageSpec(string $markdown): ?object
{
    preg_match_all('/```json\s*\n(.*?)\n```/s', $markdown, $blocks);
    foreach ($blocks[1] as $block) {
        $spec = str_contains($block, '"paths"') ? json_decode($block) : null;
        if (is_object($spec) && isset($spec->paths)) {
            return $spec;
        }
    }
    return null;
}

$snapshotFile = __DIR__ . '/asaas-openapi.json';
$snapshot = json_decode((string) file_get_contents($snapshotFile));
$index = fetchText(INDEX_URL, $options['local']);
if ($index === null) {
    fwrite(STDERR, "Could not read the index.\n");
    exit(1);
}

$paths = [];
$components = [];
$conflicts = 0;
$docPages = [];
$missing = [];
$pages = endpointPages($index);
foreach ($pages as $url) {
    $spec = ($markdown = fetchText($url, $options['local'])) === null ? null : pageSpec($markdown);
    if ($spec === null) {
        $missing[] = basename($url);
        continue;
    }
    foreach ((array) $spec->paths as $path => $operations) {
        foreach (array_keys((array) $operations) as $verb) {
            // The page file name, not the spec operationId, is what docs.asaas.com/reference/<slug> serves.
            $docPages[strtoupper($verb) . ' ' . $path] = rawurldecode(basename($url, '.md'));
        }
        $paths[$path] = (object) array_merge((array) ($paths[$path] ?? []), (array) $operations);
    }
    foreach ((array) ($spec->components ?? []) as $kind => $items) {
        foreach ((array) $items as $name => $item) {
            if (isset($components[$kind][$name]) && json_encode($components[$kind][$name]) !== json_encode($item)) {
                $conflicts++;
                continue;
            }
            $components[$kind][$name] ??= $item;
        }
    }
}
if ($missing !== []) {
    // A partial snapshot would silently drop operations, so nothing is written.
    fwrite(STDERR, 'Pages without an OpenAPI block (' . count($missing) . '), snapshot not written: ' . implode(', ', $missing) . "\n");
    exit(1);
}

// Sorted keys keep the snapshot diff stable regardless of the order of the index.
ksort($paths);
foreach ($paths as $path => $verbs) {
    $sorted = (array) $verbs;
    ksort($sorted);
    $paths[$path] = (object) $sorted;
}
ksort($components);
foreach ($components as $kind => $items) {
    ksort($items);
    $components[$kind] = $items;
}

$merged = clone $snapshot;
$merged->paths = (object) $paths;
$merged->components = (object) array_map(fn ($items) => (object) $items, $components);
$json = json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
// PHP indents with 4 spaces; the snapshot uses 1 so that diffs stay small.
$json = preg_replace_callback('/^( {4})+/m', fn ($m) => str_repeat(' ', intdiv(strlen($m[0]), 4)), $json);
file_put_contents($options['out'], $json . "\n");
ksort($docPages);
file_put_contents($options['pages-out'], json_encode($docPages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

$operations = [];
foreach ($paths as $path => $verbs) {
    foreach (array_keys((array) $verbs) as $verb) {
        if (in_array($verb, ['get', 'post', 'put', 'delete', 'patch'], true)) {
            $operations[strtoupper($verb) . ' ' . $path] = true;
        }
    }
}
$mapped = [];
foreach (json_decode((string) file_get_contents(__DIR__ . '/endpoint-map.json'), true) as $entry) {
    $mapped[$entry['method'] . ' ' . $entry['path']] = true;
}
$added = array_keys(array_diff_key($operations, $mapped));
$removed = array_keys(array_diff_key($mapped, $operations));

echo 'Pages: ' . count($pages) . ', operations: ' . count($operations) . ", component name conflicts: $conflicts\n";
echo 'Added (in spec, not in map): ' . count($added) . "\n" . implode('', array_map(fn ($o) => "  + $o\n", $added));
echo 'Removed (in map, not in spec): ' . count($removed) . "\n" . implode('', array_map(fn ($o) => "  - $o\n", $removed));
echo "Wrote {$options['out']}\n";

