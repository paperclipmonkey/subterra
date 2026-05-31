<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AssistantLogbookImportTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/assistant/logbook-import';

    private function validCsvFile(string $content = null): UploadedFile
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
    public function valid_csv_returns_content_and_filename(): void
    {
        $user = User::factory()->admin()->pipAgreed()->create();

        $response = $this->actingAs($user)
            ->post(self::ENDPOINT, ['file' => $this->validCsvFile()]);

        $response->assertStatus(200)
            ->assertJsonStructure(['csv_content', 'filename', 'size_bytes']);
    }

    #[Test]
    public function returned_csv_content_matches_uploaded_file(): void
    {
        $user = User::factory()->admin()->pipAgreed()->create();

        $content = "date,cave\n2024-06-01,Gaping Gill\n2024-07-15,OFD";
        $file = $this->validCsvFile($content);

        $response = $this->actingAs($user)
            ->post(self::ENDPOINT, ['file' => $file]);

        $response->assertStatus(200);
        $this->assertSame($content, $response->json('csv_content'));
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
            ->assertJsonStructure(['csv_content', 'filename', 'size_bytes']);
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
    public function size_bytes_reflects_actual_content_length(): void
    {
        $user = User::factory()->admin()->pipAgreed()->create();

        $content = "date,cave\n2024-06-01,Gaping Gill";
        $file = $this->validCsvFile($content);

        $response = $this->actingAs($user)
            ->post(self::ENDPOINT, ['file' => $file]);

        $response->assertStatus(200);
        $this->assertSame(strlen($content), $response->json('size_bytes'));
    }
}
