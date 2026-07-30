<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\UploadedFile;

// Shared image-upload helper for the controllers that accept uploads.
// Uploads to the Supabase disk and fails loudly with a clean 503 JSON error
// instead of silently saving a broken path (which happened when the Supabase
// project was paused and store() quietly returned false -> saved as "0").
trait StoresImages
{
    protected function storeImageOrFail(UploadedFile $image, string $folder): string
    {
        $path = $image->store($folder, 'supabase');

        abort_if(
            $path === false,
            503,
            'Image upload failed — storage unavailable, please try again'
        );

        return $path;
    }
}
