<?php

namespace Tests\Feature;

use App\Models\Form;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_visiteur_peut_soumettre_un_formulaire_et_etre_valide(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'fonction' => 'PR_BE']);

        $form = Form::create([
            'title' => 'Recensement',
            'slug' => 'recensement',
            'status' => 'open',
            'created_by' => $admin->id,
        ]);

        $nameField = $form->fields()->create([
            'label' => 'Nom complet',
            'type' => 'text',
            'is_required' => true,
            'is_identity_name' => true,
            'order' => 1,
        ]);

        $emailField = $form->fields()->create([
            'label' => 'Email',
            'type' => 'email',
            'is_required' => true,
            'order' => 2,
        ]);

        $submission = $form->submissions()->create([
            'status' => 'pending',
            'submitted_at' => now(),
        ]);

        $submission->values()->create(['form_field_id' => $nameField->id, 'value' => 'Jean Diallo']);
        $submission->values()->create(['form_field_id' => $emailField->id, 'value' => 'jean@example.com']);

        $this->assertSame('Jean Diallo', $submission->refresh()->displayName());
        $this->assertSame('pending', $submission->status);

        $submission->update([
            'status' => 'validated',
            'validated_by' => $admin->id,
            'validated_at' => now(),
        ]);

        $this->assertSame('validated', $submission->refresh()->status);
    }

    public function test_un_formulaire_ferme_n_accepte_plus_de_soumissions_publiques(): void
    {
        $admin = User::factory()->create();

        $form = Form::create([
            'title' => 'Recensement',
            'slug' => 'recensement-ferme',
            'status' => 'closed',
            'created_by' => $admin->id,
        ]);

        $response = $this->get(route('forms.show', $form));

        $response->assertNotFound();
    }
}
