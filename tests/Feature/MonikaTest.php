<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MonikaTest extends TestCase
{
    use RefreshDatabase;

    private function user($role = 'peserta')
    {
        return User::factory()->create(['role' => $role, 'active' => true, 'password' => Hash::make('Testing12345!')]);
    }

    private function group($mentor = null)
    {
        $m = $mentor ?? $this->user('mentor');
        $mid = DB::table('mentors')->insertGetId(['user_id' => $m->id, 'division' => 'IT']);

        return DB::table('kelompok')->insertGetId(['name' => 'Tim '.uniqid(), 'mentor_id' => $mid, 'start_date' => now()->subMonth()->toDateString(), 'end_date' => now()->addMonth()->toDateString()]);
    }

    private function participant($role = 'peserta', $gid = null)
    {
        $u = $this->user($role);
        $gid = $gid ?? $this->group();
        DB::table('peserta_magang')->insert(['user_id' => $u->id, 'kelompok_id' => $gid, 'institution' => 'Kampus', 'student_number' => '123']);

        return [$u, $gid];
    }

    public function test_public_pages_and_login()
    {
        foreach (['/', '/pengajuan', '/login'] as $url) {
            $this->get($url)->assertOk();
        }$this->get('/dashboard')->assertRedirect('/login');
        $u = $this->user();
        $this->post('/login', ['email' => $u->email, 'password' => 'wrong'])->assertSessionHasErrors();
        $this->post('/login', ['email' => $u->email, 'password' => 'Testing12345!'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($u);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_admin_pages_render_and_participants_cannot_access_them()
    {
        [$u] = $this->participant();
        foreach (['/pengguna', '/kelompok', '/admin/pengajuan', '/laporan'] as $url) {
            $this->actingAs($u)->get($url)->assertForbidden();
        }$a = $this->user('admin');
        foreach (['/dashboard', '/pengguna', '/kelompok', '/admin/pengajuan', '/absensi', '/logbook', '/laporan'] as $url) {
            $this->actingAs($a)->get($url)->assertOk();
        }
    }

    public function test_application_upload_private_and_does_not_create_account()
    {
        Storage::fake('local');
        $data = ['name' => 'Calon Peserta', 'email' => 'calon@example.test', 'phone' => '08123456', 'institution' => 'Kampus', 'start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addMonth()->toDateString(), 'letter' => UploadedFile::fake()->create('surat.pdf', 100, 'application/pdf')];
        $this->post('/pengajuan', $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('users', 0);
        $x = DB::table('pengajuan_magang')->first();
        Storage::disk('local')->assertExists($x->letter_path);
        $this->get('/admin/pengajuan/'.$x->id.'/surat')->assertRedirect('/login');
        $this->actingAs($this->user())->get('/admin/pengajuan/'.$x->id.'/surat')->assertForbidden();
        $this->actingAs($this->user('admin'))->get('/admin/pengajuan/'.$x->id.'/surat')->assertOk();
        $this->put('/admin/pengajuan/'.$x->id, ['status' => 'diterima'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('peserta_magang', 0);
    }

    public function test_invalid_application_rejected()
    {
        Storage::fake('local');
        $this->post('/pengajuan', ['name' => 'X', 'letter' => UploadedFile::fake()->create('malware.exe', 10)])->assertSessionHasErrors(['email', 'letter']);
        $this->assertDatabaseCount('pengajuan_magang', 0);
    }

    public function test_daily_attendance_uniqueness_and_role_restrictions()
    {
        [$u] = $this->participant();
        $v = ['date' => now()->toDateString(), 'description' => 'Hadir bekerja di kantor'];
        $this->actingAs($u)->post('/absensi', $v)->assertSessionHasNoErrors();
        $this->post('/absensi', $v)->assertSessionHasErrors('date');
        $this->assertDatabaseCount('absensi', 1);
        $this->post('/absensi', [...$v, 'date' => now()->subDay()->toDateString()])->assertSessionHasErrors('date');
        $this->post('/logbook', $v)->assertForbidden();
        $this->actingAs($this->user('admin'))->post('/absensi', $v)->assertForbidden();
    }

    public function test_only_leader_can_submit_group_log_and_mentor_scope_is_enforced()
    {
        $m = $this->user('mentor');
        $gid = $this->group($m);
        [$leader] = $this->participant('ketua', $gid);
        $this->actingAs($leader)->post('/logbook', ['date' => now()->toDateString(), 'description' => 'Membangun modul aplikasi'])->assertSessionHasNoErrors();
        $id = DB::table('logbooks')->value('id');
        $this->post('/logbook', ['date' => now()->toDateString(), 'description' => 'Duplikat kegiatan'])->assertSessionHasErrors('date');
        $outsider = $this->user('mentor');
        $this->group($outsider);
        $this->actingAs($outsider)->get('/logbook')->assertDontSee('Membangun modul aplikasi');
        $this->put('/logbook/'.$id.'/validasi', ['status' => 'disetujui'])->assertForbidden();
        $this->actingAs($m)->put('/logbook/'.$id.'/validasi', ['status' => 'ditolak'])->assertSessionHasErrors('feedback');
        $this->put('/logbook/'.$id.'/validasi', ['status' => 'disetujui', 'feedback' => 'Kerja bagus'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('logbooks', ['id' => $id, 'status' => 'disetujui', 'reviewer_id' => $m->id]);
        $this->get('/laporan?kind=logbook')->assertOk()->assertSee('Membangun modul aplikasi');
        $this->get('/laporan?kind=logbook&export=1')->assertOk()->assertDownload('monika-logbook.csv');
    }

    public function test_user_group_crud_and_self_protection()
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->post('/pengguna', ['name' => 'Mentor Baru', 'email' => 'baru@example.test', 'password' => 'Password12345!', 'role' => 'mentor', 'active' => 1, 'division' => 'IT'])->assertSessionHasNoErrors();
        $m = DB::table('mentors')->first();
        $this->post('/kelompok', ['name' => 'Kelompok Baru', 'mentor_id' => $m->id, 'start_date' => now()->toDateString(), 'end_date' => now()->addMonth()->toDateString()])->assertSessionHasNoErrors();
        $g = DB::table('kelompok')->first();
        $v = ['name' => 'Ketua Baru', 'email' => 'ketua@example.test', 'password' => 'Password12345!', 'role' => 'ketua', 'active' => 1, 'kelompok_id' => $g->id, 'institution' => 'Kampus', 'student_number' => '123'];
        $this->post('/pengguna', $v)->assertSessionHasNoErrors();
        $leader = User::where('email', 'ketua@example.test')->first();
        $this->put('/pengguna/'.$leader->id, [...$v, 'name' => 'Ketua Diperbarui', 'password' => ''])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['id' => $leader->id, 'name' => 'Ketua Diperbarui']);
        $this->put('/kelompok/'.$g->id, ['name' => 'Kelompok Diperbarui', 'mentor_id' => $m->id, 'start_date' => now()->toDateString(), 'end_date' => now()->addMonths(2)->toDateString()])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('kelompok', ['id' => $g->id, 'name' => 'Kelompok Diperbarui']);
        $this->post('/pengguna', [...$v, 'email' => 'ketua2@example.test'])->assertSessionHasErrors('kelompok_id');
        $this->delete('/kelompok/'.$g->id)->assertSessionHasErrors('group');
        $this->put('/pengguna/'.$admin->id, ['name' => $admin->name, 'email' => $admin->email, 'role' => 'peserta', 'active' => 1, 'kelompok_id' => $g->id, 'institution' => 'Kampus', 'student_number' => '321'])->assertSessionHasErrors('role');
        $this->delete('/pengguna/'.$admin->id)->assertStatus(422);
        $u = User::where('email', 'ketua@example.test')->first();
        $this->delete('/pengguna/'.$u->id)->assertSessionHasNoErrors();
        $this->delete('/kelompok/'.$g->id)->assertSessionHasNoErrors();
        $this->delete('/pengguna/'.$m->user_id)->assertSessionHasNoErrors();
    }

    public function test_disabled_account_loses_access()
    {
        $u = $this->user();
        $u->update(['active' => false]);
        $this->actingAs($u)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_participant_data_is_scoped_and_reports_do_not_leak()
    {
        [$a,$ga] = $this->participant();
        [$b,$gb] = $this->participant();
        DB::table('absensi')->insert(['user_id' => $b->id, 'kelompok_id' => $gb, 'date' => now()->toDateString(), 'description' => 'PRIVATE OTHER GROUP']);
        $this->actingAs($a)->get('/absensi')->assertDontSee('PRIVATE OTHER GROUP');
        $this->get('/laporan?export=1')->assertForbidden();
    }
}
