<?php

use App\Actions\ContactSubmissions\ChangeContactSubmissionWorkflow;
use App\Actions\ContactSubmissions\DeleteContactSubmission;
use App\ContactSubmissionCategory;
use App\ContactSubmissionPriority;
use App\ContactSubmissionStatus;
use App\Models\ContactSubmission;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Contact Submissions')] class extends Component
{
    use WithPagination;
    #[Url] public string $search = '';
    #[Url] public string $status = '';
    #[Url] public string $category = '';
    #[Url] public string $priority = '';
    #[Url] public string $assigned = '';
    #[Url] public string $read = '';
    #[Url] public string $dateFrom = '';
    #[Url] public string $dateTo = '';
    public int $perPage = 20;
    /** @var list<int|string> */ public array $selected = [];
    public string $bulkAction = '';
    public string $bulkValue = '';

    public function mount(): void { Gate::authorize('viewAny', ContactSubmission::class); }
    public function updated(string $property): void { if (! in_array($property, ['selected', 'bulkAction', 'bulkValue'], true)) { $this->resetPage(); } }
    public function clearFilters(): void { $this->reset(['search', 'status', 'category', 'priority', 'assigned', 'read', 'dateFrom', 'dateTo']); $this->resetPage(); }

    #[Computed]
    public function submissions(): LengthAwarePaginator
    {
        return ContactSubmission::query()->withTrashed()->select(['id', 'reference_number', 'name', 'email', 'phone', 'subject', 'category', 'status', 'priority', 'assigned_to', 'read_at', 'created_at', 'deleted_at'])->with('assignedUser:id,name')
            ->when($this->search !== '', fn (Builder $query): Builder => $query->search($this->search))
            ->when($this->status !== '', fn (Builder $query): Builder => $this->status === 'deleted' ? $query->onlyTrashed() : $query->where('status', $this->status))
            ->when($this->category !== '', fn (Builder $query): Builder => $query->where('category', $this->category))
            ->when($this->priority !== '', fn (Builder $query): Builder => $query->where('priority', $this->priority))
            ->when($this->assigned === 'mine', fn (Builder $query): Builder => $query->where('assigned_to', Auth::id()))
            ->when($this->assigned === 'unassigned', fn (Builder $query): Builder => $query->whereNull('assigned_to'))
            ->when(ctype_digit($this->assigned), fn (Builder $query): Builder => $query->where('assigned_to', (int) $this->assigned))
            ->when($this->read === 'unread', fn (Builder $query): Builder => $query->whereNull('read_at'))
            ->when($this->read === 'read', fn (Builder $query): Builder => $query->whereNotNull('read_at'))
            ->when($this->dateFrom !== '', fn (Builder $query): Builder => $query->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $query): Builder => $query->whereDate('created_at', '<=', $this->dateTo))
            ->latest()->paginate($this->perPage);
    }

    #[Computed] public function stats(): array { return ['new' => ContactSubmission::query()->new()->count(), 'unread' => ContactSubmission::query()->unread()->count(), 'progress' => ContactSubmission::query()->where('status', ContactSubmissionStatus::InProgress)->count(), 'waiting' => ContactSubmission::query()->where('status', ContactSubmissionStatus::WaitingForVisitor)->count(), 'resolved' => ContactSubmission::query()->resolved()->count(), 'spam' => ContactSubmission::query()->spam()->count()]; }
    #[Computed] public function assignees() { return User::query()->where('account_status', 'active')->permission('contact-submissions.update')->orderBy('name')->get(['id', 'name']); }

    public function applyBulk(ChangeContactSubmissionWorkflow $workflow, DeleteContactSubmission $delete): void
    {
        $this->validate(['selected' => ['required', 'array', 'min:1', 'max:100'], 'selected.*' => ['integer', 'distinct'], 'bulkAction' => ['required', 'in:read,status,assign,spam,delete,restore']]);
        $completed = 0; $failed = 0; $actor = Auth::user();
        foreach (ContactSubmission::withTrashed()->whereKey($this->selected)->lazyById() as $submission) {
            try {
                match ($this->bulkAction) {
                    'read' => $workflow->markRead($actor, $submission),
                    'status' => $workflow->changeStatus($actor, $submission, ContactSubmissionStatus::from($this->bulkValue)),
                    'assign' => $workflow->assign($actor, $submission, filled($this->bulkValue) ? User::findOrFail((int) $this->bulkValue) : null),
                    'spam' => $workflow->changeStatus($actor, $submission, ContactSubmissionStatus::Spam),
                    'delete' => $delete->delete($actor, $submission),
                    'restore' => $delete->restore($actor, $submission),
                }; $completed++;
            } catch (Throwable $exception) { $failed++; Log::warning('A bulk contact submission action failed.', ['submission_id' => $submission->id, 'action' => $this->bulkAction, 'exception' => $exception::class]); }
        }
        $this->reset(['selected', 'bulkAction', 'bulkValue']); unset($this->submissions, $this->stats); Flux::toast(variant: $failed ? 'warning' : 'success', text: __('Completed :completed actions; :failed failed.', ['completed' => $completed, 'failed' => $failed]));
    }
};
?>

