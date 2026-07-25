<?php

namespace App\Services\Cutting\Layout;

/**
 * A node in the guillotine cut tree. Either a leaf (a piece, an offcut or trim
 * waste) or a split: one straight cut dividing this rectangle into two.
 */
final class CutNode
{
    /** @var array<int, CutNode> */
    public array $children = [];

    public ?CutSegment $cut = null;

    public ?Placement $placement = null;

    public function __construct(
        public readonly string $kind,
        public readonly Rect $rect,
    ) {}

    public static function leaf(string $kind, Rect $rect, ?Placement $placement = null): self
    {
        $node = new self($kind, $rect);
        $node->placement = $placement;

        return $node;
    }

    public static function split(Rect $rect, ?CutSegment $cut, CutNode ...$children): self
    {
        $node = new self('split', $rect);
        $node->cut = $cut;
        $node->children = array_values($children);

        return $node;
    }

    public function toArray(): array
    {
        $data = [
            'kind' => $this->kind,
            'rect' => $this->rect->toArray(),
        ];

        if ($this->cut !== null) {
            $data['cut'] = $this->cut->toArray();
        }

        if ($this->placement !== null) {
            $data['placement'] = $this->placement->toArray();
        }

        if ($this->children !== []) {
            $data['children'] = array_map(fn (CutNode $child) => $child->toArray(), $this->children);
        }

        return $data;
    }
}
