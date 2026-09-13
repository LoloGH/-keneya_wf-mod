{{-- Messages éphémères (§50) : succès, avertissement, erreur --}}
@foreach (['success' => 'success', 'warning' => 'warning', 'error' => 'danger'] as $key => $tone)
    @if (session($key))
        <div x-data="toast()" x-show="visible" x-cloak
             class="mb-4 flex items-start gap-3 rounded-lg border px-4 py-3 text-sm
                    @class([
                        'border-keneya-500 bg-keneya-50 text-keneya-700' => $tone === 'success',
                        'border-amber-500 bg-amber-50 text-amber-800' => $tone === 'warning',
                        'border-red-500 bg-red-50 text-red-700' => $tone === 'danger',
                    ])"
             role="status" aria-live="polite">
            <x-dme::icon :name="$tone === 'success' ? 'check' : 'alert'" class="mt-0.5 h-4.5 w-4.5 shrink-0"/>
            <p class="flex-1">{{ session($key) }}</p>
            <button type="button" @click="visible = false" class="text-current/60 hover:text-current"
                    aria-label="Fermer">
                <x-dme::icon name="close" class="h-4 w-4"/>
            </button>
        </div>
    @endif
@endforeach

@if ($errors->any() && ! $errors->has('email'))
    <div class="mb-4 rounded-lg border border-red-500 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
        <p class="font-medium">Le formulaire contient {{ $errors->count() }} erreur(s) :</p>
        <ul class="mt-1.5 list-disc space-y-0.5 pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
