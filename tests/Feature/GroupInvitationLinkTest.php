<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupInvitationLinkTest extends TestCase
{
    use RefreshDatabase;

    private function grupoCon(User $dueno): Group
    {
        $group = Group::factory()->create(['created_by' => $dueno->id]);
        GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $dueno->id, 'role' => 'admin']);

        return $group;
    }

    public function test_el_enlace_es_publico_y_dice_a_que_grupo_invita(): void
    {
        $group = $this->grupoCon(User::factory()->create());

        $this->get(route('groups.invitation', $group->invite_code))
            ->assertOk()
            ->assertSee($group->name);
    }

    public function test_abrir_el_enlace_no_mete_a_nadie_en_el_grupo(): void
    {
        $group = $this->grupoCon(User::factory()->create());
        $invitado = User::factory()->create();

        $this->actingAs($invitado)
            ->get(route('groups.invitation', $group->invite_code))
            ->assertOk();

        // Hace falta confirmar: un clic en un enlace no debe cambiar estado
        $this->assertNull($invitado->fresh()->memberIn($group));
    }

    public function test_confirmar_la_invitacion_da_de_alta_en_el_grupo(): void
    {
        $group = $this->grupoCon(User::factory()->create());
        $invitado = User::factory()->create();

        $this->actingAs($invitado)
            ->post(route('groups.invitation.accept', $group->invite_code))
            ->assertRedirect(route('groups.show', $group));

        $member = $invitado->fresh()->memberIn($group);

        $this->assertNotNull($member);
        $this->assertTrue($member->active);
    }

    public function test_el_codigo_del_enlace_no_distingue_mayusculas(): void
    {
        $group = $this->grupoCon(User::factory()->create());
        $invitado = User::factory()->create();

        $this->actingAs($invitado)
            ->post(route('groups.invitation.accept', strtolower($group->invite_code)))
            ->assertRedirect(route('groups.show', $group));

        $this->assertNotNull($invitado->fresh()->memberIn($group));
    }

    public function test_aceptar_dos_veces_no_duplica_la_ficha_del_miembro(): void
    {
        $group = $this->grupoCon(User::factory()->create());
        $invitado = User::factory()->create();

        $this->actingAs($invitado)->post(route('groups.invitation.accept', $group->invite_code));
        $this->actingAs($invitado)->post(route('groups.invitation.accept', $group->invite_code));

        $this->assertSame(1, GroupMember::where('group_id', $group->id)
            ->where('user_id', $invitado->id)
            ->count());
    }

    public function test_quien_ya_esta_dentro_va_directo_al_grupo(): void
    {
        $dueno = User::factory()->create();
        $group = $this->grupoCon($dueno);

        $this->actingAs($dueno)
            ->get(route('groups.invitation', $group->invite_code))
            ->assertRedirect(route('groups.show', $group));
    }

    public function test_un_codigo_inexistente_avisa_en_lugar_de_reventar(): void
    {
        $this->get(route('groups.invitation', 'NOEXISTE'))
            ->assertOk()
            ->assertSee('Esta invitación no vale');
    }

    public function test_aceptar_sin_sesion_manda_a_entrar(): void
    {
        $group = $this->grupoCon(User::factory()->create());

        $this->post(route('groups.invitation.accept', $group->invite_code))
            ->assertRedirect(route('login'));
    }

    public function test_al_visitar_el_enlace_sin_sesion_se_recuerda_para_despues_de_entrar(): void
    {
        $group = $this->grupoCon(User::factory()->create());

        $this->get(route('groups.invitation', $group->invite_code));

        // Así, tras iniciar sesión, intended() devuelve a la invitación en vez
        // de dejar a la persona en el panel sin haber entrado al grupo
        $this->assertSame(
            route('groups.invitation', $group->invite_code),
            session('url.intended'),
        );
    }
}
