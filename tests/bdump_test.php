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
 * Regression coverage for a real leak: Tracy\Debugger::barDump() (what
 * Tracy's own bdump() calls) does not apply Debugger::$keysToHide or a
 * scrubber at all — bdump($CFG) rendered $CFG->dbpass in plain text before
 * bdump.php's replacement fixed it. See bdump.php and README.md's
 * "Sensitive data" section.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bdump_test extends \basic_testcase {
    /**
     * @runInSeparateProcess
     */
    public function test_bdump_masks_a_known_cfg_key(): void {
        bootstrap::enable('development');

        // Built at runtime, not written as a literal in the bdump() call
        // below: Tracy's dump includes an "open in editor" preview of the
        // *source line* that called bdump(), which would otherwise make
        // this test fail for the wrong reason (a literal in the source
        // showing up in that preview, not an actual masking failure).
        $secret = 'super-secret-value-' . bin2hex(random_bytes(4));
        $array = ['dbpass' => $secret, 'wwwroot' => 'http://example.test'];
        bdump($array);

        $panel = \Tracy\Debugger::getBar()->getPanel('Tracy:dumps');
        $this->assertNotNull($panel, 'bdump() must add a dumps panel to the bar');

        $html = end($panel->data)['dump'];
        $this->assertStringNotContainsString($secret, $html);
        $this->assertStringContainsString('dbpass', $html, 'the key name itself is not secret and should still show');
    }

    /**
     * The specific, concrete case reported live: bdump($CFG) must not leak
     * $CFG->dbpass, because "dbpass" is a real $CFG key (refreshCfgScrubbing()
     * covers it), not because it happens to look sensitive by name alone.
     *
     * @runInSeparateProcess
     */
    public function test_bdump_masks_real_cfg_dbpass(): void {
        global $CFG;
        bootstrap::enable('development');
        bootstrap::refreshCfgScrubbing();

        bdump($CFG);

        $panel = \Tracy\Debugger::getBar()->getPanel('Tracy:dumps');
        $html = end($panel->data)['dump'];
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
     * bdump_unsafe() is the deliberate, explicitly-named opt-out — it must
     * NOT mask anything, and its panel title must always carry a visible
     * "UNSAFE" marker so its output can never be mistaken for bdump()'s.
     *
     * @runInSeparateProcess
     */
    public function test_bdump_unsafe_does_not_mask_anything(): void {
        bootstrap::enable('development');

        $secret = 'super-secret-value-' . bin2hex(random_bytes(4));
        bdump_unsafe(['dbpass' => $secret], 'my title');

        $panel = \Tracy\Debugger::getBar()->getPanel('Tracy:dumps');
        $entry = end($panel->data);

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

        $panel = \Tracy\Debugger::getBar()->getPanel('Tracy:dumps');
        $this->assertStringContainsString('UNSAFE', end($panel->data)['title']);
    }
}
