<?php

namespace Tests\Feature\Admin;

use App\Models\Attendance;
use App\Models\Gym;
use App\Models\User;
use App\Services\Saas\LogReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

class SystemAndLogsTest extends AdminTestCase
{
    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Simula Redis y Meilisearch caídos: puertos donde no hay nada
        config([
            'database.redis.client' => 'predis',
            'database.redis.default.host' => '127.0.0.1',
            'database.redis.default.port' => 1,
            'scout.meilisearch.host' => 'http://127.0.0.1:1',
        ]);
        Http::fake(['*' => Http::failedConnection()]);
        Storage::fake('public');

        $this->logPath = storage_path('logs/laravel.log');
    }

    public function test_system_page_works_without_redis_or_meilisearch(): void
    {
        $gym = Gym::factory()->create(['slug' => 'irondemo']);
        $member = User::factory()->forGym($gym)->create();
        $attendance = new Attendance(['user_id' => $member->id, 'scanned_at' => now(), 'method' => 'qr']);
        $attendance->gym_id = $gym->id;
        $attendance->save();
        Storage::disk('public')->put("gyms/{$gym->id}/clips/a.mp4", str_repeat('x', 2048));

        $this->actingAs($this->super)->get($this->url('/admin/sistema'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/System')
                ->where('figures.active_gyms', 1)
                ->where('figures.members', 1)
                ->where('figures.scans_today', 1)
                ->where('storage.0.bytes', 2048)
                ->where('checks', fn ($checks) => collect($checks)->firstWhere('key', 'database')['ok'] === true
                    && collect($checks)->firstWhere('key', 'redis')['ok'] === false
                    && collect($checks)->firstWhere('key', 'meilisearch')['ok'] === false));
    }

    public function test_dashboard_works_without_redis(): void
    {
        Gym::factory()->create(['saas_paid_until' => today()->subDay()]);

        $this->actingAs($this->super)->get($this->url('/admin'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Dashboard')
                ->has('overdue', 1)
                ->has('alerts'));
    }

    public function test_log_reader_parses_the_tail_of_the_log(): void
    {
        $path = storage_path('framework/testing/laravel-test-'.Str::random(8).'.log');
        @mkdir(dirname($path), 0755, true);

        // Relleno grande para que el archivo pase de 512 KB
        $filler = '';
        for ($i = 0; $i < 6000; $i++) {
            $filler .= "[2026-11-01 10:00:00] local.INFO: relleno {$i} ".str_repeat('x', 80)."\n";
        }
        file_put_contents($path, $filler
            ."[2026-11-16 09:00:00] local.ERROR: Falló el pago {\"exception\":\"[object] (RuntimeException(code: 0): Falló el pago at /app/X.php:10)\n"
            ."[stacktrace]\n#0 /app/Y.php(20): foo()\n#1 {main}\n\"}\n"
            ."[2026-11-16 10:00:00] local.WARNING: Algo raro\n");

        $reader = new LogReader($path);
        $entries = $reader->latest();

        $this->assertGreaterThan(LogReader::MAX_BYTES, $reader->size());
        $this->assertCount(LogReader::MAX_ENTRIES, $entries);
        $this->assertSame('WARNING', $entries[0]['level']);
        $this->assertSame('Algo raro', $entries[0]['message']);
        $this->assertSame('ERROR', $entries[1]['level']);
        $this->assertSame('Falló el pago', $entries[1]['message']);
        $this->assertStringContainsString('#0 /app/Y.php(20)', $entries[1]['trace']);

        unlink($path);
    }

    public function test_logs_page_lists_failed_jobs_and_can_retry_and_forget(): void
    {
        $retry = (string) Str::uuid();
        $forget = (string) Str::uuid();

        foreach ([$retry, $forget] as $uuid) {
            DB::table('failed_jobs')->insert([
                'uuid' => $uuid,
                'connection' => 'database',
                'queue' => 'default',
                'payload' => json_encode(['uuid' => $uuid, 'displayName' => 'App\\Jobs\\CompressExerciseClip', 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call', 'data' => [], 'attempts' => 3]),
                'exception' => "RuntimeException: FFmpeg no respondió\n#0 /app/Job.php(1): run()",
                'failed_at' => now(),
            ]);
        }

        $this->actingAs($this->super)->get($this->url('/admin/errores'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Logs')
                ->has('failedJobs', 2)
                ->where('failedJobs.0.job', 'CompressExerciseClip')
                ->where('failedJobs.0.message', 'RuntimeException: FFmpeg no respondió'));

        $this->actingAs($this->super)->post($this->url("/admin/errores/trabajos/{$retry}/reintentar"))->assertSessionHas('success');
        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $retry]);
        $this->assertSame(1, DB::table('jobs')->count());

        $this->actingAs($this->super)->delete($this->url("/admin/errores/trabajos/{$forget}"))->assertSessionHas('success');
        $this->assertDatabaseMissing('failed_jobs', ['uuid' => $forget]);

        // uuid inexistente → 404; algo que no es uuid → la ruta no existe
        $this->actingAs($this->super)->delete($this->url('/admin/errores/trabajos/'.Str::uuid()))->assertNotFound();
        $this->actingAs($this->super)->delete($this->url('/admin/errores/trabajos/..%2F..%2F.env'))->assertNotFound();
    }
}
