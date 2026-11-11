<?php

namespace App\Services;

use App\Models\Election;
use App\Models\Form;
use App\Models\FormSubmission;

class ElectionVoterService
{
    /**
     * Construit/complète la liste électorale d'une élection à partir d'un formulaire
     * source, en ne reprenant que les inscriptions validées, avec un filtre optionnel
     * sur le nom. Déduplique par email au sein de l'élection.
     */
    public function addFromForm(Election $election, Form $form, ?string $nameFilter = null): int
    {
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

        $election->sources()->create([
            'form_id' => $form->id,
            'filters' => $nameFilter ? ['name' => $nameFilter] : null,
        ]);

        $added = 0;

        foreach ($submissions as $submission) {
            $email = $this->extractByType($submission, 'email');

            if (! $email) {
                continue;
            }

            $voter = $election->voters()->firstOrCreate(
                ['email' => $email],
                [
                    'form_submission_id' => $submission->id,
                    'name' => $submission->displayName(),
                    'phone' => $this->extractByType($submission, 'tel'),
                ]
            );

            if ($voter->wasRecentlyCreated) {
                $added++;
            }
        }

        return $added;
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
