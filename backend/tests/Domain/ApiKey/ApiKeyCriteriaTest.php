<?php

declare(strict_types=1);

namespace App\Tests\Domain\ApiKey;

use App\Domain\ApiKey\ApiKeyCriteria;
use PHPUnit\Framework\TestCase;

class ApiKeyCriteriaTest extends TestCase
{
    public function testFilterByKey(): void
    {
        $criteria = ApiKeyCriteria::createEmpty()->filterByKey('my-key');

        $this->assertSame('my-key', $criteria->getKey());
        $this->assertFalse($criteria->isEmpty());
    }

    public function testCriteriaIsEmptyByDefault(): void
    {
        $criteria = ApiKeyCriteria::createEmpty();

        $this->assertTrue($criteria->isEmpty());
        $this->assertNull($criteria->getKey());
    }
}
