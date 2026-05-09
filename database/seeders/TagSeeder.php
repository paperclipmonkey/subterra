<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // - - - - - - -- - - - - - Curated
        Tag::updateOrCreate([
            'tag' => 'Curated',
            'type' => 'cave',
            'category' => 'curated',
        ], [
            'description' => 'A well-documented cave worth visiting. Caves without this tag are smaller or less notable systems.',
            'assignable' => true,
        ]);

        Tag::updateOrCreate([
            'tag' => 'Mendip',
            'type' => 'cave',
            'category' => 'region',
        ], [
            'description' => "The Mendip hills in Somerset are home to some of the UK's most famous caves, including Wookey Hole, Swildon's Hole, and GB Cave.",
        ]);

        Tag::updateOrCreate([
            'tag' => 'South Wales',
            'type' => 'cave',
            'category' => 'region',
        ], [
            'description' => "South Wales is home to the UK's longest cave system, Ogof Ffynnon Ddu, as well as the famous Dan-yr-Ogof showcave.",
        ]);

        Tag::updateOrCreate([
            'tag' => 'Yorkshire',
            'type' => 'cave',
            'category' => 'region',
        ], [
            'description' => "Yorkshire is home to the UK's deepest cave, Gaping Gill, as well as the famous White Scar Cave.",
        ]);

        Tag::updateOrCreate([
            'tag' => 'North Wales',
            'type' => 'cave',
            'category' => 'region',
        ], [
            'description' => 'North Wales has a long history of mining and some excellent bits of limestone to explore.',
        ]);

        Tag::updateOrCreate([
            'tag' => 'Assynt',
            'type' => 'cave',
            'category' => 'region',
        ], [
            'description' => "Scotland has some of the UK's most remote and challenging caves, including the famous Claonaite System.",
        ]);

        Tag::updateOrCreate([
            'tag' => 'Forest of Dean',
            'type' => 'cave',
            'category' => 'region',
        ], [
            'description' => "The Forest of Dean is home to some of the UK's most beautiful caves, including Otter Hole.",
        ]);

        Tag::updateOrCreate([
            'tag' => 'Devon',
            'type' => 'cave',
            'category' => 'region',
        ], [
            'description' => "Devon, despite being a smaller caving region, has some excellent caves, including Baker's Pit and Pridhamsleigh Cavern.",
        ]);

        Tag::updateOrCreate([
            'tag' => 'Portland',
            'type' => 'cave',
            'category' => 'region',
        ], [
            'description' => "Portland is home to some of the UK's most challenging sea caves, including a couple of through-trips.",
        ]);

        Tag::updateOrCreate([
            'tag' => 'Peak District',
            'type' => 'cave',
            'category' => 'region',
        ], [
            'description' => "The Peak District is home to some of the UK's most famous showcaves, including Blue John Cavern and Speedwell Cavern.",
        ]);

        // - - - - - - -- - - - - - Type
        Tag::updateOrCreate([
            'tag' => 'Cave',
            'type' => 'cave',
            'category' => 'type',
        ], [
            'description' => 'A cave, naturally formed underground cavity, typically large enough for a human to enter.',
        ]);

        Tag::updateOrCreate([
            'tag' => 'Mine',
            'type' => 'cave',
            'category' => 'type',
        ], [
            'description' => 'An underground mine or quarry.',
        ]);

        // - - - - - - -- - - - - - Access
        Tag::updateOrCreate([
            'tag' => 'Open',
            'type' => 'cave',
            'category' => 'access',
        ], [
            'description' => 'A cave that is open to the public.',
        ]);

        Tag::updateOrCreate([
            'tag' => 'Spanner',
            'type' => 'cave',
            'category' => 'access',
        ], [
            'description' => 'A cave that requires a spanner to enter.',
        ]);

        Tag::updateOrCreate([
            'tag' => 'Permit',
            'type' => 'cave',
            'category' => 'access',
        ], [
            'description' => 'A cave that requires a permit to enter.',
        ]);

        Tag::updateOrCreate([
            'tag' => 'Padlocked',
            'type' => 'cave',
            'category' => 'access',
        ], [
            'description' => 'A cave that is padlocked and requires permission to enter.',
        ]);

        Tag::updateOrCreate([
            'tag' => 'Warden',
            'type' => 'cave',
            'category' => 'access',
        ], [
            'description' => 'A cave that requires a warden/leader to enter.',
        ]);

        Tag::updateOrCreate([
            'tag' => 'Keycode',
            'type' => 'cave',
            'category' => 'access',
        ], [
            'description' => 'A cave that requires a keycode to enter.',
        ]);

        Tag::updateOrCreate([
            'tag' => 'Closed',
            'type' => 'cave',
            'category' => 'access',
        ], [
            'description' => 'Access to this cave is currently not possible.',
        ]);

        // - - - - - - - - - Tackle required
        Tag::updateOrCreate([
            'tag' => 'SRT',
            'type' => 'cave',
            'category' => 'tackle',
        ], [
            'description' => 'A cave that requires Single Rope Technique (SRT) to descend.',
        ]);

        Tag::updateOrCreate([
            'tag' => 'Ladder',
            'type' => 'cave',
            'category' => 'tackle',
        ], [
            'description' => 'A cave that requires a ladder to descend.',
        ]);

        Tag::updateOrCreate([
            'tag' => 'No Tackle',
            'type' => 'cave',
            'category' => 'tackle',
        ], [
            'description' => 'A cave that requires no tackle to descend.',
        ]);

        Tag::updateOrCreate([
            'tag' => 'Handline',
            'type' => 'cave',
            'category' => 'tackle',
        ], [
            'description' => 'A cave that requires a handline to descend.',
        ]);

        // - - - - - - - - - Trip style
        Tag::updateOrCreate([
            'tag' => 'Streamway',
            'type' => 'cave',
            'category' => 'style',
        ], [
            'description' => 'A cave with a significant streamway — flood-prone in wet weather.',
        ]);

        Tag::updateOrCreate([
            'tag' => 'Through Trip',
            'type' => 'cave',
            'category' => 'style',
        ], [
            'description' => 'A cave traversed in one direction (different entrance and exit).',
        ]);

        Tag::updateOrCreate([
            'tag' => 'Showcave',
            'type' => 'cave',
            'category' => 'style',
        ], [
            'description' => 'A commercial showcave with formed paths and lighting.',
        ]);

        // - - - - - - - - - Difficulty (a hint to the assistant; routes carry the formal grade)
        Tag::updateOrCreate([
            'tag' => 'Beginner',
            'type' => 'cave',
            'category' => 'difficulty',
        ], [
            'description' => 'Suitable for newcomers — short trip, walking-sized passages, no SRT or significant water.',
        ]);

        Tag::updateOrCreate([
            'tag' => 'Sporting',
            'type' => 'cave',
            'category' => 'difficulty',
        ], [
            'description' => 'A sporting trip — committing, physical, but well within the reach of a competent recreational caver.',
        ]);

        Tag::updateOrCreate([
            'tag' => 'Hard',
            'type' => 'cave',
            'category' => 'difficulty',
        ], [
            'description' => 'Hard — long, sustained, or technical. Expect SRT, wet sections, or tight passages.',
        ]);

        Tag::updateOrCreate([
            'tag' => 'Severe',
            'type' => 'cave',
            'category' => 'difficulty',
        ], [
            'description' => 'Severe — committing, expedition-grade. Only for experienced cavers, often requires support.',
        ]);

        Tag::updateOrCreate([
            'tag' => 'Not Done Yet',
            'type' => 'cave',
            'category' => 'previously done',
        ], [
            'description' => 'A cave you are yet to visit',
            'assignable' => false,
        ]);

        Tag::updateOrCreate([
            'tag' => 'Previously Done',
            'type' => 'cave',
            'category' => 'previously done',
        ], [
            'description' => "A cave you've previously visited",
            'assignable' => false,
        ]);
    }
}
