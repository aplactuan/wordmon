<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Appearance settings') }}</flux:heading>

    <x-settings.layout :heading="__('Appearance')" :subheading="__('Wordmon uses a light interface for clear monitoring at a glance.')">
        <p class="text-sm text-slate-600">The light appearance is always on.</p>
    </x-settings.layout>
</section>
