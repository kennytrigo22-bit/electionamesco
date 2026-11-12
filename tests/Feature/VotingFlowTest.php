<?php

namespace Tests\Feature;

use App\Models\Ballot;
use App\Models\Election;
use App\Models\User;
use App\Models\Vote;
use App\Services\VoteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class VotingFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function makeElectionWithBallot(string $type, ?int $maxChoices = null): array
    {
        $admin = User::factory()->create();

        $election = Election::create([
            'title' => 'Élection test',
            'status' => 'open',
            'created_by' => $admin->id,
        ]);

        $ballot = $election->ballots()->create([
            'title' => 'Poste test',
            'type' => $type,
            'max_choices' => $maxChoices,
            'order' => 1,
        ]);

        $candidateA = $ballot->candidates()->create(['label' => 'Candidat A', 'order' => 1]);
        $candidateB = $ballot->candidates()->create(['label' => 'Candidat B', 'order' => 2]);

        $voter = $election->voters()->create([
            'name' => 'Électeur Test',
            'email' => 'electeur@example.com',
        ]);

        return [$election, $ballot, $candidateA, $candidateB, $voter];
    }

    public function test_un_electeur_ne_peut_voter_quune_seule_fois(): void
    {
        [$election, $ballot, $candidateA, , $voter] = $this->makeElectionWithBallot(Ballot::TYPE_SINGLE_CHOICE);

        $service = app(VoteService::class);

        $service->castVotes($voter, [$ballot->id => (string) $candidateA->id]);

        $this->assertNotNull($voter->refresh()->used_at);
        $this->assertSame(1, Vote::count());

        $this->expectException(RuntimeException::class);

        $service->castVotes($voter, [$ballot->id => (string) $candidateA->id]);
    }

    public function test_choix_unique_valide_un_seul_candidat(): void
    {
        [, $ballot, $candidateA] = $this->makeElectionWithBallot(Ballot::TYPE_SINGLE_CHOICE);

        $service = app(VoteService::class);

        $this->assertTrue($service->validateChoice($ballot, (string) $candidateA->id));
        $this->assertFalse($service->validateChoice($ballot, [999]));
    }

    public function test_choix_multiple_respecte_le_maximum_de_choix(): void
    {
        [, $ballot, $candidateA, $candidateB] = $this->makeElectionWithBallot(Ballot::TYPE_MULTIPLE_CHOICE, 1);

        $service = app(VoteService::class);

        $this->assertTrue($service->validateChoice($ballot, [(string) $candidateA->id]));
        $this->assertFalse($service->validateChoice($ballot, [(string) $candidateA->id, (string) $candidateB->id]));
    }

    public function test_classement_refuse_les_doublons(): void
    {
        [, $ballot, $candidateA] = $this->makeElectionWithBallot(Ballot::TYPE_RANKED);

        $service = app(VoteService::class);

        $this->assertFalse($service->validateChoice($ballot, [$candidateA->id, $candidateA->id]));
    }

    public function test_oui_non_naccepte_que_yes_ou_no(): void
    {
        [, $ballot] = $this->makeElectionWithBallot(Ballot::TYPE_YESNO);

        $service = app(VoteService::class);

        $this->assertTrue($service->validateChoice($ballot, 'yes'));
        $this->assertFalse($service->validateChoice($ballot, 'maybe'));
    }

    public function test_un_lien_de_vote_pour_une_election_fermee_est_invalide(): void
    {
        [$election, , , , $voter] = $this->makeElectionWithBallot(Ballot::TYPE_YESNO);
        $election->update(['status' => 'closed']);

        $response = $this->get(route('vote.show', ['token' => $voter->token]));

        $response->assertSee('Lien invalide');
    }
}
