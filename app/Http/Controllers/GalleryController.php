<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;

class GalleryController extends Controller
{
    public function show($dir = 'entrance_photos')
    {
        $files = Storage::files($dir);
        return view('gallery', compact('files', 'dir'));
    }
}
