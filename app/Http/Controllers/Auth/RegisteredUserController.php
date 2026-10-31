<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use App\Support\DefaultChartOfAccounts;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request): View
    {
        return view('auth.register', [
            'isPartnerRegistration' => $request->routeIs('register.partner'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->register($request, false);
    }

    public function storePartner(Request $request): RedirectResponse
    {
        return $this->register($request, true);
    }

    /**
     * @throws ValidationException
     */
    private function register(Request $request, bool $isPartner): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        [$user, $company] = DB::transaction(function () use ($data, $isPartner): array {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);
            $company = Company::create([
                'name' => $data['company_name'],
                'is_partner' => $isPartner,
            ]);
            $user->companies()->attach($company, ['role' => 'admin']);
            DefaultChartOfAccounts::seedFor($company->id);

            return [$user, $company];
        });

        session(['company_id' => $company->id]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
