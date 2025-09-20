<?php

namespace App\Console\Commands;

use App\Models\SlackFile;
use App\Services\FileStorageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class OptimizeFileStorage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'files:optimize
                           {--cleanup-orphaned : Remove orphaned files from storage}
                           {--regenerate-thumbnails : Regenerate missing thumbnails}
                           {--check-integrity : Check file integrity and fix issues}
                           {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Optimize file storage by cleaning up orphaned files, regenerating thumbnails, and checking integrity';

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
        $this->info('🚀 Starting file storage optimization...');
        
        $dryRun = $this->option('dry-run');
        $stats = [
            'orphaned_files_removed' => 0,
            'thumbnails_regenerated' => 0,
            'integrity_issues_fixed' => 0,
            'total_files_processed' => 0,
            'errors' => 0,
        ];

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        // Clean up orphaned files
        if ($this->option('cleanup-orphaned') || $this->confirm('Clean up orphaned files?', true)) {
            $stats['orphaned_files_removed'] = $this->cleanupOrphanedFiles($dryRun);
        }

        // Regenerate thumbnails
        if ($this->option('regenerate-thumbnails') || $this->confirm('Regenerate missing thumbnails?', true)) {
            $stats['thumbnails_regenerated'] = $this->regenerateThumbnails($dryRun);
        }

        // Check file integrity
        if ($this->option('check-integrity') || $this->confirm('Check file integrity?', true)) {
            $stats['integrity_issues_fixed'] = $this->checkFileIntegrity($dryRun);
        }

        // Display results
        $this->displayResults($stats);

        return 0;
    }

    /**
     * Clean up orphaned files from storage
     */
    private function cleanupOrphanedFiles(bool $dryRun = false): int
    {
        $this->info('🧹 Cleaning up orphaned files...');

        $removed = 0;
        $disks = ['r2', 'r2-public'];

        foreach ($disks as $disk) {
            $this->line("Checking disk: {$disk}");
            
            try {
                $files = Storage::disk($disk)->allFiles();
                $this->info("Found " . count($files) . " files on {$disk}");

                foreach ($files as $filePath) {
                    // Check if file exists in database
                    $existsInDb = SlackFile::where('url_private', $filePath)
                        ->orWhere('url_public', $filePath)
                        ->orWhere('thumbnail_path', 'like', '%' . $filePath . '%')
                        ->exists();

                    if (!$existsInDb) {
                        if ($dryRun) {
                            $this->line("Would remove orphaned file: {$filePath}");
                        } else {
                            if (Storage::disk($disk)->delete($filePath)) {
                                $this->line("Removed orphaned file: {$filePath}");
                                $removed++;
                            } else {
                                $this->error("Failed to remove: {$filePath}");
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->error("Error processing disk {$disk}: " . $e->getMessage());
                Log::error("File cleanup error on disk {$disk}", ['error' => $e->getMessage()]);
            }
        }

        return $removed;
    }

    /**
     * Regenerate missing thumbnails
     */
    private function regenerateThumbnails(bool $dryRun = false): int
    {
        $this->info('🖼️ Regenerating missing thumbnails...');

        $regenerated = 0;
        $imageFiles = SlackFile::where('file_type', 'image')
            ->where(function ($query) {
                $query->whereNull('thumbnail_path')
                      ->orWhere('thumbnail_path', '');
            })
            ->get();

        $this->info("Found " . $imageFiles->count() . " image files without thumbnails");

        foreach ($imageFiles as $file) {
            try {
                if ($dryRun) {
                    $this->line("Would regenerate thumbnail for: {$file->name}");
                } else {
                    // Check if original file exists
                    if (!Storage::disk($file->storage_disk ?? 'r2')->exists($file->url_private)) {
                        $this->warn("Original file not found: {$file->url_private}");
                        continue;
                    }

                    // Download file temporarily
                    $tempFile = tempnam(sys_get_temp_dir(), 'thumb_');
                    $fileContents = Storage::disk($file->storage_disk ?? 'r2')->get($file->url_private);
                    file_put_contents($tempFile, $fileContents);

                    // Create uploaded file instance for thumbnail generation
                    $uploadedFile = new \Illuminate\Http\UploadedFile(
                        $tempFile,
                        $file->original_name,
                        $file->mimetype,
                        null,
                        true
                    );

                    // Generate thumbnails using the service
                    $result = $this->fileStorageService->uploadFile(
                        $uploadedFile,
                        dirname($file->url_private),
                        $file->is_public
                    );

                    if ($result['success'] && $result['thumbnail_path']) {
                        $file->update(['thumbnail_path' => $result['thumbnail_path']]);
                        $this->line("Regenerated thumbnail for: {$file->name}");
                        $regenerated++;
                    }

                    // Cleanup temp file
                    unlink($tempFile);
                }
            } catch (\Exception $e) {
                $this->error("Failed to regenerate thumbnail for {$file->name}: " . $e->getMessage());
                Log::error("Thumbnail regeneration error", [
                    'file_id' => $file->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $regenerated;
    }

    /**
     * Check file integrity and fix issues
     */
    private function checkFileIntegrity(bool $dryRun = false): int
    {
        $this->info('🔍 Checking file integrity...');

        $fixed = 0;
        $files = SlackFile::whereNotNull('url_private')->get();

        $this->info("Checking " . $files->count() . " files");
        $progressBar = $this->output->createProgressBar($files->count());

        foreach ($files as $file) {
            $progressBar->advance();

            try {
                $disk = $file->storage_disk ?? 'r2';
                
                // Check if file exists in storage
                if (!Storage::disk($disk)->exists($file->url_private)) {
                    if ($dryRun) {
                        $this->newLine();
                        $this->warn("Missing file would be marked as broken: {$file->name}");
                    } else {
                        // Mark file as broken or remove record
                        $file->update(['metadata->status' => 'missing']);
                        $this->newLine();
                        $this->warn("Marked as missing: {$file->name}");
                        $fixed++;
                    }
                    continue;
                }

                // Check file size consistency
                $storageSize = Storage::disk($disk)->size($file->url_private);
                if ($storageSize !== $file->size) {
                    if ($dryRun) {
                        $this->newLine();
                        $this->warn("Size mismatch would be fixed for: {$file->name}");
                    } else {
                        $file->update(['size' => $storageSize]);
                        $this->newLine();
                        $this->line("Fixed size mismatch for: {$file->name}");
                        $fixed++;
                    }
                }

                // Check thumbnail integrity
                if ($file->thumbnail_path) {
                    $thumbnails = json_decode($file->thumbnail_path, true);
                    if (is_array($thumbnails)) {
                        $allThumbnailsExist = true;
                        foreach ($thumbnails as $thumbnailPath) {
                            if (!Storage::disk($disk)->exists($thumbnailPath)) {
                                $allThumbnailsExist = false;
                                break;
                            }
                        }

                        if (!$allThumbnailsExist) {
                            if ($dryRun) {
                                $this->newLine();
                                $this->warn("Missing thumbnails would be regenerated for: {$file->name}");
                            } else {
                                // Clear thumbnail path to trigger regeneration later
                                $file->update(['thumbnail_path' => null]);
                                $this->newLine();
                                $this->warn("Cleared broken thumbnails for: {$file->name}");
                                $fixed++;
                            }
                        }
                    }
                }

            } catch (\Exception $e) {
                $this->newLine();
                $this->error("Error checking {$file->name}: " . $e->getMessage());
                Log::error("File integrity check error", [
                    'file_id' => $file->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $progressBar->finish();
        $this->newLine();

        return $fixed;
    }

    /**
     * Display optimization results
     */
    private function displayResults(array $stats): void
    {
        $this->newLine();
        $this->info('📊 Optimization Results:');
        $this->table(
            ['Operation', 'Count'],
            [
                ['Orphaned files removed', $stats['orphaned_files_removed']],
                ['Thumbnails regenerated', $stats['thumbnails_regenerated']],
                ['Integrity issues fixed', $stats['integrity_issues_fixed']],
                ['Total errors', $stats['errors']],
            ]
        );

        // Storage usage statistics
        $this->newLine();
        $this->info('💾 Storage Statistics:');
        
        try {
            $totalFiles = SlackFile::count();
            $totalSize = SlackFile::sum('size');
            $imageFiles = SlackFile::where('file_type', 'image')->count();
            $documentsFiles = SlackFile::where('file_type', 'document')->count();

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Total files in database', number_format($totalFiles)],
                    ['Total storage size', $this->formatBytes($totalSize)],
                    ['Image files', number_format($imageFiles)],
                    ['Document files', number_format($documentsFiles)],
                    ['Average file size', $totalFiles > 0 ? $this->formatBytes($totalSize / $totalFiles) : '0 B'],
                ]
            );

            // Recent activity
            $recentUploads = SlackFile::where('created_at', '>=', now()->subWeek())->count();
            $this->info("📈 Recent activity: {$recentUploads} files uploaded in the last week");

        } catch (\Exception $e) {
            $this->error('Failed to collect storage statistics: ' . $e->getMessage());
        }

        $this->newLine();
        $this->info('✅ File storage optimization completed!');
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