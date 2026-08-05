<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\SuggestedEdit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SuggestionApprovedMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public $suggestedEdit;

    public function __construct(SuggestedEdit $suggestedEdit)
    {
        $this->suggestedEdit = $suggestedEdit;
    }

    public function build()
    {
        $type = str_replace('_', ' ', strtolower(class_basename($this->suggestedEdit->suggestable_type)));

        // Name the thing that changed. Prefer the live record; a suggestion that
        // creates a brand-new item has no suggestable yet, so fall back to the
        // name it proposed. Either way the email should never just say "cave".
        $itemName = $this->suggestedEdit->suggestable?->name
            ?: ($this->suggestedEdit->suggested_data['name'] ?? null);

        $base = config('app.url');
        $id = $this->suggestedEdit->suggestable_id;
        $slug = $this->suggestedEdit->suggestable->slug ?? null;
        $identifier = $slug ?: $id;
        $path = '';

        switch ($this->suggestedEdit->suggestable_type) {
            case \App\Models\Cave::class:
                $path = "/caves/$identifier";
                break;
            case \App\Models\CaveSystem::class:
                $path = "/cave-systems/$identifier";
                break;
            case \App\Models\Route::class:
                $path = "/routes/$identifier";
                break;
            case \App\Models\Collection::class:
                $path = "/collections/$identifier";
                break;
        }

        $itemUrl = $path ? rtrim($base, '/').$path : null;

        return $this->subject($itemName
                ? "Your suggestion for {$itemName} was approved!"
                : 'Your suggestion was approved!')
            ->markdown('emails.suggestion_approved')
            ->with([
                'suggestedEdit' => $this->suggestedEdit,
                'type' => $type,
                'itemName' => $itemName,
                'itemUrl' => $itemUrl,
            ]);
    }
}
