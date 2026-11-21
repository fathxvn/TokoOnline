<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class BootstrapAdmin extends Command
{
    protected $signature = 'app:bootstrap-admin';
    protected $description = 'Buat admin awal secara interaktif tanpa mengubah akun existing';

    public function handle(): int
    {
        if (!$this->input->isInteractive()) {
            $this->error('Perintah ini memerlukan terminal interaktif.');
            return self::FAILURE;
        }
        if (User::whereIn('role', ['0', '1'])->where('status', 1)->exists()) {
            $this->error('Admin aktif sudah tersedia. Tidak ada akun yang diubah.');
            return self::FAILURE;
        }
        $data = ['nama' => $this->ask('Nama admin'), 'email' => $this->ask('Email admin'),
            'password' => $this->secret('Password admin (minimal 12 karakter)', false),
            'password_confirmation' => $this->secret('Konfirmasi password', false)];
        $validator = Validator::make($data, ['nama' => ['required','string','max:255'],
            'email' => ['required','email','max:255','unique:user,email','not_regex:/[\r\n]/'],
            'password' => ['required','string','min:12','confirmed']]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) $this->error($error);
            return self::FAILURE;
        }
        User::create(['nama' => $data['nama'], 'email' => $data['email'], 'role' => '1', 'status' => 1,
            'password' => Hash::make($data['password'])]);
        $this->info('Admin awal berhasil dibuat. Password tidak ditampilkan.');
        return self::SUCCESS;
    }
}
