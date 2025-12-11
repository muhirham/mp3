<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

if (! function_exists('replace_uploaded_file')) {
    /**
     * Ganti file lama dengan file baru:
     * - kalau $file null → balikin path lama (nggak ngapa-ngapain)
     * - kalau ada file baru → hapus file lama (kalau ada & exist) lalu simpan baru
     */
    function replace_uploaded_file(
        ?string $oldPath,
        ?UploadedFile $file,
        string $directory,
        string $disk = 'public'
    ): ?string {
        if (! $file || ! $file->isValid()) {
            return $oldPath;
        }

        if ($oldPath && Storage::disk($disk)->exists($oldPath)) {
            Storage::disk($disk)->delete($oldPath);
        }

        return $file->store($directory, $disk);
    }
}

if (! function_exists('delete_file_if_exists')) {
    /**
     * Hapus 1 file kalau path-nya ada & file-nya exist.
     */
    function delete_file_if_exists(?string $path, string $disk = 'public'): void
    {
        if (! $path) {
            return;
        }

        if (Storage::disk($disk)->exists($path)) {
            Storage::disk($disk)->delete($path);
        }
    }
}
