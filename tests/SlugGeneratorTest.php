<?php

namespace XLaravel\Sluggable\Tests;

use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use XLaravel\Sluggable\SlugGenerator;

class SlugGeneratorTest extends TestCase
{
    #[DataProvider('turkishValues')]
    public function testSlugifiesTurkishText(string $value, string $expected): void
    {
        Config::set('sluggable.language', 'tr');

        $this->assertSame($expected, SlugGenerator::slugify($value));
    }

    public function testCutsToTheMaximumLengthWithoutATrailingSeparator(): void
    {
        $this->assertSame('ahmet', SlugGenerator::slugify('Ahmet Can', 6));
    }

    public function testUsesTheConfiguredSeparator(): void
    {
        Config::set('sluggable.separator', '_');

        $this->assertSame('rock_konseri', SlugGenerator::slugify('Rock Konseri'));
    }

    public function testKeepsAFreeSlug(): void
    {
        $this->assertSame('konser', SlugGenerator::unique('konser', fn () => false));
    }

    public function testNumbersATakenSlugStartingAtTwo(): void
    {
        $taken = ['konser', 'konser-2'];

        $this->assertSame('konser-3', SlugGenerator::unique('konser', fn (string $slug) => in_array($slug, $taken, true)));
    }

    public function testStartsAtTheConfiguredSuffix(): void
    {
        Config::set('sluggable.first_suffix', 1);

        $this->assertSame('konser-1', SlugGenerator::unique('konser', fn (string $slug) => $slug === 'konser'));
    }

    public function testStaysWithinTheMaximumLengthWhenNumbering(): void
    {
        $slug = SlugGenerator::unique('konser-gecesi', fn (string $slug) => $slug === 'konser-gecesi', 10);

        $this->assertSame('konser-g-2', $slug);
    }

    public function testNumbersAnEmptySlug(): void
    {
        $this->assertSame('2', SlugGenerator::unique('', fn () => false));
    }

    /** @return array<string, array{string, string}> */
    public static function turkishValues(): array
    {
        return [
            'turkish letters' => ['Iğdır Şükrü Çağlayan', 'igdir-sukru-caglayan'],
            'capital dotted i' => ['ŞİŞLİ', 'sisli'],
            'ampersand' => ['İstanbul & Ankara', 'istanbul-ankara'],
            'at sign' => ['Rock @ Bostancı', 'rock-at-bostanci'],
            'punctuation' => ['Ödül Töreni 2026!', 'odul-toreni-2026'],
            'symbols' => ['C++ / Java', 'c-java'],
            'apostrophe' => ['Ahmet’in Konseri', 'ahmetin-konseri'],
            'accents' => ['Café Crème', 'cafe-creme'],
        ];
    }
}
