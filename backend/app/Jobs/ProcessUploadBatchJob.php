<?php

namespace App\Jobs;

use App\Models\Photo;
use App\Models\UploadJob;
use App\Models\UploadRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessUploadBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public string $uploadRequestId) {}

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(): void
    {
        $uploadRequest = UploadRequest::query()->with('jobs')->whereKey($this->uploadRequestId)->first();

        if ($uploadRequest === null) {
            Log::warning('Upload request missing while processing upload batch.', [
                'upload_request_id' => $this->uploadRequestId,
            ]);

            return;
        }

        $hadFailedJobs = false;

        foreach ($uploadRequest->jobs as $job) {
            $job->refresh();

            if ($job->status === 'completed') {
                continue;
            }

            $job->forceFill(['status' => 'processing', 'attempts' => $job->attempts + 1])->save();

            try {
                $sourcePath = $this->resolveSourcePath($uploadRequest, $job);
                if ($sourcePath === null || ! Storage::disk('s3')->exists($sourcePath)) {
                    DB::transaction(function () use ($job): void {
                        $job->forceFill(['status' => 'failed', 'error_message' => 'Uploaded source file missing.'])->save();
                    });

                    $hadFailedJobs = true;

                    continue;
                }

                $previewPath = $this->buildPreviewPath($uploadRequest, $job);
                $originalPath = $this->buildOriginalPath($uploadRequest, $job);

                if (! Storage::disk('s3')->copy($sourcePath, $originalPath)) {
                    throw new \RuntimeException('Failed to copy original file to S3.');
                }

                $sourceContents = Storage::disk('s3')->get($sourcePath);
                $previewContents = $this->generatePreviewContents($sourceContents, 'Photo SaaS');

                if (! Storage::disk('s3')->put($previewPath, $previewContents)) {
                    throw new \RuntimeException('Failed to store preview file in S3.');
                }

                DB::transaction(function () use ($uploadRequest, $job, $previewPath, $originalPath): void {
                    $photo = Photo::query()->firstOrCreate(
                        ['file_key' => $job->file_key],
                        [
                            'kindergarten_id' => $uploadRequest->kindergarten_id,
                            'album_id' => $uploadRequest->album_id,
                            'storage_path' => $originalPath,
                            'preview_path' => $previewPath,
                            'price' => $uploadRequest->price,
                            'preview_status' => 'ready',
                            'uploaded_by_staff_id' => $uploadRequest->requested_by_staff_id,
                        ],
                    );

                    $job->forceFill([
                        'photo_id' => $photo->id,
                        'status' => 'metadata_persisted',
                        'error_message' => null,
                    ])->save();

                    $job->forceFill(['status' => 'completed'])->save();
                });
            } catch (Throwable $exception) {
                $hadFailedJobs = true;

                DB::transaction(function () use ($job, $exception): void {
                    $job->forceFill([
                        'status' => 'failed',
                        'error_message' => $exception->getMessage(),
                    ])->save();
                });

                continue;
            }
        }

        DB::transaction(function () use ($uploadRequest, $hadFailedJobs): void {
            $uploadRequest->forceFill([
                'status' => $hadFailedJobs ? 'failed' : 'completed',
            ])->save();
        });
    }

    public function failed(Throwable $exception): void
    {
        $uploadRequest = UploadRequest::query()->whereKey($this->uploadRequestId)->first();

        if ($uploadRequest === null) {
            Log::warning('Upload request missing after upload batch job failure.', [
                'upload_request_id' => $this->uploadRequestId,
                'exception' => $exception::class,
            ]);

            return;
        }

        $uploadRequest->forceFill(['status' => 'failed'])->save();
    }

    private function resolveSourcePath(UploadRequest $uploadRequest, UploadJob $job): ?string
    {
        $directory = 'uploads/tmp/'.$uploadRequest->kindergarten_id.'/'.$uploadRequest->id;
        $files = Storage::disk('s3')->allFiles($directory);

        foreach ($files as $path) {
            if (str_starts_with(basename($path), $job->file_key.'.') || basename($path) === $job->file_key) {
                return $path;
            }
        }

        return null;
    }

    private function buildOriginalPath(UploadRequest $uploadRequest, UploadJob $job): string
    {
        $extension = pathinfo($job->original_filename, PATHINFO_EXTENSION) ?: 'jpg';

        return 'uploads/originals/'.$uploadRequest->kindergarten_id.'/'.$job->file_key.'.'.$extension;
    }

    private function buildPreviewPath(UploadRequest $uploadRequest, UploadJob $job): string
    {
        return 'uploads/previews/'.$uploadRequest->kindergarten_id.'/'.$job->file_key.'.jpg';
    }

    private function generatePreviewContents(string $sourceContents, string $watermarkText): string
    {
        $sourceImage = imagecreatefromstring($sourceContents);

        if ($sourceImage === false) {
            throw new \RuntimeException('Uploaded source file is not a supported image.');
        }

        $previewImage = null;

        try {
            $previewImage = $this->resizePreviewImage($sourceImage);
            $this->applyWatermark($previewImage, $watermarkText);

            ob_start();
            imagejpeg($previewImage, null, 85);
            $previewContents = ob_get_clean();

            if ($previewContents === false) {
                throw new \RuntimeException('Failed to encode preview image.');
            }

            return $previewContents;
        } finally {
            imagedestroy($sourceImage);

            if ($previewImage instanceof \GdImage) {
                imagedestroy($previewImage);
            }
        }
    }

    private function resizePreviewImage(\GdImage $sourceImage): \GdImage
    {
        $width = imagesx($sourceImage);
        $height = imagesy($sourceImage);
        $maxWidth = 1600;
        $maxHeight = 1600;
        $ratio = min(1, $maxWidth / $width, $maxHeight / $height);
        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));

        $previewImage = imagescale($sourceImage, $targetWidth, $targetHeight, IMG_BILINEAR_FIXED);

        if ($previewImage === false) {
            throw new \RuntimeException('Failed to resize preview image.');
        }

        return $previewImage;
    }

    private function applyWatermark(\GdImage $image, string $watermarkText): void
    {
        $font = 5;
        $padding = 12;
        $width = imagesx($image);
        $height = imagesy($image);
        $textWidth = imagefontwidth($font) * strlen($watermarkText);
        $textHeight = imagefontheight($font);
        $x = max($padding, $width - $textWidth - $padding);
        $y = max($padding, $height - $textHeight - $padding);

        $shadowColor = imagecolorallocate($image, 0, 0, 0);
        $textColor = imagecolorallocate($image, 255, 255, 255);

        if ($shadowColor === false || $textColor === false) {
            throw new \RuntimeException('Failed to allocate preview watermark colors.');
        }

        imagestring($image, $font, $x + 1, $y + 1, $watermarkText, $shadowColor);
        imagestring($image, $font, $x, $y, $watermarkText, $textColor);
    }
}
