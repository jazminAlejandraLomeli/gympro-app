<?php

namespace Tests\Feature\Membership;

use App\Enums\Role;
use App\Models\Attendance;
use App\Models\User;
use App\Services\Membership\QrSigner;
use Illuminate\Support\Str;

class AccessScanTest extends MembershipTestCase
{
    public function test_active_member_is_granted_and_attendance_is_recorded(): void
    {
        $m = $this->member($this->iron, ['expires_on' => today()->addDays(10)], ['name' => 'Ana López']);

        $this->scan($this->payload($m))
            ->assertOk()
            ->assertJson([
                'status' => 'granted',
                'member' => ['name' => 'Ana López', 'initial' => 'A'],
                'expires_on' => today()->addDays(10)->toDateString(),
            ]);

        $this->assertSame(1, Attendance::withoutGlobalScopes()->where('user_id', $m->user_id)->where('method', 'qr')->count());
        $this->assertSame($this->iron->id, Attendance::withoutGlobalScopes()->first()->gym_id);
    }

    public function test_member_expiring_today_is_still_granted(): void
    {
        $m = $this->member($this->iron, ['expires_on' => today()]);

        $this->scan($this->payload($m))->assertJson(['status' => 'granted']);
    }

    public function test_tampered_signature_is_invalid(): void
    {
        $m = $this->member($this->iron, ['expires_on' => today()->addMonth()]);
        $payload = $this->payload($m);
        $tampered = substr($payload, 0, -1).(str_ends_with($payload, 'A') ? 'B' : 'A');

        $this->scan($tampered)->assertOk()->assertJson(['status' => 'invalid', 'member' => null]);
        $this->assertSame(0, Attendance::withoutGlobalScopes()->count());
    }

    public function test_made_up_codes_are_invalid(): void
    {
        $this->member($this->iron, ['expires_on' => today()->addMonth()]);

        foreach (['hola', 'gp1.'.Str::uuid().'.AAAAAAAAAAAAAAAAAAAAAA', 'gp1..', str_repeat('x', 150)] as $code) {
            $this->scan($code)->assertJson(['status' => 'invalid']);
        }

        // Firma válida pero token que no existe
        $token = (string) Str::uuid();
        $valid = 'gp1.'.$token.'.'.app(QrSigner::class)->sign($this->iron->id, $token);
        $this->scan($valid)->assertJson(['status' => 'invalid']);
    }

    public function test_qr_from_another_gym_is_invalid(): void
    {
        $titanMember = $this->member($this->titan, ['expires_on' => today()->addMonth()]);

        $this->scan($this->payload($titanMember))->assertJson(['status' => 'invalid']);

        // Mismo token re-firmado con el gym_id de Iron: el token no existe en Iron
        $resigned = 'gp1.'.$titanMember->qr_token.'.'.app(QrSigner::class)->sign($this->iron->id, $titanMember->qr_token);
        $this->scan($resigned)->assertJson(['status' => 'invalid']);

        // En su propio gimnasio sí entra
        $this->scan($this->payload($titanMember), $this->titanAdmin, 'titan')->assertJson(['status' => 'granted']);
    }

    public function test_expired_member_is_denied(): void
    {
        $m = $this->member($this->iron, ['expires_on' => today()->subDay(), 'credits' => 0]);

        $this->scan($this->payload($m))
            ->assertJson(['status' => 'denied', 'message' => 'ACCESO DENEGADO - MEMBRESÍA VENCIDA']);
        $this->assertSame(0, Attendance::withoutGlobalScopes()->count());
    }

    public function test_credits_are_deducted_only_once_on_double_scan(): void
    {
        $m = $this->member($this->iron, ['expires_on' => today()->subMonth(), 'credits' => 5]);
        $payload = $this->payload($m);

        $this->scan($payload)->assertJson(['status' => 'granted', 'credits' => 4, 'credits_used' => 1, 'already_registered' => false]);
        $this->scan($payload)->assertJson(['status' => 'granted', 'already_registered' => true, 'credits_used' => 0]);

        $this->assertSame(4, $this->fresh($m)->credits);
        $attendances = Attendance::withoutGlobalScopes()->where('user_id', $m->user_id)->get();
        $this->assertCount(1, $attendances);
        $this->assertSame(1, $attendances->first()->credits_used);
    }

    public function test_last_credit_then_denied(): void
    {
        $m = $this->member($this->iron, ['credits' => 1]);

        $this->scan($this->payload($m))->assertJson(['status' => 'granted', 'credits' => 0]);
        $this->assertSame(0, $this->fresh($m)->credits);

        $this->travel(3)->minutes(); // pasa el candado anti doble escaneo
        $this->scan($this->payload($m))->assertJson(['status' => 'denied']);
    }

