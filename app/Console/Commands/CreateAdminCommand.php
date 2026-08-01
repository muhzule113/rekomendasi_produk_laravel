<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateAdminCommand extends Command
{
    protected $signature = 'app:create-admin
        {--email= : Email admin (atau ADMIN_EMAIL)}
        {--password= : Password admin (atau ADMIN_PASSWORD)}
        {--name= : Nama admin (atau ADMIN_NAME)}';

    protected $description = 'Buat atau perbarui akun admin dari environment / opsi CLI (untuk development)';

    public function handle(): int
    {
        $email = $this->option('email') ?: env('ADMIN_EMAIL');
        $password = $this->option('password') ?: env('ADMIN_PASSWORD');
        $name = $this->option('name') ?: env('ADMIN_NAME', 'Administrator');

        if (!$email || !$password) {
            $this->error('ADMIN_EMAIL dan ADMIN_PASSWORD wajib diisi (env atau opsi --email/--password).');
            return self::FAILURE;
        }

        $existing = DB::table('users')->where('email', $email)->first();

        if ($existing) {
            DB::table('users')->where('id_user', $existing->id_user)->update([
                'nama' => $name,
                'password' => Hash::make($password),
                'role' => 'admin',
                'status' => 'aktif',
            ]);
            $this->info("Admin diperbarui: {$email}");
        } else {
            DB::table('users')->insert([
                'nama' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'admin',
                'status' => 'aktif',
                'created_at' => now(),
            ]);
            $this->info("Admin dibuat: {$email}");
        }

        return self::SUCCESS;
    }
}
