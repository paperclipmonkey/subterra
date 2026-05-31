<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CaveSystem;
use App\Models\CaveSystemAnnotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaveSystemAnnotationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create();
        $this->user = User::factory()->create();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_can_fetch_annotations_for_a_cave_system()
    {
        $this->actingAs($this->user);
        $caveSystem = CaveSystem::factory()->create();
        $annotation = CaveSystemAnnotation::factory()->create([
            'cave_system_id' => $caveSystem->id,
        ]);

        $response = $this->getJson("/api/cave_systems/{$caveSystem->id}/annotations");

        $response->assertOk()
            ->assertJsonPath('data.cave_system_id', $caveSystem->id)
            ->assertJsonPath('data.geojson.type', 'FeatureCollection');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_returns_null_when_no_annotations_exist()
    {
        $this->actingAs($this->user);
        $caveSystem = CaveSystem::factory()->create();

        $response = $this->getJson("/api/cave_systems/{$caveSystem->id}/annotations");

        $response->assertOk()
            ->assertJsonPath('data', null);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function admins_can_create_annotations()
    {
        $this->actingAs($this->admin);
        $caveSystem = CaveSystem::factory()->create();

        $geojson = [
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [-3.5, 51.8],
                    ],
                    'properties' => [
                        'annotation_type' => 'parking',
                        'description' => 'Main car park near the road',
                    ],
                ],
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [[-3.5, 51.8], [-3.51, 51.81], [-3.52, 51.82]],
                    ],
                    'properties' => [
                        'annotation_type' => 'walking_route',
                        'description' => 'Path from car park to entrance',
                    ],
                ],
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [-3.48, 51.79],
                    ],
                    'properties' => [
                        'annotation_type' => 'house',
                        'description' => 'Farm - ask permission before caving',
                    ],
                ],
            ],
        ];

        $response = $this->postJson("/api/cave_systems/{$caveSystem->id}/annotations", [
            'geojson' => $geojson,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.geojson.type', 'FeatureCollection')
            ->assertJsonPath('data.geojson.features.0.properties.annotation_type', 'parking')
            ->assertJsonPath('data.geojson.features.1.properties.annotation_type', 'walking_route')
            ->assertJsonPath('data.geojson.features.2.properties.annotation_type', 'house');

        $this->assertDatabaseHas('cave_system_annotations', [
            'cave_system_id' => $caveSystem->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function admins_can_update_existing_annotations()
    {
        $this->actingAs($this->admin);
        $caveSystem = CaveSystem::factory()->create();
        CaveSystemAnnotation::factory()->create([
            'cave_system_id' => $caveSystem->id,
        ]);

        $updatedGeojson = [
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [-3.6, 51.9],
                    ],
                    'properties' => [
                        'annotation_type' => 'parking',
                        'description' => 'Updated parking spot',
                    ],
                ],
            ],
        ];

        $response = $this->postJson("/api/cave_systems/{$caveSystem->id}/annotations", [
            'geojson' => $updatedGeojson,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.geojson.features.0.properties.description', 'Updated parking spot');

        // Should still be only one annotation record
        $this->assertCount(1, CaveSystemAnnotation::where('cave_system_id', $caveSystem->id)->get());
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_admins_cannot_create_annotations()
    {
        $this->actingAs($this->user);
        $caveSystem = CaveSystem::factory()->create();

        $response = $this->postJson("/api/cave_systems/{$caveSystem->id}/annotations", [
            'geojson' => [
                'type' => 'FeatureCollection',
                'features' => [],
            ],
        ]);

        $response->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function non_admins_cannot_delete_annotations()
    {
        $this->actingAs($this->user);
        $caveSystem = CaveSystem::factory()->create();
        CaveSystemAnnotation::factory()->create([
            'cave_system_id' => $caveSystem->id,
        ]);

        $response = $this->deleteJson("/api/cave_systems/{$caveSystem->id}/annotations");

        $response->assertForbidden();
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function admins_can_delete_annotations()
    {
        $this->actingAs($this->admin);
        $caveSystem = CaveSystem::factory()->create();
        CaveSystemAnnotation::factory()->create([
            'cave_system_id' => $caveSystem->id,
        ]);

        $response = $this->deleteJson("/api/cave_systems/{$caveSystem->id}/annotations");

        $response->assertNoContent();
        $this->assertDatabaseMissing('cave_system_annotations', [
            'cave_system_id' => $caveSystem->id,
        ]);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_geojson_structure()
    {
        $this->actingAs($this->admin);
        $caveSystem = CaveSystem::factory()->create();

        // Missing FeatureCollection type
        $response = $this->postJson("/api/cave_systems/{$caveSystem->id}/annotations", [
            'geojson' => [
                'type' => 'InvalidType',
                'features' => [],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['geojson.type']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_feature_geometry_types()
    {
        $this->actingAs($this->admin);
        $caveSystem = CaveSystem::factory()->create();

        $response = $this->postJson("/api/cave_systems/{$caveSystem->id}/annotations", [
            'geojson' => [
                'type' => 'FeatureCollection',
                'features' => [
                    [
                        'type' => 'Feature',
                        'geometry' => [
                            'type' => 'Polygon',
                            'coordinates' => [[[0, 0], [1, 1], [1, 0], [0, 0]]],
                        ],
                        'properties' => [],
                    ],
                ],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['geojson.features.0.geometry.type']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function it_validates_annotation_type()
    {
        $this->actingAs($this->admin);
        $caveSystem = CaveSystem::factory()->create();

        $response = $this->postJson("/api/cave_systems/{$caveSystem->id}/annotations", [
            'geojson' => [
                'type' => 'FeatureCollection',
                'features' => [
                    [
                        'type' => 'Feature',
                        'geometry' => [
                            'type' => 'Point',
                            'coordinates' => [-3.5, 51.8],
                        ],
                        'properties' => [
                            'annotation_type' => 'invalid_type',
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['geojson.features.0.properties.annotation_type']);
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function annotations_are_included_in_cave_system_show()
    {
        $this->actingAs($this->user);
        $caveSystem = CaveSystem::factory()->create();
        CaveSystemAnnotation::factory()->create([
            'cave_system_id' => $caveSystem->id,
        ]);

        $response = $this->getJson("/api/cave_systems/{$caveSystem->id}");

        $response->assertOk()
            ->assertJsonPath('data.annotation.geojson.type', 'FeatureCollection');
    }

    #[\PHPUnit\Framework\Attributes\Test]
    public function annotations_are_deleted_when_cave_system_is_deleted()
    {
        $caveSystem = CaveSystem::factory()->create();
        CaveSystemAnnotation::factory()->create([
            'cave_system_id' => $caveSystem->id,
        ]);

        $caveSystem->delete();

        $this->assertDatabaseMissing('cave_system_annotations', [
            'cave_system_id' => $caveSystem->id,
        ]);
    }
}
