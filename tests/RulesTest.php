<?php

namespace XLaravel\Sluggable\Tests;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use XLaravel\Sluggable\Rules\Slug;
use XLaravel\Sluggable\Rules\UniqueSlug;
use XLaravel\Sluggable\Tests\Fixtures\Post;

class RulesTest extends TestCase
{
    #[DataProvider('validSlugs')]
    public function testAcceptsValidSlugs(string $value): void
    {
        $this->assertTrue(Validator::make(['slug' => $value], ['slug' => new Slug])->passes());
    }

    #[DataProvider('invalidSlugs')]
    public function testRejectsInvalidSlugs(mixed $value): void
    {
        $this->assertTrue(Validator::make(['slug' => $value], ['slug' => new Slug])->fails());
    }

    public function testExplainsTheFormatInTurkish(): void
    {
        App::setLocale('tr');

        $this->assertSame(
            'slug yalnızca küçük İngilizce harf, rakam ve "-" karakteri içerebilir; bu karakterle başlayamaz, bitemez ve art arda iki kez kullanılamaz.',
            Validator::make(['slug' => 'Konser'], ['slug' => new Slug])->errors()->first('slug'),
        );
    }

    public function testLimitsTheLength(): void
    {
        $validator = Validator::make(['slug' => 'konser-gecesi'], ['slug' => Slug::make()->max(5)]);

        $this->assertSame('The slug field must not be greater than 5 characters.', $validator->errors()->first('slug'));
    }

    public function testStringAlias(): void
    {
        $this->assertTrue(Validator::make(['slug' => 'konser'], ['slug' => 'slug'])->passes());
        $this->assertTrue(Validator::make(['slug' => 'Konser'], ['slug' => 'slug'])->fails());
    }

    public function testUniqueSlugSeesLiveAndReservedSlugs(): void
    {
        $post = Post::create(['title' => 'Konser']);
        $post->update(['slug' => 'yaz-konseri']);
        App::setLocale('tr');

        $rule = ['slug' => new UniqueSlug(Post::class)];

        $this->assertSame('slug başka bir kayıt tarafından kullanılıyor.', Validator::make(['slug' => 'yaz-konseri'], $rule)->errors()->first('slug'));
        $this->assertTrue(Validator::make(['slug' => 'Konser'], $rule)->fails());
        $this->assertTrue(Validator::make(['slug' => 'baska'], $rule)->passes());
    }

    public function testUniqueSlugIgnoresTheRecordItself(): void
    {
        $post = Post::create(['title' => 'Konser']);
        $post->update(['slug' => 'yaz-konseri']);

        $rule = ['slug' => new UniqueSlug($post)];

        $this->assertTrue(Validator::make(['slug' => 'yaz-konseri'], $rule)->passes());
        $this->assertTrue(Validator::make(['slug' => 'konser'], $rule)->passes());
    }

    /** @return array<string, array{string}> */
    public static function validSlugs(): array
    {
        return [
            'word' => ['konser'],
            'words' => ['rock-konseri-2026'],
            'digits' => ['2026'],
            'single letter' => ['x'],
        ];
    }

    /** @return array<string, array{mixed}> */
    public static function invalidSlugs(): array
    {
        return [
            'uppercase' => ['Konser'],
            'turkish letter' => ['çağrı'],
            'leading separator' => ['-konser'],
            'trailing separator' => ['konser-'],
            'double separator' => ['rock--konser'],
            'underscore' => ['rock_konser'],
            'dot' => ['rock.konser'],
            'array' => [['konser']],
        ];
    }
}
