<?php

namespace App\Services;

use App\Models\Election;
use App\Models\ElectionVoter;
use App\Models\Form;
use App\Models\FormSubmission;
use Illuminate\Support\Collection;

class ElectionVoterService
{
    /**
     * Construit/complète la liste électorale d'une élection à partir d'un formulaire
     * source, en ne reprenant que les inscriptions validées, avec un filtre optionnel
     * sur le nom et/ou sur un champ à options précis (établissement, niveau, ville...).
     * Déduplique par email au sein de l'élection. Si un $ballotId est fourni, les
     * électeurs ajoutés ou déjà présents sont directement assignés à cette urne.
     *
     * @return Collection<int, ElectionVoter> les électeurs concernés par cet appel
     */
    public function addFromForm(
        Election $election,
        Form $form,
        ?string $nameFilter = null,
        ?int $criterionFieldId = null,
        ?string $criterionValue = null,
        ?int $ballotId = null,
    ): Collection {
        $query = $form->submissions()
            ->with('values.field')
            ->where('status', 'validated');

        $submissions = $query->get();

        if ($nameFilter) {
            $submissions = $submissions->filter(
                fn (FormSubmission $submission) => str_contains(
                    mb_strtolower($submission->displayName()),
                    mb_strtolower($nameFilter)
                )
            );
        }

        if ($criterionFieldId && $criterionValue) {
            $submissions = $submissions->filter(
                fn (FormSubmission $submission) => $submission->values
                    ->firstWhere('form_field_id', $criterionFieldId)
                    ?->value === $criterionValue
            );
        }

        $election->sources()->create([
            'form_id' => $form->id,
            'filters' => $nameFilter ? ['name' => $nameFilter] : null,
        ]);

        $voters = collect();

        foreach ($submissions as $submission) {
            $email = $this->extractByType($submission, 'email');

            if (! $email) {
                continue;
            }

            $attributes = [
                'form_submission_id' => $submission->id,
                'name' => $submission->displayName(),
                'phone' => $this->extractByType($submission, 'tel'),
            ];

            if ($ballotId) {
                $attributes['ballot_id'] = $ballotId;
            }

            $voter = $election->voters()->firstOrCreate(['email' => $email], $attributes);

            if (! $voter->wasRecentlyCreated && $ballotId && ! $voter->ballot_id) {
                $voter->update(['ballot_id' => $ballotId]);
            }

            $voters->push($voter);
        }

        return $voters;
    }

    protected function extractByType(FormSubmission $submission, string $type): ?string
    {
        $field = $submission->form->fields->firstWhere('type', $type);

        if (! $field) {
            return null;
        }

        return $submission->valueFor($field)?->value;
    }
}
