<?php

declare(strict_types=1);

namespace App\Services\TripImport;

/** A logbook file that can't be read. The message is safe to show the user. */
class InvalidLogbookException extends \RuntimeException
{
}
