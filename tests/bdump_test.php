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
 * Tests for the global bdump() shim in bdump.php.
 *
 * Regression coverage: unpatched bdump($CFG) rendered $CFG->dbpass in
 * plain text — see bdump.php.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bdump_test extends \basic_testcase {
    /**
     * @return array<int, array{title: ?string, dump: string}>
     */
    private function dumpsPanelData(): array {
        $panel = \Tracy\Debugger::getBar()->getPanel('Tracy:dumps');
        $this->assertInstanceOf(\Tracy\DefaultBarPanel::class, $panel, 'bdump() must add a dumps panel to the bar');

        return $panel->data;
    }

    /**
     * @runInSeparateProcess
     */
    public function test_bdump_masks_a_known_cfg_key(): void {
        bootstrap::enable('development');

        // Built at runtime, not a literal: Tracy's dump previews the
        // calling source line, which would otherwise leak it too.
        $secret = 'super-secret-value-' . bin2hex(random_bytes(4));
        $array = ['dbpass' => $secret, 'wwwroot' => 'http://example.test'];
        bdump($array);

        $data = $this->dumpsPanelData();
        $html = end($data)['dump'];
        $this->assertStringNotContainsString($secret, $html);
        $this->assertStringContainsString('dbpass', $html, 'the key name itself is not secret and should still show');
    }

    /**
     * @runInSeparateProcess
     */
    public function test_bdump_masks_real_cfg_dbpass(): void {
        global $CFG;
        bootstrap::enable('development');
        bootstrap::refreshCfgScrubbing();

        bdump($CFG);

        $data = $this->dumpsPanelData();
        $html = end($data)['dump'];
        $this->assertStringNotContainsString((string) $CFG->dbpass, $html);
    }

    /**
     * @runInSeparateProcess
     */
    public function test_bdump_still_returns_the_original_value(): void {
        bootstrap::enable('development');

        $this->assertSame('unchanged', bdump('unchanged'));
    }

    /**
     * @runInSeparateProcess
     */
    public function test_bdump_unsafe_does_not_mask_anything(): void {
        bootstrap::enable('development');

        $secret = 'super-secret-value-' . bin2hex(random_bytes(4));
        bdump_unsafe(['dbpass' => $secret], 'my title');

        $data = $this->dumpsPanelData();
        $entry = end($data);

        $this->assertStringContainsString($secret, $entry['dump'], 'bdump_unsafe() must show the real, unmasked value');
        $this->assertStringContainsString('UNSAFE', $entry['title']);
        $this->assertStringContainsString('my title', $entry['title']);
    }

    /**
     * @runInSeparateProcess
     */
    public function test_bdump_unsafe_marks_title_even_when_none_given(): void {
        bootstrap::enable('development');

        bdump_unsafe('x');

        $data = $this->dumpsPanelData();
        $this->assertStringContainsString('UNSAFE', end($data)['title']);
    }
}
