<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class CaisseController extends Controller
{
    public function __invoke(): View
    {
        return view('pages.caisse');
    }
}
