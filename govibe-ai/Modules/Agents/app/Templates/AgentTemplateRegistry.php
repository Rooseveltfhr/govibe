<?php

namespace Modules\Agents\Templates;

use InvalidArgumentException;
use Modules\Agents\DTO\AgentDefinition;

/**
 * Katalòg modèl ajan disponib — se sa ki ranpli lis « chwazi yon modèl »
 * kliyan an wè anvan li kreye ajan pa l.
 *
 * Menm patèn ak ProviderRegistry nan AIProvider: ajoute yon modèl sektè
 * nouvo se yon dosye Template + yon `register()`, zewo modifikasyon nan
 * rès sistèm nan (Ouvè/Fèmen). Klas la se yon enstans, pa yon rejis
 * estatik — konsa chak tès kòmanse ak yon katalòg pwòp li, san eta ki
 * kole ant tès.
 */
class AgentTemplateRegistry
{
    /** @var array<string, TemplateDescriptor> */
    private array $templates = [];

    public function __construct(bool $withDefaults = true)
    {
        if ($withDefaults) {
            $this->registerDefaults();
        }
    }

    /**
     * @param  callable(string, string, array<string, mixed>, list<string>, list<string>, ?string): AgentDefinition  $factory
     * @param  callable(string): list<string>  $sampleQuestions
     */
    public function register(string $sector, string $label, string $description, callable $factory, callable $sampleQuestions): void
    {
        $this->templates[$sector] = new TemplateDescriptor($sector, $label, $description, $factory, $sampleQuestions);
    }

    public function has(string $sector): bool
    {
        return isset($this->templates[$sector]);
    }

    public function get(string $sector): ?TemplateDescriptor
    {
        return $this->templates[$sector] ?? null;
    }

    /** @return list<TemplateDescriptor> katalòg la, triye pa etikèt pou yon lis stab */
    public function all(): array
    {
        $all = array_values($this->templates);
        usort($all, static fn (TemplateDescriptor $a, TemplateDescriptor $b): int => $a->label <=> $b->label);

        return $all;
    }

    /**
     * Kreye yon ajan konkrè apati yon modèl — sa bouton « Kreye » a rele.
     *
     * @param  array<string, mixed>  $knowledge
     * @param  list<string>  $channels
     * @param  list<string>  $languages
     */
    public function make(
        string $sector,
        string $key,
        string $name,
        array $knowledge = [],
        array $channels = ['whatsapp'],
        array $languages = ['ht', 'fr'],
        ?string $handoffTo = null,
    ): AgentDefinition {
        $descriptor = $this->templates[$sector]
            ?? throw new InvalidArgumentException("Modèl sektè enkoni: {$sector}. Katalòg la genyen: ".implode(', ', array_keys($this->templates)));

        return ($descriptor->factory)($key, $name, $knowledge, $channels, $languages, $handoffTo);
    }

    /**
     * Kesyon egzanp pou bouton « Demo » a.
     *
     * @return list<string>
     */
    public function sampleQuestions(string $sector, string $language = 'ht'): array
    {
        $descriptor = $this->templates[$sector]
            ?? throw new InvalidArgumentException("Modèl sektè enkoni: {$sector}");

        return ($descriptor->sampleQuestions)($language);
    }

    private function registerDefaults(): void
    {
        $this->register(
            'restaurant',
            'Restoran',
            'Kòmand, mni, pri, rezèvasyon tab.',
            RestaurantTemplate::make(...),
            RestaurantTemplate::sampleQuestions(...),
        );

        $this->register(
            'clinic',
            'Klinik / Sante',
            'Randevou, sèvis, pri — zewo konsèy medikal.',
            ClinicTemplate::make(...),
            ClinicTemplate::sampleQuestions(...),
        );

        $this->register(
            'school',
            'Lekòl',
            'Enskripsyon, pwogram, frè, randevou ak direksyon an.',
            SchoolTemplate::make(...),
            SchoolTemplate::sampleQuestions(...),
        );
    }
}
