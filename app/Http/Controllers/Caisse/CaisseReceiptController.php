<?php

namespace App\Http\Controllers\Caisse;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\StaffType;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Recu d'encaissement imprimable, depuis /caisse.
 */
class CaisseReceiptController extends Controller
{
    public function __invoke(Request $request, Payment $payment): View
    {
        // Masquer le lien ne suffit pas : l'URL se tape (v3.2.2).
        abort_unless(
            $request->user()->hasCapability(StaffType::CAP_PRINT_TICKET),
            403,
            'Cette action ne releve pas de votre fonction.',
        );

        $payment->load(['patient', 'service', 'recordedBy']);

        return view('print.receipt', [
            'payment' => $payment,
            'hospitalName' => hospital_name(),
        ]);
    }
}
