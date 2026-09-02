<?php

namespace Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AdminPaginationTest extends TestCase
{
    public function test_shared_component_renders_dynamic_range_and_bootstrap_links(): void
    {
        $paginator = new LengthAwarePaginator(
            new Collection(range(1, 10)),
            83,
            10,
            1,
            ['path' => '/admin/users']
        );

        $html = view('components.admin-pagination', ['paginator' => $paginator])->render();

        $this->assertStringContainsString('Showing 1 to 10 of 83 entries', $html);
        $this->assertStringContainsString('class="pagination', $html);
        $this->assertStringContainsString('Previous', $html);
        $this->assertStringContainsString('Next', $html);
    }

    public function test_shared_component_uses_a_clean_zero_range_for_empty_results(): void
    {
        $paginator = new LengthAwarePaginator(
            new Collection(),
            0,
            10,
            1,
            ['path' => '/admin/users']
        );

        $html = view('components.admin-pagination', ['paginator' => $paginator])->render();

        $this->assertStringContainsString('Showing 0 to 0 of 0 entries', $html);
        $this->assertStringNotContainsString('Showing  to  of', $html);
    }
}
