<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main id="admin-main" class="!p-0">
        <div class="flex min-h-[calc(100vh-4rem)] flex-col">
            <div class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                {{ $slot }}
            </div>

            <x-admin.footer />
        </div>
    </flux:main>
</x-layouts::app.sidebar>
