<?php

namespace App\Console\Commands;

use App\Models\Tag;

class SyncMcraCaves extends SyncCaveRegistryBaseCommand
{
    protected $signature = 'sync:mcra-caves
                            {--dry-run : Parse the file without inserting data}
                            {--blocklist= : Comma-separated list of names to always skip}
                            {--min-length=10 : Minimum length in meters to import (0 = all)}';

    protected $description = 'Sync caves from the Mendip Cave Registry and Archive (MCRA) KML feed';

    protected function registryId(): string
    {
        return 'mcra';
    }

    protected function baseUrl(): string
    {
        return 'https://www.mcra.org.uk/registry';
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
        return 'Mendip';
    }

    protected function slugPrefix(): string
    {
        return 'mendip_';
    }

    protected function blocklistFilename(): string
    {
        return 'mcra_blocklist.txt';
    }

    protected function whitelistFilename(): string
    {
        return 'mcra_whitelist.txt';
    }

    protected function registryLinkLabel(): string
    {
        return 'MCRA Registry';
    }

    /**
     * Portland is geographically distinct from the Mendip Hills even though
     * its caves are registered in the MCRA. If the location name contains
     * "Portland", assign the Portland region tag instead of Mendip.
     */
    protected function resolveRegionTags(string $locationName, Tag $defaultRegionTag): array
    {
        if (stripos($locationName, 'Portland') !== false) {
            $portlandTag = Tag::where('tag', 'Portland')->where('category', 'region')->first();
            if ($portlandTag) {
                return [$portlandTag];
            }
        }

        return [$defaultRegionTag];
    }
}
