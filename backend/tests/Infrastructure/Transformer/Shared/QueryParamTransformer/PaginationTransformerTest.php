<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Transformer\Shared\QueryParamTransformer;

use App\Infrastructure\Transformer\Shared\QueryParamTransformer\PaginationTransformer;
use PHPUnit\Framework\TestCase;

class PaginationTransformerTest extends TestCase
{
    public function testToPaginationTransformsExplicitPageAndLimit(): void
    {
        $pagination = PaginationTransformer::toPagination([
            'page' => 3,
            'limit' => 25,
        ]);

        $this->assertSame(3, $pagination->page());
        $this->assertSame(25, $pagination->limit());
    }

    public function testToPaginationUsesPageSizeWhenLimitIsMissing(): void
    {
        $pagination = PaginationTransformer::toPagination([
            'page' => 2,
            'page_size' => 50,
        ]);

        $this->assertSame(2, $pagination->page());
        $this->assertSame(50, $pagination->limit());
    }

    public function testToPaginationUsesDefaultsWhenValuesAreMissingOrInvalid(): void
    {
        $pagination = PaginationTransformer::toPagination([
            'page' => 0,
            'limit' => -10,
        ]);

        $this->assertSame(1, $pagination->page());
        $this->assertSame(10, $pagination->limit());
    }

    public function testToPaginationCastsNumericStringValues(): void
    {
        $pagination = PaginationTransformer::toPagination([
            'page' => '4',
            'limit' => '30',
        ]);

        $this->assertSame(4, $pagination->page());
        $this->assertSame(30, $pagination->limit());
    }
}
