<?php

namespace App\Jobs;

use App\Models\SlackFile;
use App\Services\FileStorageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ProcessFileUpload implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300; // 5 minutes
    public int $tries = 3;

    private SlackFile $file;
    private array $options;

    /**
     * Create a new job instance.
     */
    public function __construct(SlackFile $file, array $options = [])
    {
        $this->file = $file;
        $this->options = $options;
    }

    /**
     * Execute the job.
     */
    public function handle(FileStorageService $fileStorageService): void
    {
        try {
            Log::info('Processing file upload job', [
                'file_id' => $this->file->id,
                'file_name' => $this->file->name,
                'options' => $this->options,
            ]);

            // Generate thumbnails if image and not already generated
            if ($this->shouldGenerateThumbnails()) {
                $this->generateThumbnails($fileStorageService);
            }

            // Optimize file if requested
            if ($this->options['optimize'] ?? false) {
                $this->optimizeFile($fileStorageService);
            }

            // Generate metadata if requested
            if ($this->options['analyze'] ?? false) {
                $this->analyzeFile($fileStorageService);
            }

            // Update processing status
            $this->file->update([
                'metadata->processing_status' => 'completed',
                'metadata->processed_at' => now()->toISOString(),
            ]);

            Log::info('File upload processing completed', [
                'file_id' => $this->file->id,
            ]);

        } catch (\Exception $e) {
            Log::error('File upload processing failed', [
                'file_id' => $this->file->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update error status
            $this->file->update([
                'metadata->processing_status' => 'failed',
                'metadata->error' => $e->getMessage(),
                'metadata->failed_at' => now()->toISOString(),
            ]);

            throw $e;
        }
    }

    /**
     * Check if thumbnails should be generated
     */
    private function shouldGenerateThumbnails(): bool
    {
        return $this->file->file_type === 'image' && 
               empty($this->file->thumbnail_path) &&
               !empty($this->file->url_private);
    }

    /**
     * Generate thumbnails for image files
     */
    private function generateThumbnails(FileStorageService $fileStorageService): void
    {
        $disk = $this->file->storage_disk ?? 'r2';
        
        if (!$fileStorageService->getFileInfo($this->file->url_private, $disk)) {
            throw new \Exception('Original file not found in storage');
        }

        // Download file temporarily for processing
        $stream = $fileStorageService->getFileStream($this->file->url_private, $disk);
        if (!$stream) {
            throw new \Exception('Could not get file stream');
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'thumbnail_');
        file_put_contents($tempFile, $stream);

        try {
            $imageManager = new ImageManager(new Driver());
            $image = $imageManager->read($tempFile);
            
            $thumbnailSizes = [
                'thumb' => ['width' => 150, 'height' => 150],
                'small' => ['width' => 300, 'height' => 300],
                'medium' => ['width' => 600, 'height' => 600],
                'large' => ['width' => 1200, 'height' => 1200],
            ];

            $thumbnailPaths = [];
            $baseDir = dirname($this->file->url_private) . '/thumbnails';

            foreach ($thumbnailSizes as $sizeName => $dimensions) {
                // Create resized image
                $resized = clone $image;
                $resized->scale(
                    width: $dimensions['width'],
                    height: $dimensions['height']
                );

                // Generate filename
                $filename = pathinfo($this->file->name, PATHINFO_FILENAME);
                $thumbnailFilename = "{$filename}_{$sizeName}_" . time() . '.jpg';
                $thumbnailPath = "{$baseDir}/{$thumbnailFilename}";

                // Convert to JPEG and upload
                $imageData = $resized->toJpeg(quality: 85);
                
                if ($fileStorageService->uploadFile($thumbnailPath, $imageData, $disk)) {
                    $thumbnailPaths[$sizeName] = $thumbnailPath;
                }
            }

            if (!empty($thumbnailPaths)) {
                $this->file->update([
                    'thumbnail_path' => json_encode($thumbnailPaths),
                ]);

                Log::info('Thumbnails generated successfully', [
                    'file_id' => $this->file->id,
                    'thumbnails' => array_keys($thumbnailPaths),
                ]);
            }

        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Optimize file (compress images, etc.)
     */
    private function optimizeFile(FileStorageService $fileStorageService): void
    {
        if ($this->file->file_type !== 'image') {
            return; // Only optimize images for now
        }

        $disk = $this->file->storage_disk ?? 'r2';
        $stream = $fileStorageService->getFileStream($this->file->url_private, $disk);
        
        if (!$stream) {
            throw new \Exception('Could not get file stream for optimization');
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'optimize_');
        file_put_contents($tempFile, $stream);

        try {
            $imageManager = new ImageManager(new Driver());
            $image = $imageManager->read($tempFile);
            
            // Get original dimensions
            $originalSize = $this->file->size;
            $width = $image->width();
            $height = $image->height();

            // Optimize based on file size and dimensions
            $quality = 85;
            $shouldResize = false;
            $maxDimension = 2048;

            // Reduce quality for large files
            if ($originalSize > 5 * 1024 * 1024) { // > 5MB
                $quality = 70;
            } elseif ($originalSize > 2 * 1024 * 1024) { // > 2MB
                $quality = 80;
            }

            // Resize if too large
            if ($width > $maxDimension || $height > $maxDimension) {
                $shouldResize = true;
                $image->scale(width: $maxDimension, height: $maxDimension);
            }

            // Convert to optimized JPEG
            $optimizedData = $image->toJpeg(quality: $quality);
            $optimizedSize = strlen($optimizedData);

            // Only use optimized version if it's significantly smaller
            if ($optimizedSize < $originalSize * 0.8) {
                // Create optimized filename
                $filename = pathinfo($this->file->name, PATHINFO_FILENAME);
                $optimizedPath = dirname($this->file->url_private) . "/optimized/{$filename}_optimized.jpg";
                
                if ($fileStorageService->uploadRaw($optimizedPath, $optimizedData, $disk)) {
                    $this->file->update([
                        'metadata->optimized_path' => $optimizedPath,
                        'metadata->optimized_size' => $optimizedSize,
                        'metadata->optimization_ratio' => round(($originalSize - $optimizedSize) / $originalSize * 100, 2),
                    ]);

                    Log::info('File optimized successfully', [
                        'file_id' => $this->file->id,
                        'original_size' => $originalSize,
                        'optimized_size' => $optimizedSize,
                        'savings_percent' => round(($originalSize - $optimizedSize) / $originalSize * 100, 2),
                    ]);
                }
            }

        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Analyze file and extract metadata
     */
    private function analyzeFile(FileStorageService $fileStorageService): void
    {
        $disk = $this->file->storage_disk ?? 'r2';
        $analysis = [];

        // Basic file analysis
        $fileInfo = $fileStorageService->getFileInfo($this->file->url_private, $disk);
        if ($fileInfo) {
            $analysis['storage_info'] = $fileInfo;
        }

        // Image-specific analysis
        if ($this->file->file_type === 'image') {
            $stream = $fileStorageService->getFileStream($this->file->url_private, $disk);
            if ($stream) {
                $tempFile = tempnam(sys_get_temp_dir(), 'analysis_');
                file_put_contents($tempFile, $stream);

                try {
                    // Get image dimensions and properties
                    $imageInfo = getimagesize($tempFile);
                    if ($imageInfo) {
                        $analysis['image_info'] = [
                            'width' => $imageInfo[0],
                            'height' => $imageInfo[1],
                            'type' => $imageInfo[2],
                            'bits' => $imageInfo['bits'] ?? null,
                            'channels' => $imageInfo['channels'] ?? null,
                            'aspect_ratio' => round($imageInfo[0] / $imageInfo[1], 3),
                        ];

                        // Detect if image has transparency
                        if ($imageInfo[2] === IMAGETYPE_PNG) {
                            $analysis['image_info']['has_transparency'] = $this->hasTransparency($tempFile);
                        }
                    }

                    // Extract EXIF data if available
                    if (function_exists('exif_read_data') && in_array($imageInfo[2], [IMAGETYPE_JPEG, IMAGETYPE_TIFF_II, IMAGETYPE_TIFF_MM])) {
                        $exifData = @exif_read_data($tempFile);
                        if ($exifData) {
                            // Clean and filter EXIF data
                            $cleanExif = [];
                            $allowedKeys = ['DateTime', 'Make', 'Model', 'Software', 'Orientation', 'XResolution', 'YResolution'];
                            
                            foreach ($allowedKeys as $key) {
                                if (isset($exifData[$key])) {
                                    $cleanExif[$key] = $exifData[$key];
                                }
                            }
                            
                            if (!empty($cleanExif)) {
                                $analysis['exif_data'] = $cleanExif;
                            }
                        }
                    }

                } finally {
                    if (file_exists($tempFile)) {
                        unlink($tempFile);
                    }
                }
            }
        }

        // Update file with analysis results
        if (!empty($analysis)) {
            $this->file->update([
                'metadata->analysis' => $analysis,
                'metadata->analyzed_at' => now()->toISOString(),
            ]);

            Log::info('File analysis completed', [
                'file_id' => $this->file->id,
                'analysis_keys' => array_keys($analysis),
            ]);
        }
    }

    /**
     * Check if PNG image has transparency
     */
    private function hasTransparency(string $filePath): bool
    {
        try {
            $image = imagecreatefrompng($filePath);
            if (!$image) {
                return false;
            }

            $width = imagesx($image);
            $height = imagesy($image);

            // Sample pixels to check for transparency
            $sampleSize = min(100, $width * $height);
            $step = max(1, floor(($width * $height) / $sampleSize));

            for ($i = 0; $i < $width * $height; $i += $step) {
                $x = $i % $width;
                $y = floor($i / $width);
                
                if ($y >= $height) break;

                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba & 0x7F000000) >> 24;
                
                if ($alpha > 0) {
                    imagedestroy($image);
                    return true;
                }
            }

            imagedestroy($image);
            return false;

        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('File processing job failed permanently', [
            'file_id' => $this->file->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        $this->file->update([
            'metadata->processing_status' => 'failed',
            'metadata->error' => $exception->getMessage(),
            'metadata->failed_at' => now()->toISOString(),
            'metadata->attempts' => $this->attempts(),
        ]);
    }
}