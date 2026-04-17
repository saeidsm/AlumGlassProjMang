<?php

use PHPUnit\Framework\TestCase;

final class PaginationTest extends TestCase
{
    protected function setUp(): void
    {
        $_GET = [];
    }

    public function testRenderPaginationHidesForSinglePage(): void
    {
        $result = ['total_pages' => 1, 'page' => 1, 'total' => 5];
        $this->assertSame('', renderPagination($result, '/reports.php'));
    }

    public function testRenderPaginationShowsCurrentPageAsActive(): void
    {
        $result = ['total_pages' => 5, 'page' => 3, 'total' => 100];
        $html = renderPagination($result, '/reports.php');
        $this->assertStringContainsString('class="active"', $html);
        $this->assertStringContainsString('>3</a>', $html);
    }

    public function testRenderPaginationIncludesPrevAndNextWhenMidway(): void
    {
        $result = ['total_pages' => 10, 'page' => 5, 'total' => 250];
        $html = renderPagination($result, '/reports.php');
        $this->assertStringContainsString('قبلی', $html);
        $this->assertStringContainsString('بعدی', $html);
    }

    public function testRenderPaginationOmitsPrevOnFirstPage(): void
    {
        $result = ['total_pages' => 5, 'page' => 1, 'total' => 100];
        $html = renderPagination($result, '/reports.php');
        $this->assertStringNotContainsString('قبلی', $html);
        $this->assertStringContainsString('بعدی', $html);
    }

    public function testRenderPaginationOmitsNextOnLastPage(): void
    {
        $result = ['total_pages' => 5, 'page' => 5, 'total' => 100];
        $html = renderPagination($result, '/reports.php');
        $this->assertStringContainsString('قبلی', $html);
        $this->assertStringNotContainsString('بعدی', $html);
    }

    public function testRenderPaginationPreservesExistingQueryParams(): void
    {
        $_GET = ['project' => 'pardis', 'page' => 2];
        $result = ['total_pages' => 5, 'page' => 2, 'total' => 100];
        $html = renderPagination($result, '/reports.php');
        $this->assertStringContainsString('project=pardis', $html);
        // Ensure the preserved query is rebuilt rather than duplicated.
        $this->assertSame(
            substr_count($html, 'project=pardis'),
            substr_count($html, 'href=')
        );
    }

    public function testRenderPaginationShowsTotalsFooter(): void
    {
        $result = ['total_pages' => 4, 'page' => 2, 'total' => 73];
        $html = renderPagination($result, '/reports.php');
        $this->assertStringContainsString('صفحه 2 از 4', $html);
        $this->assertStringContainsString('مجموع: 73', $html);
    }

    public function testRenderPaginationAddsEllipsisWhenManyPages(): void
    {
        $result = ['total_pages' => 20, 'page' => 10, 'total' => 500];
        $html = renderPagination($result, '/reports.php');
        $this->assertStringContainsString('…', $html);
    }
}
