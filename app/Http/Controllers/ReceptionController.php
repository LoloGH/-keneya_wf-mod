<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class ReceptionController extends Controller
{
    public function __invoke(): View
    {
        return view('pages.reception');
    }
}
