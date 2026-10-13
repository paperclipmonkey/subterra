<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cave;
use App\Models\CaveSystem;
use App\Models\TripImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssistantLogbookImportTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/assistant/logbook-import';

    private function validCsvFile(?string $content = null): UploadedFile
    {
        $content ??= implode("\n", [
            'date,cave,description',
            '2024-06-01,Gaping Gill,Great trip through the main shaft',
            '2024-07-15,Lancaster Hole,Wet through but worth it',
        ]);

        return UploadedFile::fake()->createWithContent('logbook.csv', $content);
    }

    // =========================================================================
    // Authentication & authorisation
    // =========================================================================

    #[Test]
    public function unauthenticated_request_is_rejected(): void
    {
        $response = $this->postJson(self::ENDPOINT);

        $response->assertStatus(401);
    }

    #[Test]
    public function user_without_pip_access_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->postJson(self::ENDPOINT);

        $response->assertStatus(403);
    }

    #[Test]
    public function pip_user_who_has_not_agreed_is_rejected(): void
    {
        $user = User::factory()->pipAccess()->create();

        $response = $this->actingAs($user)
            ->post(self::ENDPOINT, [
                'file' => $this->validCsvFile(),
            ]);

        $response->assertStatus(403)
            ->assertJson(['code' => 'pip_agreement_required']);
    }

    // =========================================================================
    // File validation
    // =========================================================================

    #[Test]
    public function missing_file_returns_validation_error(): void
    {
        $user = User::factory()->admin()->pipAgreed()->create();

        $response = $this->actingAs($user)
            ->postJson(self::ENDPOINT, []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    #[Test]
    public function non_file_field_returns_validation_error(): void
    {
        $user = User::factory()->admin()->pipAgreed()->create();

        $response = $this->actingAs($user)
            ->postJson(self::ENDPOINT, ['file' => 'not-a-file']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    #[Test]
    public function disallowed_file_type_is_rejected(): void
    {
        $user = User::factory()->admin()->pipAgreed()->create();

        $file = UploadedFile::fake()->create('logbook.pdf', 100, 'application/pdf');

        $response = $this->actingAs($user)
            ->post(self::ENDPOINT, ['file' => $file]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    #[Test]
    public function file_exceeding_size_limit_is_rejected(): void
    {
        $user = User::factory()->admin()->pipAgreed()->create();

        // Create a file just over the 2 MB limit
        $file = UploadedFile::fake()->create('logbook.csv', 2049, 'text/csv');

        $response = $this->actingAs($user)
            ->post(self::ENDPOINT, ['file' => $file]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    // =========================================================================
    // Successful responses
    // =========================================================================

    #[Test]
    public function valid_csv_is_staged_as_an_import(): void
    {
        $user = User::factory()->admin()->pipAgreed()->create();
        $this->gapingGill();

        $response = $this->actingAs($user)
            ->post(self::ENDPOINT, ['file' => $this->validCsvFile()]);

        $response->assertStatus(200)
            ->assertJsonStructure(['import_id', 'filename', 'rows_added', 'rows_rejected', 'parse' => ['columns', 'date_format'], 'counts'])
            ->assertJson(['filename' => 'logbook.csv', 'rows_added' => 2]);

        $import = TripImport::findOrFail($response->json('import_id'));
        $this->assertSame($user->id, $import->user_id);
        $this->assertSame(2, $import->rows()->count());
        // Gaping Gill matched exactly; Lancaster Hole isn't in the database
        $this->assertSame(1, $response->json('counts.ready'));
        $this->assertSame(1, $response->json('counts.needs_review'));
    }

    #[Test]
    public function the_csv_content_is_not_echoed_back_into_the_chat(): void
    {
        $user = User::factory()->admin()->pipAgreed()->create();

        $response = $this->actingAs($user)
            ->post(self::ENDPOINT, ['file' => $this->validCsvFile()]);

        $response->assertStatus(200)->assertJsonMissingPath('csv_content');
    }

    #[Test]
    public function returned_filename_matches_uploaded_file_name(): void
    {
        $user = User::factory()->admin()->pipAgreed()->create();

        $file = UploadedFile::fake()->createWithContent('my-caving-log.csv', "date,cave\n2024-06-01,OFD");

        $response = $this->actingAs($user)
            ->post(self::ENDPOINT, ['file' => $file]);

        $response->assertStatus(200);
        $this->assertSame('my-caving-log.csv', $response->json('filename'));
    }

    #[Test]
    public function tsv_file_is_accepted(): void
    {
        $user = User::factory()->admin()->pipAgreed()->create();

        $content = "date\tcave\tdescription\n2024-06-01\tGaping Gill\tGreat trip";
        $file = UploadedFile::fake()->createWithContent('logbook.tsv', $content);

        $response = $this->actingAs($user)
            ->post(self::ENDPOINT, ['file' => $file]);

        $response->assertStatus(200)
            ->assertJson(['rows_added' => 1, 'parse' => ['delimiter' => 'tab']]);
    }

    #[Test]
    public function txt_file_is_accepted(): void
    {
        $user = User::factory()->admin()->pipAgreed()->create();

        $content = "date,cave\n2024-06-01,OFD";
        $file = UploadedFile::fake()->createWithContent('logbook.txt', $content);

        $response = $this->actingAs($user)
            ->post(self::ENDPOINT, ['file' => $file]);

        $response->assertStatus(200);
    }

    #[Test]
    public function pip_access_user_can_upload_logbook(): void
    {
        $user = User::factory()->pipAccess()->pipAgreed()->create();

        $response = $this->actingAs($user)
            ->post(self::ENDPOINT, ['file' => $this->validCsvFile()]);

        $response->assertStatus(200);
    }

    #[Test]
    public function file_without_a_recognisable_header_is_rejected_with_a_helpful_message(): void
    {
        $user = User::factory()->pipAccess()->pipAgreed()->create();

        $file = UploadedFile::fake()->createWithContent('log.csv', "foo,bar\n1,2\n3,4");

        $response = $this->actingAs($user)
            ->post(self::ENDPOINT, ['file' => $file]);

        $response->assertStatus(422)->assertJson(['code' => 'invalid_logbook']);
        $this->assertStringContainsString('header', $response->json('error'));
        $this->assertSame(0, TripImport::count());
    }

    #[Test]
    public function header_only_file_is_rejected(): void
    {
        $user = User::factory()->pipAccess()->pipAgreed()->create();

        $file = UploadedFile::fake()->createWithContent('log.csv', "date,cave\n,\n");

        $this->actingAs($user)
            ->post(self::ENDPOINT, ['file' => $file])
            ->assertStatus(422)
            ->assertJson(['code' => 'invalid_logbook']);
    }

    #[Test]
    public function a_second_upload_adds_to_the_open_import(): void
    {
        $user = User::factory()->pipAccess()->pipAgreed()->create();

        $first = $this->actingAs($user)->post(self::ENDPOINT, ['file' => $this->validCsvFile()]);
        $second = $this->actingAs($user)->post(self::ENDPOINT, [
            'file' => UploadedFile::fake()->createWithContent('more.csv', "date,cave\n2024-08-01,OFD"),
        ]);

        $this->assertSame($first->json('import_id'), $second->json('import_id'));
        $this->assertSame(3, $second->json('counts.total'));
        $this->assertSame([1, 2, 3], TripImport::first()->rows()->orderBy('row_number')->pluck('row_number')->all());
    }

    #[Test]
    public function import_status_endpoint_returns_the_open_import(): void
    {
        $user = User::factory()->pipAccess()->pipAgreed()->create();

        $this->actingAs($user)->getJson('/api/assistant/import')
            ->assertOk()
            ->assertJson(['data' => null]);

        $this->actingAs($user)->post(self::ENDPOINT, ['file' => $this->validCsvFile()]);

        $this->actingAs($user)->getJson('/api/assistant/import')
            ->assertOk()
            ->assertJsonPath('data.filename', 'logbook.csv')
            ->assertJsonPath('data.counts.total', 2);
    }

    #[Test]
    public function import_status_only_shows_the_users_own_import(): void
    {
        $owner = User::factory()->pipAccess()->pipAgreed()->create();
        $other = User::factory()->pipAccess()->pipAgreed()->create();

        $this->actingAs($owner)->post(self::ENDPOINT, ['file' => $this->validCsvFile()]);

        $this->actingAs($other)->getJson('/api/assistant/import')
            ->assertOk()
            ->assertJson(['data' => null]);
    }

    #[Test]
    public function import_status_requires_pip_access(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/assistant/import')->assertForbidden();
    }

    private function gapingGill(): void
    {
        $system = CaveSystem::factory()->create(['name' => 'Gaping Gill', 'slug' => 'gaping-gill']);
        Cave::factory()->create(['name' => 'Main Shaft', 'slug' => 'main-shaft', 'cave_system_id' => $system->id]);
    }
}
