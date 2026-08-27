<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function index(): JsonResponse
    {
        $disk = $this->disk();

        $files = collect($disk->allFiles($this->backupName()))
            ->filter(fn (string $path) => str_ends_with($path, '.zip'))
            ->map(fn (string $path) => [
                'filename' => basename($path),
                'size' => $disk->size($path),
                'created_at' => date('Y-m-d H:i:s', $disk->lastModified($path)),
            ])
            ->sortByDesc('created_at')
            ->values();

        return response()->json($files);
    }

    public function store(): JsonResponse
    {
        Artisan::call('backup:run', ['--only-db' => true]);

        return response()->json(['message' => 'تم إنشاء النسخة الاحتياطية بنجاح']);
    }

    public function download(string $filename): StreamedResponse
    {
        $path = $this->backupName().'/'.$filename;

        abort_unless($this->disk()->exists($path), 404);

        return $this->disk()->download($path);
    }

    public function destroy(string $filename): JsonResponse
    {
        $path = $this->backupName().'/'.$filename;

        abort_unless($this->disk()->exists($path), 404);

        $this->disk()->delete($path);

        return response()->json(['message' => 'تم حذف النسخة الاحتياطية']);
    }

    private function disk(): FilesystemAdapter
    {
        return Storage::disk(config('backup.backup.destination.disks.0', 'local'));
    }

    private function backupName(): string
    {
        return config('backup.backup.name');
    }
}
