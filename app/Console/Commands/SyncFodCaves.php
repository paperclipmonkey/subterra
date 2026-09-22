<?php

declare(strict_types=1);

namespace App\Console\Commands;

class SyncFodCaves extends SyncCaveRegistryBaseCommand
{
    protected $signature = 'sync:fod-caves
                            {--dry-run : Parse the file without inserting data}
                            {--blocklist= : Comma-separated list of names to always skip}
                            {--min-length=10 : Minimum length in meters to import (0 = all)}';

    protected $description = 'Sync caves from the Forest of Dean (FODCCAG) registry KML feed';

    protected function registryId(): string
    {
        return 'fod';
    }

    protected function baseUrl(): string
    {
        return 'http://www.fodccag.org.uk/registry';
    }

    protected function kmlUrls(): array
    {
        return [
            $this->baseUrl().'/googleEarth/placemarks.php?query=Caves100',
            $this->baseUrl().'/googleEarth/placemarks.php?query=Caves',
        ];
    }

    protected function defaultRegionTagName(): string
    {
        return 'Forest of Dean';
    }

    protected function slugPrefix(): string
    {
        return 'fod_';
    }

    protected function blocklistFilename(): string
    {
        return 'fod_blocklist.txt';
    }

    protected function whitelistFilename(): string
    {
        return 'fod_whitelist.txt';
    }

    protected function registryLinkLabel(): string
    {
        return 'FoD Registry';
    }

    /**
     * The FODCCAG KML endpoint (placemarks.php) has been returning HTTP 500
     * since at least 2026-09-21 while the rest of the registry stays healthy.
     * Site IDs are contiguous (1..269 at the time of writing) and every field
     * the KML carried is on the site-details page, so a sweep is an equivalent
     * source. The KML feed is still tried first and will take over again on
     * its own once upstream fix it.
     */
    protected function supportsSiteIdSweepFallback(): bool
    {
        return true;
    }
}
