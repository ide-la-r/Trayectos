@if (session('status'))
    <div class="mb-4 rounded-xl border border-credit-500/30 bg-credit-50 px-4 py-3 text-sm text-credit-700">
        {{ session('status') }}
    </div>
@endif

@if ($errors->any())
    <div class="mb-4 rounded-xl border border-debt-500/30 bg-debt-50 px-4 py-3 text-sm text-debt-700">
        <p class="font-semibold">Revisa estos puntos:</p>
        <ul class="mt-1 list-disc space-y-0.5 pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
