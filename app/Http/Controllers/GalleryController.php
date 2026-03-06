<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

class GalleryController extends Controller
{
    public function show($dir = null)
    {
        $dir = $dir ? trim((string) $dir, '/') : trim((string) config('app.gallery_root_dir', config('app.gallery_root_dir_default', '')), '/');

        $files = Storage::files($dir);
        return view('gallery', compact('files', 'dir'));
    }
}
