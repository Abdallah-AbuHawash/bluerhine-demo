<?php

namespace App\Services\Intake;

/**
 * The strict JSON schema the model must fill in. Shared by the API parser
 * (as a structured-output format) and by the offline fixtures, so both
 * paths produce exactly the same shape.
 */
final class CutListSchema
{
    public const SYSTEM_PROMPT = <<<'PROMPT'
        You extract cut lists for a UAE polymer sheet trader (acrylic, polycarbonate, HDPE).

        Rules:
        - Every dimension you output is in MILLIMETRES. Convert cm (x10), m (x1000)
          and inches (x25.4). "60x40cm" is 600 x 400 mm.
        - width_mm is the first dimension given, height_mm the second. A third
          number in a "1200x1200x6" style triple is the thickness, not a dimension.
        - qty defaults to 1 when the customer does not say otherwise.
        - material_hint is the customer's own wording ("clear PC", "opal white acrylic",
          "mirror silver 3mm"). Do not invent SKUs; a human maps hints to stock.
        - Put anything you had to assume, or anything ambiguous, in warnings.
        - confidence is your own 0-1 estimate for the extraction as a whole.
        - Never drop a line you do not understand: emit it with null dimensions and
          a warning instead.
        PROMPT;

    public static function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'pieces' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'material_hint' => ['type' => ['string', 'null']],
                            'thickness_mm' => ['type' => ['number', 'null']],
                            'width_mm' => ['type' => ['number', 'null']],
                            'height_mm' => ['type' => ['number', 'null']],
                            'qty' => ['type' => 'integer'],
                            'notes' => ['type' => ['string', 'null']],
                        ],
                        'required' => ['material_hint', 'thickness_mm', 'width_mm', 'height_mm', 'qty', 'notes'],
                        'additionalProperties' => false,
                    ],
                ],
                'confidence' => ['type' => 'number'],
                'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['pieces', 'confidence', 'warnings'],
            'additionalProperties' => false,
        ];
    }
}
