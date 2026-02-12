<?php

namespace Database\Seeders;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\Collection;
use App\Models\Route as CavingRoute;
use App\Models\SuggestedEdit;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuggestedEditSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::factory()->create();
        $cave = Cave::first();
        $system = CaveSystem::first();
        $route = CavingRoute::first();
        $collection = Collection::first();

        if ($cave) {
            SuggestedEdit::create([
                'user_id' => $user->id,
                'suggestable_type' => 'cave',
                'suggestable_id' => $cave->id,
                'original_data' => $cave->toArray(),
                'suggested_data' => array_merge($cave->toArray(), ['description' => ($cave->description ?? '')."\n\nSuggested update: Added more details about the entrance."]),
                'status' => 'pending',
            ]);
        }

        if ($system) {
            SuggestedEdit::create([
                'user_id' => $user->id,
                'suggestable_type' => 'cave_system',
                'suggestable_id' => $system->id,
                'original_data' => $system->toArray(),
                'suggested_data' => array_merge($system->toArray(), ['name' => $system->name.' (Suggested Name Change)']),
                'status' => 'pending',
            ]);
        }

        // New Item Suggestion
        SuggestedEdit::create([
            'user_id' => $user->id,
            'suggestable_type' => 'cave',
            'suggestable_id' => null,
            'original_data' => null,
            'suggested_data' => [
                'name' => 'Newly Discovered Cave',
                'description' => 'A large new cavern found in the Mendips.',
                'location_lat' => 51.3,
                'location_lng' => -2.7,
            ],
            'status' => 'pending',
        ]);

        // Approved Suggestion
        if ($collection) {
            SuggestedEdit::create([
                'user_id' => $user->id,
                'suggestable_type' => 'collection',
                'suggestable_id' => $collection->id,
                'original_data' => $collection->toArray(),
                'suggested_data' => array_merge($collection->toArray(), ['description' => 'Updated collection description.']),
                'status' => 'approved',
                'admin_comment' => 'Great update, thanks!',
            ]);
        }
    }
}
