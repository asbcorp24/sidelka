<?php

namespace App\Http\Controllers;

use App\Models\NewsPost;

class NewsController extends Controller
{
    public function index()
    {
        $posts = NewsPost::where('is_published', true)->latest('published_at')->get();

        return view('news.index', compact('posts'));
    }
}
