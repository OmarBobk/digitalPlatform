<x-layouts::frontend.header :title="$title ?? null">
    <flux:main class="!py-0 !px-3" style="background-image: url('{{asset('images/background-pattern.jpg')}}')">

        {{ $slot }}

        <x-layouts::frontend.footer />
    </flux:main>
</x-layouts::frontend.header>
