<?php

declare(strict_types=1);

/**
 * Code generator for the Asaas SDK: services, request classes, models, tests, docs and AI skill references.
 *
 * php tools/generator/generate.php [--only=PaymentService,PixService] [--out=dir] [--check [--strict]] [--prune]
 *
 *   --out    target root (default: the repository root)
 *   --check  regenerate in memory and exit 1 on drift or an invalid map; writes nothing
 *   --strict with --check: stale request classes and references (no longer in the map) also count as drift; otherwise they are warnings
 *   --prune  also delete request classes under <out>/src/Http/Requests and references under <out>/resources/boost/skills/asaas-sdk-laravel/references that the map no longer references
 */

namespace AsaasTools;

foreach (['Php', 'Spec', 'RulesBuilder', 'Sample', 'MapValidator', 'Plan', 'ServiceEmitter', 'RequestEmitter', 'ModelEmitter', 'TestEmitter', 'DocsEmitter', 'ReferenceEmitter'] as $file) {
    require_once __DIR__."/$file.php";
}

function fail(string $message, int $code = 1): never
{
    fwrite(STDERR, $message."\n");
    exit($code);
}

/** @return array<string, string|bool> */
function parseOptions(array $argv): array
{
    $options = ['out' => dirname(__DIR__, 2), 'only' => '', 'check' => false, 'prune' => false, 'strict' => false];
    foreach (array_slice($argv, 1) as $arg) {
        if (in_array($arg, ['--check', '--prune', '--strict'], true)) {
            $options[substr($arg, 2)] = true;
        } elseif (preg_match('/^--(out|only)=(.+)$/', $arg, $m)) {
            $options[$m[1]] = $m[2];
        } else {
            fail("Unknown argument: $arg\nUsage: php tools/generator/generate.php [--only=A,B] [--out=dir] [--check] [--prune]");
        }
    }

    return $options;
}

/**
 * Runs the emitted PHP through Laravel Pint with the repository's pint.json, so generated and
 * hand-written code share one style and `composer format:check` agrees with `--check`.
 *
 * @param  array<string, string>  $files  relative path => content
 * @return array<string, string>
 */
function formatWithPint(array $files): array
{
    $repo = dirname(__DIR__, 2);
    $pint = "$repo/vendor/laravel/pint/builds/pint";
    if (! is_file($pint)) {
        fail('Laravel Pint is missing: run `composer install` before generating.');
    }

    $tmp = sys_get_temp_dir().'/asaas-generator-'.bin2hex(random_bytes(8));
    $php = array_filter($files, fn (string $path) => str_ends_with($path, '.php'), ARRAY_FILTER_USE_KEY);
    foreach ($php as $relative => $content) {
        $path = "$tmp/$relative";
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0o700, true);
        }
        file_put_contents($path, $content);
    }

    try {
        $process = proc_open(
            [PHP_BINARY, '-d', 'xdebug.mode=off', $pint, '--config='."$repo/pint.json", '--quiet', $tmp],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        if (! is_resource($process)) {
            fail('Could not start Laravel Pint.');
        }
        $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
        if (proc_close($process) !== 0) {
            fail("Laravel Pint failed on the generated files:\n$output");
        }
        foreach (array_keys($php) as $relative) {
            $files[$relative] = (string) file_get_contents("$tmp/$relative");
        }
    } finally {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($tmp, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($tmp);
    }

    return $files;
}

/** @return list<string> request class and reference files under $root that the generator does not own anymore */
function staleRequestFiles(string $root, array $generated): array
{
    $stale = [];
    $found = [...(glob("$root/src/Http/Requests/*/*.php") ?: []), ...(glob("$root/".ReferenceEmitter::DIR.'/*.md') ?: [])];
    foreach ($found as $file) {
        $relative = substr($file, strlen($root) + 1);
        if (! isset($generated[$relative])) {
            $stale[] = $relative;
        }
    }

    return $stale;
}

$options = parseOptions($argv);
$root = rtrim((string) $options['out'], '/');
$toolsDir = dirname(__DIR__);

$map = json_decode((string) file_get_contents("$toolsDir/endpoint-map.json"), true);
if (! is_array($map)) {
    fail('tools/endpoint-map.json is not valid JSON.');
}
$spec = new Spec("$toolsDir/asaas-openapi.json");

$docPages = json_decode((string) file_get_contents("$toolsDir/doc-pages.json"), true);
if (! is_array($docPages)) {
    fail('tools/doc-pages.json is missing or invalid (run tools/sync-spec.php).');
}
$errors = MapValidator::validate($map, $spec, $docPages);
if ($errors !== []) {
    fail("Invalid map:\n  ".implode("\n  ", $errors));
}
$plan = new Plan($map, $spec);
if ($plan->errors !== []) {
    fail("Invalid map:\n  ".implode("\n  ", $plan->errors));
}

$all = array_keys($plan->services);
$only = $options['only'] === '' ? [] : explode(',', (string) $options['only']);
if (array_diff($only, $all) !== []) {
    fail('Unknown service(s): '.implode(', ', array_diff($only, $all)));
}
$services = $only ?: $all;

$files = array_merge(
    (new ServiceEmitter($plan))->emit($services),
    (new RequestEmitter($plan))->emit($services),
    (new ModelEmitter($plan))->emit($services),
    (new TestEmitter($plan))->emit($services),
    (new DocsEmitter($plan))->emit($services, $only === []),
    (new ReferenceEmitter($plan))->emit($services),
);
$files = formatWithPint($files);
ksort($files);
$stale = $only === [] ? staleRequestFiles($root, $files) : [];

if ($options['check']) {
    $drift = [];
    foreach ($files as $relative => $content) {
        $path = "$root/$relative";
        if (! is_file($path)) {
            $drift[] = "missing: $relative";
        } elseif (file_get_contents($path) !== $content) {
            $drift[] = "changed: $relative";
        }
    }
    foreach ($stale as $relative) {
        if ($options['strict']) {
            $drift[] = "stale: $relative";
        } else {
            fwrite(STDERR, "warning, stale (no longer in the map): $relative\n");
        }
    }
    if ($drift !== []) {
        fail('Drift in '.count($drift)." file(s):\n  ".implode("\n  ", array_slice($drift, 0, 40)).(count($drift) > 40 ? "\n  ..." : ''));
    }
    echo 'OK: '.count($files)." generated files match the map.\n";
    exit(0);
}

foreach ($files as $relative => $content) {
    $path = "$root/$relative";
    if (! is_dir(dirname($path))) {
        mkdir(dirname($path), 0o755, true);
    }
    file_put_contents($path, $content);
}
foreach ($stale as $relative) {
    if ($options['prune']) {
        unlink("$root/$relative");
        echo "pruned $relative\n";
    } else {
        echo "stale (use --prune to delete): $relative\n";
    }
}
echo 'Wrote '.count($files)." files to $root\n";
