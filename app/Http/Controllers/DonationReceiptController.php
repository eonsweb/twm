<?php

namespace App\Http\Controllers;

use App\Models\Donation;
use App\Settings\SettingManager;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

class DonationReceiptController extends Controller
{
    public function __invoke(Donation $donation, SettingManager $settings): View
    {
        Gate::authorize('printReceipt', $donation);
        $donation->load(['donor', 'category', 'campaign', 'recorder']);

        return view('donations.receipt', ['donation' => $donation, 'church' => $settings->publicGroups(['church', 'contact', 'branding']), 'message' => $settings->get('donations', 'confirmation_message')]);
    }
}
