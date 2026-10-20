<?php

namespace Tests\Feature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class TranslationCompletenessTest extends TestCase
{
    public function test_every_literal_application_translation_has_an_arabic_value(): void
    {
        $translations = json_decode(file_get_contents(resource_path('lang/ar.json')), true, flags: JSON_THROW_ON_ERROR);
        $missing = [];

        foreach ([app_path(), resource_path('views')] as $root) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

            foreach ($files as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                preg_match_all(
                    '/__\(\s*[\'\"]([^\'\"]+)[\'\"]/',
                    file_get_contents($file->getPathname()),
                    $matches
                );

                foreach ($matches[1] as $key) {
                    if (! array_key_exists($key, $translations) || trim((string) $translations[$key]) === '') {
                        $missing[$key][] = $file->getPathname();
                    }
                }
            }
        }

        $this->assertSame([], $missing, 'Missing Arabic translations: '.implode(', ', array_keys($missing)));
    }

    public function test_arabic_layout_uses_rtl_direction(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $this->assertStringContainsString("app()->getLocale() === 'ar' ? 'rtl' : 'ltr'", $layout);
    }
}
