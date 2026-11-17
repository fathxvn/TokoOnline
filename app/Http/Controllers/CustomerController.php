<?php

namespace App\Http\Controllers;

use App\Helpers\ImageHelper;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Socialite\Facades\Socialite;

class CustomerController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request)
    {
        try {
            // Keep Socialite's OAuth state and provider validation enabled.
            $socialUser = Socialite::driver('google')->user();
            $googleId = $socialUser->getId();
            $claims = $socialUser->getRaw();
            if (!is_string($googleId) || $googleId === '' || ($claims['sub'] ?? null) !== $googleId) {
                throw new \RuntimeException('Identitas Google tidak valid.');
            }

            $user = DB::transaction(function () use ($socialUser, $googleId, $claims) {
                // Google subject remains the identity even after a profile email changes.
                $linkedCustomers = Customer::where('google_id', $googleId)->lockForUpdate()->get();
                if ($linkedCustomers->count() > 1) {
                    throw new \RuntimeException('Identitas Google terhubung lebih dari sekali.');
                }
                $customer = $linkedCustomers->first();
                if ($customer) {
                    $user = $customer->user()->lockForUpdate()->firstOrFail();
                } else {
                    $email = $socialUser->getEmail();
                    if (($claims['email_verified'] ?? null) !== true || !is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new \RuntimeException('Email Google harus terverifikasi.');
                    }
                    $user = User::where('email', $email)->lockForUpdate()->first();
                    if (!$user) {
                        $user = User::create([
                            'nama' => $socialUser->getName() ?: $email,
                            'email' => $email,
                            'role' => '2',
                            'status' => 1,
                            'password' => Hash::make(Str::random(64)),
                        ]);
                    }
                    $customer = Customer::where('user_id', $user->id)->lockForUpdate()->first();
                    if ($customer && $customer->google_id !== null && $customer->google_id !== '' && $customer->google_id !== $googleId) {
                        throw new \RuntimeException('Akun terhubung dengan identitas Google lain.');
                    }
                }

                if ((string) $user->role !== '2' || (int) $user->status !== 1) {
                    throw new \RuntimeException('Akun bukan customer aktif.');
                }

                $customer ??= new Customer(['user_id' => $user->id]);
                $customer->fill([
                    'google_id' => $googleId,
                    'google_token' => $socialUser->token,
                ])->save();

                return $user;
            });
        } catch (\Exception $e) {
            return redirect()->route('beranda')->with('error', 'Login Google gagal. Gunakan akun customer aktif dengan identitas dan email Google terverifikasi.');
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('beranda');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Anda telah berhasil logout.');
    }

    public function index()
    {
        return view('backend.v_customer.index', [
            'judul' => 'Customer',
            'sub' => 'Halaman Customer',
            'index' => Customer::with('user')->orderBy('id', 'desc')->get(),
        ]);
    }

    public function akun($id)
    {
        abort_unless((string) $id === (string) Auth::id(), 403);
        $customer = Customer::with('user')->where('user_id', Auth::id())->firstOrFail();

        return view('v_customer.edit', [
            'judul' => 'Customer',
            'subJudul' => 'Akun Customer',
            'edit' => $customer,
        ]);
    }

    public function updateAkun(Request $request, $id)
    {
        abort_unless((string) $id === (string) Auth::id(), 403);
        $customer = Customer::with('user')->where('user_id', Auth::id())->firstOrFail();
        $validated = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('user', 'email')->ignore($customer->user_id)],
            'hp' => ['required', 'string', 'regex:/^[0-9]{10,13}$/'],
            'alamat' => ['nullable', 'string'],
            'pos' => ['nullable', 'string', 'max:255'],
            'foto' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif', 'max:1024'],
        ], [
            'foto.image' => 'Format gambar gunakan file dengan ekstensi jpeg, jpg, png, atau gif.',
            'foto.max' => 'Ukuran file gambar maksimal adalah 1024 KB.',
            'hp.regex' => 'Nomor HP harus terdiri dari 10 sampai 13 angka.',
        ]);

        $userData = Arr::only($validated, ['nama', 'email', 'hp']);
        $oldPhoto = $customer->user->foto;
        $newPhoto = null;
        try {
            if ($request->hasFile('foto')) {
                $file = $request->file('foto');
                $newPhoto = Str::uuid().'.'.$file->extension();
                File::ensureDirectoryExists(public_path('storage/img-customer'));
                ImageHelper::uploadAndResize($file, 'storage/img-customer/', $newPhoto, 385, 400);
                $userData['foto'] = $newPhoto;
            }
            DB::transaction(function () use ($customer, $userData, $validated) {
                $customer->user->update($userData);
                $customer->update(Arr::only($validated, ['alamat', 'pos']));
            });
        } catch (\Throwable $e) {
            if ($newPhoto) {
                File::delete(public_path('storage/img-customer/'.$newPhoto));
            }
            throw $e;
        }
        if ($newPhoto && $oldPhoto) {
            File::delete(public_path('storage/img-customer/'.basename($oldPhoto)));
        }

        return redirect()->route('customer.akun', ['id' => Auth::id()])->with('success', 'Akun berhasil diperbarui.');
    }
}
