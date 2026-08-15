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
use Illuminate\Support\Facades\Storage;

class ProcessUploadBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public string $uploadRequestId)
    {
    }

    public function handle(): void
    {
        $uploadRequest = UploadRequest::query()->with('jobs')->whereKey($this->uploadRequestId)->first();

        if ($uploadRequest === null) {
            return;
        }

        DB::transaction(function () use ($uploadRequest): void {
            $uploadRequest->update(['status' => 'processing']);

            foreach ($uploadRequest->jobs as $job) {
                $job->refresh();
                $job->forceFill(['status' => 'processing', 'attempts' => $job->attempts + 1])->save();

                $sourcePath = $this->resolveSourcePath($uploadRequest, $job);
                if ($sourcePath === null || ! Storage::disk('s3')->exists($sourcePath)) {
                    $job->forceFill(['status' => 'failed', 'error_message' => 'Uploaded source file missing.'])->save();
                    continue;
                }

                $job->forceFill(['status' => 'previewing'])->save();

                $previewExtension = pathinfo($job->original_filename, PATHINFO_EXTENSION) ?: 'jpg';
                $previewPath = 'uploads/previews/'.$uploadRequest->kindergarten_id.'/'.$job->file_key.'.'.$previewExtension;
                $originalPath = 'uploads/originals/'.$uploadRequest->kindergarten_id.'/'.$job->file_key.'.'.$previewExtension;

                Storage::disk('s3')->put($previewPath, Storage::disk('s3')->get($sourcePath));
                Storage::disk('s3')->put($originalPath, Storage::disk('s3')->get($sourcePath));

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
            }

            $failedJobsCount = $uploadRequest->jobs()->where('status', 'failed')->count();
            $uploadRequest->forceFill([
                'status' => $failedJobsCount > 0 ? 'failed' : 'completed',
            ])->save();
        });
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
}
