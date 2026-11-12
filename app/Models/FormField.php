<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'form_id', 'form_section_id', 'label', 'type', 'options', 'is_required', 'is_identity_name',
    'condition_field_id', 'condition_value', 'order',
])]
class FormField extends Model
{
    public const TYPES_WITH_OPTIONS = ['select', 'radio', 'checkbox', 'country', 'institution', 'level'];

    public const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

    public const COUNTRIES = [
        'République du Congo', 'République démocratique du Congo', 'Sénégal', 'Gabon',
        'Cameroun', "Côte d'Ivoire", 'France', 'Autre',
    ];

    public const INSTITUTIONS = [
        'Université Cheikh Anta Diop (UCAD)', 'Université Gaston Berger (UGB)',
        'École Supérieure Polytechnique (ESP)', 'Université Iba Der Thiam de Thiès',
        'Université Assane Seck de Ziguinchor', 'Université Alioune Diop de Bambey',
        'Institut Supérieur de Management (ISM)', 'Groupe SUPDECO Dakar', 'CESAG',
        'Autre établissement',
    ];

    public const LEVELS = [
        'Lycée', 'BTS', 'Licence 1', 'Licence 2', 'Licence 3', 'Master 1', 'Master 2', 'Doctorat', 'Stage professionnel',
    ];

    public const TYPE_GROUPS = [
        'text' => 'Texte', 'textarea' => 'Texte', 'email' => 'Texte', 'tel' => 'Texte', 'number' => 'Texte', 'date' => 'Texte',
        'select' => 'Choix', 'radio' => 'Choix', 'checkbox' => 'Choix',
        'file' => 'Fichiers', 'image' => 'Fichiers',
        'address' => 'Spécialisés', 'country' => 'Spécialisés', 'institution' => 'Spécialisés', 'level' => 'Spécialisés', 'custom' => 'Spécialisés',
    ];

    public const TYPE_LABELS = [
        'text' => 'Texte court', 'textarea' => 'Texte long', 'email' => 'Email', 'tel' => 'Téléphone',
        'number' => 'Nombre', 'date' => 'Date', 'select' => 'Liste déroulante', 'radio' => 'Choix unique',
        'checkbox' => 'Cases à cocher', 'file' => 'Fichier', 'image' => 'Image', 'address' => 'Adresse',
        'country' => 'Pays', 'institution' => 'Établissement', 'level' => "Niveau d'études",
        'custom' => 'Champ personnalisé',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'is_identity_name' => 'boolean',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(FormSection::class, 'form_section_id');
    }

    public function condition(): BelongsTo
    {
        return $this->belongsTo(self::class, 'condition_field_id');
    }

    public function hasOptions(): bool
    {
        return in_array($this->type, self::TYPES_WITH_OPTIONS, true);
    }

    public function isVisibleFor(array $values): bool
    {
        if (! $this->condition_field_id) {
            return true;
        }

        $value = $values[$this->condition_field_id] ?? null;

        if (is_array($value)) {
            return in_array($this->condition_value, $value, true);
        }

        return (string) $value === (string) $this->condition_value;
    }

    public function fixedOptions(): ?array
    {
        return match ($this->type) {
            'country' => self::COUNTRIES,
            'institution' => self::INSTITUTIONS,
            'level' => self::LEVELS,
            default => null,
        };
    }

    public function effectiveOptions(): array
    {
        return $this->fixedOptions() ?? ($this->options ?? []);
    }

    public function isFileType(): bool
    {
        return in_array($this->type, ['file', 'image'], true);
    }
}
