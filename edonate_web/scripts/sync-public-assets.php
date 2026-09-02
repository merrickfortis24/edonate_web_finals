#!/usr/bin/env php
<?php

declare(strict_types=1);

$projectRoot = realpath(__DIR__ . '/..');
if ($projectRoot === false) {
    fwrite(STDERR, "Unable to resolve project root.\n");
    exit(1);
}

chdir($projectRoot);

$options = parseOptions($argv);
$dryRun = isset($options['dry-run']);
$skipCache = isset($options['skip-cache-clear']);
$webRoot = resolveWebRoot($projectRoot, $options);
$laravelPublic = resolveLaravelPublic($projectRoot, $webRoot);
$legacyPublic = resolveLegacyPublic($projectRoot, $webRoot);

if (! is_dir($laravelPublic)) {
    fail("Laravel public directory not found: {$laravelPublic}");
}

if ($webRoot === null) {
    fail(
        "Unable to detect the served web root.\n" .
        "Run with --web-root=/home/USER/domains/edonate.online/public_html " .
        "or set EDONATE_WEB_ROOT to that path."
    );
}

$webRoot = normalizePath($webRoot);
if (! is_dir($webRoot)) {
    fail("Served web root does not exist: {$webRoot}");
}

$laravelPublicReal = normalizePath($laravelPublic);
$legacyPublicReal = $legacyPublic !== null ? normalizePath($legacyPublic) : null;
if ($webRoot === $laravelPublicReal) {
    writeln("Laravel public path is already the served web root: {$webRoot}");
    if (! $skipCache) {
        clearCaches($dryRun);
    }
    exit(0);
}

$assetDirs = ['js', 'css', 'images', 'build', 'vendor'];
$publicFiles = ['index.php', '.htaccess', 'favicon.ico', 'robots.txt', 'sw.js'];
$totals = [
    'copied' => 0,
    'unchanged' => 0,
    'skipped_dirs' => 0,
];

writeln("Project root: {$projectRoot}");
writeln("Laravel public assets: {$laravelPublicReal}");
if ($legacyPublicReal !== null && $legacyPublicReal !== $laravelPublicReal) {
    writeln("Legacy public asset fallback: {$legacyPublicReal}");
}
writeln("Served web root: {$webRoot}");
writeln("asset('js/admin/...') resolves to /js/admin/... and is served from {$webRoot}/js/admin/.");

foreach ($assetDirs as $dir) {
    $destination = $webRoot . DIRECTORY_SEPARATOR . $dir;
    $source = resolvePublicAssetSource($laravelPublicReal, $legacyPublicReal, $dir, true);

    if ($source === null) {
        $totals['skipped_dirs']++;
        writeln("Skip missing public/{$dir}");
        continue;
    }

    if (normalizePath($source) === normalizePath($destination)) {
        $totals['unchanged']++;
        writeln("Skip {$dir}: source is already the served web root.");
        continue;
    }

    $result = syncDirectory($source, $destination, $dryRun);
    $totals['copied'] += $result['copied'];
    $totals['unchanged'] += $result['unchanged'];
}

foreach ($publicFiles as $file) {
    $destination = $webRoot . DIRECTORY_SEPARATOR . $file;
    $source = resolvePublicAssetSource($laravelPublicReal, $legacyPublicReal, $file, false);

    if ($source === null) {
        $totals['skipped_dirs']++;
        writeln("Skip missing public/{$file}");
        continue;
    }

    if (normalizePath($source) === normalizePath($destination)) {
        $totals['unchanged']++;
        writeln("Skip {$file}: source is already the served web root.");
        continue;
    }

    if (! shouldCopyFile($source, $destination)) {
        $totals['unchanged']++;
        continue;
    }

    if ($dryRun) {
        writeln("copy {$source} -> {$destination}");
    } else {
        if (! copy($source, $destination)) {
            fail("Failed to copy {$source} to {$destination}");
        }
    }

    $totals['copied']++;
}

writeln("Public sync complete. Copied: {$totals['copied']}; unchanged: {$totals['unchanged']}; missing source items: {$totals['skipped_dirs']}.");

if (! $skipCache) {
    clearCaches($dryRun);
}

writeln($dryRun ? 'Dry run complete.' : 'Deploy asset sync complete.');

function parseOptions(array $argv): array
{
    $options = [];

    foreach (array_slice($argv, 1) as $arg) {
        if (str_starts_with($arg, '--web-root=')) {
            $options['web-root'] = substr($arg, strlen('--web-root='));
            continue;
        }

        if ($arg === '--dry-run') {
            $options['dry-run'] = true;
            continue;
        }

        if ($arg === '--skip-cache-clear') {
            $options['skip-cache-clear'] = true;
            continue;
        }

        if ($arg === '--help' || $arg === '-h') {
            writeln("Usage: php scripts/sync-public-assets.php [--web-root=/path/to/public_html] [--dry-run] [--skip-cache-clear]");
            exit(0);
        }

        fail("Unknown option: {$arg}");
    }

    return $options;
}

