<?php

namespace App\Http\Controllers;
use Inertia\Inertia;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function create()
    {
        return Inertia::render('Plants/Categories/CreateCategory');
    }
}
