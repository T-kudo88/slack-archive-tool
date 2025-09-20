<?php

namespace App\Console\Commands;

use App\Models\SlackFile;
use App\Models\User;
use App\Services\FileStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class SyncSlackFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'slack:sync-files
                           {--user= : Specific user ID to sync files for}
                           {--limit=100 : Number of files to process per batch}
                           {--download : Download files to R2 storage}
                           {--thumbnails : Generate thumbnails for images}
                           {--force : Re-download existing files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Slack files and download them to R2 storage';

    private FileStorageService $fileStorageService;

    public function __construct(FileStorageService $fileStorageService)
    {
        parent::__construct();
        $this->fileStorageService = $fileStorageService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔄 Starting Slack files sync...');

        $userId = $this->option('user');
        $limit = (int) $this->option('limit');
        $downloadFiles = $this->option('download');
        $generateThumbnails = $this->option('thumbnails');
        $force = $this->option('force');

        $stats = [
            'processed' => 0,
            'downloaded' => 0,
            'thumbnails_generated' => 0,
            'errors' => 0,
        ];

        // Get users with Slack tokens
        $users = $userId ?
            User::where('id', $userId)->whereNotNull('access_token')->get() :
            User::whereNotNull('access_token')->get();

        if ($users->isEmpty()) {
            $this->error('No users with Slack access tokens found.');
            return 1;
        }

        $this->info("Found {$users->count()} users with Slack access");

        foreach ($users as $user) {
            $this->info("Processing files for user: {$user->display_name} ({$user->id})");

            try {
                $userStats = $this->syncUserFiles($user, $limit, $downloadFiles, $generateThumbnails, $force);

                $stats['processed'] += $userStats['processed'];
                $stats['downloaded'] += $userStats['downloaded'];
                $stats['thumbnails_generated'] += $userStats['thumbnails_generated'];
                $stats['errors'] += $userStats['errors'];
            } catch (\Exception $e) {
                $this->error("Error processing user {$user->id}: " . $e->getMessage());
                $stats['errors']++;
                Log::error('Slack files sync error for user', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->displayResults($stats);
        return 0;
    }

    /**
     * Sync files for a specific user
     */
    private function syncUserFiles(User $user, int $limit, bool $download, bool $thumbnails, bool $force): array
    {
        $stats = [
            'processed' => 0,
            'downloaded' => 0,
            'thumbnails_generated' => 0,
            'errors' => 0,
        ];

        $cursor = null;
        $hasMore = true;

        while ($hasMore && $stats['processed'] < $limit) {
            try {
                // Call Slack API to get files
                $response = Http::withToken($user->access_token)
                    ->get('https://slack.com/api/files.list', [
                        'user' => $user->slack_user_id,
                        'count' => min(100, $limit - $stats['processed']),
                        'cursor' => $cursor,
                    ]);

                if (!$response->successful()) {
                    throw new \Exception("Slack API request failed: " . $response->body());
                }

                $data = $response->json();

                if (!$data['ok']) {
                    throw new \Exception("Slack API error: " . ($data['error'] ?? 'Unknown error'));
                }

                $files = $data['files'] ?? [];
                $hasMore = $data['response_metadata']['next_cursor'] ?? false;
                $cursor = $hasMore ? $data['response_metadata']['next_cursor'] : null;

                foreach ($files as $slackFile) {
                    try {
                        $processed = $this->processSlackFile($slackFile, $user, $download, $thumbnails, $force);

                        if ($processed['downloaded']) {
                            $stats['downloaded']++;
                        }

                        if ($processed['thumbnail_generated']) {
                            $stats['thumbnails_generated']++;
                        }

                        $stats['processed']++;

                        if ($stats['processed'] % 10 === 0) {
                            $this->line("  Processed {$stats['processed']} files...");
                        }
                    } catch (\Exception $e) {
                        $this->error("  Error processing file {$slackFile['id']}: " . $e->getMessage());
                        $stats['errors']++;

                        Log::error('Error processing Slack file', [
                            'file_id' => $slackFile['id'],
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Rate limiting - pause between API calls
                sleep(1);
            } catch (\Exception $e) {
                $this->error("Error fetching files from Slack API: " . $e->getMessage());
                $stats['errors']++;
                break;
            }
        }

        return $stats;
    }

    /**
     * Process a single Slack file
     */
    private function processSlackFile(array $slackFile, User $user, bool $download, bool $thumbnails, bool $force): array
    {
        $result = [
            'downloaded' => false,
            'thumbnail_generated' => false,
        ];

        // Check if file already exists
        $existingFile = SlackFile::where('slack_file_id', $slackFile['id'])->first();

        if ($existingFile && !$force) {
            return $result; // Skip if already processed and not forcing
        }

        $fileData = [
            'slack_file_id' => $slackFile['id'],
            'name' => $slackFile['name'] ?? 'unknown',
            'title' => $slackFile['title'] ?? null,
            'mimetype' => $slackFile['mimetype'] ?? 'application/octet-stream',
            'file_type' => $this->determineFileType($slackFile['mimetype'] ?? 'application/octet-stream'),
            'size' => $slackFile['size'] ?? 0,
            'url_private' => $slackFile['url_private'] ?? null,
            'url_public' => ($slackFile['public_url_shared'] ?? false) ? $slackFile['url_public'] ?? null : null,
            'user_id' => $user->id,
            'is_public' => $slackFile['public_url_shared'] ?? false,
            'metadata' => json_encode($slackFile),
            'created_at' => isset($slackFile['timestamp']) ?
                \Carbon\Carbon::createFromTimestamp($slackFile['timestamp']) :
                now(),
        ];

        // Download file if requested and URL is available
        if ($download && !empty($slackFile['url_private_download'])) {
            try {
                $downloadResult = $this->downloadSlackFile($slackFile, $user);

                if ($downloadResult['success']) {
                    $fileData['url_private'] = $downloadResult['storage_path'];
                    $fileData['storage_disk'] = $downloadResult['disk'];
                    $fileData['thumbnail_path'] = $downloadResult['thumbnail_path'];
                    $result['downloaded'] = true;

                    if ($downloadResult['thumbnail_path']) {
                        $result['thumbnail_generated'] = true;
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to download Slack file', [
                    'file_id' => $slackFile['id'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Create or update database record
        if ($existingFile) {
            $existingFile->update($fileData);
        } else {
            SlackFile::create($fileData);
        }

        return $result;
    }

    /**
     * Download file from Slack to R2 storage
     */
    private function downloadSlackFile(array $slackFile, User $user): array
    {
        $downloadUrl = $slackFile['url_private_download'];

        // Download file from Slack
        $response = Http::withToken($user->access_token)
            ->timeout(60)
            ->get($downloadUrl);

        if (!$response->successful()) {
            throw new \Exception("Failed to download file: " . $response->status());
        }

        // Create temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'slack_file_');
        file_put_contents($tempFile, $response->body());

        try {
            // Create UploadedFile instance
            $uploadedFile = new UploadedFile(
                $tempFile,
                $slackFile['name'] ?? 'unknown',
                $slackFile['mimetype'] ?? 'application/octet-stream',
                null,
                true
            );

            // Upload to R2 using our service
            $uploadResult = $this->fileStorageService->uploadFile(
                $uploadedFile,
                'slack-files/' . date('Y/m'),
                $slackFile['public_url_shared'] ?? false
            );

            if (!$uploadResult['success']) {
                throw new \Exception('Failed to upload to R2: ' . $uploadResult['error']);
            }

            return [
                'success' => true,
                'storage_path' => $uploadResult['file_path'],
                'disk' => $uploadResult['disk'],
                'thumbnail_path' => $uploadResult['thumbnail_path'],
            ];
        } finally {
            // Clean up temporary file
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Determine file type from MIME type
     */
    private function determineFileType(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            return 'video';
        } elseif (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        } elseif (in_array($mimeType, [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])) {
            return 'document';
        } elseif (in_array($mimeType, [
            'application/zip',
            'application/x-rar-compressed',
        ])) {
            return 'archive';
        } else {
            return 'other';
        }
    }

    /**
     * Display sync results
     */
    private function displayResults(array $stats): void
    {
        $this->newLine();
        $this->info('📊 Sync Results:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Files processed', $stats['processed']],
                ['Files downloaded', $stats['downloaded']],
                ['Thumbnails generated', $stats['thumbnails_generated']],
                ['Errors', $stats['errors']],
            ]
        );

        // Storage statistics
        $totalFiles = SlackFile::count();
        $totalSize = SlackFile::sum('size');

        $this->newLine();
        $this->info('💾 Current Storage Statistics:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total files in system', number_format($totalFiles)],
                ['Total storage used', $this->formatBytes($totalSize)],
            ]
        );

        $this->newLine();
        $this->info('✅ Slack files sync completed!');
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $size, int $precision = 2): string
    {
        if ($size === 0) {
            return '0 B';
        }

        $base = log($size, 1024);
        $suffixes = ['B', 'KB', 'MB', 'GB', 'TB'];

        return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
    }
}
