<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <main class="min-h-screen bg-[#f3f5f1] p-4 sm:p-8">
            <div class="mx-auto grid min-h-[calc(100vh-4rem)] max-w-6xl overflow-hidden rounded-3xl bg-white shadow-xl lg:grid-cols-[1fr_0.9fr]">
                <section class="relative hidden flex-col justify-between overflow-hidden bg-[#192522] p-10 text-white lg:flex xl:p-14">
                    <div class="absolute -right-20 -top-20 h-72 w-72 rounded-full bg-[#227c70]/40 blur-3xl"></div>
                    <a href="/" class="relative inline-flex items-center gap-3 text-2xl font-black tracking-tight">
                        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-[#227c70] text-xl">SU+</span>
                        SU+ ERP
                    </a>
                    <div class="relative max-w-lg">
                        <p class="mb-4 text-xs font-bold uppercase tracking-[0.24em] text-[#8ed0bd]">Gestión clara. Crecimiento constante.</p>
                        <h1 class="text-4xl font-black leading-tight xl:text-5xl">Tu negocio, organizado en un solo lugar.</h1>
                        <p class="mt-5 max-w-md text-base leading-7 text-white/70">Administra inventario, ventas y contabilidad con herramientas creadas para acompañar tu operación.</p>
                    </div>
                    <p class="relative text-xs text-white/50">Acceso seguro a tu espacio de trabajo</p>
                </section>
                <section class="flex items-center justify-center px-5 py-10 sm:px-10">
                    <div class="w-full max-w-md">
                        <a href="/" class="mb-8 inline-flex items-center gap-2 text-lg font-black text-[#192522] lg:hidden">
                            <span class="grid h-10 w-10 place-items-center rounded-xl bg-[#227c70] text-white">SU+</span>
                            SU+ ERP
                        </a>
                        {{ $slot }}
                    </div>
                </section>
            </div>
        </main>
    </body>
</html>
