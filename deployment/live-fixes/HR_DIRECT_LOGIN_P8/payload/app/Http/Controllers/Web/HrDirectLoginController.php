<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ErpAuthUser;
use App\Support\HrAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

final class HrDirectLoginController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        if (HrAccess::permitted($request->user())) {
            return redirect()->route('erp.hr.operations');
        }
        return view('hr.direct-login', [
            'currentAccount' => $request->user() instanceof ErpAuthUser
                ? (string)$request->user()->email : null,
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:180'],
            'password' => ['required', 'string', 'max:200'],
        ]);
        $user = ErpAuthUser::query()
            ->whereRaw('LOWER(TRIM(email))=?', [strtolower(trim($data['email']))])
            ->where('active', 1)->first();

        // Require valid credentials AND existing server-side HR grant.
        // This does not change any staff role or grant Admin privileges.
        if (!$user || !Hash::check($data['password'], $user->password)
            || !HrAccess::permitted($user)) {
            return back()->withErrors([
                'email' => 'HR sign-in failed. Check the assigned HR credentials or contact Admin.',
            ])->onlyInput('email');
        }

        Auth::login($user, false);
        $request->session()->regenerate();
        return redirect()->route('erp.hr.operations');
    }
}