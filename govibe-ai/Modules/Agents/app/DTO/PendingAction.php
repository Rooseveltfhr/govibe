<?php

namespace Modules\Agents\DTO;

/**
 * Yon aksyon ajan an vle fè, men ki poko fèt.
 *
 * Li rete an atant jiskaske itilizatè a konfime — se sa ki pèmèt lanse ak
 * yon ASR ki pa pafè: yon erè vin yon ti korije, se pa yon move kòmand.
 */
final readonly class PendingAction
{
    /**
     * @param  string  $tool  non zouti a: 'create_order', 'book_appointment'…
     * @param  array<string, mixed>  $arguments
     * @param  float  $confidence  0..1 — konbyen ajan an sèten
     */
    public function __construct(
        public string $tool,
        public array $arguments = [],
        public float $confidence = 1.0,
    ) {}

    /** Rezime lizib pou moun nan konfime — « 2 poul, 1 diri ». */
    public function summary(): string
    {
        $parts = [];

        foreach ($this->arguments as $key => $value) {
            if (is_scalar($value) && (string) $value !== '') {
                $parts[] = (string) $value;
            }
        }

        return implode(', ', $parts);
    }

    public function withConfidence(float $confidence): self
    {
        return new self($this->tool, $this->arguments, $confidence);
    }
}
