<?php

namespace XLaravel\Sluggable\Tests;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use XLaravel\Sluggable\Events\SlugChanged;
use XLaravel\Sluggable\Models\SlugHistory;
use XLaravel\Sluggable\Tests\Fixtures\Post;
use XLaravel\Sluggable\Tests\Fixtures\RacyPost;
use XLaravel\Sluggable\Tests\Fixtures\RegeneratingPost;

class SluggableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RacyPost::$misses = 0;
    }

    public function testGeneratesTheSlugFromTheSource(): void
    {
        $this->assertSame('rock-konseri', Post::create(['title' => 'Rock Konseri'])->slug);
    }

    public function testFindsTheNextNumberWithAFixedNumberOfQueries(): void
    {
        foreach (range(1, 20) as $i) {
            Post::create(['title' => 'Konser']);
        }

        $post = Post::create(['title' => 'Konser']);
        $post->update(['slug' => 'yaz-konseri']);

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->assertSame('konser-22', Post::create(['title' => 'Konser'])->slug);
        $this->assertLessThanOrEqual(6, $queries);
    }

    public function testNumbersCorrectlyWhenTheBaseIsCutToMakeRoom(): void
    {
        $post = new class extends Post {
            protected $table = 'posts';

            protected ?int $slugMaxLength = 10;
        };

        $first = new $post;
        $first->fill(['title' => 'Konser Gecesi'])->save();
        $second = new $post;
        $second->fill(['title' => 'Konser Gecesi'])->save();
        $third = new $post;
        $third->fill(['title' => 'Konser Gecesi'])->save();

        $this->assertSame(['konser-gec', 'konser-g-2', 'konser-g-3'], [$first->slug, $second->slug, $third->slug]);
    }

    public function testJoinsSeveralSourceAttributes(): void
    {
        $post = new class extends Post {
            protected $table = 'posts';

            public function slugSource(): array
            {
                return ['title', 'subtitle'];
            }
        };

        $post->fill(['title' => 'Rock', 'subtitle' => 'Gecesi'])->save();

        $this->assertSame('rock-gecesi', $post->slug);
    }

    public function testNumbersATakenSlug(): void
    {
        Post::create(['title' => 'Konser']);

        $this->assertSame('konser-2', Post::create(['title' => 'Konser'])->slug);
        $this->assertSame('konser-3', Post::create(['title' => 'Konser'])->slug);
    }

    public function testASoftDeletedRecordKeepsItsSlug(): void
    {
        Post::create(['title' => 'Konser'])->delete();

        $this->assertSame('konser-2', Post::create(['title' => 'Konser'])->slug);
    }

    public function testKeepsTheSlugWhenTheSourceChangesByDefault(): void
    {
        $post = Post::create(['title' => 'Konser']);

        $post->update(['title' => 'Yeni Konser']);

        $this->assertSame('konser', $post->fresh()?->slug);
    }

    public function testRegeneratesWhenTheModelAsksForIt(): void
    {
        $post = RegeneratingPost::create(['title' => 'Konser']);

        $post->update(['title' => 'Yeni Konser']);

        $this->assertSame('yeni-konser', $post->slug);
    }

    public function testRegeneratesWhenTheConfigAsksForIt(): void
    {
        Config::set('sluggable.regenerate', true);
        $post = Post::create(['title' => 'Konser']);

        $post->update(['title' => 'Yeni Konser']);

        $this->assertSame('yeni-konser', $post->slug);
    }

    public function testLowercasesAGivenSlug(): void
    {
        $this->assertSame('rock-gecesi', Post::create(['title' => 'Konser', 'slug' => 'Rock-Gecesi'])->slug);
    }

    public function testRecordsTheOldSlugAndResolvesIt(): void
    {
        $post = Post::create(['title' => 'Konser']);
        $post->update(['slug' => 'yaz-konseri']);

        $this->assertDatabaseHas('slug_history', ['sluggable_id' => $post->id, 'slug' => 'konser']);
        $this->assertTrue(Post::findBySlug('konser')?->is($post));
        $this->assertTrue(Post::findBySlug('yaz-konseri')?->is($post));
        $this->assertNull(Post::findBySlug('baska'));
    }

    public function testTheScopeCombinesWithOtherConstraints(): void
    {
        $post = Post::create(['title' => 'Konser']);
        $post->update(['slug' => 'yaz-konseri']);

        $this->assertSame(1, Post::query()->whereSlug('konser')->where('title', 'Konser')->count());
        $this->assertSame(0, Post::query()->whereSlug('konser')->where('title', 'Başka')->count());
    }

    public function testRouteBindingFollowsTheHistory(): void
    {
        $post = Post::create(['title' => 'Konser']);
        $post->update(['slug' => 'yaz-konseri']);

        $this->assertTrue((new Post)->resolveRouteBinding('konser')?->is($post));
    }

    public function testAnOldSlugIsReservedForItsRecord(): void
    {
        $post = Post::create(['title' => 'Konser']);
        $post->update(['slug' => 'yaz-konseri']);

        $this->assertSame('konser-2', Post::create(['title' => 'Konser'])->slug);
        $this->assertTrue((new Post)->slugTaken('konser'));
    }

    public function testTheReservationEndsAfterItsPeriod(): void
    {
        $post = Post::create(['title' => 'Konser']);
        $post->update(['slug' => 'yaz-konseri']);

        $this->travel(91)->days();

        $other = Post::create(['title' => 'Konser']);

        $this->assertSame('konser', $other->slug);
        $this->assertTrue(Post::findBySlug('konser')?->is($other));
        $this->assertDatabaseMissing('slug_history', ['sluggable_id' => $post->id, 'slug' => 'konser']);
    }

    public function testANullReservationNeverEnds(): void
    {
        Config::set('sluggable.history.reserve_for', null);
        $post = Post::create(['title' => 'Konser']);
        $post->update(['slug' => 'yaz-konseri']);

        $this->travel(5000)->days();

        $this->assertSame('konser-2', Post::create(['title' => 'Konser'])->slug);
    }

    public function testAZeroReservationReservesNothing(): void
    {
        Config::set('sluggable.history.reserve_for', 0);
        $post = Post::create(['title' => 'Konser']);
        $post->update(['slug' => 'yaz-konseri']);

        $this->assertSame('konser', Post::create(['title' => 'Konser'])->slug);
    }

    public function testTheModelCanSetItsOwnReservation(): void
    {
        $post = new class extends Post {
            protected $table = 'posts';

            protected ?int $slugReservedFor = 10;
        };

        $post->fill(['title' => 'Konser'])->save();
        $post->update(['slug' => 'yaz-konseri']);

        $this->assertSame(10, $post->slugReservedFor());
        $this->assertTrue($post->slugIsReserved('konser') === false);
        $this->assertTrue((new $post)->slugIsReserved('konser'));
    }

    public function testARecordCanReturnToItsOldSlug(): void
    {
        $post = Post::create(['title' => 'Konser']);
        $post->update(['slug' => 'yaz-konseri']);

        $post->update(['slug' => 'konser']);

        $this->assertSame('konser', $post->slug);
        $this->assertDatabaseMissing('slug_history', ['sluggable_id' => $post->id, 'slug' => 'konser']);
        $this->assertDatabaseHas('slug_history', ['sluggable_id' => $post->id, 'slug' => 'yaz-konseri']);
    }

    public function testHistoryCanBeTurnedOffInTheConfig(): void
    {
        Config::set('sluggable.history.enabled', false);
        $post = Post::create(['title' => 'Konser']);
        $post->update(['slug' => 'yaz-konseri']);

        $this->assertSame(0, SlugHistory::query()->count());
        $this->assertNull(Post::findBySlug('konser'));
        $this->assertSame('konser', Post::create(['title' => 'Konser'])->slug);
    }

    public function testHistoryCanBeTurnedOffOnTheModel(): void
    {
        $post = new class extends Post {
            protected $table = 'posts';

            protected bool $slugHistory = false;
        };

        $post->fill(['title' => 'Konser'])->save();
        $post->update(['slug' => 'yaz-konseri']);

        $this->assertSame(0, SlugHistory::query()->count());
    }

    public function testAForceDeletedRecordLeavesNoHistory(): void
    {
        $post = Post::create(['title' => 'Konser']);
        $post->update(['slug' => 'yaz-konseri']);

        $post->delete();
        $this->assertSame(1, SlugHistory::query()->count());

        $post->forceDelete();
        $this->assertSame(0, SlugHistory::query()->count());
    }

    public function testOldHistoryIsPruned(): void
    {
        $post = Post::create(['title' => 'Konser']);
        $post->update(['slug' => 'yaz-konseri']);
        $this->travel(366)->days();
        $post->update(['slug' => 'kis-konseri']);

        $this->artisan('model:prune', ['--model' => [SlugHistory::class]]);

        $this->assertSame(['yaz-konseri'], SlugHistory::query()->pluck('slug')->all());
    }

    public function testNothingIsPrunedWithoutAPeriod(): void
    {
        Config::set('sluggable.prune.after', null);
        $post = Post::create(['title' => 'Konser']);
        $post->update(['slug' => 'yaz-konseri']);
        $this->travel(5000)->days();

        $this->artisan('model:prune', ['--model' => [SlugHistory::class]]);

        $this->assertSame(1, SlugHistory::query()->count());
    }

    public function testAnnouncesTheChange(): void
    {
        $heard = $this->recorder();
        Event::listen(SlugChanged::class, fn (SlugChanged $event) => $heard[] = "{$event->old} -> {$event->new}");
        Post::slugChanged(function (Post $post) use ($heard) {
            $old = $post->getRawOriginal('slug');
            $heard[] = 'model: ' . (is_string($old) ? $old : '') . ' -> ' . $post->slug;
        });

        Post::create(['title' => 'Konser'])->update(['slug' => 'yaz-konseri']);

        $this->assertSame(['model: konser -> yaz-konseri', 'konser -> yaz-konseri'], $heard->getArrayCopy());
    }

    public function testDoesNotAnnounceTheFirstSlug(): void
    {
        $heard = $this->recorder();
        Event::listen(SlugChanged::class, fn () => $heard[] = 'changed');

        Post::create(['title' => 'Konser'])->update(['title' => 'Başka']);

        $this->assertCount(0, $heard);
    }

    public function testChangingCanCancelTheSave(): void
    {
        $post = Post::create(['title' => 'Konser']);
        Post::slugChanging(fn () => false);

        $this->assertFalse($post->update(['slug' => 'yaz-konseri']));
        $this->assertSame('konser', Post::query()->whereKey($post->id)->value('slug'));
        $this->assertSame(0, SlugHistory::query()->count());
    }

    public function testTheChangeIsAnnouncedAfterCommit(): void
    {
        $heard = $this->recorder();
        Event::listen(SlugChanged::class, fn () => $heard[] = 'changed');
        $post = Post::create(['title' => 'Konser']);

        DB::beginTransaction();
        $post->update(['slug' => 'yaz-konseri']);
        $this->assertCount(0, $heard);
        DB::rollBack();

        $this->assertCount(0, $heard);
    }

    public function testRetriesAGeneratedSlugThatLosesARace(): void
    {
        Post::create(['title' => 'Konser']);
        RacyPost::$misses = 1;

        $this->assertSame('konser-2', RacyPost::create(['title' => 'Konser'])->slug);
    }

    public function testARetryKeepsTheSurroundingTransactionUsable(): void
    {
        Post::create(['title' => 'Konser']);
        RacyPost::$misses = 1;

        DB::transaction(function () {
            RacyPost::create(['title' => 'Konser']);
            Post::create(['title' => 'Tiyatro']);
        });

        $this->assertSame(['konser', 'konser-2', 'tiyatro'], Post::query()->orderBy('id')->pluck('slug')->all());
    }

    public function testGivesUpAfterTheConfiguredRetries(): void
    {
        Post::create(['title' => 'Konser']);
        RacyPost::$misses = 3;

        $this->expectException(UniqueConstraintViolationException::class);

        RacyPost::create(['title' => 'Konser']);
    }

    public function testDoesNotRetryAGivenSlug(): void
    {
        Post::create(['title' => 'Konser']);

        $this->expectException(UniqueConstraintViolationException::class);

        Post::create(['title' => 'Başka', 'slug' => 'konser']);
    }

    /**
     * @return \ArrayObject<int, string>
     */
    private function recorder(): \ArrayObject
    {
        /** @var \ArrayObject<int, string> */
        return new \ArrayObject;
    }

    public function testRequiresASource(): void
    {
        $post = new class extends \Illuminate\Database\Eloquent\Model {
            use \XLaravel\Sluggable\Concerns\Sluggable;

            protected $table = 'posts';

            public $timestamps = false;
        };

        $this->expectException(\LogicException::class);

        $post->save();
    }
}
