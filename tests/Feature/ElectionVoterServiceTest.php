<?php

namespace Tests\Feature;

use App\Models\Election;
use App\Models\Form;
use App\Models\User;
use App\Services\ElectionVoterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ElectionVoterServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_seules_les_inscriptions_validees_sont_reprises_dans_la_liste_electorale(): void
    {
        $admin = User::factory()->create();

        $form = Form::create([
            'title' => 'Recensement',
            'slug' => 'recensement',
            'status' => 'open',
            'created_by' => $admin->id,
        ]);

        $nameField = $form->fields()->create(['label' => 'Nom', 'type' => 'text', 'is_identity_name' => true, 'order' => 1]);
        $emailField = $form->fields()->create(['label' => 'Email', 'type' => 'email', 'order' => 2]);

        $validated = $form->submissions()->create(['status' => 'validated', 'submitted_at' => now()]);
        $validated->values()->create(['form_field_id' => $nameField->id, 'value' => 'Alice']);
        $validated->values()->create(['form_field_id' => $emailField->id, 'value' => 'alice@example.com']);

        $pending = $form->submissions()->create(['status' => 'pending', 'submitted_at' => now()]);
        $pending->values()->create(['form_field_id' => $nameField->id, 'value' => 'Bob']);
        $pending->values()->create(['form_field_id' => $emailField->id, 'value' => 'bob@example.com']);

        $election = Election::create(['title' => 'Élection', 'status' => 'draft', 'created_by' => $admin->id]);

        $added = app(ElectionVoterService::class)->addFromForm($election, $form);

        $this->assertSame(1, $added);
        $this->assertSame(1, $election->voters()->count());
        $this->assertSame('alice@example.com', $election->voters()->first()->email);
    }

    public function test_lajout_est_idempotent_par_email(): void
    {
        $admin = User::factory()->create();

        $form = Form::create([
            'title' => 'Recensement',
            'slug' => 'recensement-2',
            'status' => 'open',
            'created_by' => $admin->id,
        ]);

        $nameField = $form->fields()->create(['label' => 'Nom', 'type' => 'text', 'is_identity_name' => true, 'order' => 1]);
        $emailField = $form->fields()->create(['label' => 'Email', 'type' => 'email', 'order' => 2]);

        $validated = $form->submissions()->create(['status' => 'validated', 'submitted_at' => now()]);
        $validated->values()->create(['form_field_id' => $nameField->id, 'value' => 'Alice']);
        $validated->values()->create(['form_field_id' => $emailField->id, 'value' => 'alice@example.com']);

        $election = Election::create(['title' => 'Élection', 'status' => 'draft', 'created_by' => $admin->id]);

        $service = app(ElectionVoterService::class);
        $service->addFromForm($election, $form);
        $secondRun = $service->addFromForm($election, $form);

        $this->assertSame(0, $secondRun);
        $this->assertSame(1, $election->voters()->count());
    }
}
