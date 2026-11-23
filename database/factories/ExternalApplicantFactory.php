<?php

namespace Database\Factories;

use App\Models\ExamPeriod;
use App\Models\ExternalApplicant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExternalApplicant>
 */
class ExternalApplicantFactory extends Factory
{
    protected $model = ExternalApplicant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->optional()->phoneNumber(),
            'birth_date' => $this->faker->dateTimeBetween('-20 years', '-17 years'),
            // exam_periods ist eine ZENTRALE Tabelle (mysql-Connection) — die
            // Factory legt bewusst keine neue Periode an, sondern erwartet, dass
            // Tests eine exam_period_id explizit übergeben (bzw. forExamPeriod()
            // nutzen). Fallback: neueste vorhandene Periode.
            'exam_period_id' => fn () => ExamPeriod::query()->latest('year')->value('id'),
            'notes' => $this->faker->optional()->sentence(),
            'anonymize_at' => null,
            'is_anonymized' => false,
        ];
    }

    /**
     * Indicate that the applicant is anonymized.
     *
     * Befund 07/2026 (→ Modul 2.1): first_name/last_name sind im Schema
     * NOT NULL — Namens-Anonymisierung (nullen) ist derzeit nicht möglich,
     * daher werden hier nur die nullable Kontaktdaten geleert.
     */
    public function anonymized(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => null,
            'phone' => null,
            'is_anonymized' => true,
        ]);
    }

    /**
     * Set a specific exam period (Referenz auf zentrale exam_periods-Tabelle).
     * anonymize_at wird analog zum Controller auf Jahresende +2 gesetzt.
     */
    public function forExamPeriod(ExamPeriod $examPeriod): static
    {
        return $this->state(fn (array $attributes) => [
            'exam_period_id' => $examPeriod->id,
            'anonymize_at' => Carbon::create($examPeriod->year + 2, 12, 31),
        ]);
    }
}
