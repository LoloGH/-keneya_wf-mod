@props(['name'])

@error($name)
    <p class="k-error" role="alert">{{ $message }}</p>
@enderror
