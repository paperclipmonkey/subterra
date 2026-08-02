<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cave;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class GenericImportTest extends TestCase
{
    use RefreshDatabase;

    protected $csvPath;
    protected $tsvPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->csvPath = base_path('tests/fixtures/caves.csv');
        $this->tsvPath = base_path('tests/fixtures/caves.tsv');

        if (!File::exists(dirname($this->csvPath))) {
            File::makeDirectory(dirname($this->csvPath), 0755, true);
        }
    }

    protected function tearDown(): void
    {
        if (File::exists($this->csvPath)) {
            File::delete($this->csvPath);
        }
        if (File::exists($this->tsvPath)) {
            File::delete($this->tsvPath);
        }
        parent::tearDown();
    }

    public function test_it_fails_if_file_does_not_exist()
    {
        $this->artisan('import:caves', ['file' => 'non_existent.csv'])
            ->expectsOutput('File not found: non_existent.csv')
            ->assertExitCode(1);
    }

    public function test_it_imports_caves_from_csv()
    {
        $content = "name,system,length,depth,latitude,longitude,location_name,tags,description\n";
        $content .= "Cave A,System A,100,10,51.0,-3.0,Town A,\"Tag1,Tag2\",Desc A\n";
        File::put($this->csvPath, $content);

        $this->artisan('import:caves', ['file' => $this->csvPath])
            ->expectsOutput('Import completed successfully.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('caves', [
            'name' => 'Cave A',
            'location_name' => 'Town A',
            'description' => 'Desc A',
        ]);

        $this->assertDatabaseHas('cave_systems', [
            'name' => 'System A',
            'length' => 100,
            'vertical_range' => 10,
        ]);

        $cave = Cave::where('name', 'Cave A')->first();
        $this->assertTrue($cave->tags->contains('tag', 'Tag1'));
        $this->assertTrue($cave->tags->contains('tag', 'Tag2'));
    }

    public function test_it_imports_caves_from_tsv()
    {
        $content = "name\tsystem\tlength\tdepth\n";
        $content .= "Cave B\tSystem B\t200\t20\n";
        File::put($this->tsvPath, $content);

        $this->artisan('import:caves', ['file' => $this->tsvPath])
            ->expectsOutput('Import completed successfully.')
            ->assertExitCode(0);

        $this->assertDatabaseHas('caves', ['name' => 'Cave B']);
        $this->assertDatabaseHas('cave_systems', ['name' => 'System B']);
    }

    public function test_it_does_not_import_in_dry_run()
    {
        $content = "name,system\nCave C,System C";
        File::put($this->csvPath, $content);

        $this->artisan('import:caves', ['file' => $this->csvPath, '--dry-run' => true])
            ->expectsOutput('Processing: Cave C')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('caves', ['name' => 'Cave C']);
    }

    public function test_reimport_does_not_change_an_existing_caves_slug_or_system()
    {
        $existing = Cave::factory()->create([
            'name' => 'Cave A',
            'slug' => 'cave-a-yorkshire',
            'description' => 'Old description',
            'location_lat' => 51.0,
            'location_lng' => -3.0,
        ]);
        $originalSystemId = $existing->cave_system_id;

        $content = "name,system,length,depth,latitude,longitude,location_name,description\n";
        $content .= "Cave A,System A,100,10,51.0,-3.0,Town A,Desc A\n";
        File::put($this->csvPath, $content);

        $this->artisan('import:caves', ['file' => $this->csvPath])
            ->assertExitCode(0);

        $existing->refresh();
        // Other fields update, but the slug (public URL) and system are kept.
        $this->assertSame('Desc A', $existing->description);
        $this->assertSame('cave-a-yorkshire', $existing->slug);
        $this->assertSame($originalSystemId, $existing->cave_system_id);
        $this->assertDatabaseCount('caves', 1);
    }

    public function test_it_skips_rows_whose_coordinates_are_far_from_the_existing_same_named_cave()
    {
        $existing = Cave::factory()->create([
            'name' => 'Giants Cave',
            'description' => 'Mendip original',
            'location_lat' => 51.28,
            'location_lng' => -2.71,
        ]);

        // Same name but ~230km away in the Peak District — a different cave.
        $content = "name,latitude,longitude,description\n";
        $content .= "Giants Cave,53.25,-1.78,Peak District import\n";
        File::put($this->csvPath, $content);

        $this->artisan('import:caves', ['file' => $this->csvPath])
            ->expectsOutputToContain("Skipping 'Giants Cave'")
            ->assertExitCode(0);

        $existing->refresh();
        $this->assertSame('Mendip original', $existing->description);
        $this->assertDatabaseCount('caves', 1);
    }

    public function test_it_updates_an_existing_cave_when_coordinates_are_close()
    {
        $existing = Cave::factory()->create([
            'name' => 'Cave E',
            'description' => 'Old',
            'location_lat' => 51.280,
            'location_lng' => -2.710,
        ]);

        // Within the same-place threshold — same cave, refine in place.
        $content = "name,latitude,longitude,description\n";
        $content .= "Cave E,51.281,-2.712,Refreshed\n";
        File::put($this->csvPath, $content);

        $this->artisan('import:caves', ['file' => $this->csvPath])
            ->assertExitCode(0);

        $existing->refresh();
        $this->assertSame('Refreshed', $existing->description);
        $this->assertDatabaseCount('caves', 1);
    }

    public function test_it_creates_system_if_missing()
    {
        $content = "name,length\nCave D,50";
        File::put($this->csvPath, $content);

        $this->artisan('import:caves', ['file' => $this->csvPath])
            ->assertExitCode(0);

        $this->assertDatabaseHas('caves', ['name' => 'Cave D']);
        // Should create system with same name
        $this->assertDatabaseHas('cave_systems', [
            'name' => 'Cave D',
            'length' => 50,
        ]);
    }
}
