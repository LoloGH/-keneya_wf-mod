<?php

declare(strict_types=1);

namespace Keneya\Dme\Http\Controllers\Web;

use Keneya\Dme\Http\Controllers\Controller;
use Keneya\Dme\Services\Patients\GlobalSearch;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Recherche globale depuis la barre supérieure (§34).
 */
class SearchController extends Controller
{
    public function __invoke(Request $request, GlobalSearch $search): View
    {
        $term = $request->string('q')->toString();

        return view('dme::search', [
            'term' => $term,
            'groups' => $search->search($term, $request->user()),
        ]);
    }
}
