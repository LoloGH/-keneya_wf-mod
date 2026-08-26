<div>
    @if ($message)
        <div class="alert alert--{{ $level }}" role="{{ $level === 'error' ? 'alert' : 'status' }}">
            <span>{{ $message }}</span>
            <button type="button" class="alert__dismiss" wire:click="dismiss"
                    aria-label="Masquer ce message" title="Masquer ce message">&times;</button>
        </div>
    @endif
</div>
