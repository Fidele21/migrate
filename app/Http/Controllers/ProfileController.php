<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * A person's own account: their details and their signature.
 *
 * The signature is registered once by the officer themselves, never by
 * an administrator — a signature applied on someone's behalf would mean
 * nothing. It is then placed on reports they sign, above their name and
 * position.
 */
class ProfileController extends Controller
{
    public function edit()
    {
        return view('profile.edit', [
            'user' => auth()->user()->load('district'),
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name'            => ['required', 'string', 'max:120'],
            'position'        => ['nullable', 'string', 'max:120'],
            'phone'           => ['nullable', 'string', 'max:30'],
            'employee_number' => ['nullable', 'string', 'max:40'],
        ]);

        $user->fill($data)->save();

        return back()->with('status', 'Your details have been updated.');
    }

    /**
     * Register a signature, either drawn on screen or uploaded.
     *
     * Stored as PNG with transparency so it sits cleanly on a document.
     */
    public function storeSignature(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'signature_data' => ['nullable', 'string'],
            'signature_file' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
        ]);

        if (! $request->filled('signature_data') && ! $request->hasFile('signature_file')) {
            return back()->withErrors(['signature' => 'Draw a signature or choose an image file.']);
        }

        if ($user->signature_path) {
            Storage::disk('public')->delete($user->signature_path);
        }

        if ($request->hasFile('signature_file')) {
            $path = $request->file('signature_file')->store('signatures', 'public');
        } else {
            $data = $request->input('signature_data');

            if (! preg_match('#^data:image/png;base64,#', $data)) {
                return back()->withErrors(['signature' => 'The drawn signature could not be read.']);
            }

            $binary = base64_decode(substr($data, strlen('data:image/png;base64,')), true);

            if ($binary === false || strlen($binary) < 200) {
                return back()->withErrors(['signature' => 'The signature appears to be empty.']);
            }

            $path = 'signatures/' . $user->id . '-' . now()->format('YmdHis') . '.png';
            Storage::disk('public')->put($path, $binary);
        }

        $user->forceFill([
            'signature_path'          => $path,
            'signature_registered_at' => now(),
        ])->save();

        return back()->with('status', 'Your signature has been registered.');
    }

    public function destroySignature(Request $request)
    {
        $user = $request->user();

        if ($user->signature_path) {
            Storage::disk('public')->delete($user->signature_path);
        }

        $user->forceFill([
            'signature_path'          => null,
            'signature_registered_at' => null,
        ])->save();

        return back()->with('status', 'Signature removed.');
    }
}
