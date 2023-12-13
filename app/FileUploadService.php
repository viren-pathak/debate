<?php

namespace App;

use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class FileUploadService
{
    /**
     * Upload a file and return its path.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param string $directory
     * @return string|null
     */
    public static function upload(UploadedFile $file, string $directory)
    {
        $fileName = $file->getClientOriginalName();
        $filePath = $file->storeAs($directory, $fileName, 'public');

        return $filePath;
    }

    /**
     * Delete a file.
     *
     * @param string|null $filePath
     * @return bool
     */
    public static function delete(?string $filePath): bool
    {
        if ($filePath && Storage::disk('public')->exists($filePath)) {
            return Storage::disk('public')->delete($filePath);
        }

        return false;
    }
}
