<?php

declare(strict_types=1);

/**
 * Vendors Bootstrap's official .map files to match whatever Bootstrap
 * version is currently vendored under users/css|js/ (Issue #1414).
 *
 * users/css/ and users/js/ are gitignored — provisioned by UserSpice's own
 * admin-triggered updater (users/admin.php?view=updates), independent of
 * this repo's git history — so the .map files can't simply be committed;
 * they'd silently go stale the next time UserSpice's Bootstrap build is
 * updated. This script re-derives them from whatever version is actually on
 * disk every time it runs (invoked by .githooks/post-merge locally on every
 * `git pull`/`git merge`, and by scripts/server-hooks/post-receive on every
 * test/prod deploy), so they can't drift out of sync for long.
 *
 * Before trusting a fetched .map, the corresponding official .min.css/.min.js
 * for that exact version is fetched and byte-hash-compared against the local
 * vendored file — this is skipped (not overwritten) if they don't match,
 * since a mismatch means the local file isn't the unmodified official build.
 *
 * Usage: php scripts/vendor-bootstrap-maps.php
 *
 * Never allowed to fail the caller: every failure path is caught and exits 0.
 */

function warn(string $message): void
{
    fwrite(STDERR, "vendor-bootstrap-maps: $message\n");
}

function fetchUrl(string $url): ?string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $body = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    if (PHP_VERSION_ID < 80500) {
        curl_close($ch);
    }

    if ($body === false) {
        warn("curl error fetching $url: $curlError");
        return null;
    }
    if ($httpCode !== 200) {
        warn("unexpected HTTP $httpCode fetching $url");
        return null;
    }

    return $body;
}

try {
    $projectRoot = dirname(__DIR__);

    $versionFile = $projectRoot . '/users/js/bootstrap.bundle.min.js';
    if (!file_exists($versionFile)) {
        warn("$versionFile not found, skipping.");
        exit(0);
    }

    $header = file_get_contents($versionFile, false, null, 0, 200);
    if ($header === false || !preg_match('/Bootstrap v(\d+\.\d+\.\d+)/', $header, $matches)) {
        warn("could not parse Bootstrap version from $versionFile, skipping.");
        exit(0);
    }
    $version = $matches[1];

    $assets = [
        [
            'minified' => 'users/css/bootstrap.min.css',
            'map' => 'users/css/bootstrap.min.css.map',
            'cdnPath' => 'dist/css/bootstrap.min.css',
        ],
        [
            'minified' => 'users/js/bootstrap.bundle.min.js',
            'map' => 'users/js/bootstrap.bundle.min.js.map',
            'cdnPath' => 'dist/js/bootstrap.bundle.min.js',
        ],
    ];

    foreach ($assets as $asset) {
        $localPath = $projectRoot . '/' . $asset['minified'];
        $mapPath = $projectRoot . '/' . $asset['map'];
        $cdnBase = "https://cdn.jsdelivr.net/npm/bootstrap@{$version}/{$asset['cdnPath']}";

        $localHash = @hash_file('sha256', $localPath);
        if ($localHash === false) {
            $err = error_get_last();
            warn("could not hash $localPath, skipping." . ($err !== null ? ' (' . $err['message'] . ')' : ''));
            continue;
        }

        // fetchUrl() already warns with the specific curl/HTTP failure reason on null.
        $remoteMinified = fetchUrl($cdnBase);
        if ($remoteMinified === null) {
            continue;
        }
        if (hash('sha256', $remoteMinified) !== $localHash) {
            warn(
                "{$asset['minified']} does not byte-match the official v$version build " .
                    '(customized locally?) — skipping its map to avoid vendoring a mismatched one.'
            );
            continue;
        }

        $remoteMap = fetchUrl($cdnBase . '.map');
        if ($remoteMap === null) {
            continue;
        }
        json_decode($remoteMap);
        if (json_last_error() !== JSON_ERROR_NONE) {
            warn("fetched map for {$asset['map']} is not valid JSON, skipping.");
            continue;
        }

        // Write via a temp file + rename (atomic on the same filesystem) so a killed
        // process or a concurrent deploy/pull can't leave a truncated .map on disk.
        $tmpPath = $mapPath . '.tmp';
        if (@file_put_contents($tmpPath, $remoteMap) === false || !@rename($tmpPath, $mapPath)) {
            $err = error_get_last();
            warn("could not write {$asset['map']}, skipping." . ($err !== null ? ' (' . $err['message'] . ')' : ''));
            @unlink($tmpPath);
            continue;
        }
        echo "vendor-bootstrap-maps: {$asset['map']} updated (Bootstrap v$version).\n";
    }
} catch (\Throwable $e) {
    fwrite(STDERR, sprintf(
        "WARNING: vendor-bootstrap-maps failed (non-fatal): %s: %s in %s:%d\n",
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));
}

exit(0);
