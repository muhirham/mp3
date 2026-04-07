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

if (! function_exists('save_optimized_image')) {
    /**
     * Helper to resize and compress images using GD.
     * Max dimension: 1200px, Quality: 75%
     */
    function save_optimized_image($file, string $directory, string $disk = 'public'): string
    {
        $maxDim = 1200;
        $quality = 75;
        $path = $file->getRealPath();
        
        $info = @getimagesize($path);
        if (!$info) return $file->store($directory, $disk);
        
        [$width, $height, $type] = $info;

        // Auto-Rotate if JPEG
        $rotateDeg = 0;
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            if (!empty($exif['Orientation'])) {
                switch ($exif['Orientation']) {
                    case 3: $rotateDeg = 180; break;
                    case 6: $rotateDeg = -90; break;
                    case 8: $rotateDeg = 90; break;
                }
            }
        }

        // Scale
        $newWidth = $width;
        $newHeight = $height;
        if ($width > $maxDim || $height > $maxDim) {
            $ratio = $width / $height;
            if ($ratio > 1) {
                $newWidth = $maxDim;
                $newHeight = (int)($maxDim / $ratio);
            } else {
                $newHeight = $maxDim;
                $newWidth = (int)($maxDim * $ratio);
            }
        }

        $src = match($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG  => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            default        => null,
        };
        if (!$src) return $file->store($directory, $disk);

        if ($rotateDeg) {
            $src = imagerotate($src, $rotateDeg, 0);
            $width = imagesx($src);
            $height = imagesy($src);
            $newWidth = $width;
            $newHeight = $height;
            if ($width > $maxDim || $height > $maxDim) {
                $ratio = $width / $height;
                if ($ratio > 1) {
                    $newWidth = $maxDim;
                    $newHeight = (int)($maxDim / $ratio);
                } else {
                    $newHeight = $maxDim;
                    $newWidth = (int)($maxDim * $ratio);
                }
            }
        }

        $dst = imagecreatetruecolor($newWidth, $newHeight);
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        $fileName = time() . '_' . bin2hex(random_bytes(4)) . '.jpg';
        $finalPath = $directory . '/' . $fileName;

        ob_start();
        imagejpeg($dst, null, $quality);
        $imageData = ob_get_clean();

        Storage::disk($disk)->put($finalPath, $imageData);
        imagedestroy($src);
        imagedestroy($dst);

        return $finalPath;
    }
}
