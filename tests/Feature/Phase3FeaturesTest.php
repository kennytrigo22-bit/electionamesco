<?php

namespace Tests\Feature;

use App\Models\Ballot;
use App\Models\Election;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class Phase3FeaturesTest extends TestCase
{
    use RefreshDatabase;

    protected function makeCensusForm(array $overrides = []): Form
    {
        $admin = User::factory()->create();

        $form = Form::create(array_merge([
            'title' => 'Recensement',
            'slug' => 'recensement-'.uniqid(),
            'status' => 'open',
            'auto_validate' => true,
            'one_response_per_person' => true,
            'created_by' => $admin->id,
        ], $overrides));

        $section = $form->sections()->create(['title' => 'Section 1', 'order' => 1]);

        $form->fields()->create([
            'form_section_id' => $section->id, 'type' => 'email', 'label' => 'Email', 'is_required' => true, 'order' => 1,
        ]);

        $statut = $form->fields()->create([
            'form_section_id' => $section->id, 'type' => 'select', 'label' => 'Statut', 'options' => ['Étudiant', 'Stagiaire'],
            'is_required' => true, 'order' => 2,
        ]);

        $form->fields()->create([
            'form_section_id' => $section->id, 'type' => 'text', 'label' => 'Structure de stage', 'is_required' => true,
            'condition_field_id' => $statut->id, 'condition_value' => 'Stagiaire', 'order' => 3,
        ]);

        return $form->fresh(['sections.fields', 'fields']);
    }

    public function test_une_seconde_inscription_avec_le_meme_email_est_refusee(): void
    {
        $form = $this->makeCensusForm();
        $fields = $form->fields;
        $emailField = $fields->firstWhere('type', 'email');
        $statutField = $fields->firstWhere('label', 'Statut');

        $component = Volt::test('public.form-fill', ['form' => $form])
            ->set("values.{$emailField->id}", 'test@example.com')
            ->set("values.{$statutField->id}", 'Étudiant')
            ->call('submit');

        $component->assertSet('submitted', true);
        $this->assertSame(1, FormSubmission::count());

        $component2 = Volt::test('public.form-fill', ['form' => $form])
            ->set("values.{$emailField->id}", 'test@example.com')
            ->set("values.{$statutField->id}", 'Étudiant')
            ->call('submit');

        $component2->assertSet('submitted', false);
        $component2->assertSet('duplicateError', fn ($message) => $message !== '');
        $this->assertSame(1, FormSubmission::count());
    }

    public function test_une_seconde_inscription_avec_un_email_different_mais_le_meme_appareil_est_refusee(): void
    {
        $form = $this->makeCensusForm();
        $fields = $form->fields;
        $emailField = $fields->firstWhere('type', 'email');
        $statutField = $fields->firstWhere('label', 'Statut');

        Volt::test('public.form-fill', ['form' => $form])
            ->set("values.{$emailField->id}", 'premier@example.com')
            ->set("values.{$statutField->id}", 'Étudiant')
            ->call('submit')
            ->assertSet('submitted', true);

        $component2 = Volt::test('public.form-fill', ['form' => $form])
            ->set("values.{$emailField->id}", 'second@example.com')
            ->set("values.{$statutField->id}", 'Étudiant')
            ->call('submit');

        $component2->assertSet('submitted', false);
        $this->assertSame(1, FormSubmission::count());
    }

    public function test_un_champ_conditionnel_nest_pas_obligatoire_quand_il_est_masque(): void
    {
        $form = $this->makeCensusForm();
        $fields = $form->fields;
        $emailField = $fields->firstWhere('type', 'email');
        $statutField = $fields->firstWhere('label', 'Statut');
        $stageField = $fields->firstWhere('label', 'Structure de stage');

        $this->assertFalse($stageField->isVisibleFor([$statutField->id => 'Étudiant']));
        $this->assertTrue($stageField->isVisibleFor([$statutField->id => 'Stagiaire']));

        $component = Volt::test('public.form-fill', ['form' => $form])
            ->set("values.{$emailField->id}", 'etudiant@example.com')
            ->set("values.{$statutField->id}", 'Étudiant')
            ->call('submit');

        $component->assertSet('submitted', true);

        $submission = FormSubmission::first();
        $this->assertNull($submission->valueFor($stageField));
    }

    public function test_un_electeur_assigne_a_une_urne_ne_vote_que_dans_cette_urne(): void
    {
        $admin = User::factory()->create();
        $election = Election::create(['title' => 'Bureau Exécutif', 'status' => 'open', 'created_by' => $admin->id]);

        $thies = $election->ballots()->create(['title' => 'Thies', 'type' => Ballot::TYPE_SINGLE_CHOICE, 'order' => 1]);
        $saintLouis = $election->ballots()->create(['title' => 'Saint Louis', 'type' => Ballot::TYPE_SINGLE_CHOICE, 'order' => 2]);
        $thies->candidates()->create(['label' => 'Liste A', 'order' => 1]);
        $saintLouis->candidates()->create(['label' => 'Liste B', 'order' => 1]);

        $voter = $election->voters()->create([
            'ballot_id' => $thies->id, 'name' => 'Voter', 'email' => 'voter@example.com',
        ]);

        $component = Volt::test('public.vote', ['token' => $voter->token]);

        $this->assertSame(1, $component->instance()->ballots()->count());
        $this->assertSame($thies->id, $component->instance()->ballots()->first()->id);
    }

    public function test_le_nom_du_membre_concatene_tous_les_champs_identite(): void
    {
        $admin = User::factory()->create();
        $form = Form::create(['title' => 'F', 'slug' => 'f2-'.uniqid(), 'status' => 'open', 'created_by' => $admin->id]);
        $section = $form->sections()->create(['title' => 'S1', 'order' => 1]);

        $first = $form->fields()->create(['form_section_id' => $section->id, 'type' => 'text', 'label' => 'Prénom', 'is_identity_name' => true, 'order' => 1]);
        $last = $form->fields()->create(['form_section_id' => $section->id, 'type' => 'text', 'label' => 'Nom', 'is_identity_name' => true, 'order' => 2]);

        $submission = $form->submissions()->create(['status' => 'validated', 'submitted_at' => now()]);
        $submission->values()->create(['form_field_id' => $first->id, 'value' => 'Grâce']);
        $submission->values()->create(['form_field_id' => $last->id, 'value' => 'MALONGA']);

        $this->assertSame('Grâce MALONGA', $submission->fresh(['form.fields', 'values'])->displayName());
    }
}
