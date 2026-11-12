<?php

namespace Tests\Feature;

use App\Models\Ballot;
use App\Models\Election;
use App\Models\Form;
use App\Models\FormSubmissionValue;
use App\Models\User;
use App\Services\VoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class Phase2FeaturesTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_valeur_de_champ_image_est_detectee_comme_image(): void
    {
        $value = new FormSubmissionValue(['file_path' => 'submissions/1/photo.jpg']);
        $this->assertTrue($value->isImage());

        $value = new FormSubmissionValue(['file_path' => 'submissions/1/cv.pdf']);
        $this->assertFalse($value->isImage());
    }

    public function test_les_types_de_champs_pays_etablissement_niveau_ont_des_options_fixes(): void
    {
        $admin = User::factory()->create();
        $form = Form::create(['title' => 'F', 'slug' => 'f', 'status' => 'open', 'created_by' => $admin->id]);

        $field = $form->fields()->create(['type' => 'country', 'label' => 'Pays', 'order' => 1]);

        $this->assertNotEmpty($field->effectiveOptions());
        $this->assertContains('Sénégal', $field->effectiveOptions());
    }

    public function test_approuver_une_candidature_cree_un_candidat_sur_lurne(): void
    {
        $admin = User::factory()->create();
        $election = Election::create(['title' => 'E', 'status' => 'draft', 'created_by' => $admin->id]);
        $ballot = $election->ballots()->create(['title' => 'Président', 'type' => Ballot::TYPE_SINGLE_CHOICE, 'order' => 1]);

        $candidacy = $election->candidacies()->create([
            'name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'pending', 'submitted_at' => now(),
        ]);

        $this->actingAs($admin);
        Volt::test('admin.candidatures.index')
            ->set('ballotChoice.'.$candidacy->id, $ballot->id)
            ->call('approve', $candidacy);

        $this->assertSame('approved', $candidacy->refresh()->status);
        $this->assertSame(1, $ballot->candidates()->count());
        $this->assertSame('Alice', $ballot->candidates()->first()->label);
    }

    public function test_le_vote_blanc_est_accepte_uniquement_si_autorise(): void
    {
        $admin = User::factory()->create();
        $election = Election::create(['title' => 'E', 'status' => 'open', 'created_by' => $admin->id]);
        $ballot = $election->ballots()->create(['title' => 'Président', 'type' => Ballot::TYPE_SINGLE_CHOICE, 'allow_blank' => false, 'order' => 1]);

        $service = app(VoteService::class);
        $this->assertFalse($service->validateChoice($ballot, 'blank'));

        $ballot->update(['allow_blank' => true]);
        $this->assertTrue($service->validateChoice($ballot->fresh(), 'blank'));
    }
}