<div class="mx-auto w-full max-w-[100rem] space-y-6"><flux:breadcrumbs><flux:breadcrumbs.item :href="route('dashboard')" wire:navigate>{{ __('Dashboard') }}</flux:breadcrumbs.item><flux:breadcrumbs.item>{{ __('Contact submissions') }}</flux:breadcrumbs.item></flux:breadcrumbs><x-admin.page-header :title="__('Contact submissions')" :description="__('Manage public enquiries, ownership, replies, and resolution while keeping visitor data protected.')" :eyebrow="__('Engagement')"/>
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6"><x-admin.stat-card :label="__('New')" :value="$this->stats['new']" :caption="__('New enquiries')" icon="inbox"/><x-admin.stat-card :label="__('Unread')" :value="$this->stats['unread']" :caption="__('Not yet opened')" icon="envelope"/><x-admin.stat-card :label="__('In progress')" :value="$this->stats['progress']" :caption="__('Being handled')" icon="clock"/><x-admin.stat-card :label="__('Waiting')" :value="$this->stats['waiting']" :caption="__('Visitor response')" icon="chat-bubble-left-right"/><x-admin.stat-card :label="__('Resolved')" :value="$this->stats['resolved']" :caption="__('Completed')" icon="check-circle"/><x-admin.stat-card :label="__('Spam')" :value="$this->stats['spam']" :caption="__('Excluded from open')" icon="no-symbol"/></div>
<section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-admin-panel dark:border-zinc-800 dark:bg-zinc-900"><div class="grid gap-4 border-b p-5 md:grid-cols-2 xl:grid-cols-4 dark:border-zinc-800"><div class="md:col-span-2"><flux:input wire:model.live.debounce.350ms="search" :label="__('Search submissions')" icon="magnifying-glass"/></div><flux:select wire:model.live="status" :label="__('Status')"><flux:select.option value="">{{ __('All statuses') }}</flux:select.option>@foreach(ContactSubmissionStatus::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach<flux:select.option value="deleted">{{ __('Trash') }}</flux:select.option></flux:select><flux:select wire:model.live="category" :label="__('Category')"><flux:select.option value="">{{ __('All categories') }}</flux:select.option>@foreach(ContactSubmissionCategory::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select><flux:select wire:model.live="priority" :label="__('Priority')"><flux:select.option value="">{{ __('All priorities') }}</flux:select.option>@foreach(ContactSubmissionPriority::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select><flux:select wire:model.live="assigned" :label="__('Assigned to')"><flux:select.option value="">{{ __('Anyone') }}</flux:select.option><flux:select.option value="mine">{{ __('Me') }}</flux:select.option><flux:select.option value="unassigned">{{ __('Unassigned') }}</flux:select.option>@foreach($this->assignees as $user)<flux:select.option :value="$user->id">{{ $user->name }}</flux:select.option>@endforeach</flux:select><flux:select wire:model.live="read" :label="__('Read state')"><flux:select.option value="">{{ __('All') }}</flux:select.option><flux:select.option value="unread">{{ __('Unread') }}</flux:select.option><flux:select.option value="read">{{ __('Read') }}</flux:select.option></flux:select><div class="grid grid-cols-2 gap-2"><flux:input wire:model.live="dateFrom" type="date" :label="__('From')"/><flux:input wire:model.live="dateTo" type="date" :label="__('To')"/></div><div class="flex items-end"><flux:button wire:click="clearFilters" icon="x-mark">{{ __('Reset filters') }}</flux:button></div></div>
@if($selected)<form wire:submit="applyBulk" class="flex flex-wrap items-end gap-3 border-b bg-slate-50 p-4 dark:border-zinc-800 dark:bg-zinc-800/50"><flux:select wire:model.live="bulkAction" :label="__('Bulk action')"><flux:select.option value="">{{ __('Choose') }}</flux:select.option><flux:select.option value="read">{{ __('Mark read') }}</flux:select.option><flux:select.option value="status">{{ __('Change status') }}</flux:select.option><flux:select.option value="assign">{{ __('Assign') }}</flux:select.option><flux:select.option value="spam">{{ __('Mark spam') }}</flux:select.option><flux:select.option value="delete">{{ __('Delete') }}</flux:select.option><flux:select.option value="restore">{{ __('Restore') }}</flux:select.option></flux:select>@if($bulkAction === 'status')<flux:select wire:model="bulkValue" :label="__('New status')">@foreach(ContactSubmissionStatus::cases() as $item)<flux:select.option :value="$item->value">{{ $item->label() }}</flux:select.option>@endforeach</flux:select>@elseif($bulkAction === 'assign')<flux:select wire:model="bulkValue" :label="__('Assignee')"><flux:select.option value="">{{ __('Unassigned') }}</flux:select.option>@foreach($this->assignees as $user)<flux:select.option :value="$user->id">{{ $user->name }}</flux:select.option>@endforeach</flux:select>@endif<flux:button type="submit" variant="primary" wire:confirm="{{ __('Apply this action to the selected submissions?') }}">{{ __('Apply to :count', ['count' => count($selected)]) }}</flux:button></form>@endif
<div class="overflow-x-auto" wire:loading.class="opacity-60"><table class="w-full text-left text-sm"><thead class="bg-slate-50 text-slate-600 dark:bg-zinc-800/70 dark:text-zinc-300"><tr><th class="px-5 py-3"></th><th class="px-5 py-3">{{ __('Reference') }}</th><th class="px-5 py-3">{{ __('Visitor') }}</th><th class="px-5 py-3">{{ __('Subject') }}</th><th class="px-5 py-3">{{ __('Status') }}</th><th class="px-5 py-3">{{ __('Assigned') }}</th><th class="px-5 py-3">{{ __('Received') }}</th><th class="px-5 py-3"></th></tr></thead><tbody class="divide-y dark:divide-zinc-800">@forelse($this->submissions as $submission)<tr wire:key="contact-{{ $submission->id }}" @class(['font-semibold' => $submission->read_at === null])><td class="px-5 py-4"><flux:checkbox wire:model.live="selected" :value="$submission->id" :aria-label="__('Select :reference', ['reference' => $submission->reference_number])"/></td><td class="px-5 py-4 font-mono text-xs">@if($submission->read_at === null)<span class="sr-only">{{ __('Unread') }}</span><span class="me-2 inline-block size-2 rounded-full bg-blue-500" aria-hidden="true"></span>@endif{{ $submission->reference_number }}</td><td class="px-5 py-4"><p>{{ $submission->name }}</p><p class="text-xs font-normal text-slate-500">{{ $submission->email }}</p></td><td class="max-w-sm px-5 py-4"><p class="truncate">{{ $submission->subject }}</p><flux:badge size="sm" class="mt-1">{{ $submission->category->label() }}</flux:badge></td><td class="px-5 py-4"><div class="flex gap-2"><flux:badge :color="$submission->status->color()">{{ $submission->status->label() }}</flux:badge><flux:badge :color="$submission->priority->color()">{{ $submission->priority->label() }}</flux:badge></div></td><td class="px-5 py-4 font-normal">{{ $submission->assignedUser?->name ?? __('Unassigned') }}</td><td class="px-5 py-4 font-normal">{{ $submission->created_at?->format('M j, Y') }}</td><td class="px-5 py-4 text-right">@unless($submission->trashed())<flux:button size="sm" :href="route('contact-submissions.show', $submission)" wire:navigate>{{ __('Open') }}</flux:button>@endunless</td></tr>@empty<tr><td colspan="8" class="p-10 text-center text-slate-500">{{ __('No contact submissions match these filters.') }}</td></tr>@endforelse</tbody></table></div>@if($this->submissions->hasPages())<div class="border-t p-4 dark:border-zinc-800">{{ $this->submissions->links() }}</div>@endif</section></div>