    public function test_date_access_does_not_spend_credits(): void
    {
        $m = $this->member($this->iron, ['expires_on' => today()->addDays(3), 'credits' => 4]);

        $this->scan($this->payload($m))->assertJson(['status' => 'granted', 'credits_used' => 0]);
        $this->assertSame(4, $this->fresh($m)->credits);
    }

    public function test_renewal_extends_date_and_invalidates_cache(): void
    {
        $m = $this->member($this->iron, ['expires_on' => today()->subDays(5)]);

        // Primer escaneo: denegado y queda en caché
        $this->scan($this->payload($m))->assertJson(['status' => 'denied']);

        $this->actingAs($this->ironAdmin)
            ->post($this->url("/panel/socios/{$m->id}/pagos"), ['amount' => 550, 'method' => 'efectivo', 'months_added' => 1])
            ->assertRedirect()
            ->assertSessionHas('success');

        // Vencido → max(hoy, vencimiento) + 1 mes = hoy + 1 mes
        $this->assertSame(today()->addMonthNoOverflow()->toDateString(), $this->fresh($m)->expires_on->toDateString());

        $this->scan($this->payload($m))->assertJson(['status' => 'granted']);
    }

    public function test_renewal_of_active_member_extends_from_current_expiry(): void
    {
        $m = $this->member($this->iron, ['expires_on' => today()->addDays(10)]);

        $this->actingAs($this->ironAdmin)
            ->post($this->url("/panel/socios/{$m->id}/pagos"), ['amount' => 900, 'method' => 'tarjeta', 'months_added' => 2, 'credits_added' => 5])
            ->assertSessionHasNoErrors();

        $fresh = $this->fresh($m);
        $this->assertSame(today()->addDays(10)->addMonthsNoOverflow(2)->toDateString(), $fresh->expires_on->toDateString());
        $this->assertSame(5, $fresh->credits);
        $this->assertDatabaseHas('member_payments', [
            'user_id' => $m->user_id, 'gym_id' => $this->iron->id, 'method' => 'tarjeta',
            'months_added' => 2, 'credits_added' => 5, 'recorded_by' => $this->ironAdmin->id,
        ]);
    }

    public function test_regenerated_qr_invalidates_old_code(): void
    {
        $m = $this->member($this->iron, ['expires_on' => today()->addMonth()]);
        $old = $this->payload($m);

        $this->scan($old)->assertJson(['status' => 'granted']); // token cacheado

        $this->actingAs($this->ironAdmin)->post($this->url("/panel/socios/{$m->id}/qr"))->assertRedirect();

        $this->scan($old)->assertJson(['status' => 'invalid']);
        $this->scan($this->payload($this->fresh($m)))->assertJson(['status' => 'granted', 'already_registered' => true]);
    }

    public function test_manual_check_in(): void
    {
        $m = $this->member($this->iron, ['expires_on' => today()->addMonth()]);

        $this->actingAs($this->ironAdmin)
            ->postJson($this->url('/panel/asistencia/manual'), ['user_id' => $m->user_id])
            ->assertOk()->assertJson(['status' => 'granted']);

        $this->assertSame('manual', Attendance::withoutGlobalScopes()->first()->method);
    }

    public function test_manual_check_in_rejects_user_from_another_gym(): void
    {
        $titanMember = $this->member($this->titan, ['expires_on' => today()->addMonth()]);

        $this->actingAs($this->ironAdmin)
            ->postJson($this->url('/panel/asistencia/manual'), ['user_id' => $titanMember->user_id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_id');

        $this->assertSame(0, Attendance::withoutGlobalScopes()->count());
    }

    public function test_member_cannot_scan(): void
    {
        $m = $this->member($this->iron, ['expires_on' => today()->addMonth()]);
        $member = User::withoutGlobalScopes()->find($m->user_id);

        $this->scan($this->payload($m), $member)->assertForbidden();
    }

    public function test_trainer_cannot_scan(): void
    {
        $trainer = User::factory()->forGym($this->iron)->role(Role::Trainer)->create();
        $m = $this->member($this->iron, ['expires_on' => today()->addMonth()]);

        $this->scan($this->payload($m), $trainer)->assertForbidden();
    }

    public function test_scan_is_rate_limited(): void
    {
        for ($i = 0; $i < 90; $i++) {
            $this->scan('x')->assertOk();
        }

        $this->scan('x')->assertTooManyRequests();
    }
}
