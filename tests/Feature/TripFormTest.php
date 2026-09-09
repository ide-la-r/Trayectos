<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_buscador_invalida_las_coordenadas_al_teclear_no_al_cambiar_el_texto(): void
    {
        /*
         * Guardián de un fallo que dejó el formulario inservible sin dar la
         * cara: la invalidación colgaba de un $watch sobre el texto, así que
         * elegir un sitio de la lista —que cambia el texto— borraba las
         * coordenadas que se acababan de guardar, en el microtask siguiente. El
         * formulario contestaba «elige origen y destino del buscador» con el
         * origen y el destino puestos.
         *
         * Esto comprueba el cableado y no el comportamiento: el proyecto no
         * tiene con qué ejecutar Alpine en un test, y montarlo por un
         * componente no compensa. El comportamiento se verificó en el navegador
         * —coordenadas presentes tras elegir, vacías tras teclear—; esto sólo
         * evita que se vuelva al mecanismo que estaba roto.
         */
        $user = User::factory()->create();
        $group = Group::factory()->create(['created_by' => $user->id]);
        GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $user->id, 'role' => 'admin']);
        Vehicle::factory()->create(['owner_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('trips.create', $group))
            ->assertOk()
            ->assertSee('@input="onInput($event.target.value)"', false);
    }
}
