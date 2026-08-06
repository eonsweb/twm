<?php

use App\Actions\PrayerRequests\AddPrayerRequestUpdate;
use App\Actions\PrayerRequests\ChangePrayerRequestWorkflow;
use App\Actions\PrayerRequests\DeletePrayerRequest;
use App\Actions\PrayerRequests\PublishPrayerRequest;
use App\Models\PrayerRequest;
use App\Models\User;
use App\PrayerRequestPriority;
use App\PrayerRequestStatus;
use App\PrayerRequestUpdateType;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Prayer Request')] class extends Component
{
    public PrayerRequest $prayerRequest;
    public string $note = '';
    public string $noteType = 'note';
    public string $status = '';
    public string $priority = '';
    public int|string|null $assignedTo = null;
    public string $publicTitle = '';
    public string $publicExcerpt = '';
    public string $publicContent = '';

    public function mount(PrayerRequest $prayerRequest): void
    {
        Gate::authorize('view', $prayerRequest); abort_if($prayerRequest->trashed(), 404); $this->prayerRequest = $prayerRequest;
        $this->status = $prayerRequest->status->value; $this->priority = $prayerRequest->priority->value; $this->assignedTo = $prayerRequest->assigned_to;
        $this->publicTitle = $prayerRequest->public_title ?? ''; $this->publicExcerpt = $prayerRequest->public_excerpt ?? ''; $this->publicContent = $prayerRequest->public_content ?? ''; $this->refreshRequest();
    }
    public function addNote(AddPrayerRequestUpdate $action): void { $this->validate(['note' => ['required', 'string', 'max:20000'], 'noteType' => ['required', 'in:note,follow_up,contact_attempt,testimony']]); $action->handle(Auth::user(), $this->prayerRequest, PrayerRequestUpdateType::from($this->noteType), $this->note); $this->reset('note'); $this->refreshRequest(); Flux::toast(variant: 'success', text: __('Update added.')); }
    public function updateStatus(ChangePrayerRequestWorkflow $action): void { $this->prayerRequest = $action->changeStatus(Auth::user(), $this->prayerRequest, PrayerRequestStatus::from($this->status)); $this->refreshRequest(); }
    public function updatePriority(ChangePrayerRequestWorkflow $action): void { $this->prayerRequest = $action->changePriority(Auth::user(), $this->prayerRequest, PrayerRequestPriority::from($this->priority)); $this->refreshRequest(); }
    public function updateAssignment(ChangePrayerRequestWorkflow $action): void { $assignee = filled($this->assignedTo) ? User::findOrFail((int) $this->assignedTo) : null; $this->prayerRequest = $action->assign(Auth::user(), $this->prayerRequest, $assignee); $this->refreshRequest(); }
    public function publish(PublishPrayerRequest $action): void { $this->validate(['publicTitle' => ['required', 'string', 'max:255'], 'publicExcerpt' => ['nullable', 'string', 'max:1000'], 'publicContent' => ['required', 'string', 'max:20000']]); $this->prayerRequest = $action->publish(Auth::user(), $this->prayerRequest, ['public_title' => $this->publicTitle, 'public_excerpt' => $this->publicExcerpt ?: null, 'public_content' => $this->publicContent]); $this->refreshRequest(); Flux::toast(variant: 'success', text: __('Public-safe story published.')); }
    public function unpublish(PublishPrayerRequest $action): void { $this->prayerRequest = $action->unpublish(Auth::user(), $this->prayerRequest); $this->refreshRequest(); }
    public function delete(DeletePrayerRequest $action): void { $action->delete(Auth::user(), $this->prayerRequest); session()->flash('success', __('Prayer request moved to trash.')); $this->redirectRoute('prayer-requests.index', navigate: true); }
    private function refreshRequest(): void { $this->prayerRequest = $this->prayerRequest->refresh()->load(['assignee:id,name', 'reviewer:id,name', 'updates.user:id,name']); }
    public function assignees() { return User::query()->where('account_status', 'active')->permission('prayer-requests.update')->orderBy('name')->get(['id', 'name']); }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6"><flux:breadcrumbs><flux:breadcrumbs.item :href="route('prayer-requests.index')" wire:navigate>{{ __('Prayer requests') }}</flux:breadcrumbs.item><flux:breadcrumbs.item>{{ $prayerRequest->reference_number }}</flux:breadcrumbs.item></flux:breadcrumbs><x-admin.page-header :title="$prayerRequest->subject" :description="$prayerRequest->reference_number" :eyebrow="__('Confidential prayer request')"><x-slot:actions>@can('update', $prayerRequest)<flux:button :href="route('prayer-requests.edit', $prayerRequest)" icon="pencil-square" wire:navigate>{{ __('Edit') }}</flux:button>@endcan @can('delete', $prayerRequest)<flux:button variant="danger" wire:click="delete" wire:confirm="{{ __('Move this request to trash?') }}">{{ __('Delete') }}</flux:button>@endcan</x-slot:actions></x-admin.page-header>
<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]"><div class="space-y-6"><section class="rounded-xl border bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900"><div class="flex flex-wrap gap-2"><flux:badge :color="$prayerRequest->status->color()">{{ $prayerRequest->status->label() }}</flux:badge><flux:badge :color="$prayerRequest->priority->color()">{{ $prayerRequest->priority->label() }}</flux:badge><flux:badge>{{ $prayerRequest->privacy_level->label() }}</flux:badge></div><flux:heading size="lg" class="mt-6">{{ __('Original request') }}</flux:heading><div class="mt-4 whitespace-pre-line leading-7">{{ $prayerRequest->request }}</div></section>
@can('viewContactDetails', $prayerRequest)<section class="rounded-xl border bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900"><flux:heading>{{ __('Requester contact') }}</flux:heading><dl class="mt-4 grid gap-4 sm:grid-cols-2"><div><dt class="text-sm text-slate-500">{{ __('Name') }}</dt><dd>{{ $prayerRequest->requesterLabel() }}</dd></div><div><dt class="text-sm text-slate-500">{{ __('Email') }}</dt><dd>{{ $prayerRequest->email ?: '—' }}</dd></div><div><dt class="text-sm text-slate-500">{{ __('Phone') }}</dt><dd>{{ $prayerRequest->phone ?: '—' }}</dd></div><div><dt class="text-sm text-slate-500">{{ __('Location') }}</dt><dd>{{ collect([$prayerRequest->city, $prayerRequest->country])->filter()->join(', ') ?: '—' }}</dd></div></dl></section>@endcan
<section class="rounded-xl border bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900"><flux:heading>{{ __('Care timeline') }}</flux:heading><div class="mt-5 space-y-4">@forelse($prayerRequest->updates as $update)<article class="border-l-2 border-church-maroon-300 pl-4"><div class="flex justify-between gap-3"><p class="font-medium">{{ $update->type->label() }}</p><time class="text-xs text-slate-500">{{ $update->created_at?->format('M j, Y g:i A') }}</time></div><p class="mt-1 whitespace-pre-line text-sm">{{ $update->note }}</p><p class="mt-1 text-xs text-slate-500">{{ $update->user?->name ?? __('System') }}</p></article>@empty<p class="text-slate-500">{{ __('No updates yet.') }}</p>@endforelse</div>@can('addNote', $prayerRequest)<form wire:submit="addNote" class="mt-6 space-y-3"><flux:select wire:model="noteType" :label="__('Update type')">@foreach([PrayerRequestUpdateType::Note, PrayerRequestUpdateType::FollowUp, PrayerRequestUpdateType::ContactAttempt, PrayerRequestUpdateType::Testimony] as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select><flux:textarea wire:model="note" :label="__('Private update')" rows="4"/><div class="text-right"><flux:button type="submit" variant="primary">{{ __('Add update') }}</flux:button></div></form>@endcan</section>
@can('publish', $prayerRequest)<section class="space-y-4 rounded-xl border bg-white p-6 dark:border-zinc-800 dark:bg-zinc-900"><flux:heading>{{ __('Public-safe answered prayer story') }}</flux:heading><flux:callout variant="warning" icon="shield-check" heading="{{ __('Use only de-identified content') }}">{{ __('Do not copy names, contact details, exact locations, or other identifying information.') }}</flux:callout><flux:input wire:model="publicTitle" :label="__('Public title')"/><flux:input wire:model="publicExcerpt" :label="__('Public excerpt')"/><flux:textarea wire:model="publicContent" :label="__('Public-safe story')" rows="7"/><div class="flex justify-end gap-3">@if($prayerRequest->is_published)<flux:button wire:click="unpublish">{{ __('Unpublish') }}</flux:button>@endif<flux:button wire:click="publish" variant="primary">{{ __('Approve and publish') }}</flux:button></div></section>@endcan</div>
<aside class="space-y-6"><section class="space-y-4 rounded-xl border bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><flux:heading>{{ __('Workflow') }}</flux:heading>@can('update', $prayerRequest)<flux:select wire:model="status" wire:change="updateStatus" :label="__('Status')">@foreach(PrayerRequestStatus::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select><flux:select wire:model="priority" wire:change="updatePriority" :label="__('Priority')">@foreach(PrayerRequestPriority::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select>@endcan @can('assign', $prayerRequest)<flux:select wire:model="assignedTo" wire:change="updateAssignment" :label="__('Assigned to')"><flux:select.option value="">{{ __('Unassigned') }}</flux:select.option>@foreach($this->assignees() as $user)<flux:select.option :value="$user->id">{{ $user->name }}</flux:select.option>@endforeach</flux:select>@endcan</section><section class="rounded-xl border bg-white p-5 dark:border-zinc-800 dark:bg-zinc-900"><flux:heading>{{ __('Consent') }}</flux:heading><dl class="mt-4 space-y-3 text-sm"><div><dt class="text-slate-500">{{ __('Contact allowed') }}</dt><dd>{{ $prayerRequest->allow_contact ? __('Yes') : __('No') }}</dd></div><div><dt class="text-slate-500">{{ __('Publication consent') }}</dt><dd>{{ $prayerRequest->allow_publication ? __('Yes') : __('No') }}</dd></div><div><dt class="text-slate-500">{{ __('Published') }}</dt><dd>{{ $prayerRequest->is_published ? __('Yes') : __('No') }}</dd></div></dl></section>@can('viewSensitiveMetadata', $prayerRequest)<section class="rounded-xl border bg-white p-5 text-sm dark:border-zinc-800 dark:bg-zinc-900"><flux:heading>{{ __('Sensitive metadata') }}</flux:heading><p class="mt-3">IP: {{ $prayerRequest->ip_address ?: '—' }}</p><p class="mt-2 break-words">{{ $prayerRequest->user_agent ?: '—' }}</p></section>@endcan</aside></div></div>
