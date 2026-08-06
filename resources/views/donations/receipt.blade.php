<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>{{ __('Receipt :number', ['number' => $donation->receipt_number]) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-body bg-white text-black">
<main class="mx-auto min-h-[297mm] max-w-[210mm] p-10 print:p-6">
    <header class="border-b-4 border-church-maroon-800 pb-6 text-center">
        <h1 class="text-3xl font-bold">{{ data_get($church, 'church.church_name', 'Triumphant World Ministry') }}</h1>
        <p class="text-lg">{{ data_get($church, 'church.tagline', 'The Land of Overflow') }}</p>
        <p class="mt-2 text-sm">{{ data_get($church, 'contact.physical_address') }} · {{ data_get($church, 'contact.primary_phone') }} · {{ data_get($church, 'contact.primary_email') }}</p>
    </header>
    <section class="mt-10">
        <div class="flex justify-between">
            <div><p class="text-sm uppercase text-slate-500">{{ __('Receipt number') }}</p><p class="font-mono text-lg font-semibold">{{ $donation->receipt_number }}</p></div>
            <div class="text-right"><p class="text-sm uppercase text-slate-500">{{ __('Issued') }}</p><p>{{ $donation->approved_at?->format('F j, Y') ?? $donation->updated_at->format('F j, Y') }}</p></div>
        </div>
        <dl class="mt-10 grid grid-cols-2 gap-x-8 gap-y-6 border-y py-8">
            <div><dt class="text-sm text-slate-500">{{ __('Donation reference') }}</dt><dd>{{ $donation->reference }}</dd></div>
            <div><dt class="text-sm text-slate-500">{{ __('Donor') }}</dt><dd>{{ $donation->donorLabel() }}</dd></div>
            <div><dt class="text-sm text-slate-500">{{ __('Category') }}</dt><dd>{{ $donation->category->name }}</dd></div>
            <div><dt class="text-sm text-slate-500">{{ __('Campaign') }}</dt><dd>{{ $donation->campaign?->name ?? '—' }}</dd></div>
            <div><dt class="text-sm text-slate-500">{{ __('Payment method') }}</dt><dd>{{ $donation->payment_method->label() }}</dd></div>
            <div><dt class="text-sm text-slate-500">{{ __('Donation date') }}</dt><dd>{{ $donation->donated_at->format('F j, Y') }}</dd></div>
            <div><dt class="text-sm text-slate-500">{{ __('Transaction reference') }}</dt><dd>{{ $donation->transaction_reference ?? '—' }}</dd></div>
        </dl>
        <div class="mt-10 rounded-xl border-2 p-6 text-center"><p class="text-sm uppercase text-slate-500">{{ __('Amount received') }}</p><p class="mt-2 text-4xl font-bold">{{ $donation->currency }} {{ number_format((float) $donation->amount, 2) }}</p></div>
        <p class="mt-10 text-center text-lg">{{ $message ?: __('Thank you for your generous contribution to the work of God through Triumphant World Ministry. May the Lord richly bless you.') }}</p>
        <p class="mt-12 text-sm">{{ __('Authorized by: :name', ['name' => $donation->approver?->name ?? $donation->recorder?->name ?? __('Finance Office')]) }}</p>
    </section>
    <div class="mt-10 text-center print:hidden"><button x-on:click="$window.print()" class="rounded-lg bg-black px-5 py-3 text-white">{{ __('Print receipt') }}</button></div>
</main>
</body>
</html>
