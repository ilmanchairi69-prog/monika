<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('MONIKA_ADMIN_PASSWORD');
        if (! $password || strlen($password) < 10) {
            throw new \RuntimeException('Atur MONIKA_ADMIN_PASSWORD minimal 10 karakter sebelum menjalankan seeder.');
        }
        User::firstOrCreate(['email' => env('MONIKA_ADMIN_EMAIL', 'admin@monika.test')], ['name' => 'Administrator', 'password' => Hash::make($password), 'role' => 'admin', 'active' => true]);
        if (! app()->environment('local', 'testing') || ! env('MONIKA_DEMO', false)) {
            return;
        }
        $mentor = User::firstOrCreate(['email' => 'mentor@monika.test'], ['name' => 'Ratna Pramesti', 'password' => Hash::make($password), 'role' => 'mentor', 'active' => true]);
        DB::table('mentors')->updateOrInsert(['user_id' => $mentor->id], ['division' => 'Teknologi Informasi', 'created_at' => now(), 'updated_at' => now()]);
        $mid = DB::table('mentors')->where('user_id', $mentor->id)->value('id');
        DB::table('kelompok')->updateOrInsert(['name' => 'Tim Digital 01'], ['mentor_id' => $mid, 'start_date' => now()->startOfMonth()->toDateString(), 'end_date' => now()->addMonths(3)->endOfMonth()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
        $gid = DB::table('kelompok')->where('name', 'Tim Digital 01')->value('id');
        foreach (['ketua' => 'Dimas Aditya', 'peserta' => 'Nadia Putri'] as $role => $name) {
            $u = User::firstOrCreate(['email' => $role.'@monika.test'], ['name' => $name, 'password' => Hash::make($password), 'role' => $role, 'active' => true]);
            DB::table('peserta_magang')->updateOrInsert(['user_id' => $u->id], ['kelompok_id' => $gid, 'institution' => 'Universitas Nusantara', 'student_number' => '2026'.$u->id, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
