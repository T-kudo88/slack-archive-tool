<?php

namespace App\Http\Controllers;

use App\Http\Requests\FileUploadRequest;
use App\Models\SlackFile;
use App\Services\FileStorageService;
use Illuminate\Support\Facades\Storage;  // ✅ 正しい
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FileController extends Controller
{
    private FileStorageService $fileStorageService;

    public function __construct(FileStorageService $fileStorageService)
    {
        $this->fileStorageService = $fileStorageService;
    }

    /**
     * Display a listing of files
     */
    public function index(Request $request): Response
    {
        $query = SlackFile::with(['user', 'channel'])
            ->when(!Gate::allows('admin-access'), function ($query) {
                // Apply personal data restrictions
                return $query->where('user_id', Auth::id());
            })
            ->when($request->search, function ($query, $search) {
                return $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%");
                });
            })
            ->when($request->channel_id, function ($query, $channelId) {
                return $query->where('channel_id', $channelId);
            })
            ->when($request->file_type, function ($query, $fileType) {
                return $query->where('mimetype', 'like', "{$fileType}%");
            })
            ->when($request->date_from, function ($query, $dateFrom) {
                return $query->where('created_at', '>=', $dateFrom);
            })
            ->when($request->date_to, function ($query, $dateTo) {
                return $query->where('created_at', '<=', $dateTo);
            });

        $files = $query->orderBy('created_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Files/Index', [
            'files' => $files->through(function ($file) {
                return array_merge($file->toArray(), [
                    'local_file_url' => $file->is_public
                        ? Storage::disk('r2-public')->url($file->local_path)
                        : $this->fileStorageService->generateSignedUrl(
                            $file->local_path,
                            600, // 10分
                            'r2'
                        ),
                ]);
            }),
            'filters' => $request->only(['search', 'channel_id', 'file_type', 'date_from', 'date_to']),
        ]);
    }

    /**
     * Upload a new file
     */
    public function store(FileUploadRequest $request): JsonResponse
    {
        try {
            $file = $request->file('file');
            $isPublic = $request->boolean('is_public', false);
            $channelId = $request->input('channel_id');
            $messageId = $request->input('message_id');

            // Upload file to R2
            $uploadResult = $this->fileStorageService->uploadFile(
                $file,
                'slack-files',
                $isPublic
            );

            if (!$uploadResult['success']) {
                return response()->json([
                    'message' => 'Failed to upload file: ' . $uploadResult['error']
                ], 500);
            }

            // Create file record in database
            $slackFile = SlackFile::create([
                'slack_file_id' => 'upload_' . time() . '_' . Auth::id(),
                'name' => $uploadResult['metadata']['original_name'],
                'title' => pathinfo($uploadResult['metadata']['original_name'], PATHINFO_FILENAME),
                'mimetype' => $uploadResult['metadata']['mime_type'],
                'file_type' => $this->determineFileType($uploadResult['metadata']['mime_type']),
                'size' => $uploadResult['metadata']['size'],
                'url_private' => $uploadResult['file_path'],
                'user_id' => Auth::id(),
                'channel_id' => $channelId,
                'is_public' => $isPublic,
                'metadata' => $uploadResult['metadata'],
                'local_path' => $uploadResult['file_path'],
            ]);

            return response()->json([
                'message' => 'File uploaded successfully',
                'file' => $slackFile->load(['user', 'channel']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'File upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified file
     */
    public function show(SlackFile $file)
    {
        // 1. ローカル or R2 に保存済みの場合はそれを返す
        if ($file->hasLocalFile()) {
            return Storage::disk('r2')->response($file->local_path, $file->name, [
                'Content-Type' => $file->mimetype,
                'Content-Disposition' => 'inline; filename="' . $file->name . '"',
            ]);
        }

        // 2. Slack API から取得する fallback
        $url = $file->url_private_download ?? $file->url_private;
        if (!$url) {
            abort(404, 'ファイルURLが存在しません');
        }

        $token = config('services.slack.bot_token'); // ← SLACK_BOT_TOKEN を使う

        $response = \Illuminate\Support\Facades\Http::withToken($token)->get($url);

        if (!$response->successful()) {
            \Log::error('Slack file fetch failed', [
                'file_id' => $file->id,
                'status' => $response->status(),
                'url' => $url,
            ]);
            abort(404, 'ファイルを取得できませんでした');
        }

        return response($response->body(), 200)
            ->header('Content-Type', $file->mimetype)
            ->header('Content-Disposition', 'inline; filename="' . $file->name . '"');
    }

    /**
     * Download a file
     */
    public function download(SlackFile $file)
    {
        if (!Gate::allows('view-file', $file)) {
            abort(403, 'Access denied to this file');
        }

        // 1. Public + R2保存済み → 直リンク
        if ($file->is_public && $file->local_path) {
            return redirect(Storage::disk('r2-public')->url($file->local_path));
        }

        // 2. Private + R2保存済み → サーバーから返す
        if ($file->hasLocalFile()) {
            return Storage::disk('r2')->download($file->local_path, $file->name);
        }

        // 3. Slack API fallback
        $url = $file->url_private_download ?? $file->url_private;
        $token = config('services.slack.bot_token');

        $response = \Illuminate\Support\Facades\Http::withToken($token)->get($url);

        if (!$response->successful()) {
            abort(404, 'File not found');
        }

        return response($response->body(), 200)
            ->header('Content-Type', $file->mimetype)
            ->header('Content-Disposition', 'attachment; filename="' . $file->name . '"');
    }

    /**
     * Get thumbnail for a file
     */
    public function thumbnail(SlackFile $file, string $size = 'thumb'): JsonResponse
    {
        // Check access permissions
        if (!Gate::allows('view-file', $file)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        if (!$file->thumbnail_path) {
            return response()->json(['error' => 'No thumbnail available'], 404);
        }

        $thumbnails = json_decode($file->thumbnail_path, true) ?? [];

        if (!isset($thumbnails[$size])) {
            return response()->json(['error' => 'Thumbnail size not available'], 404);
        }

        $thumbnailPath = $thumbnails[$size];

        if ($file->is_public) {
            // For public files, return public URL if available
            $url = Storage::disk($file->storage_disk ?? 'r2-public')->url($thumbnailPath);
        } else {
            // For private files, generate signed URL
            $url = $this->fileStorageService->generateSignedUrl(
                $thumbnailPath,
                60,
                $file->storage_disk ?? 'r2'
            );
        }

        if (!$url) {
            return response()->json(['error' => 'Failed to generate thumbnail URL'], 500);
        }

        return response()->json(['url' => $url]);
    }

    /**
     * Delete a file
     */
    public function destroy(SlackFile $file): JsonResponse
    {
        // Check permissions
        if (!Gate::allows('delete-file', $file)) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        try {
            // Delete file from R2 storage
            $deleted = $this->fileStorageService->deleteFile(
                $file->url_private,
                $file->storage_disk ?? 'r2'
            );

            // Delete thumbnails
            if ($file->thumbnail_path) {
                $thumbnails = json_decode($file->thumbnail_path, true) ?? [];
                foreach ($thumbnails as $thumbnailPath) {
                    $this->fileStorageService->deleteFile(
                        $thumbnailPath,
                        $file->storage_disk ?? 'r2'
                    );
                }
            }

            // Delete database record
            $file->delete();

            return response()->json(['message' => 'File deleted successfully']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete file: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get file statistics
     */
    public function statistics(): JsonResponse
    {
        // ベースクエリ
        $baseQuery = SlackFile::query()
            ->when(!Gate::allows('admin-access'), function ($query) {
                return $query->where('user_id', Auth::id());
            });

        $stats = [
            'total_files' => (clone $baseQuery)->count(),
            'total_size'  => (clone $baseQuery)->sum('size'),

            // ファイルタイプごとの集計
            'file_types' => (clone $baseQuery)
                ->selectRaw('file_type, COUNT(*) as count, SUM(size) as total_size')
                ->groupBy('file_type')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->file_type => [
                        'count' => $item->count,
                        'size'  => $item->total_size,
                    ]];
                }),

            // 最近アップロード
            'recent_uploads' => (clone $baseQuery)
                ->orderBy('created_at', 'desc')
                ->take(10)
                ->with(['user', 'channel'])
                ->get(),
        ];

        return response()->json($stats);
    }

    /**
     * Bulk delete files
     */
    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'file_ids' => 'required|array',
            'file_ids.*' => 'exists:slack_files,id',
        ]);

        $fileIds = $request->input('file_ids');
        $deletedCount = 0;
        $errors = [];

        foreach ($fileIds as $fileId) {
            try {
                $file = SlackFile::findOrFail($fileId);

                // Check permissions
                if (!Gate::allows('delete-file', $file)) {
                    $errors[] = "Access denied for file: {$file->name}";
                    continue;
                }

                // Delete from storage
                $this->fileStorageService->deleteFile(
                    $file->url_private,
                    $file->storage_disk ?? 'r2'
                );

                // Delete thumbnails
                if ($file->thumbnail_path) {
                    $thumbnails = json_decode($file->thumbnail_path, true) ?? [];
                    foreach ($thumbnails as $thumbnailPath) {
                        $this->fileStorageService->deleteFile(
                            $thumbnailPath,
                            $file->storage_disk ?? 'r2'
                        );
                    }
                }

                // Delete from database
                $file->delete();
                $deletedCount++;
            } catch (\Exception $e) {
                $errors[] = "Failed to delete file ID {$fileId}: {$e->getMessage()}";
            }
        }

        return response()->json([
            'message' => "Deleted {$deletedCount} files successfully",
            'deleted_count' => $deletedCount,
            'errors' => $errors,
        ]);
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
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ])) {
            return 'document';
        } elseif (in_array($mimeType, [
            'application/zip',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
            'application/gzip',
        ])) {
            return 'archive';
        } else {
            return 'other';
        }
    }

    // public function uploadFile(UploadedFile $file, string $directory = 'files', bool $isPublic = false): array
    // {
    //     try {
    //         $filename = $this->generateUniqueFilename($file);
    //         $filePath = $directory . '/' . $filename;
    //         $disk = $isPublic ? 'r2-public' : 'r2';

    //         $result = Storage::disk($disk)->put($filePath, file_get_contents($file->getRealPath()));

    //         if (!$result) {
    //             return ['success' => false, 'error' => 'Failed to upload'];
    //         }

    //         // 🔹 ここでメタデータを作成
    //         $metadata = $this->getFileMetadata($file, $filePath, $disk);

    //         // 🔹 サムネイル生成（画像の場合のみ）
    //         $thumbnailPath = null;
    //         if ($this->isImage($file)) {
    //             $thumbnailPath = $this->generateThumbnail($file, $directory, $disk);
    //         }

    //         return [
    //             'success' => true,
    //             'file_path' => $filePath,
    //             'url' => $isPublic ? Storage::disk($disk)->url($filePath) : null,
    //             'disk' => $disk,
    //             'metadata' => $metadata,
    //             'thumbnail_path' => $thumbnailPath,
    //         ];
    //     } catch (\Exception $e) {
    //         Log::error('File upload failed (debug)', [
    //             'error' => $e->getMessage(),
    //             'exception' => get_class($e),
    //             'trace' => $e->getTraceAsString(),
    //         ]);
    //         return ['success' => false, 'error' => $e->getMessage()];
    //     }
    // }
}
