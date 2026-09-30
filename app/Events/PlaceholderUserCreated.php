<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when one member creates an account record for another person — the
 * "add a participant who isn't on Subterra yet" flow.
 *
 * Distinct from UserCreated, which also covers genuine self-signups via magic
 * link and Google. Only this case carries an Article 14 duty, because only here
 * did the data come from someone other than the person it describes.
 */
class PlaceholderUserCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public User $user,
        public User $creator,
    ) {
    }
}
