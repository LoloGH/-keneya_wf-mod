<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class BoardController extends Controller
{
    public function __invoke(): View
    {
        return view('pages.board');
    }
}
