<?php

namespace Database\Seeders;

use App\Models\Tag;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Tag::factory()->create([
            'tag' => 'Mendip',
            'type' => 'cave',
            'category' => 'region',
            'description' => "The Mendip hills in Somerset are home to some of the UK's most famous caves, including Wookey Hole, Swildon's Hole, and GB Cave.",
        ]);

        Tag::factory()->create([
            'tag' => 'South Wales',
            'type' => 'cave',
            'category' => 'region',
            'description' => "South Wales is home to the UK's longest cave system, Ogof Ffynnon Ddu, as well as the famous Dan-yr-Ogof showcave.",
        ]);

        Tag::factory()->create([
            'tag' => 'Yorkshire',
            'type' => 'cave',
            'category' => 'region',
            'description' => "Yorkshire is home to the UK's deepest cave, Gaping Gill, as well as the famous White Scar Cave.",
        ]);

        Tag::factory()->create([
            "tag"=> "North Wales",
            "type"=> "cave",
            "category"=> "region",
            "description"=> "North Wales has a long history of mining and some excellent bits of limestone to explore.",
        ]);

        Tag::factory()->create([
            "tag"=> "Assynt",
            "type"=> "cave",
            "category"=> "region",
            "description"=> "Scotland has some of the UK's most remote and challenging caves, including the famous Claonaite System.",
        ]);

        Tag::factory()->create([
            "tag"=> "Forest of Dean",
            "type"=> "cave",
            "category"=> "region",
            "description"=> "The Forest of Dean is home to some of the UK's most beautiful caves, including Otter Hole.",
        ]);

        Tag::factory()->create([
            "tag"=> "Devon",
            "type"=> "cave",
            "category"=> "region",
            "description"=> "Devon, despite being a smaller caving region, has some excellent caves, including Baker's Pit and Pridhamsleigh Cavern.",
        ]);

        Tag::factory()->create([
            "tag"=> "Portland",
            "type"=> "cave",
            "category"=> "region",
            "description"=> "Portland is home to some of the UK's most challenging sea caves, including a couple of through-trips.",
        ]);

        Tag::factory()->create([
            "tag"=> "Peak District",
            "type"=> "cave",
            "category"=> "region",
            "description"=> "The Peak District is home to some of the UK's most famous showcaves, including Blue John Cavern and Speedwell Cavern.",
        ]);

        // - - - - - - -- - - - - - Type
        Tag::factory()->create([
            "tag"=> "Cave",
            "type"=> "cave",
            "category"=> "type",
            "description"=> "A cave, naturally formed underground cavity, typically large enough for a human to enter.",
        ]);

        Tag::factory()->create([
            "tag"=> "Mine",
            "type"=> "cave",
            "category"=> "type",
            "description"=> "An underground mine or quarry.",
        ]);

        // - - - - - - -- - - - - - Access

        Tag::factory()->create([
            "tag"=> "Open",
            "type"=> "cave",
            "category"=> "access",
            "description"=> "A cave that is open to the public.",
        ]);

        Tag::factory()->create([
            "tag"=> "Gated",
            "type"=> "cave",
            "category"=> "access",
            "description"=> "A cave that is gated and requires permission to enter.",
        ]);

        Tag::factory()->create([
            "tag"=> "Leader",
            "type"=> "cave",
            "category"=> "access",
            "description"=> "A cave that requires a leader to enter.",
        ]);

        Tag::factory()->create([
            "tag"=> "Keycode",
            "type"=> "cave",
            "category"=> "access",
            "description"=> "A cave that requires a keycode to enter.",
        ]);

        // - - - - - - - - - Tackle required
        Tag::factory()->create([
            "tag"=> "SRT",
            "type"=> "cave",
            "category"=> "tackle",
            "description"=> "A cave that requires Single Rope Technique (SRT) to descend.",
        ]);

        Tag::factory()->create([
            "tag"=> "Ladder",
            "type"=> "cave",
            "category"=> "tackle",
            "description"=> "A cave that requires a ladder to descend.",
        ]);

        Tag::factory()->create([
            "tag"=> "No Tackle",
            "type"=> "cave",
            "category"=> "tackle",
            "description"=> "A cave that requires no tackle to descend.",
        ]);

        Tag::factory()->create([
            "tag"=> "Handline",
            "type"=> "cave",
            "category"=> "tackle",
            "description"=> "A cave that requires a handline to descend.",
        ]);
    }
}
