<?php

namespace Tests\Feature\Training;

use App\Enums\Role;
use App\Jobs\CompressExerciseClip;
use App\Models\Exercise;
use App\Services\Training\ClipTranscoder;
use App\Services\Training\FfmpegClipTranscoder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ExerciseClipTest extends TrainingTestCase
{
    private FakeClipTranscoder $transcoder;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        $this->transcoder = new FakeClipTranscoder;
        $this->app->instance(ClipTranscoder::class, $this->transcoder);
    }

    private function video(string $name = 'clip.mp4', int $kb = 2048, string $mime = 'video/mp4'): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kb, $mime);
    }

    public function test_upload_stores_raw_privately_marks_processing_and_queues_the_job(): void
    {
        Queue::fake();
        $trainer = $this->user($this->iron, Role::Trainer);
        $exercise = $this->exercise($this->iron);

        $this->actingAs($trainer)
            ->post($this->url("/panel/ejercicios/{$exercise->id}/clip"), ['clip' => $this->video('grabacion.mov', 4096, 'video/quicktime')])
            ->assertRedirect()->assertSessionHas('success');

        $this->assertSame(Exercise::CLIP_PROCESSING, $exercise->fresh()->clip_status);

        $raw = Storage::disk('local')->allFiles("gyms/{$this->iron->id}/clips/raw");
        $this->assertCount(1, $raw);
        $this->assertSame([], Storage::disk('public')->allFiles());

        Queue::assertPushed(CompressExerciseClip::class, fn (CompressExerciseClip $job) => $job->exerciseId === $exercise->id
            && $job->gymId === $this->iron->id
            && $job->rawPath === $raw[0]);
    }

    public function test_upload_end_to_end_with_sync_queue_leaves_a_ready_clip(): void
    {
        $admin = $this->user($this->iron, Role::GymAdmin);
        $exercise = $this->exercise($this->iron);

        $this->actingAs($admin)
            ->post($this->url("/panel/ejercicios/{$exercise->id}/clip"), ['clip' => $this->video()])
            ->assertRedirect();

        $exercise = Exercise::withoutGlobalScopes()->find($exercise->id);
        $this->assertSame(Exercise::CLIP_READY, $exercise->clip_status);
        Storage::disk('public')->assertExists($exercise->clip_path);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_rejects_invalid_type_and_size(): void
    {
        Queue::fake();
        $trainer = $this->user($this->iron, Role::Trainer);
        $exercise = $this->exercise($this->iron);
        $url = $this->url("/panel/ejercicios/{$exercise->id}/clip");

        $this->actingAs($trainer)->post($url, ['clip' => UploadedFile::fake()->create('foto.png', 100, 'image/png')])
            ->assertSessionHasErrors('clip');
        $this->actingAs($trainer)->post($url, ['clip' => $this->video('largo.mp4', 51 * 1024)])
            ->assertSessionHasErrors('clip');
        $this->actingAs($trainer)->post($url, [])->assertSessionHasErrors('clip');

        Queue::assertNothingPushed();
        $this->assertSame(Exercise::CLIP_NONE, $exercise->fresh()->clip_status);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_member_cannot_upload_and_trainer_cannot_upload_to_another_gym(): void
    {
        Queue::fake();
        $member = $this->user($this->iron);
        $trainer = $this->user($this->iron, Role::Trainer);
        $own = $this->exercise($this->iron);
        $foreign = $this->exercise($this->titan);

        $this->actingAs($member)->post($this->url("/panel/ejercicios/{$own->id}/clip"), ['clip' => $this->video()])->assertForbidden();
        $this->actingAs($trainer)->post($this->url("/panel/ejercicios/{$foreign->id}/clip"), ['clip' => $this->video()])->assertNotFound();

        Queue::assertNothingPushed();
    }

    public function test_job_saves_mp4_deletes_raw_and_marks_ready(): void
    {
        $exercise = $this->exercise($this->iron);
        $raw = "gyms/{$this->iron->id}/clips/raw/original.mov";
        Storage::disk('local')->put($raw, 'video crudo');

        $this->inGym($this->iron, fn () => CompressExerciseClip::dispatchSync($exercise->id, $raw));

        $exercise = Exercise::withoutGlobalScopes()->find($exercise->id);
        $this->assertSame(Exercise::CLIP_READY, $exercise->clip_status);
        $this->assertStringStartsWith("gyms/{$this->iron->id}/clips/", $exercise->clip_path);
        $this->assertStringEndsWith('.mp4', $exercise->clip_path);
        Storage::disk('public')->assertExists($exercise->clip_path);
        $this->assertSame(1000, $exercise->clip_size_bytes);
        Storage::disk('local')->assertMissing($raw);
        $this->assertSame([30], $this->transcoder->crfs);
        $this->assertSame(Storage::disk('local')->path($raw), $this->transcoder->inputs[0]);
    }

    public function test_job_re_encodes_with_higher_crf_when_too_heavy(): void
    {
        config(['gympro.ffmpeg.max_output_bytes' => 500]);
        $this->transcoder->sizes = [30 => 900, 34 => 700, 38 => 400];
        $exercise = $this->exercise($this->iron);
        $raw = "gyms/{$this->iron->id}/clips/raw/original.mp4";
        Storage::disk('local')->put($raw, 'x');

        $this->inGym($this->iron, fn () => CompressExerciseClip::dispatchSync($exercise->id, $raw));

        $this->assertSame([30, 34, 38], $this->transcoder->crfs);
        $this->assertSame(400, Exercise::withoutGlobalScopes()->find($exercise->id)->clip_size_bytes);
    }

    public function test_job_replaces_the_previous_clip(): void
    {
        $exercise = $this->exercise($this->iron);
        $old = "gyms/{$this->iron->id}/clips/viejo.mp4";
        Storage::disk('public')->put($old, 'viejo');
        Exercise::withoutGlobalScopes()->whereKey($exercise->id)->update(['clip_path' => $old, 'clip_status' => Exercise::CLIP_PROCESSING]);
        $raw = "gyms/{$this->iron->id}/clips/raw/nuevo.mp4";
        Storage::disk('local')->put($raw, 'x');

        $this->inGym($this->iron, fn () => CompressExerciseClip::dispatchSync($exercise->id, $raw));

        $exercise = Exercise::withoutGlobalScopes()->find($exercise->id);
        $this->assertNotSame($old, $exercise->clip_path);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertExists($exercise->clip_path);
    }

    public function test_job_failure_marks_failed_and_leaves_no_garbage(): void
    {
        $this->transcoder->fail = true;
        $exercise = $this->exercise($this->iron);
        $old = "gyms/{$this->iron->id}/clips/anterior.mp4";
        Storage::disk('public')->put($old, 'anterior');
        Exercise::withoutGlobalScopes()->whereKey($exercise->id)->update(['clip_path' => $old, 'clip_status' => Exercise::CLIP_PROCESSING]);
        $raw = "gyms/{$this->iron->id}/clips/raw/roto.mp4";
        Storage::disk('local')->put($raw, 'x');

        $this->inGym($this->iron, fn () => CompressExerciseClip::dispatchSync($exercise->id, $raw));

        $exercise = Exercise::withoutGlobalScopes()->find($exercise->id);
        $this->assertSame(Exercise::CLIP_FAILED, $exercise->clip_status);
        Storage::disk('local')->assertMissing($raw);
        // El clip anterior se conserva; no queda ningún archivo nuevo
        $this->assertSame([$old], Storage::disk('public')->allFiles());
        $this->assertSame($old, $exercise->clip_path);
        foreach ($this->transcoder->outputs as $output) {
            $this->assertFileDoesNotExist($output);
        }
    }

    public function test_job_cleans_raw_when_exercise_was_deleted(): void
    {
        $exercise = $this->exercise($this->iron);
        $raw = "gyms/{$this->iron->id}/clips/raw/huerfano.mp4";
        Storage::disk('local')->put($raw, 'x');

        $this->inGym($this->iron, function () use ($exercise, $raw) {
            $job = new CompressExerciseClip($exercise->id, $raw);
            $exercise->delete();
            dispatch_sync($job);
        });

        Storage::disk('local')->assertMissing($raw);
        $this->assertSame([], $this->transcoder->crfs);
    }

    public function test_ffmpeg_arguments_follow_the_spec(): void
    {
        $args = (new FfmpegClipTranscoder('ffmpeg', 60))->arguments('/in.mov', '/out.mp4', 30);
        $line = implode(' ', $args);

        $this->assertContains('-an', $args);
        $this->assertStringContainsString('-t 6', $line);
        $this->assertStringContainsString('-c:v libx264', $line);
        $this->assertStringContainsString('-pix_fmt yuv420p', $line);
        $this->assertStringContainsString('-movflags +faststart', $line);
        $this->assertStringContainsString('-crf 30', $line);
        $this->assertStringContainsString('480', $line);
    }

    public function test_real_ffmpeg_compresses_a_clip(): void
    {
        $binary = (new ExecutableFinder)->find((string) config('gympro.ffmpeg.binary', 'ffmpeg'));
        if (! $binary) {
            $this->markTestSkipped('FFmpeg no está instalado en esta máquina.');
        }

        $dir = sys_get_temp_dir().'/gympro-ffmpeg-test-'.uniqid();
        mkdir($dir);
        $input = "{$dir}/in.mp4";
        $output = "{$dir}/out.mp4";

        // Video vertical de 8 s con audio, como uno grabado en el celular
        (new Process([$binary, '-nostdin', '-y', '-loglevel', 'error',
            '-f', 'lavfi', '-i', 'testsrc=size=1080x1920:rate=30:duration=8',
            '-f', 'lavfi', '-i', 'sine=duration=8',
            '-c:v', 'libx264', '-c:a', 'aac', '-shortest', $input]))->mustRun();

        try {
            (new FfmpegClipTranscoder($binary, 120))->transcode($input, $output, 30);
            $this->assertFileExists($output);

            $ffprobe = (new ExecutableFinder)->find((string) config('gympro.ffmpeg.ffprobe', 'ffprobe'));
            if ($ffprobe) {
                $probe = new Process([$ffprobe, '-v', 'error', '-show_entries', 'stream=codec_type,width,height,pix_fmt:format=duration', '-of', 'json', $output]);
                $info = json_decode($probe->mustRun()->getOutput(), true);
                $this->assertCount(1, $info['streams']); // sin audio
                $this->assertSame(480, $info['streams'][0]['width']);
                $this->assertSame(0, $info['streams'][0]['height'] % 2);
                $this->assertSame('yuv420p', $info['streams'][0]['pix_fmt']);
                $this->assertLessThanOrEqual(6.1, (float) $info['format']['duration']);
            }
        } finally {
            @unlink($input);
            @unlink($output);
            @rmdir($dir);
        }
    }
}

/** Transcodificador de prueba: escribe N bytes según el CRF o falla. */
class FakeClipTranscoder implements ClipTranscoder
{
    public array $crfs = [];

    public array $inputs = [];

    public array $outputs = [];

    /** @var array<int, int> */
    public array $sizes = [];

    public bool $fail = false;

    public function transcode(string $inputPath, string $outputPath, int $crf): void
    {
        $this->crfs[] = $crf;
        $this->inputs[] = $inputPath;
        $this->outputs[] = $outputPath;

        if ($this->fail) {
            file_put_contents($outputPath, 'parcial');
            throw new RuntimeException('códec no soportado');
        }

        file_put_contents($outputPath, str_repeat('x', $this->sizes[$crf] ?? 1000));
    }
}
