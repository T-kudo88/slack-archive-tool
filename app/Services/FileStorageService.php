<?php

namespace App\Services;

use App\Models\SlackFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class FileStorageService
{
    private ImageManager $imageManager;

    public function __construct()
    {
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Upload file to Cloudflare R2 storage
     */

    public function uploadFile(UploadedFile $file, string $directory = 'files', bool $isPublic = false): array
    {
        try {
            $filename = $this->generateUniqueFilename($file);
            $filePath = $directory . '/' . $filename;
            $disk = $isPublic ? 'r2-public' : 'r2';

            // R2へアップロード
            Storage::disk($disk)->put($filePath, file_get_contents($file->getRealPath()));

            Log::info('R2 upload success', ['file_path' => $filePath]);

            // 🔹 メタデータを生成
            $metadata = $this->getFileMetadata($file, $filePath, $disk);

            return [
                'success' => true,
                'file_path' => $filePath,
                'url' => $isPublic ? Storage::disk($disk)->url($filePath) : null,
                'disk' => $disk,
                'metadata' => $metadata,   // ← 追加！
            ];
        } catch (\Exception $e) {
            Log::error('File upload failed', [
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function generateSignedUrl(string $filePath, int $expirationMinutes = 60, string $disk = 'r2'): ?string
    {
        try {
            if (!Storage::disk($disk)->exists($filePath)) {
                return null;
            }

            return Storage::disk($disk)->temporaryUrl(
                $filePath,
                now()->addMinutes($expirationMinutes)
            );
        } catch (\Exception $e) {
            Log::error('Failed to generate signed URL', [
                'error' => $e->getMessage(),
                'file_path' => $filePath,
                'disk' => $disk,
            ]);

            return null;
        }
    }

    public function downloadFile(string $filePath, string $disk = 'r2')
    {
        try {
            if (!Storage::disk($disk)->exists($filePath)) {
                return null;
            }

            return Storage::disk($disk)->download($filePath);
        } catch (\Exception $e) {
            Log::error('File download failed', [
                'error' => $e->getMessage(),
                'file_path' => $filePath,
                'disk' => $disk,
            ]);

            return null;
        }
    }

    public function deleteFile(string $filePath, string $disk = 'r2'): bool
    {
        try {
            if (!Storage::disk($disk)->exists($filePath)) {
                return false;
            }

            return Storage::disk($disk)->delete($filePath);
        } catch (\Exception $e) {
            Log::error('File deletion failed', [
                'error' => $e->getMessage(),
                'file_path' => $filePath,
                'disk' => $disk,
            ]);

            return false;
        }
    }

    private function generateThumbnail(UploadedFile $file, string $directory, string $disk): ?string
    {
        try {
            $image = $this->imageManager->read($file->getRealPath());

            $thumbnails = [
                'thumb' => ['width' => 150, 'height' => 150],
                'small' => ['width' => 300, 'height' => 300],
                'medium' => ['width' => 600, 'height' => 600],
            ];

            $thumbnailPaths = [];

            foreach ($thumbnails as $size => $dimensions) {
                $resized = $image->scale(
                    width: $dimensions['width'],
                    height: $dimensions['height']
                );

                $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $extension = strtolower($file->getClientOriginalExtension());
                $thumbnailFilename = Str::slug($originalName) . '_' . $size . '_' . time() . '.' . $extension;
                $thumbnailPath = $directory . '/thumbnails/' . $thumbnailFilename;

                $imageData = $resized->toJpeg(quality: 85);

                Storage::disk($disk)->put($thumbnailPath, $imageData);

                $thumbnailPaths[$size] = $thumbnailPath;
            }

            return json_encode($thumbnailPaths);
        } catch (\Exception $e) {
            Log::error('Thumbnail generation failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
            ]);

            return null;
        }
    }

    private function generateUniqueFilename(UploadedFile $file): string
    {
        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = strtolower($file->getClientOriginalExtension());

        return Str::slug($originalName) . '_' . time() . '_' . Str::random(8) . '.' . $extension;
    }

    private function getFileMetadata(UploadedFile $file, string $filePath, string $disk): array
    {
        $metadata = [
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'extension' => strtolower($file->getClientOriginalExtension()),
        ];

        if ($this->isImage($file)) {
            try {
                $imageSize = getimagesize($file->getRealPath());
                if ($imageSize) {
                    $metadata['width'] = $imageSize[0];
                    $metadata['height'] = $imageSize[1];
                    $metadata['aspect_ratio'] = round($imageSize[0] / $imageSize[1], 2);
                }
            } catch (\Exception $e) {
                // Ignore
            }
        }

        $metadata['hash'] = hash_file('sha256', $file->getRealPath());

        return $metadata;
    }

    private function isImage(UploadedFile $file): bool
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg'];
        $extension = strtolower($file->getClientOriginalExtension());

        return in_array($extension, $imageExtensions) && str_starts_with($file->getMimeType(), 'image/');
    }
}
