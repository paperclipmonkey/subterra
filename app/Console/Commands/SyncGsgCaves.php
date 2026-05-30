<?php

namespace App\Console\Commands;

class SyncGsgCaves extends SyncCaveRegistryBaseCommand
{
    protected $signature = 'sync:gsg-caves
                            {--dry-run : Parse the file without inserting data}
                            {--blocklist= : Comma-separated list of names to always skip}
                            {--min-length=10 : Minimum length in meters to import (0 = all)}';

    protected $description = 'Sync caves from the Scottish Cave and Mine Database (GSG) KML feed';

    protected function registryId(): string
    {
        return 'gsg';
    }

    protected function baseUrl(): string
    {
        return 'https://registry.gsg.org.uk/sr';
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
        return 'Scotland';
    }

    protected function slugPrefix(): string
    {
        return 'scotland_';
    }

    protected function blocklistFilename(): string
    {
        return 'gsg_blocklist.txt';
    }

    protected function whitelistFilename(): string
    {
        return 'gsg_whitelist.txt';
    }

    protected function registryLinkLabel(): string
    {
        return 'GSG Registry';
    }

    /**
     * The GSG registry uses "Vert. Range:" for the vertical extent field
     * rather than the "Depth:" label used by MCRA and FoD.
     */
    protected function depthFieldLabel(): string
    {
        return 'Vert. Range:';
    }
}
