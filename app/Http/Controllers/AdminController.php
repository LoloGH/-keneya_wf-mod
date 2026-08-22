<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class AdminController extends Controller
{
    public function __invoke(): View
    {
        return view('pages.admin');
    }
}
