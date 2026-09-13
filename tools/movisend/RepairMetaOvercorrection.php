<?php

namespace App\Console\Commands;

use App\Models\Page;
use Illuminate\Console\Command;

/**
 * Repairs damage done by the first run of movisend:rebrand (see
 * RebrandToMovisend::FROM): it matched "Remitta" as a case-insensitive
 * SUBSTRING, which also matched inside the generic industry word
 * "remittance"/"Remittance" (remitta+nce), turning it into "MoviSendnce"
 * everywhere it appeared in the home Page's SEO fields. "Remittance" is a
 * category word, not the product's brand name, and should not have been
 * touched at all.
 *
 * This command restores the exact known-good text (the original values,
 * captured from the movisend:rebrand run's own BEFORE/AFTER log, with only
 * the genuine "Remito" brand mention swapped to "MoviSend") — guarded on
 * the current value still containing the "MoviSendnce" corruption marker,
 * so it only ever touches the rows it actually broke and is a no-op on any
 * later run. movisend:rebrand itself has since been fixed to use
 * word-boundary matching so this cannot recur.
 */
class RepairMetaOvercorrection extends Command
{
    protected $signature = 'movisend:repair-meta-overcorrection';

    protected $description = 'Restore Page SEO fields corrupted by the first (buggy) run of movisend:rebrand ("remittance" -> "MoviSendnce")';

    protected const MARKER = 'MoviSendnce';

    protected const CORRECTED = [
        'meta_title' => 'MoviSend – Complete Laravel Remittance Software for Global Money Transfer',
        'meta_description' => 'MoviSend is a complete Laravel-based remittance software that helps fintech startups, agencies, and money transfer companies manage secure international transactions, KYC verification, and agent-based payouts effortlessly.',
        'og_description' => 'MoviSend is a complete Laravel-based remittance software that helps fintech startups, agencies, and money transfer companies manage secure international transactions, KYC verification, and agent-based payouts effortlessly.',
    ];

    protected const CORRECTED_KEYWORDS = [
        'bank transfer', 'cross-border currency exchange', 'deposit', 'digital banking', 'dispute',
        'international cryptocurrency transfer', 'merchants', 'mobile money transfer', 'online mobile banking',
        'online payment', 'payment gateway', 'remittance script', 'send money', 'taptap send', 'world remit',
        'remittance software', 'money transfer software', 'Laravel remittance system', 'money transfer platform',
        'international remittance solution', 'fintech remittance script', 'Laravel money transfer app',
        'secure remittance solution', 'remittance management software', 'agent-based remittance system',
    ];

    public function handle(): int
    {
        $page = Page::where('slug', '/')->first();

        if (!$page) {
            $this->line('MISSING page slug=/');
            return self::SUCCESS;
        }

        foreach (self::CORRECTED as $field => $correctedValue) {
            $before = $page->{$field};
            $this->line("BEFORE page.{$field}: " . ($before ?: '(empty)'));

            if ($before && str_contains($before, self::MARKER)) {
                $page->{$field} = $correctedValue;
                $this->line("AFTER  page.{$field}: {$correctedValue}");
            } else {
                $this->line("SKIPPED page.{$field} (no '" . self::MARKER . "' marker found)");
            }
        }

        if ($page->isDirty()) {
            $page->save();
        }

        if (is_array($page->meta_keywords)) {
            $before = $page->meta_keywords;
            $corrupted = collect($before)->contains(fn ($k) => is_string($k) && str_contains($k, self::MARKER));

            $this->line('BEFORE page.meta_keywords: ' . implode(',', $before));

            if ($corrupted) {
                $page->meta_keywords = self::CORRECTED_KEYWORDS;
                $page->save();
                $this->line('AFTER  page.meta_keywords: ' . implode(',', $page->fresh()->meta_keywords));
            } else {
                $this->line('SKIPPED page.meta_keywords (no "' . self::MARKER . '" marker found)');
            }
        }

        return self::SUCCESS;
    }
}
