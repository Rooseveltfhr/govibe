<?php

namespace App\Console\Commands;

use App\Models\Content;
use Illuminate\Console\Command;

/**
 * One-off maintenance command for demomovisend.govibeht.com.
 *
 * The hero ("Open an Account") and cta ("Register Now") sections on the
 * home page were shipped (CodeCanyon "Remito" demo content) with their
 * button_url pointing at the vendor's own domain (bugfinder.app) instead
 * of this site's /register route. Fixes exactly those two Content rows,
 * only when the current value still contains "bugfinder.app" — anything
 * else is left untouched and reported as SKIPPED.
 *
 * Safe to run more than once (idempotent: the guard means a second run
 * only prints SKIPPED lines). Deployed via the movisend-home.yml
 * GitHub Actions workflow (mode=fix_cta_links), then removed from the
 * server once confirmed working.
 */
class FixCtaLinks extends Command
{
    protected $signature = 'movisend:fix-cta-links';

    protected $description = 'Fix hero/cta button_url still pointing at the vendor bugfinder.app domain';

    public function handle(): int
    {
        $this->fix('hero', 'single');
        $this->fix('cta', 'single');

        return self::SUCCESS;
    }

    protected function fix(string $type, string $name): void
    {
        $content = Content::where('type', $type)->where('name', $name)->first();

        if (!$content) {
            $this->line("MISSING {$type}/{$name}");
            return;
        }

        $media = $content->media;
        $before = isset($media->button_url) ? $media->button_url : null;

        $this->line("BEFORE {$type}/{$name}: " . ($before ?: '(empty)'));

        if ($before && str_contains($before, 'bugfinder.app')) {
            $media->button_url = 'https://demomovisend.govibeht.com/register';
            $content->media = $media;
            $content->save();

            $this->line("AFTER  {$type}/{$name}: " . $content->fresh()->media->button_url);
        } else {
            $this->line("SKIPPED {$type}/{$name} (does not contain bugfinder.app)");
        }
    }
}
