<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="rtrim(env('FRONTEND_URL'), '/')">
<img src="{{ rtrim(env('FRONTEND_URL'), '/') }}/logo.png" class="logo" alt="{{ config('app.name') }}" style="max-height: 40px;">
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
