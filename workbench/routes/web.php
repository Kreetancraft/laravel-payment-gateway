<?php

use Illuminate\Support\Facades\Route;
use Kreetancraft\PaymentGateway\Models\Payment;
use Workbench\App\Models\DemoInvoice;

Route::get('/', function () {
    return view('welcome', [
        'invoice' => DemoInvoice::first(),
        'payments' => Payment::latest()->take(10)->get(),
    ]);
})->name('workbench.home');

/*
 * Flux ships a compiled stylesheet but nothing serves it — in a real app you
 * import it into your own Tailwind build. The bench has no build step, so it
 * hands the file over directly and lets the Tailwind browser build generate the
 * utility classes the package's own markup uses.
 */
Route::get('/workbench/flux.css', function () {
    $path = base_path('vendor/livewire/flux/dist/flux.css');

    abort_unless(is_file($path), 404);

    return response()->file($path, ['Content-Type' => 'text/css']);
})->name('workbench.flux.css');
