<?php

namespace Tests\Feature;

use App\Models\Competence;
use App\Models\CompetenceBlock;
use App\Models\Permission;
use App\Models\Referentiel;
use Tests\TestCase;

/** Pins how a referentiel (blocks of competences) is created, restructured and deleted. */
class ReferentielManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsSuperAdmin();
    }

    /** @return array{0: Referentiel, 1: CompetenceBlock, 2: Competence} */
    private function referentiel(string $name = 'Dev Web'): array
    {
        $referentiel = Referentiel::create(['name' => $name]);
        $block = $referentiel->blocks()->create(['name' => 'Front', 'order' => 0]);
        $competence = $block->competences()->create(['name' => 'HTML', 'order' => 0]);

        return [$referentiel, $block, $competence];
    }

    private function update(Referentiel $referentiel, array $payload)
    {
        return $this->put(route('referentiels.update', $referentiel), ['name' => $referentiel->name, ...$payload]);
    }

    public function test_a_referentiel_is_created_and_validated(): void
    {
        $this->post(route('referentiels.store'), ['name' => 'Data', 'description' => 'Analyse'])
            ->assertRedirect()->assertSessionHas('success', 'Référentiel créé avec succès.');

        $created = Referentiel::where('name', 'Data')->firstOrFail();
        $this->assertSame('Analyse', $created->description);

        $this->post(route('referentiels.store'), ['name' => ''])->assertSessionHasErrors('name');
        $this->post(route('referentiels.store'), ['name' => str_repeat('x', 256)])->assertSessionHasErrors('name');
    }

    public function test_the_structure_is_rebuilt_from_the_submitted_order(): void
    {
        [$referentiel, $front, $html] = $this->referentiel();

        $this->update($referentiel, [
            'name' => 'Dev Web 2',
            'description' => 'Nouveau',
            'blocks' => [
                ['name' => 'Back', 'competences' => [['name' => 'PHP'], ['name' => 'SQL']]],            // new, now first
                ['id' => $front->id, 'name' => 'Front-end', 'competences' => [['id' => $html->id, 'name' => 'HTML5']]], // renamed, now second
            ],
        ])->assertRedirect(route('referentiels.show', $referentiel))->assertSessionHas('success', 'Référentiel mis à jour.');

        $referentiel->refresh();
        $this->assertSame(['Dev Web 2', 'Nouveau'], [$referentiel->name, $referentiel->description]);

        $blocks = $referentiel->blocks()->get();
        $this->assertSame(['Back', 'Front-end'], $blocks->pluck('name')->all());
        $this->assertSame([0, 1], $blocks->pluck('order')->all());
        $this->assertSame($front->id, $blocks[1]->id, 'an existing block keeps its identity');
        $this->assertSame(['PHP', 'SQL'], $blocks[0]->competences()->pluck('name')->all());
        $this->assertSame([0, 1], $blocks[0]->competences()->pluck('order')->all());
        $this->assertSame($html->id, $blocks[1]->competences()->first()->id);
        $this->assertSame('HTML5', $html->fresh()->name);
    }

    public function test_what_is_not_submitted_is_removed(): void
    {
        [$referentiel, $front, $html] = $this->referentiel();
        $css = $front->competences()->create(['name' => 'CSS', 'order' => 1]);
        $back = $referentiel->blocks()->create(['name' => 'Back', 'order' => 1]);
        $php = $back->competences()->create(['name' => 'PHP', 'order' => 0]);

        $this->update($referentiel, ['blocks' => [['id' => $front->id, 'name' => 'Front', 'competences' => [['id' => $html->id, 'name' => 'HTML']]]]]);

        $this->assertNotNull(Competence::find($html->id));
        $this->assertNull(Competence::find($css->id), 'a competence left out is deleted');
        $this->assertNull(CompetenceBlock::find($back->id), 'a block left out is deleted');
        $this->assertNull(Competence::find($php->id), 'with its competences');
    }

    public function test_submitting_no_block_empties_the_referentiel(): void
    {
        [$referentiel, $front] = $this->referentiel();

        $this->update($referentiel, ['blocks' => []])->assertRedirect();

        $this->assertSame(0, $referentiel->blocks()->count());
        $this->assertNull(CompetenceBlock::find($front->id));
    }

    public function test_the_structure_is_validated_and_nothing_changes_on_error(): void
    {
        [$referentiel, $front] = $this->referentiel();

        $this->update($referentiel, ['blocks' => [['name' => '']]])->assertSessionHasErrors('blocks.0.name');
        $this->update($referentiel, ['blocks' => [['id' => 'not-a-uuid', 'name' => 'X']]])->assertSessionHasErrors('blocks.0.id');
        $this->update($referentiel, ['blocks' => [['name' => 'X', 'competences' => [['name' => '']]]]])->assertSessionHasErrors('blocks.0.competences.0.name');

        $this->assertSame('Front', $front->fresh()->name);
    }

    /** The ids of blocks and competences used to be looked up in the whole database. */
    public function test_a_block_or_competence_of_another_referentiel_cannot_be_hijacked(): void
    {
        [$mine] = $this->referentiel('Mine');
        [$other, $foreignBlock, $foreignCompetence] = $this->referentiel('Other');

        $this->update($mine, ['blocks' => [[
            'id' => $foreignBlock->id, 'name' => 'Hijacked block',
            'competences' => [['id' => $foreignCompetence->id, 'name' => 'Hijacked competence']],
        ]]])->assertRedirect();

        $this->assertSame('Front', $foreignBlock->fresh()->name);
        $this->assertSame($other->id, $foreignBlock->fresh()->referentiel_id);
        $this->assertSame('HTML', $foreignCompetence->fresh()->name);
        $this->assertSame(['Hijacked block'], $mine->blocks()->pluck('name')->all(), 'the submitted block is created in this referentiel instead');
    }

    public function test_a_failure_in_the_middle_leaves_the_referentiel_untouched(): void
    {
        [$referentiel, $front] = $this->referentiel();
        Competence::creating(fn () => throw new \RuntimeException('database failure while saving a competence'));

        try {
            $this->withoutExceptionHandling()->update($referentiel, ['name' => 'Renamed', 'blocks' => [
                ['id' => $front->id, 'name' => 'Front renamed', 'competences' => [['name' => 'A new competence']]],
            ]]);
            $this->fail('the failure should have reached the test');
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame('Dev Web', $referentiel->fresh()->name, 'nothing is half applied');
        $this->assertSame('Front', $front->fresh()->name);
    }

    public function test_a_referentiel_is_deleted_with_its_blocks_and_competences(): void
    {
        [$referentiel, $block, $competence] = $this->referentiel();

        $this->delete(route('referentiels.destroy', $referentiel))
            ->assertRedirect(route('referentiels.index'))->assertSessionHas('success', 'Référentiel supprimé.');

        $this->assertNull(Referentiel::find($referentiel->id));
        $this->assertNull(CompetenceBlock::find($block->id));
        $this->assertNull(Competence::find($competence->id));
    }

    public function test_the_pages_list_and_show_the_referentiels(): void
    {
        [$referentiel] = $this->referentiel();

        $this->get(route('referentiels.index'))->assertOk();
        $props = $this->get(route('referentiels.show', $referentiel))->assertOk()->viewData('page')['props'];

        $this->assertSame('Front', $props['referentiel']['blocks'][0]['name']);
        $this->assertSame('HTML', $props['referentiel']['blocks'][0]['competences'][0]['name']);
    }

    public function test_every_action_needs_its_permission(): void
    {
        [$referentiel] = $this->referentiel();
        $admin = $this->createAdmin();
        $admin->permissions()->attach(Permission::firstOrCreate(['slug' => 'referentiels.view'], ['name' => 'view', 'group' => 'T'])->id);
        $this->actingAs($admin);

        $this->get(route('referentiels.show', $referentiel))->assertOk();
        $this->post(route('referentiels.store'), ['name' => 'X'])->assertForbidden();
        $this->put(route('referentiels.update', $referentiel), ['name' => 'X'])->assertForbidden();
        $this->delete(route('referentiels.destroy', $referentiel))->assertForbidden();
        $this->assertSame('Dev Web', $referentiel->fresh()->name);
    }
}
