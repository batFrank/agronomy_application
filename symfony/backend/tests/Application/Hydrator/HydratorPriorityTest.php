<?php

declare(strict_types=1);

namespace App\Tests\Application\Hydrator;

use App\Application\Hydrator\HydratorPriority;
use PHPUnit\Framework\TestCase;

class HydratorPriorityTest extends TestCase
{
    public function testGetInOrder(): void
    {
        $this->assertSame(
            [HydratorPriority::First, HydratorPriority::Second, HydratorPriority::Third],
            HydratorPriority::getInOrder()
        );
    }
}
