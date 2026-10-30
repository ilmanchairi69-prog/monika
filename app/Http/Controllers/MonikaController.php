<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MonikaController extends Controller
{
    private function admin()
    {
        abort_unless(Auth::user()->role === 'admin', 403);
    }

    private function scope($table)
    {
        $q = DB::table($table)->join('users', 'users.id', "$table.user_id")->join('kelompok', 'kelompok.id', "$table.kelompok_id")->select("$table.*", 'users.name as author', 'kelompok.name as group_name');
        $u = Auth::user();
        if ($u->role === 'mentor') {
            $q->whereIn("$table.kelompok_id", DB::table('kelompok')->whereIn('mentor_id', DB::table('mentors')->where('user_id', $u->id)->select('id'))->select('id'));
        } elseif ($u->role !== 'admin') {
            if ($table === 'absensi') {
                $q->where("$table.user_id", $u->id);
            } else {
                $q->where("$table.kelompok_id", DB::table('peserta_magang')->where('user_id', $u->id)->value('kelompok_id') ?? 0);
            }
        }

        return $q;
    }

    public function login(Request $r)
    {
        $v = $r->validate(['email' => 'required|email', 'password' => 'required']);
        if (! Auth::attempt([...$v, 'active' => true])) {
            return back()->withErrors(['email' => 'Email atau password tidak sesuai, atau akun tidak aktif.'])->onlyInput('email');
        }$r->session()->regenerate();

        return redirect()->intended('/dashboard');
    }

    public function logout(Request $r)
    {
        Auth::logout();
        $r->session()->invalidate();
        $r->session()->regenerateToken();

        return redirect('/');
    }

    public function apply(Request $r)
    {
        $v = $r->validate(['name' => 'required|string|max:120', 'email' => 'required|email|max:150', 'phone' => 'required|string|max:30', 'institution' => 'required|string|max:180', 'start_date' => 'required|date|after_or_equal:today', 'end_date' => 'required|date|after_or_equal:start_date', 'letter' => 'required|file|mimes:pdf|max:2048']);
        unset($v['letter']);
        $path = $r->file('letter')->store('letters', 'local');
        try {
            DB::table('pengajuan_magang')->insert([...$v, 'reference' => (string) Str::uuid(), 'letter_path' => $path, 'created_at' => now(), 'updated_at' => now()]);
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }

return back()->with('success', 'Pengajuan berhasil dikirim. Admin akan meninjau data Anda.');
    }

    public function dashboard()
    {
        return view('dashboard', ['attendance' => $this->scope('absensi')->count(), 'logs' => $this->scope('logbooks')->count(), 'pending' => $this->scope('logbooks')->where('logbooks.status', 'menunggu')->count(), 'groups' => Auth::user()->role === 'admin' ? DB::table('kelompok')->count() : (Auth::user()->role === 'mentor' ? DB::table('kelompok')->whereIn('mentor_id', DB::table('mentors')->where('user_id', Auth::id())->select('id'))->count() : DB::table('peserta_magang')->where('user_id', Auth::id())->count()), 'recent' => $this->scope('logbooks')->orderByDesc('logbooks.id')->limit(5)->get()]);
    }

    public function users()
    {
        $this->admin();

        return view('users', ['users' => DB::table('users')->leftJoin('peserta_magang as p', 'p.user_id', 'users.id')->leftJoin('mentors as m', 'm.user_id', 'users.id')->select('users.*', 'p.kelompok_id', 'p.institution', 'p.student_number', 'm.division')->orderBy('users.name')->get(), 'groups' => DB::table('kelompok')->get()]);
    }

    public function saveUser(Request $r, ?User $user = null)
    {
        $this->admin();
        $v = $r->validate(['name' => 'required|string|max:120', 'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($user?->id)], 'password' => [$user ? 'nullable' : 'required', 'string', 'min:10', 'max:200'], 'role' => 'required|in:admin,mentor,ketua,peserta', 'active' => 'required|boolean', 'division' => 'required_if:role,mentor|nullable|string|max:150', 'kelompok_id' => 'required_if:role,ketua,peserta|nullable|exists:kelompok,id', 'institution' => 'required_if:role,ketua,peserta|nullable|string|max:180', 'student_number' => 'required_if:role,ketua,peserta|nullable|string|max:80']);
        if ($user && $user->id === Auth::id() && ($v['role'] !== 'admin' || ! $v['active'])) {
            return back()->withErrors(['role' => 'Anda tidak dapat menonaktifkan atau mengganti peran akun sendiri.']);
        }
        if ($user && $user->role !== $v['role'] && (DB::table('absensi')->where('user_id', $user->id)->exists() || DB::table('logbooks')->where('user_id', $user->id)->exists() || DB::table('kelompok')->whereIn('mentor_id', DB::table('mentors')->where('user_id', $user->id)->select('id'))->exists())) {
            return back()->withErrors(['role' => 'Akun sudah memiliki riwayat atau kelompok. Pertahankan peran dan nonaktifkan bila diperlukan.']);
        }
        if ($v['role'] === 'ketua' && DB::table('users')->join('peserta_magang as p', 'p.user_id', 'users.id')->where('p.kelompok_id', $v['kelompok_id'])->where('role', 'ketua')->where('users.id', '!=', $user?->id ?? 0)->exists()) {
            return back()->withErrors(['kelompok_id' => 'Kelompok sudah memiliki ketua.']);
        }
        DB::transaction(function () use ($v, $user) {
            if (in_array($v['role'], ['ketua', 'peserta'])) {
                DB::table('kelompok')->where('id', $v['kelompok_id'])->lockForUpdate()->first();
                if ($v['role'] === 'ketua' && DB::table('users')->join('peserta_magang as p', 'p.user_id', 'users.id')->where('p.kelompok_id', $v['kelompok_id'])->where('role', 'ketua')->where('users.id', '!=', $user?->id ?? 0)->exists()) {
                    throw ValidationException::withMessages(['kelompok_id' => 'Kelompok sudah memiliki ketua.']);
                }
            }$u = $user ?? new User;
            $u->name = $v['name'];
            $u->email = $v['email'];
            $u->role = $v['role'];
            $u->active = $v['active'];
            if (! empty($v['password'])) {
                $u->password = Hash::make($v['password']);
            }$u->save();
            if ($v['role'] === 'mentor') {
                DB::table('peserta_magang')->where('user_id', $u->id)->delete();
                DB::table('mentors')->updateOrInsert(['user_id' => $u->id], ['division' => $v['division'], 'updated_at' => now()]);
            } elseif (in_array($v['role'], ['ketua', 'peserta'])) {
                DB::table('mentors')->where('user_id', $u->id)->delete();
                DB::table('peserta_magang')->updateOrInsert(['user_id' => $u->id], ['kelompok_id' => $v['kelompok_id'], 'institution' => $v['institution'], 'student_number' => $v['student_number'], 'updated_at' => now()]);
            } else {
                DB::table('mentors')->where('user_id', $u->id)->delete();
                DB::table('peserta_magang')->where('user_id', $u->id)->delete();
            }
        });

        return back()->with('success', 'Akun berhasil disimpan.');
    }

    public function deleteUser(User $user)
    {
        $this->admin();
        abort_if($user->id === Auth::id(), 422, 'Akun sendiri tidak dapat dihapus.');
        try {
            $user->delete();
        } catch (QueryException $e) {
            if (! in_array($e->getCode(), ['23000', '23503'])) {
                throw $e;
            }

return back()->withErrors(['user' => 'Akun memiliki riwayat atau kelompok. Gunakan nonaktifkan akun.']);
        }

return back()->with('success', 'Akun dihapus.');
    }

    public function groups()
    {
        $this->admin();

        return view('groups', ['groups' => DB::table('kelompok')->join('mentors', 'mentors.id', 'kelompok.mentor_id')->join('users', 'users.id', 'mentors.user_id')->select('kelompok.*', 'users.name as mentor_name')->get(), 'mentors' => DB::table('mentors')->join('users', 'users.id', 'mentors.user_id')->where('users.active', true)->select('mentors.id', 'users.name')->get()]);
    }

    public function saveGroup(Request $r, ?int $id = null)
    {
        $this->admin();
        if ($id) {
            abort_unless(DB::table('kelompok')->where('id', $id)->exists(), 404);
        }$v = $r->validate(['name' => ['required', 'string', 'max:100', Rule::unique('kelompok')->ignore($id)], 'mentor_id' => 'required|exists:mentors,id', 'start_date' => 'required|date', 'end_date' => 'required|date|after_or_equal:start_date']);
        $v['updated_at'] = now();
        if ($id) {
            DB::table('kelompok')->where('id', $id)->update($v);
        } else {
            DB::table('kelompok')->insert([...$v, 'created_at' => now()]);
        }

return back()->with('success', 'Kelompok disimpan.');
    }

    public function deleteGroup(int $id)
    {
        $this->admin();
        try {
            DB::table('kelompok')->where('id', $id)->delete();
        } catch (QueryException $e) {
            if (! in_array($e->getCode(), ['23000', '23503'])) {
                throw $e;
            }

return back()->withErrors(['group' => 'Kelompok masih memiliki peserta atau riwayat.']);
        }

return back()->with('success', 'Kelompok dihapus.');
    }

    public function applications()
    {
        $this->admin();

        return view('applications', ['items' => DB::table('pengajuan_magang')->latest()->paginate(15)]);
    }

    public function reviewApplication(Request $r, int $id)
    {
        $this->admin();
        $v = $r->validate(['status' => 'required|in:menunggu,ditinjau,diterima,ditolak', 'notes' => 'nullable|string|max:2000']);
        abort_unless(DB::table('pengajuan_magang')->where('id', $id)->exists(), 404);
        DB::table('pengajuan_magang')->where('id', $id)->update([...$v, 'updated_at' => now()]);

        return back()->with('success', 'Status pengajuan diperbarui. Akun peserta dibuat secara terpisah.');
    }

    public function letter(int $id)
    {
        $this->admin();
        $a = DB::table('pengajuan_magang')->find($id);
        abort_unless($a, 404);

        return Storage::disk('local')->download($a->letter_path, 'surat-pengajuan-'.$id.'.pdf', ['Content-Type' => 'application/pdf']);
    }

    public function records(string $kind)
    {
        $table = $kind === 'absensi' ? 'absensi' : 'logbooks';

        return view('records', ['kind' => $kind, 'items' => $this->scope($table)->orderByDesc("$table.date")->paginate(20)]);
    }

    public function storeRecord(Request $r, string $kind)
    {
        $u = Auth::user();
        abort_unless(in_array($u->role, $kind === 'absensi' ? ['peserta', 'ketua'] : ['ketua']), 403);
        $p = DB::table('peserta_magang')->where('user_id', $u->id)->first();
        abort_unless($p, 422, 'Akun belum memiliki kelompok.');
        $g = DB::table('kelompok')->find($p->kelompok_id);
        $v = $r->validate(['date' => 'required|date|before_or_equal:today|after_or_equal:'.$g->start_date.'|before_or_equal:'.$g->end_date, 'description' => 'required|string|min:5|max:5000']);
        if ($kind === 'absensi' && $v['date'] !== now()->toDateString()) {
            return back()->withErrors(['date' => 'Absensi hanya dapat dicatat untuk hari ini.']);
        }$table = $kind === 'absensi' ? 'absensi' : 'logbooks';
        try {
            DB::table($table)->insert([...$v, 'user_id' => $u->id, 'kelompok_id' => $g->id, 'created_at' => now(), 'updated_at' => now()]);
        } catch (QueryException $e) {
            if (! in_array($e->getCode(), ['23000', '23505'])) {
                throw $e;
            }

return back()->withErrors(['date' => 'Catatan tanggal ini sudah ada.']);
        }

return back()->with('success', 'Catatan berhasil dikirim untuk validasi mentor.');
    }

    public function reviewRecord(Request $r, string $kind, int $id)
    {
        abort_unless(Auth::user()->role === 'mentor', 403);
        $table = $kind === 'absensi' ? 'absensi' : 'logbooks';
        abort_unless($this->scope($table)->where("$table.id", $id)->exists(), 403);
        $v = $r->validate(['status' => 'required|in:disetujui,ditolak', 'feedback' => 'required_if:status,ditolak|nullable|string|max:2000']);
        DB::table($table)->where('id', $id)->update([...$v, 'reviewer_id' => Auth::id(), 'reviewed_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Validasi tersimpan.');
    }

    public function report(Request $r)
    {
        abort_unless(in_array(Auth::user()->role, ['admin', 'mentor']), 403);
        $v = $r->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'kind' => 'nullable|in:absensi,logbook']);
        $kind = $v['kind'] ?? 'absensi';
        $table = $kind === 'absensi' ? 'absensi' : 'logbooks';
        $q = $this->scope($table);
        if (! empty($v['from'])) {
            $q->whereDate("$table.date", '>=', $v['from']);
        }if (! empty($v['to'])) {
            $q->whereDate("$table.date", '<=', $v['to']);
        }$items = $q->orderByDesc("$table.date")->get();
        if ($r->boolean('export')) {
            return response()->streamDownload(function () use ($items) {
                $f = fopen('php://output', 'w');
                fwrite($f, "\xEF\xBB\xBF");
                fputcsv($f, ['Tanggal', 'Nama', 'Kelompok', 'Kegiatan', 'Status', 'Catatan mentor']);
                foreach ($items as $x) {
                    fputcsv($f,array_map(fn ($s) => preg_match('/^[=+@\-\t\r]/u',(string) $s) ? "'".$s : $s,[$x->date, $x->author, $x->group_name, $x->description, $x->status, $x->feedback]));
                }fclose($f);
            }, 'monika-'.$kind.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

return view('report',compact('items','kind'));
    }
}
