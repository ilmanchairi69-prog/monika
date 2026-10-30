<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $t) {
            $t->string('name')->primary();
        });
        foreach (['admin', 'mentor', 'ketua', 'peserta'] as $r) {
            DB::table('roles')->insert(['name' => $r]);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('peserta');
            $t->foreign('role')->references('name')->on('roles');
            $t->boolean('active')->default(true);
        });
        Schema::create('mentors', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('division');
            $t->timestamps();
        });
        Schema::create('kelompok', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->foreignId('mentor_id')->constrained('mentors')->restrictOnDelete();
            $t->date('start_date');
            $t->date('end_date');
            $t->timestamps();
        });
        Schema::create('peserta_magang', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('kelompok_id')->constrained('kelompok')->restrictOnDelete();
            $t->string('institution');
            $t->string('student_number');
            $t->timestamps();
        });
        Schema::create('pengajuan_magang', function (Blueprint $t) {
            $t->id();
            $t->uuid('reference')->unique();
            $t->string('name');
            $t->string('email');
            $t->string('phone');
            $t->string('institution');
            $t->date('start_date');
            $t->date('end_date');
            $t->string('letter_path');
            $t->string('status')->default('menunggu');
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        foreach (['absensi', 'logbooks'] as $table) {
            Schema::create($table, function (Blueprint $t) use ($table) {
                $t->id();
                $t->foreignId('user_id')->constrained()->restrictOnDelete();
                $t->foreignId('kelompok_id')->constrained('kelompok')->restrictOnDelete();
                $t->date('date');
                $t->text('description');
                $t->string('status')->default('menunggu');
                $t->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamp('reviewed_at')->nullable();
                $t->text('feedback')->nullable();
                $t->timestamps();
                $t->unique([$table === 'absensi' ? 'user_id' : 'kelompok_id', 'date']);
            });
        }
    }

    public function down(): void
    {
        foreach (['logbooks', 'absensi', 'pengajuan_magang', 'peserta_magang', 'kelompok', 'mentors'] as $t) {
            Schema::dropIfExists($t);
        }Schema::table('users', function (Blueprint $t) {
            $t->dropForeign(['role']);
            $t->dropColumn(['role', 'active']);
        });
        Schema::dropIfExists('roles');
    }
};
