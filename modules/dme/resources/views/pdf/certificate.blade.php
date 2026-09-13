@extends('dme::pdf._layout', ['title' => $title])

@section('content')
    <h2>{{ $title }}</h2>
    <div style="margin-top: 12px; line-height: 1.8;">
        {!! nl2br(e($content)) !!}
    </div>

    <div class="signature">
        <table width="100%">
            <tr>
                <td width="55%" class="small muted">
                    Fait à {{ $facility['address'] }}, le {{ $generatedAt->translatedFormat('d F Y') }}.
                </td>
                <td width="45%">
                    <div class="signature-line">Signature et cachet du médecin</div>
                </td>
            </tr>
        </table>
    </div>
@endsection
