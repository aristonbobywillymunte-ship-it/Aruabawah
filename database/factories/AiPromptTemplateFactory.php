<?php

namespace Database\Factories;

use App\Models\AiPromptTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiPromptTemplate>
 */
class AiPromptTemplateFactory extends Factory
{
    protected $model = AiPromptTemplate::class;

    public function definition(): array
    {
        $types = ['article', 'social', 'report'];

        return [
            'name'                 => $this->faker->words(3, true),
            'source_type'          => $this->faker->randomElement($types),
            'system_prompt'        => 'Kamu adalah AI analis. ' . $this->faker->sentence(),
            'user_prompt_template' => 'Analisis konten berikut: {content}',
            'output_schema'        => '{"type":"object","properties":{"summary":{"type":"string"}}}',
            'is_active'            => true,
            'is_default'           => false,
        ];
    }
}
