<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TranslationFilesTest extends TestCase
{
    public static function translationFiles(): array
    {
        $files = glob(__DIR__.'/../../resources/lang/*/*.php');
        $cases = [];
        foreach ($files as $file) {
            $cases[basename(dirname($file)).'/'.basename($file)] = [$file];
        }

        return $cases;
    }

    #[DataProvider('translationFiles')]
    public function test_translation_file_returns_an_array_without_output(string $file): void
    {
        ob_start();
        try {
            $translations = require $file;
        } finally {
            $output = ob_get_clean();
        }

        $this->assertSame('', $output, $file);
        $this->assertIsArray($translations, $file);
    }
}
