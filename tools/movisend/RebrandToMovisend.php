<?php

namespace App\Console\Commands;

use App\Models\BasicControl;
use App\Models\Page;
use Illuminate\Console\Command;

/**
 * One-off maintenance command for demomovisend.govibeht.com.
 *
 * The site is a white-labeled CodeCanyon "Remito" demo (.env already has
 * APP_NAME="MoviSend", but the page-facing branding still literally says
 * "Remito"). Two DB sources drive that text:
 *
 *  - BasicControl::firstOrCreate()->site_title (cached under the
 *    'ConfigureSetting' key — see helpers.php's basicControl()) feeds the
 *    <title> tag, <meta name="author">, og:site_name, the footer logo's
 *    alt text, and the footer copyright line — ALL from this one field.
 *  - Page (slug '/')'s page_title/meta_title/meta_description/
 *    og_description feed the rest of the per-page SEO tags (see
 *    FrontendController::getPageSeo()).
 *
 * Only replaces occurrences of "Remito"/"Remitta" — anything else is left
 * untouched. Safe to run more than once (idempotent: a second run only
 * prints SKIPPED lines). Deployed via the movisend-home.yml GitHub Actions
 * workflow (mode=rebrand), then removed from the server once confirmed
 * working.
 */
class RebrandToMovisend extends Command
{
    protected $signature = 'movisend:rebrand';

    protected $description = 'Replace remaining "Remito"/"Remitta" branding with "MoviSend" in BasicControl and the home Page SEO fields';

    protected const FROM = ['Remito', 'Remitta'];
    protected const TO = 'MoviSend';

    public function handle(): int
    {
        $this->fixSiteTitle();
        $this->fixPageSeo();

        return self::SUCCESS;
    }

    protected function fixSiteTitle(): void
    {
        $control = BasicControl::firstOrCreate();
        $before = $control->site_title;

        $this->line('BEFORE BasicControl.site_title: ' . ($before ?: '(empty)'));

        if ($before && str_ireplace(self::FROM, self::TO, $before) !== $before) {
            $control->site_title = str_ireplace(self::FROM, self::TO, $before);
            $control->save();
            \Cache::forget('ConfigureSetting');

            $this->line('AFTER  BasicControl.site_title: ' . $control->fresh()->site_title);
        } else {
            $this->line('SKIPPED BasicControl.site_title (no Remito/Remitta found)');
        }
    }

    protected function fixPageSeo(): void
    {
        $page = Page::where('slug', '/')->first();

        if (!$page) {
            $this->line('MISSING page slug=/');
            return;
        }

        foreach (['page_title', 'meta_title', 'meta_description', 'og_description'] as $field) {
            $before = $page->{$field};
            $this->line("BEFORE page.{$field}: " . ($before ?: '(empty)'));

            if ($before && str_ireplace(self::FROM, self::TO, $before) !== $before) {
                $page->{$field} = str_ireplace(self::FROM, self::TO, $before);
                $this->line("AFTER  page.{$field}: " . $page->{$field});
            } else {
                $this->line("SKIPPED page.{$field} (no Remito/Remitta found)");
            }
        }

        if ($page->isDirty()) {
            $page->save();
        }

        if (is_array($page->meta_keywords)) {
            $beforeKeywords = $page->meta_keywords;
            $afterKeywords = array_map(
                fn ($keyword) => is_string($keyword) ? str_ireplace(self::FROM, self::TO, $keyword) : $keyword,
                $beforeKeywords
            );

            $this->line('BEFORE page.meta_keywords: ' . implode(',', $beforeKeywords));

            if ($afterKeywords !== $beforeKeywords) {
                $page->meta_keywords = $afterKeywords;
                $page->save();
                $this->line('AFTER  page.meta_keywords: ' . implode(',', $page->fresh()->meta_keywords));
            } else {
                $this->line('SKIPPED page.meta_keywords (no Remito/Remitta found)');
            }
        }
    }
}
