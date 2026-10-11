<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tool_tracy;

/**
 * Tests for \tool_tracy\database_panel.
 *
 * Uses unique marker SQL strings, same reasoning as query_log_test.php.
 *
 * @covers \tool_tracy\database_panel
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class database_panel_test extends \basic_testcase {
    public function test_gettab_contains_svg_icon(): void {
        bootstrap::enable('development');
        $this->assertStringContainsString('<svg', (new database_panel())->getTab());
    }

    /**
     * Queries repeated past the threshold must be grouped with their
     * combined runtime.
     *
     * @runInSeparateProcess
     */
    public function test_duplicate_queries_are_summarised_with_combined_time(): void {
        $marker = 'SELECT TRACY_TEST_MARKER_DUP ' . uniqid();
        for ($i = 0; $i < 6; $i++) {
            query_log::add($marker, 0.01);
        }

        $html = (new database_panel())->getPanel();

        $this->assertStringContainsString('Duplicate queries', $html);
        $this->assertStringNotContainsString('&amp;ge;', $html, 'legend entities must not be double-escaped');
        $this->assertStringNotContainsString('&amp;times;', $html);
        $this->assertStringContainsString(htmlspecialchars($marker, ENT_QUOTES), $html);
        // 6 occurrences * 0.01s each = 0.06s = 60.00 ms combined.
        $this->assertStringContainsString('60.00 ms', $html);
    }

    /**
     * A query run only once is unique (uniqid()), so it can only match
     * itself: if it appeared in both "All queries" and the duplicate
     * summary, its SQL text would show up twice in the HTML instead of once.
     *
     * @runInSeparateProcess
     */
    public function test_query_run_below_threshold_is_not_flagged_as_duplicate(): void {
        $marker = 'SELECT TRACY_TEST_MARKER_ONCE ' . uniqid();
        query_log::add($marker, 0.01);

        $html = (new database_panel())->getPanel();

        $this->assertSame(1, substr_count($html, htmlspecialchars($marker, ENT_QUOTES)));
    }

    /**
     * The per-query table must show SQL text and timing only, no params.
     *
     * @runInSeparateProcess
     */
    public function test_no_params_column_in_the_output(): void {
        query_log::add('SELECT TRACY_TEST_MARKER ' . uniqid(), 0.01);

        $html = (new database_panel())->getPanel();

        $this->assertStringNotContainsString('<th>Params</th>', $html);
    }
}
