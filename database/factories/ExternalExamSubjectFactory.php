<?php

namespace Database\Factories;

use App\Models\ExternalApplicant;
use App\Models\ExternalExamSubject;
use App\Models\Subject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExternalExamSubject>
 */
class ExternalExamSubjectFactory extends Factory
{
    protected $model = ExternalExamSubject::class;

    /**
     * Das Fach kommt aus dem echten Katalog. Die frühere eigene Liste enthielt
     * mit "Sozialkunde", "Sport" und "Religion" drei Namen, die es in subjects
     * gar nicht gibt.
     *
     * Niveau und Leistungsfach stehen hier nicht mehr: beides ergibt sich nach
     * § 61 Abs. 1 S. 5 aus dem Fach und aus
     * external_applicants.leistungsfach_subject_id.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_applicant_id' => ExternalApplicant::factory(),
            'subject_id' => Subject::query()->where('can_abitur', true)->inRandomOrder()->value('id'),
            'exam_part' => $this->faker->randomElement(['part1_written', 'part2_oral']),
            'subject_number' => $this->faker->numberBetween(1, 4),
        ];
    }

    /** Ein bestimmtes Fach aus dem Katalog, über seinen Namen. */
    public function fach(string $name): static
    {
        return $this->state(fn (array $attributes): array => [
            'subject_id' => Subject::query()->where('name', $name)->value('id'),
        ]);
    }

    public function written(): static
    {
        return $this->state(fn (array $attributes): array => [
            'exam_part' => 'part1_written',
            'subject_number' => $this->faker->numberBetween(1, 4),
        ]);
    }

    public function oral(): static
    {
        return $this->state(fn (array $attributes): array => [
            'exam_part' => 'part2_oral',
            'subject_number' => $this->faker->numberBetween(1, 4),
        ]);
    }
}
