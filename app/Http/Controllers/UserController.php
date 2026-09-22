<?php

namespace App\Http\Controllers;


use App\Models\District;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * User administration.
 *
 * Restricted to holders of the user.manage permission, which only the
 * Administrator role carries. Note that an Administrator can create
 * accounts but can never sign a document - the two capabilities are
 * deliberately separated.
 */
class UserController extends Controller
{
    /** Roles that must be bound to exactly one district. */
    private const DISTRICT_BOUND = ['Inspector', 'Lead Inspector', 'Director of Inspection'];

    /** Roles that operate across all districts. */
    private const CITY_WIDE = ['Senior Inspector', 'Chief Inspector', 'Administrator'];

    public function index()
    {
        return view('users.index', [
            'users'     => User::with(['district', 'roles'])->orderBy('name')->get(),
            'roles'     => Role::orderBy('name')->pluck('name'),
            'districts' => District::orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('users.create', [
            'roles'          => Role::orderBy('name')->pluck('name'),
            'districts'      => District::orderBy('name')->get(),
            'districtBound'  => self::DISTRICT_BOUND,
            'cityWide'       => self::CITY_WIDE,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:120'],
            'email'           => ['required', 'email', 'max:180', 'unique:users,email'],
            'password'        => ['required', 'string', 'min:8', 'confirmed'],
            'role'            => ['required', Rule::in(Role::pluck('name')->all())],
            'district_id'     => ['nullable', 'exists:districts,id'],
            'employee_number' => ['nullable', 'string', 'max:40'],
            'phone'           => ['nullable', 'string', 'max:30'],
        ]);

        // Enforce the scoping rules rather than trusting the form.
        if (in_array($data['role'], self::DISTRICT_BOUND, true) && empty($data['district_id'])) {
            throw ValidationException::withMessages([
                'district_id' => "A {$data['role']} must be assigned to exactly one district.",
            ]);
        }

        if (in_array($data['role'], self::CITY_WIDE, true)) {
            $data['district_id'] = null;   // city-wide roles are never district-bound
        }

        $user = User::create([
            'name'            => $data['name'],
            'email'           => $data['email'],
            'password'        => Hash::make($data['password']),
            'district_id'     => $data['district_id'] ?? null,
            'employee_number' => $data['employee_number'] ?? null,
            'phone'           => $data['phone'] ?? null,
            'is_active'       => true,
        ]);

        $user->assignRole($data['role']);

        return redirect()->route('users.index')
            ->with('status', "Account created for {$user->name} as {$data['role']}.");
    }

    /** Deactivate rather than delete. Accounts appear in the audit trail. */
    public function toggle(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'You cannot deactivate your own account.']);
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('status',
            $user->name . ' has been ' . ($user->is_active ? 'reactivated' : 'deactivated') . '.');
    }
    /**
     * Issue a temporary password.
     *
     * Shown to the administrator once and never stored in readable form.
     * The account is marked so the holder must set their own password
     * before reaching anything else, which means the administrator never
     * knows the password actually in use.
     */
    public function resetPassword(User $user)
    {
        abort_unless(auth()->user()->can('user.manage'), 403);

        abort_if($user->id === auth()->id(), 422,
            'Change your own password from My Account rather than resetting it here.');

        $temporary = $this->temporaryPassword();

        $user->forceFill([
            'password'             => Hash::make($temporary),
            'must_change_password' => true,
            'password_changed_at'  => null,
        ])->save();

        // Any active sessions for this account are no longer valid.
        DB::table('sessions')->where('user_id', $user->id)->delete();

        return back()
            ->with('reset_user', $user->name)
            ->with('reset_email', $user->email)
            ->with('reset_password', $temporary);
    }

    /**
     * A temporary password that can be read aloud without ambiguity.
     *
     * Avoids characters that are easily confused when written down or
     * spoken — no 0 and O, no 1, l and I.
     */
    private function temporaryPassword(): string
    {
        $letters = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $digits  = '23456789';

        $out = '';
        for ($i = 0; $i < 4; $i++) {
            $out .= $letters[random_int(0, strlen($letters) - 1)];
        }
        $out .= '-';
        for ($i = 0; $i < 4; $i++) {
            $out .= $digits[random_int(0, strlen($digits) - 1)];
        }

        return $out;
    }
}
