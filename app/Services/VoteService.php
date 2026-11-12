<?php

namespace App\Services;

use App\Models\Ballot;
use App\Models\ElectionVoter;
use App\Models\Vote;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VoteService
{
    /**
     * Enregistre les bulletins d'un électeur pour toutes les urnes de son élection.
     *
     * @param  array<int, mixed>  $choicesByBallotId  [ballot_id => choice_data]
     *
     * @throws RuntimeException si le lien a déjà été utilisé
     */
    public function castVotes(ElectionVoter $voter, array $choicesByBallotId): void
    {
        DB::transaction(function () use ($voter, $choicesByBallotId) {
            // Marquage atomique : échoue si used_at a déjà été renseigné par une
            // requête concurrente (double soumission, double onglet, etc.)
            $claimed = ElectionVoter::whereKey($voter->id)
                ->whereNull('used_at')
                ->update(['used_at' => now()]);

            if ($claimed === 0) {
                throw new RuntimeException('Ce lien de vote a déjà été utilisé.');
            }

            foreach ($choicesByBallotId as $ballotId => $choiceData) {
                Vote::create([
                    'ballot_id' => $ballotId,
                    'choice_data' => $choiceData,
                    'submitted_at' => now(),
                ]);
            }
        });
    }

    public function validateChoice(Ballot $ballot, mixed $choiceData): bool
    {
        if ($choiceData === 'blank' && $ballot->allow_blank) {
            return true;
        }

        if ($choiceData === 'abstain' && $ballot->allow_abstention) {
            return true;
        }

        $candidateIds = $ballot->candidates->pluck('id')->all();

        return match ($ballot->type) {
            Ballot::TYPE_SINGLE_CHOICE => is_scalar($choiceData) && in_array((int) $choiceData, $candidateIds, true),
            Ballot::TYPE_MULTIPLE_CHOICE => is_array($choiceData)
                && count($choiceData) > 0
                && (! $ballot->max_choices || count($choiceData) <= $ballot->max_choices)
                && empty(array_diff(array_map('intval', $choiceData), $candidateIds)),
            Ballot::TYPE_RANKED => is_array($choiceData)
                && count($choiceData) > 0
                && empty(array_diff(array_map('intval', $choiceData), $candidateIds))
                && count($choiceData) === count(array_unique($choiceData)),
            Ballot::TYPE_YESNO => in_array($choiceData, ['yes', 'no'], true),
            default => false,
        };
    }
}
