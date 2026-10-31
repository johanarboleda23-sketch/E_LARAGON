<x-guest-layout>
    <div class="mb-8">
        <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#227c70]">Bienvenido de nuevo</p>
        <h1 class="mt-2 text-3xl font-black tracking-tight text-[#192522]">Inicia sesión</h1>
        <p class="mt-2 text-sm text-gray-500">Ingresa a tu cuenta para continuar.</p>
    </div>
    <x-auth-session-status class="mb-4" :status="session('status')" />
    <form method="POST" action="{{ route('login') }}">
        @csrf
        <div class="space-y-5">
            <div>
            <x-input-label for="email" value="Correo electrónico" class="font-semibold text-gray-700" />
            <x-text-input id="email" class="mt-2 block w-full rounded-xl border-gray-300 py-3 shadow-sm focus:border-[#227c70] focus:ring-[#227c70]" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
            <div>
            <x-input-label for="password" value="Contraseña" class="font-semibold text-gray-700" />
            <x-text-input id="password" class="mt-2 block w-full rounded-xl border-gray-300 py-3 shadow-sm focus:border-[#227c70] focus:ring-[#227c70]" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
        </div>
        <div class="mt-5 flex items-center justify-between">
            <label for="remember_me" class="inline-flex items-center gap-2 text-sm text-gray-600">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-[#227c70] shadow-sm focus:ring-[#227c70]" name="remember">
                <span>Recordarme</span>
            </label>
            @if (Route::has('password.request'))
                <a class="text-sm font-semibold text-[#227c70] hover:underline" href="{{ route('password.request') }}">
                    ¿Olvidaste tu contraseña?
                </a>
            @endif
        </div>
        <button type="submit" class="mt-7 w-full rounded-xl bg-[#227c70] px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-[#195f56] focus:outline-none focus:ring-2 focus:ring-[#227c70] focus:ring-offset-2">Ingresar</button>
    </form>
    <p class="mt-7 text-center text-sm text-gray-600">¿Aún no tienes cuenta? <a href="{{ route('register') }}" class="font-bold text-[#227c70] hover:underline">Crea tu empresa</a></p>
    <a href="{{ route('register.partner') }}" class="mt-3 block text-center text-sm font-semibold text-gray-500 hover:text-[#227c70]">Registro para socios</a>
</x-guest-layout>
