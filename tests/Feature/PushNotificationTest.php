<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\JournalEntry;
use App\Models\PushSubscription;
use App\Models\Trip;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Push\Announcer;
use App\Services\Push\PushMessage;
use App\Services\Push\PushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Avisos en el móvil: quién se da de alta, a quién se avisa y qué pasa cuando
 * el envío falla.
 *
 * Aquí NO se manda nada de verdad: el que habla con Apple y Google se cambia
 * por un doble. Lo que se comprueba es la decisión de a quién avisar y con qué
 * palabras, que es lo que se puede equivocar.
 */
class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;

    private User $ana;

    private User $bea;

    private GroupMember $anaMember;

    private GroupMember $beaMember;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('trayectos.push.public_key', 'clave-publica-de-pruebas');
        config()->set('trayectos.push.private_key', 'clave-privada-de-pruebas');

        $this->group = Group::factory()->create();

        $this->ana = User::factory()->create(['name' => 'Ana']);
        $this->bea = User::factory()->create(['name' => 'Bea']);

        $this->anaMember = GroupMember::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->ana->id,
        ]);

        $this->beaMember = GroupMember::factory()->create([
            'group_id' => $this->group->id,
            'user_id' => $this->bea->id,
        ]);
    }

    private function suscripcion(User $user, string $endpoint = 'https://push.example/abc'): PushSubscription
    {
        return PushSubscription::create([
            'user_id' => $user->id,
            'endpoint' => $endpoint,
            'endpoint_hash' => PushSubscription::hash($endpoint),
            'public_key' => 'p256dh-de-pruebas',
            'auth_token' => 'auth-de-pruebas',
        ]);
    }

    public function test_el_navegador_se_da_de_alta(): void
    {
        $this->actingAs($this->ana)
            ->postJson(route('push.store'), [
                'endpoint' => 'https://web.push.apple.com/abc123',
                'keys' => ['p256dh' => 'clave-del-navegador', 'auth' => 'secreto-del-navegador'],
            ])
            ->assertOk();

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $this->ana->id,
            'endpoint' => 'https://web.push.apple.com/abc123',
        ]);
    }

    public function test_darse_de_alta_dos_veces_no_duplica(): void
    {
        // El navegador renueva la suscripción y devuelve el mismo endpoint
        foreach (['clave-vieja', 'clave-nueva'] as $clave) {
            $this->actingAs($this->ana)->postJson(route('push.store'), [
                'endpoint' => 'https://web.push.apple.com/abc123',
                'keys' => ['p256dh' => $clave, 'auth' => 'secreto'],
            ])->assertOk();
        }

        $this->assertSame(1, PushSubscription::count());
        $this->assertSame('clave-nueva', PushSubscription::first()->public_key);
    }

    public function test_nadie_puede_borrar_la_suscripcion_de_otro(): void
    {
        /*
         * El endpoint lo manda el navegador, así que no vale como permiso:
         * quien lo conozca podría dejar sin avisos a otra persona.
         */
        $suya = $this->suscripcion($this->bea, 'https://push.example/de-bea');

        $this->actingAs($this->ana)
            ->deleteJson(route('push.destroy'), ['endpoint' => 'https://push.example/de-bea'])
            ->assertOk();

        $this->assertDatabaseHas('push_subscriptions', ['id' => $suya->id]);
    }

    public function test_sin_claves_configuradas_no_se_ofrece_ni_se_manda(): void
    {
        config()->set('trayectos.push.public_key', null);
        config()->set('trayectos.push.private_key', null);

        $this->assertFalse(PushNotifier::configured());

        $this->actingAs($this->ana)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Avisos en el móvil');

        // Y la ruta de alta ni existe
        $this->actingAs($this->ana)
            ->postJson(route('push.store'), [
                'endpoint' => 'https://push.example/abc',
                'keys' => ['p256dh' => 'x', 'auth' => 'y'],
            ])
            ->assertNotFound();
    }

    public function test_el_interruptor_sale_cuando_hay_claves(): void
    {
        $this->actingAs($this->ana)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Avisos en el móvil')
            ->assertSee('clave-publica-de-pruebas');
    }

    public function test_no_se_avisa_a_quien_ha_hecho_la_cosa(): void
    {
        /*
         * Recibir un aviso de tu propio viaje es la forma más rápida de que
         * alguien apague los avisos el primer día.
         */
        $this->suscripcion($this->ana);

        $notifier = new PushNotifier;

        $enviados = $notifier->notify(
            [$this->ana],
            new PushMessage('Da igual', 'Da igual'),
            exceptUserId: $this->ana->id,
        );

        $this->assertSame(0, $enviados);
    }

    public function test_a_quien_no_tiene_el_movil_dado_de_alta_no_se_le_manda_nada(): void
    {
        $notifier = new PushNotifier;

        $this->assertSame(0, $notifier->notify([$this->bea], new PushMessage('Hola', 'Qué tal')));
    }

    /** Un doble que se queda con lo que se le pide mandar, para poder mirarlo. */
    private function espia(array &$avisos): PushNotifier
    {
        $push = Mockery::mock(PushNotifier::class);

        $push->shouldReceive('notify')->andReturnUsing(
            function (array $users, PushMessage $message, ?int $except) use (&$avisos) {
                $avisos[] = ['users' => $users, 'message' => $message, 'except' => $except];

                return count($users);
            }
        );

        return $push;
    }

    public function test_un_pago_avisa_a_quien_cobra_con_el_importe(): void
    {
        $avisos = [];

        (new Announcer($this->espia($avisos)))->settlementRecorded(
            from: $this->anaMember,
            to: $this->beaMember,
            amountCents: 1250,
            byUserId: $this->ana->id,
        );

        $this->assertCount(1, $avisos);
        $this->assertTrue($avisos[0]['users'][0]->is($this->bea));
        $this->assertSame('Ana te ha pagado 12,50 €', $avisos[0]['message']->title);
        $this->assertSame($this->ana->id, $avisos[0]['except']);
    }

    public function test_un_viaje_avisa_a_cada_uno_con_lo_que_le_toca(): void
    {
        /*
         * «Ana apuntó un viaje» obliga a abrir la aplicación para saber si son
         * dos euros o veinte, así que el importe va en el propio aviso. Y como
         * cada uno paga lo suyo, el aviso se compone por cabeza.
         */
        $trip = $this->viajeConAsiento(anaCents: -800, beaCents: -450);

        $avisos = [];
        (new Announcer($this->espia($avisos)))->tripRecorded($trip, byUserId: $this->ana->id);

        // Ana lo apuntó: a ella no se le avisa de su propio viaje
        $this->assertCount(1, $avisos);
        $this->assertTrue($avisos[0]['users'][0]->is($this->bea));
        $this->assertSame('Ana apuntó Málaga → Granada', $avisos[0]['message']->title);
        $this->assertSame('Te toca poner 4,50 €.', $avisos[0]['message']->body);
        // Al tocarlo se abre el viaje, no la portada
        $this->assertStringContainsString("/viajes/{$trip->id}", $avisos[0]['message']->url);
    }

    public function test_anular_un_viaje_tambien_avisa(): void
    {
        $trip = $this->viajeConAsiento(anaCents: -800, beaCents: -450);

        $avisos = [];
        (new Announcer($this->espia($avisos)))->tripCancelled($trip, byUserId: $this->ana->id);

        $this->assertCount(1, $avisos);
        $this->assertSame('Ana anuló Málaga → Granada', $avisos[0]['message']->title);
        $this->assertStringContainsString('Ya no cuenta en tu saldo', $avisos[0]['message']->body);
    }

    /** Un viaje de Ana conduciendo, con Bea de acompañante, ya repartido. */
    private function viajeConAsiento(int $anaCents, int $beaCents): Trip
    {
        $entry = JournalEntry::create([
            'group_id' => $this->group->id,
            'kind' => 'trip',
            'description' => 'Málaga → Granada',
            'occurred_on' => now()->toDateString(),
        ]);

        foreach ([$this->anaMember->id => $anaCents, $this->beaMember->id => $beaCents] as $memberId => $cents) {
            $entry->lines()->create(['group_member_id' => $memberId, 'amount_cents' => $cents]);
        }

        // La línea que cuadra el asiento: lo que el grupo le debe a quien pagó
        $entry->lines()->create([
            'group_member_id' => $this->anaMember->id,
            'amount_cents' => -($anaCents + $beaCents),
        ]);

        $trip = Trip::create([
            'group_id' => $this->group->id,
            'vehicle_id' => Vehicle::factory()->create(['owner_id' => $this->ana->id])->id,
            'driver_member_id' => $this->anaMember->id,
            'travelled_on' => now()->toDateString(),
            'origin_label' => 'Málaga',
            'destination_label' => 'Granada',
            'distance_m' => 125_000,
            'total_cost_cents' => 1250,
            'cost_inputs' => [],
            'formula_version' => 1,
            'journal_entry_id' => $entry->id,
        ]);

        $trip->passengers()->create(['group_member_id' => $this->anaMember->id, 'weight' => 1.00]);
        $trip->passengers()->create(['group_member_id' => $this->beaMember->id, 'weight' => 1.00]);

        return $trip;
    }

    public function test_apuntar_un_viaje_no_se_rompe_aunque_el_aviso_falle(): void
    {
        /*
         * Lo importante es el viaje; el aviso es un extra. Si Apple no
         * contesta o la librería revienta, el viaje tiene que quedar apuntado
         * igual — por eso el envío va envuelto y no lanza nada.
         */
        $this->suscripcion($this->bea);

        config()->set('trayectos.push.subject', 'esto-no-es-un-mailto-ni-una-url');

        $notifier = new PushNotifier;

        $enviados = $notifier->notify([$this->bea], new PushMessage('Hola', 'Qué tal'));

        $this->assertSame(0, $enviados);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }
}
