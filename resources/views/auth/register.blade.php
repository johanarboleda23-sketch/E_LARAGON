<x-guest-layout>
    <div class="mb-7">
        <p class="text-xs font-bold uppercase tracking-[0.18em] text-[#227c70]">{{ $isPartnerRegistration ? 'Programa de socios' : 'Comienza hoy' }}</p>
        <h1 class="mt-2 text-3xl font-black tracking-tight text-[#192522]">{{ $isPartnerRegistration ? 'Registra tu empresa' : 'Crea tu cuenta' }}</h1>
        <p class="mt-2 text-sm leading-6 text-gray-500">{{ $isPartnerRegistration ? 'Crea tu empresa y activa su registro como socio.' : 'Configura tu empresa y administra tu operación desde SU+ ERP.' }}</p>
    </div>
    <form method="POST" action="{{ route($isPartnerRegistration ? 'register.partner.store' : 'register') }}" class="space-y-4">
        @csrf
        <div>
            <x-input-label for="name" value="Nombre completo" class="font-semibold text-gray-700" />
            <x-text-input id="name" class="mt-2 block w-full rounded-xl border-gray-300 py-3 shadow-sm focus:border-[#227c70] focus:ring-[#227c70]" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="company_name" value="Nombre de la empresa" class="font-semibold text-gray-700" />
            <x-text-input id="company_name" class="mt-2 block w-full rounded-xl border-gray-300 py-3 shadow-sm focus:border-[#227c70] focus:ring-[#227c70]" type="text" name="company_name" :value="old('company_name')" required autocomplete="organization" />
            <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="email" value="Correo electrónico" class="font-semibold text-gray-700" />
            <x-text-input id="email" class="mt-2 block w-full rounded-xl border-gray-300 py-3 shadow-sm focus:border-[#227c70] focus:ring-[#227c70]" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="password" value="Contraseña" class="font-semibold text-gray-700" />
            <x-text-input id="password" class="mt-2 block w-full rounded-xl border-gray-300 py-3 shadow-sm focus:border-[#227c70] focus:ring-[#227c70]" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
        <div>
            <x-input-label for="password_confirmation" value="Confirmar contraseña" class="font-semibold text-gray-700" />
            <x-text-input id="password_confirmation" class="mt-2 block w-full rounded-xl border-gray-300 py-3 shadow-sm focus:border-[#227c70] focus:ring-[#227c70]" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>
        <button type="submit" class="w-full rounded-xl bg-[#227c70] px-4 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-[#195f56] focus:outline-none focus:ring-2 focus:ring-[#227c70] focus:ring-offset-2">{{ $isPartnerRegistration ? 'Crear cuenta de socio' : 'Crear mi cuenta' }}</button>
    </form>
    <p class="mt-6 text-center text-sm text-gray-600">¿Ya tienes una cuenta? <a href="{{ route('login') }}" class="font-bold text-[#227c70] hover:underline">Inicia sesión</a></p>
    @if (! $isPartnerRegistration)
        <a href="{{ route('register.partner') }}" class="mt-3 block text-center text-sm font-semibold text-gray-500 hover:text-[#227c70]">Soy socio</a>
    @else
        <a href="{{ route('register') }}" class="mt-3 block text-center text-sm font-semibold text-gray-500 hover:text-[#227c70]">Registrar una cuenta normal</a>
    @endif
</x-guest-layout>
