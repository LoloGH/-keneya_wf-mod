<?php

declare(strict_types=1);

namespace Keneya\Dme\Sms\Pipeline\Http;

use Keneya\Dme\Http\Controllers\Controller;
use Keneya\Dme\Models\Patient;
use Keneya\Dme\Models\SmsMessage;
use Keneya\Dme\Models\SmsTemplate;
use Keneya\Dme\Contracts\SmsDispatcherContract;
use Keneya\Dme\Sms\SmsContext;
use Keneya\Dme\Sms\Pipeline\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Service SMS (§35) : historique, statuts, envoi manuel et réessai.
 *
 * L'écran expose le fonctionnement du service transversal : passerelle
 * active, file d'attente, tentatives et erreurs. En configuration par
 * défaut la passerelle est « log » : aucun SMS réel n'est émis.
 */
class SmsPipelineController extends Controller
{
    public function index(Request $request, \Keneya\Dme\Sms\Pipeline\SmsGatewayManager $gateways): View
    {
        $this->authorize('viewAny', SmsMessage::class);

        $messages = SmsMessage::query()
            ->with(['patient:id,patient_number,first_name,last_name', 'template:id,name'])
            ->when($request->string('status')->toString(), fn ($q, $s) => $q->where('status', $s))
            ->when($request->string('q')->toString(), fn ($q, $term) => $q->where(fn ($inner) => $inner
                ->where('recipient', 'like', '%'.$term.'%')
                ->orWhere('reference', 'like', '%'.$term.'%')))
            ->orderByDesc('created_at')
            ->paginate(config('dme.pagination.default'))
            ->withQueryString();

        return view('dme::sms.index', [
            'messages' => $messages,
            'filters' => $request->only(['q', 'status']),
            'templates' => SmsTemplate::orderBy('name')->get(),
            'gateway' => $gateways->defaultName(),
            'simulated' => $gateways->isSimulated(),
            'stats' => [
                'delivered' => SmsMessage::where('status', 'delivered')->count(),
                'sent' => SmsMessage::where('status', 'sent')->count(),
                'in_transit' => SmsMessage::whereIn('status', ['pending', 'queued', 'accepted'])->count(),
                'failed' => SmsMessage::where('status', 'failed')->count(),
            ],
        ]);
    }

    public function store(Request $request, SmsDispatcherContract $sms): RedirectResponse
    {
        $this->authorize('create', SmsMessage::class);

        $data = $request->validate([
            'recipient' => ['required', 'string', 'max:30'],
            'body' => ['required', 'string', 'max:480'],
            'patient_id' => ['nullable', 'exists:dme_patients,id'],
        ], [], [
            'recipient' => 'destinataire',
            'body' => 'message',
        ]);

        try {
            $sms->dispatch(
                $data['recipient'],
                $data['body'],
                (new SmsContext(
                    patientId: isset($data['patient_id']) ? (int) $data['patient_id'] : null
                ))->toContextString(),
            );
        } catch (Throwable $exception) {
            return back()->withInput()->withErrors(['recipient' => $exception->getMessage()]);
        }

        return back()->with('success', 'Message placé dans la file d\'envoi.');
    }

    public function retry(SmsMessage $smsMessage, SmsService $sms): RedirectResponse
    {
        $this->authorize('retry', $smsMessage);

        try {
            $sms->retry($smsMessage);
        } catch (Throwable $exception) {
            return back()->withErrors(['sms' => $exception->getMessage()]);
        }

        return back()->with('success', 'Message replacé dans la file d\'envoi.');
    }
}
