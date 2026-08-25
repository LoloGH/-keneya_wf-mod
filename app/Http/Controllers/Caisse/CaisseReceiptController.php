<?php

namespace App\Http\Controllers\Caisse;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Contracts\View\View;

/**
 * Recu d'encaissement imprimable, depuis /caisse.
 */
class CaisseReceiptController extends Controller
{
    public function __invoke(Payment $payment): View
    {
        $payment->load(['patient', 'service', 'recordedBy']);

        return view('print.receipt', [
            'payment' => $payment,
            'hospitalName' => hospital_name(),
        ]);
    }
}
