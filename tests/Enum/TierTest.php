<?php

declare(strict_types=1);

namespace App\Tests\Enum;

use App\Enum\Tier;
use PHPUnit\Framework\TestCase;

class TierTest extends TestCase
{
    public function testTiersAreDeclaredFromLowestToHighest(): void
    {
        $this->assertSame(
            ['Iron', 'Bronze', 'Silver', 'Gold', 'Platinum', 'Emerald', 'Diamond', 'Master', 'Grandmaster', 'Challenger'],
            Tier::values()
        );
    }

    public function testEmeraldSitsBetweenPlatinumAndDiamond(): void
    {
        $this->assertGreaterThan(Tier::PLATINUM->rank(), Tier::EMERALD->rank());
        $this->assertLessThan(Tier::DIAMOND->rank(), Tier::EMERALD->rank());
    }

    public function testCompositeLabelIsParsed(): void
    {
        $this->assertSame(Tier::DIAMOND, Tier::fromLabel('Diamond II'));
        $this->assertSame(Tier::MASTER, Tier::fromLabel('Master I'));
        $this->assertSame(Tier::GRANDMASTER, Tier::fromLabel('Grandmaster '));
    }

    public function testParsingIsCaseInsensitive(): void
    {
        $this->assertSame(Tier::DIAMOND, Tier::fromLabel('diamond'));
        $this->assertSame(Tier::DIAMOND, Tier::fromLabel('DIAMOND IV'));
    }

    public function testUnrankedAndUnknownLabelsYieldNull(): void
    {
        $this->assertNull(Tier::fromLabel('Unranked'));
        $this->assertNull(Tier::fromLabel('Legendaire'));
        $this->assertNull(Tier::fromLabel(''));
        $this->assertNull(Tier::fromLabel(null));
    }

    public function testAndAboveIncludesItself(): void
    {
        $this->assertContains(Tier::MASTER, Tier::MASTER->andAbove());
    }

    public function testAndAboveReturnsOnlyHigherTiers(): void
    {
        $this->assertSame(
            [Tier::DIAMOND, Tier::MASTER, Tier::GRANDMASTER, Tier::CHALLENGER],
            Tier::DIAMOND->andAbove()
        );
    }

    public function testLowestTierAcceptsEveryone(): void
    {
        $this->assertCount(count(Tier::cases()), Tier::IRON->andAbove());
    }

    public function testHighestTierAcceptsOnlyItself(): void
    {
        $this->assertSame([Tier::CHALLENGER], Tier::CHALLENGER->andAbove());
    }
}
