<?php

namespace XLaravel\Sluggable\Tests;

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

class ScheduleTest extends TestCase
{
    public function testSchedulesPruningDailyInChunksOfAThousand(): void
    {
        $event = $this->pruningEvent();

        $this->assertNotNull($event);
        $this->assertSame('0 0 * * *', $event->expression);
        $this->assertStringContainsString('SlugHistory', (string) $event->command);
        $this->assertStringContainsString('--chunk=1000', (string) $event->command);
    }

    public function testTheChunkSizeCanBeConfigured(): void
    {
        Config::set('sluggable.prune.chunk', 250);

        $this->assertStringContainsString('--chunk=250', (string) $this->pruningEvent()?->command);
    }

    public function testAcceptsACronExpression(): void
    {
        Config::set('sluggable.prune.schedule', '30 3 * * 1');

        $this->assertSame('30 3 * * 1', $this->pruningEvent()?->expression);
    }

    public function testAcceptsAFrequencyWithArguments(): void
    {
        Config::set('sluggable.prune.schedule', 'weeklyOn:1,04:00');

        $this->assertSame('0 4 * * 1', $this->pruningEvent()?->expression);
    }

    public function testSchedulesNothingWhenPruningIsOff(): void
    {
        Config::set('sluggable.prune.enabled', false);

        $this->assertNull($this->pruningEvent());
    }

    public function testSchedulesNothingWithoutAPeriod(): void
    {
        Config::set('sluggable.prune.after', null);

        $this->assertNull($this->pruningEvent());
    }

    public function testSchedulesNothingWhenHistoryIsOff(): void
    {
        Config::set('sluggable.history.enabled', false);

        $this->assertNull($this->pruningEvent());
    }

    public function testRejectsAnUnknownFrequency(): void
    {
        Config::set('sluggable.prune.schedule', 'sometimes');

        $this->expectException(InvalidArgumentException::class);

        $this->pruningEvent();
    }

    private function pruningEvent(): ?Event
    {
        $app = $this->app;
        $this->assertNotNull($app);
        $app->forgetInstance(Schedule::class);

        foreach ($app->make(Schedule::class)->events() as $event) {
            if (str_contains((string) $event->command, 'model:prune')) {
                return $event;
            }
        }

        return null;
    }
}
