<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your account')" :description="__('Enter your email or username and password below to log in')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify />

        <form
            method="POST"
            action="{{ route('login.store') }}"
            class="flex flex-col gap-6"
            x-data="{ submitting: false }"
            x-on:submit="if (submitting) { $event.preventDefault() } else { submitting = true }"
        >
            @csrf

            <!-- Login -->
            <flux:input
                name="login"
                :label="__('Email or username')"
                :value="old('login')"
                type="text"
                required
                autofocus
                autocomplete="username"
                :placeholder="__('Email or username')"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button
                    variant="primary"
                    type="submit"
                    class="w-full"
                    data-test="login-button"
                    x-bind:disabled="submitting"
                    x-bind:aria-busy="submitting.toString()"
                >
                    <span x-show="! submitting">{{ __('Log in') }}</span>
                    <span x-cloak x-show="submitting" aria-live="polite">{{ __('Loading...') }}</span>
                </flux:button>
            </div>
        </form>

        <div class="space-x-1 text-sm text-center rtl:space-x-reverse text-zinc-600 dark:text-zinc-400">
            <span>{{ __('Don\'t have an account?') }}</span>
            <flux:link :href="route('register')" wire:navigate>{{ __('Sign up') }}</flux:link>
        </div>
    </div>
</x-layouts::auth>
