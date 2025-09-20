<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\SlackFile;
use App\Services\FileStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class FileStorageTest extends TestCase
{
    use RefreshDatabase;

    private FileStorageService $fileStorageService;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->fileStorageService = new FileStorageService();
        $this->user = User::factory()->create(['role' => 'user']);
        Auth::login($this->user);
        
        // Use fake storage for testing
        Storage::fake('r2');
        Storage::fake('r2-public');
    }

    public function test_file_upload_endpoint()
    {
        $file = UploadedFile::fake()->image('test.jpg', 600, 400);

        $response = $this->actingAs($this->user)
            ->postJson(route('files.store'), [
                'file' => $file,
                'is_public' => false,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'file' => [
                    'id',
                    'name',
                    'size',
                    'mimetype',
                    'file_type',
                    'user_id',
                ]
            ]);

        $this->assertDatabaseHas('slack_files', [
            'name' => 'test.jpg',
            'user_id' => $this->user->id,
            'is_public' => false,
        ]);
    }

    public function test_file_download_requires_authentication()
    {
        $file = SlackFile::factory()->create([
            'user_id' => $this->user->id,
            'is_public' => false,
        ]);

        // Test unauthenticated access
        $response = $this->get(route('files.download', $file));
        $response->assertRedirect(route('login'));

        // Test authenticated access
        $response = $this->actingAs($this->user)
            ->get(route('files.download', $file));
        
        // Should redirect to signed URL or return file stream
        $this->assertTrue(
            $response->isRedirect() || $response->getStatusCode() === 200
        );
    }

    public function test_public_file_access()
    {
        $file = SlackFile::factory()->create([
            'user_id' => $this->user->id,
            'is_public' => true,
            'url_public' => 'https://example.com/public/file.jpg',
        ]);

        // Public files should redirect to public URL
        $response = $this->actingAs($this->user)
            ->get(route('files.download', $file));
        
        $response->assertRedirect($file->url_public);
    }

    public function test_file_access_control()
    {
        $otherUser = User::factory()->create(['role' => 'user']);
        
        $privateFile = SlackFile::factory()->create([
            'user_id' => $otherUser->id,
            'is_public' => false,
        ]);

        // Should not be able to access other user's private files
        $response = $this->actingAs($this->user)
            ->get(route('files.show', $privateFile));
        
        $response->assertStatus(403);
    }

    public function test_admin_can_access_all_files()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherUser = User::factory()->create(['role' => 'user']);
        
        $privateFile = SlackFile::factory()->create([
            'user_id' => $otherUser->id,
            'is_public' => false,
        ]);

        // Admin should be able to access any file
        $response = $this->actingAs($admin)
            ->get(route('files.show', $privateFile));
        
        $response->assertStatus(200);
    }

    public function test_file_thumbnail_generation()
    {
        Storage::fake('r2');
        
        $file = UploadedFile::fake()->image('test.jpg', 600, 400);

        $response = $this->actingAs($this->user)
            ->postJson(route('files.store'), [
                'file' => $file,
                'is_public' => false,
            ]);

        $response->assertStatus(200);

        $fileRecord = SlackFile::latest()->first();
        
        // Check if thumbnail data was stored
        $this->assertNotNull($fileRecord->thumbnail_path);
        
        $thumbnails = json_decode($fileRecord->thumbnail_path, true);
        $this->assertIsArray($thumbnails);
        $this->assertArrayHasKey('thumb', $thumbnails);
        $this->assertArrayHasKey('small', $thumbnails);
        $this->assertArrayHasKey('medium', $thumbnails);
    }

    public function test_file_bulk_delete()
    {
        $files = SlackFile::factory()->count(3)->create([
            'user_id' => $this->user->id,
        ]);

        $fileIds = $files->pluck('id')->toArray();

        $response = $this->actingAs($this->user)
            ->postJson(route('files.bulk-delete'), [
                'file_ids' => $fileIds,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'deleted_count',
                'errors',
            ]);

        // Check that files were deleted
        foreach ($fileIds as $fileId) {
            $this->assertDatabaseMissing('slack_files', ['id' => $fileId]);
        }
    }

    public function test_file_statistics()
    {
        SlackFile::factory()->count(5)->create([
            'user_id' => $this->user->id,
            'size' => 1024 * 1024, // 1MB each
            'file_type' => 'image',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('files.statistics'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_files',
                'total_size',
                'file_types',
                'recent_uploads',
            ]);

        $data = $response->json();
        $this->assertEquals(5, $data['total_files']);
        $this->assertEquals(5 * 1024 * 1024, $data['total_size']);
        $this->assertArrayHasKey('image', $data['file_types']);
    }

    public function test_file_validation()
    {
        // Test file too large
        $largeFile = UploadedFile::fake()->create('large.txt', 51 * 1024); // 51MB

        $response = $this->actingAs($this->user)
            ->postJson(route('files.store'), [
                'file' => $largeFile,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        // Test invalid file type
        $invalidFile = UploadedFile::fake()->create('script.exe');

        $response = $this->actingAs($this->user)
            ->postJson(route('files.store'), [
                'file' => $invalidFile,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_signed_url_generation()
    {
        $file = SlackFile::factory()->create([
            'user_id' => $this->user->id,
            'is_public' => false,
            'url_private' => 'files/test.jpg',
        ]);

        // Mock the storage to avoid actual R2 connection
        Storage::shouldReceive('disk')
            ->with('r2')
            ->andReturnSelf();
        
        Storage::shouldReceive('exists')
            ->with('files/test.jpg')
            ->andReturn(true);
        
        Storage::shouldReceive('temporaryUrl')
            ->andReturn('https://signed-url.example.com/test.jpg');

        $signedUrl = $this->fileStorageService->generateSignedUrl(
            $file->url_private,
            60,
            'r2'
        );

        $this->assertNotNull($signedUrl);
        $this->assertStringContainsString('signed-url.example.com', $signedUrl);
    }

    public function test_file_search_and_filtering()
    {
        // Create files with different types and dates
        SlackFile::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'document.pdf',
            'file_type' => 'document',
            'created_at' => now()->subDays(5),
        ]);

        SlackFile::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'image.jpg',
            'file_type' => 'image',
            'created_at' => now()->subDay(),
        ]);

        // Test search by name
        $response = $this->actingAs($this->user)
            ->get(route('files.index', ['search' => 'document']));

        $response->assertStatus(200);
        $files = $response->viewData('files');
        $this->assertCount(1, $files->items());
        $this->assertEquals('document.pdf', $files->items()[0]->name);

        // Test filter by file type
        $response = $this->actingAs($this->user)
            ->get(route('files.index', ['file_type' => 'image']));

        $response->assertStatus(200);
        $files = $response->viewData('files');
        $this->assertCount(1, $files->items());
        $this->assertEquals('image.jpg', $files->items()[0]->name);
    }

    protected function tearDown(): void
    {
        // Clean up any real files if they were created during testing
        Storage::fake('r2');
        Storage::fake('r2-public');
        
        parent::tearDown();
    }
}