function resolveWebRoot(string $projectRoot, array $options): ?string
{
    if (! empty($options['web-root'])) {
        return (string) $options['web-root'];
    }

    $envWebRoot = getenv('EDONATE_WEB_ROOT');
    if (is_string($envWebRoot) && trim($envWebRoot) !== '') {
        return trim($envWebRoot);
    }

    $parent = dirname($projectRoot);
    $nestedPublicHtml = $parent . DIRECTORY_SEPARATOR . 'public_html';

    if (basename($parent) === 'public_html' && is_dir($nestedPublicHtml)) {
        return $parent;
    }

    if (basename($projectRoot) === 'public_html') {
        return $projectRoot;
    }

    $siblingPublicHtml = $parent . DIRECTORY_SEPARATOR . 'public_html';
    if (is_dir($siblingPublicHtml)) {
        return $siblingPublicHtml;
    }

    return null;
}

function resolveLaravelPublic(string $projectRoot, ?string $webRoot): string
{
    $defaultPublic = $projectRoot . DIRECTORY_SEPARATOR . 'public';
    if (is_dir($defaultPublic)) {
        return $defaultPublic;
    }

    $nestedPublic = dirname($projectRoot) . DIRECTORY_SEPARATOR . 'public_html';
    if ($webRoot !== null && normalizePath($nestedPublic) !== normalizePath($webRoot) && is_dir($nestedPublic)) {
        return $nestedPublic;
    }

    if ($webRoot !== null) {
        return $webRoot;
    }

    return $defaultPublic;
}

function resolveLegacyPublic(string $projectRoot, ?string $webRoot): ?string
{
    $nestedPublic = dirname($projectRoot) . DIRECTORY_SEPARATOR . 'public_html';

    if ($webRoot !== null && normalizePath($nestedPublic) !== normalizePath($webRoot) && is_dir($nestedPublic)) {
        return $nestedPublic;
    }

    if ($webRoot !== null && is_dir($webRoot)) {
        return $webRoot;
    }

    return null;
}

function resolvePublicAssetSource(
    string $primaryPublic,
    ?string $fallbackPublic,
    string $relativePath,
    bool $directory,
): ?string {
    $candidates = [$primaryPublic];

    if ($fallbackPublic !== null && $fallbackPublic !== $primaryPublic) {
        $candidates[] = $fallbackPublic;
    }

    foreach ($candidates as $candidatePublic) {
        $source = $candidatePublic . DIRECTORY_SEPARATOR . $relativePath;
        if ($directory ? is_dir($source) : is_file($source)) {
            return $source;
        }
    }

    return null;
}

function syncDirectory(string $source, string $destination, bool $dryRun): array
{
    $copied = 0;
    $unchanged = 0;

    writeln("Sync {$source} -> {$destination}");

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        $sourcePath = $item->getPathname();
        $relativePath = ltrim(substr($sourcePath, strlen($source)), DIRECTORY_SEPARATOR . '/\\');
        $destinationPath = $destination . DIRECTORY_SEPARATOR . $relativePath;

        if ($item->isDir()) {
            if (! is_dir($destinationPath)) {
                if ($dryRun) {
                    writeln("  mkdir {$destinationPath}");
                } else {
                    mkdir($destinationPath, 0755, true);
                }
            }
            continue;
        }

        if (! shouldCopyFile($sourcePath, $destinationPath)) {
            $unchanged++;
            continue;
        }

        $destinationDir = dirname($destinationPath);
        if (! is_dir($destinationDir)) {
            if ($dryRun) {
                writeln("  mkdir {$destinationDir}");
            } else {
                mkdir($destinationDir, 0755, true);
            }
        }

        if ($dryRun) {
            writeln("  copy {$sourcePath} -> {$destinationPath}");
        } else {
            if (! copy($sourcePath, $destinationPath)) {
                fail("Failed to copy {$sourcePath} to {$destinationPath}");
            }
        }

        $copied++;
    }

    return [
        'copied' => $copied,
        'unchanged' => $unchanged,
    ];
}

function shouldCopyFile(string $sourcePath, string $destinationPath): bool
{
    if (! is_file($destinationPath)) {
        return true;
    }

    if (filesize($sourcePath) !== filesize($destinationPath)) {
        return true;
    }

    return hash_file('sha256', $sourcePath) !== hash_file('sha256', $destinationPath);
}

function clearCaches(bool $dryRun): void
{
    $commands = [
        ['artisan', 'optimize:clear'],
        ['artisan', 'view:clear'],
        ['artisan', 'route:clear'],
        ['artisan', 'config:clear'],
    ];

    foreach ($commands as $command) {
        $display = 'php ' . implode(' ', $command);

        if ($dryRun) {
            writeln("Would run: {$display}");
            continue;
        }

        writeln("Run: {$display}");
        $exitCode = runPhpCommand($command);
        if ($exitCode !== 0) {
            fail("Command failed with exit code {$exitCode}: {$display}");
        }
    }
}

function runPhpCommand(array $arguments): int
{
    $command = escapeshellarg(PHP_BINARY);
    foreach ($arguments as $argument) {
        $command .= ' ' . escapeshellarg($argument);
    }

    passthru($command, $exitCode);
    return (int) $exitCode;
}

function normalizePath(string $path): string
{
    $real = realpath($path);
    return $real !== false ? rtrim($real, DIRECTORY_SEPARATOR . '/\\') : rtrim($path, DIRECTORY_SEPARATOR . '/\\');
}

function writeln(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function fail(string $message): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}
