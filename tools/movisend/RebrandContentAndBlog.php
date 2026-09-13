<?php

namespace App\Console\Commands;

use App\Models\Blog;
use App\Models\Content;
use App\Models\ContentDetails;
use Illuminate\Console\Command;

/**
 * One-off maintenance command for demomovisend.govibeht.com.
 *
 * PR #179 rebranded BasicControl.site_title and the home Page's SEO
 * fields. This command extends the same word-boundary rebrand (see
 * RebrandToMovisend::rebrand()) to the remaining "Remito"/"Remitta"
 * mentions Roosevelt asked to remove everywhere:
 *
 *  - Content.media (JSON) and ContentDetails.description (JSON,
 *    per-language) — section text such as "Why People Choose Remitta",
 *    "Business Already Running on Remitta" (choose/testimonial/blog
 *    header sections on the home page).
 *  - Blog.title / slug / meta_title / meta_description / meta_keywords —
 *    demo blog post metadata (slug included: it's still a visible
 *    "Remitta" mention in the URL, and blog-details links are generated
 *    dynamically from this column, so updating it keeps internal links
 *    consistent).
 *  - BlogDetails' string columns (the article body) — walked generically
 *    (field name confirmed only that the model exists via belongsTo(Blog),
 *    not assumed) so this doesn't depend on guessing a column name.
 *
 * Same safety rules as RebrandToMovisend: word-boundary regex only (never
 * touches "remittance"), only writes when the value actually changed,
 * logs before/after per row/field, idempotent (a second run only prints
 * rows that still match, which after the first run is none).
 */
class RebrandContentAndBlog extends Command
{
    protected $signature = 'movisend:rebrand-content';

    protected $description = 'Extend the Remito/Remitta -> MoviSend rebrand to Content sections and Blog posts';

    protected const FROM_PATTERN = '/\b(Remito|Remitta)\b/i';
    protected const TO = 'MoviSend';

    protected static function rebrand(string $text): string
    {
        return preg_replace(self::FROM_PATTERN, self::TO, $text);
    }

    protected function rebrandValue($value)
    {
        if (is_string($value)) {
            return static::rebrand($value);
        }
        if (is_array($value)) {
            return array_map(fn ($v) => $this->rebrandValue($v), $value);
        }
        if (is_object($value)) {
            $clone = clone $value;
            foreach (get_object_vars($clone) as $key => $val) {
                $clone->$key = $this->rebrandValue($val);
            }
            return $clone;
        }
        return $value;
    }

    public function handle(): int
    {
        $this->rebrandContentMedia();
        $this->rebrandContentDetails();
        $this->rebrandBlogs();

        return self::SUCCESS;
    }

    protected function rebrandContentMedia(): void
    {
        $this->line('=== Content.media ===');

        Content::all()->each(function (Content $content) {
            $before = $content->media;
            $beforeJson = json_encode($before);

            if (!$beforeJson || !preg_match(self::FROM_PATTERN, $beforeJson)) {
                return;
            }

            $after = $this->rebrandValue($before);
            $afterJson = json_encode($after);

            $this->line("Content#{$content->id} ({$content->name}/{$content->type})");
            $this->line("  BEFORE: {$beforeJson}");

            if ($afterJson !== $beforeJson) {
                $content->media = $after;
                $content->save();
                $this->line('  AFTER:  ' . json_encode($content->fresh()->media));
            } else {
                $this->line('  SKIPPED (no change after word-boundary match)');
            }
        });
    }

    protected function rebrandContentDetails(): void
    {
        $this->line('=== ContentDetails.description ===');

        ContentDetails::with('content')->get()->each(function (ContentDetails $cd) {
            $before = $cd->description;
            $beforeJson = json_encode($before);

            if (!$beforeJson || !preg_match(self::FROM_PATTERN, $beforeJson)) {
                return;
            }

            $after = $this->rebrandValue($before);
            $afterJson = json_encode($after);

            $name = optional($cd->content)->name;
            $type = optional($cd->content)->type;

            $this->line("ContentDetails#{$cd->id} (content: {$name}/{$type}, language_id={$cd->language_id})");
            $this->line("  BEFORE: {$beforeJson}");

            if ($afterJson !== $beforeJson) {
                $cd->description = $after;
                $cd->save();
                $this->line('  AFTER:  ' . json_encode($cd->fresh()->description));
            } else {
                $this->line('  SKIPPED (no change after word-boundary match)');
            }
        });
    }

    protected function rebrandBlogs(): void
    {
        $this->line('=== Blog ===');

        Blog::with('details')->get()->each(function (Blog $blog) {
            foreach (['title', 'slug', 'meta_title', 'meta_description'] as $field) {
                $before = $blog->{$field};
                if (!is_string($before) || !preg_match(self::FROM_PATTERN, $before)) {
                    continue;
                }
                $after = static::rebrand($before);
                if ($field === 'slug') {
                    $after = strtolower($after);
                }
                $this->line("Blog#{$blog->id}.{$field} BEFORE: {$before}");
                if ($after !== $before) {
                    $blog->{$field} = $after;
                    $this->line("Blog#{$blog->id}.{$field} AFTER:  {$after}");
                }
            }

            // Repair pass: a slug must always be fully lowercase. The first
            // run of this command (before this safeguard existed) left
            // "MoviSend" mixed-case inside a slug ("...-with-MoviSend-today"
            // instead of "...-with-movisend-today"). Self-heals on rerun.
            if (is_string($blog->slug) && $blog->slug !== strtolower($blog->slug)) {
                $before = $blog->slug;
                $blog->slug = strtolower($blog->slug);
                $this->line("Blog#{$blog->id}.slug case-repair BEFORE: {$before}");
                $this->line("Blog#{$blog->id}.slug case-repair AFTER:  {$blog->slug}");
            }

            if (is_array($blog->meta_keywords)) {
                $beforeKeywords = $blog->meta_keywords;
                $afterKeywords = array_map(
                    fn ($k) => is_string($k) ? static::rebrand($k) : $k,
                    $beforeKeywords
                );
                if ($afterKeywords !== $beforeKeywords) {
                    $this->line("Blog#{$blog->id}.meta_keywords BEFORE: " . implode(',', $beforeKeywords));
                    $blog->meta_keywords = $afterKeywords;
                    $this->line("Blog#{$blog->id}.meta_keywords AFTER:  " . implode(',', $afterKeywords));
                }
            }

            if ($blog->isDirty()) {
                $blog->save();
            }

            $detail = $blog->details;
            if (!$detail) {
                return;
            }

            $dirty = false;
            foreach ($detail->getAttributes() as $key => $value) {
                if (in_array($key, ['id', 'blog_id'], true) || !is_string($value) || !preg_match(self::FROM_PATTERN, $value)) {
                    continue;
                }
                $after = static::rebrand($value);
                $this->line("BlogDetails#{$detail->id}.{$key} BEFORE (first 150 chars): " . substr($value, 0, 150));
                if ($after !== $value) {
                    $detail->{$key} = $after;
                    $dirty = true;
                    $this->line("BlogDetails#{$detail->id}.{$key} AFTER  (first 150 chars): " . substr($after, 0, 150));
                }
            }
            if ($dirty) {
                $detail->save();
            }
        });
    }
}
