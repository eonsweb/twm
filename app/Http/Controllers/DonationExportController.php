<?php

namespace App\Http\Controllers;

use App\Activity\ActivityLogger;
use App\Models\Donation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DonationExportController extends Controller
{
    public function __invoke(Request $request, ActivityLogger $logger): StreamedResponse
    {
        Gate::authorize('export', Donation::class);
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:200'], 'status' => ['nullable', 'string', 'max:40'], 'category' => ['nullable', 'integer'], 'method' => ['nullable', 'string', 'max:40'], 'source' => ['nullable', 'string', 'max:40'], 'from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $query = Donation::query()->with(['donor', 'category', 'campaign', 'recorder', 'approver'])->when($filters['search'] ?? null, fn (Builder $q, $v) => $q->search($v))->when($filters['status'] ?? null, fn (Builder $q, $v) => $q->where('payment_status', $v))->when($filters['category'] ?? null, fn (Builder $q, $v) => $q->where('donation_category_id', $v))->when($filters['method'] ?? null, fn (Builder $q, $v) => $q->where('payment_method', $v))->when($filters['source'] ?? null, fn (Builder $q, $v) => $q->where('source', $v))->when($filters['from'] ?? null, fn (Builder $q, $v) => $q->whereDate('donated_at', '>=', $v))->when($filters['to'] ?? null, fn (Builder $q, $v) => $q->whereDate('donated_at', '<=', $v));
        $logger->log('donations', 'donations.exported', 'Exported filtered donations.', null, $request->user(), ['filters' => array_keys(array_filter($filters))]);

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                throw new RuntimeException('Unable to open the CSV output stream.');
            }
            fputcsv($out, ['Reference', 'Receipt', 'Donor', 'Email', 'Phone', 'Anonymous', 'Category', 'Campaign', 'Amount', 'Currency', 'Method', 'Status', 'Provider reference', 'Donation date', 'Source', 'Recorded by', 'Approved by', 'Created']);
            $query->orderBy('id')->lazyById(500)->each(function (Donation $d) use ($out): void {
                $safe = static fn (mixed $v): string => preg_match('/^[=+\-@]/', (string) $v) ? "'".(string) $v : (string) $v;
                fputcsv($out, array_map($safe, [$d->reference, $d->receipt_number, $d->donorLabel(), $d->is_anonymous ? '' : $d->donor?->email, $d->is_anonymous ? '' : $d->donor?->phone, $d->is_anonymous ? 'Yes' : 'No', $d->category->name, $d->campaign?->name, $d->amount, $d->currency, $d->payment_method->label(), $d->payment_status->label(), $d->provider_reference, $d->donated_at->toIso8601String(), $d->source->label(), $d->recorder?->name, $d->approver?->name, $d->created_at->toIso8601String()]));
            });
            fclose($out);
        }, 'donations-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
