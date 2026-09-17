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
 * Tests for \tool_tracy\bootstrap.
 *
 * Does NOT test Tracy's own AJAX-safety (Tracy\Helpers::isHtmlMode() always
 * returns false under PHPUnit's CLI SAPI) — see tests/ajax_smoke.sh for that.
 * What's testable here is our narrower PHP_SAPI check in enable().
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bootstrap_test extends \basic_testcase {
    /**
     * enable() must never turn the debug bar on for CLI/cron scripts, since
     * there is no browser to render it in and nothing should be injected
     * into a CLI script's stdout.
     *
     * @runInSeparateProcess
     */
    public function test_showbar_disabled_under_cli(): void {
        $this->assertSame('cli', PHP_SAPI, 'this test assumes it runs under CLI SAPI, like PHPUnit itself does');

        bootstrap::enable('development');

        $this->assertTrue(class_exists(\Tracy\Debugger::class, false));
        $this->assertFalse(\Tracy\Debugger::$showBar);
    }

    /**
     * @runInSeparateProcess
     * @dataProvider mode_provider
     */
    public function test_enable_maps_mode_string_to_debugger_productionmode(
        string|bool|null $mode,
        bool $expectedproductionmode
    ): void {
        bootstrap::enable($mode);

        $this->assertSame($expectedproductionmode, \Tracy\Debugger::$productionMode);
    }

    public static function mode_provider(): array {
        return [
            'development string' => ['development', false],
            'production string' => ['production', true],
            // detectDebugMode() resolves immediately to a bool; under CLI
            // (no REMOTE_ADDR) that's always true (production).
            'detect string' => ['detect', true],
            'unrecognised string falls back to detect' => ['bogus', true],
            'raw bool true (Tracy\Debugger::Production)' => [true, true],
            'raw bool false (Tracy\Debugger::Development)' => [false, false],
        ];
    }

    /**
     * Peek-and-restore must not itself change which handler is active.
     *
     * @runInSeparateProcess
     */
    public function test_hijackedhandlers_does_not_disturb_current_handlers(): void {
        $marker = static function (\Throwable $e): void {
        };
        set_exception_handler($marker);

        bootstrap::hijackedHandlers();

        // set_exception_handler() returns the *previous* handler, so calling
        // it again is the only way to read + immediately restore "current".
        $current = set_exception_handler($marker);
        set_exception_handler($current);

        $this->assertSame($marker, $current);
    }

    /**
     * @runInSeparateProcess
     */
    public function test_hijackedhandlers_detects_moodles_default_handlers(): void {
        set_exception_handler('default_exception_handler');
        set_error_handler('default_error_handler');

        $this->assertSame(['exception', 'error'], bootstrap::hijackedHandlers());
    }

    /**
     * @runInSeparateProcess
     */
    public function test_hijackedhandlers_empty_when_handlers_are_not_moodles(): void {
        set_exception_handler(static fn () => null);
        set_error_handler(static fn () => false);

        $this->assertSame([], bootstrap::hijackedHandlers());
    }

    /**
     * @runInSeparateProcess
     */
    public function test_enable_can_restore_the_handlers_active_before_tracy(): void {
        $exceptionHandler = static function (\Throwable $e): void {
        };
        $errorHandler = static fn (): bool => false;
        set_exception_handler($exceptionHandler);
        set_error_handler($errorHandler);

        bootstrap::enable('development', null, false);

        $current = bootstrap::captureHandlers();
        $this->assertSame($exceptionHandler, $current['exception']);
        $this->assertSame($errorHandler, $current['error']);
    }

    /**
     * @runInSeparateProcess
     */
    public function test_reclaimhandlers_takes_the_handlers_back_from_moodle(): void {
        bootstrap::enable('development');

        // Simulate lib/setup.php claiming the handlers after enable() ran.
        set_exception_handler('default_exception_handler');
        set_error_handler('default_error_handler');
        $this->assertSame(['exception', 'error'], bootstrap::hijackedHandlers());

        bootstrap::reclaimHandlers();

        $this->assertSame([], bootstrap::hijackedHandlers(), 'Moodle\'s handlers must no longer be active');
    }

    /**
     * @runInSeparateProcess
     */
    public function test_reclaimhandlers_installs_tracys_real_handlers(): void {
        bootstrap::enable('development');
        set_exception_handler('default_exception_handler');
        set_error_handler('default_error_handler');

        bootstrap::reclaimHandlers();

        $errorHandler = set_error_handler(static fn () => false);
        set_error_handler($errorHandler);
        $this->assertSame([\Tracy\Debugger::class, 'errorHandler'], $errorHandler);
    }

    /**
     * @runInSeparateProcess
     */
    public function test_reclaimhandlers_does_not_throw_when_tracy_was_never_enabled(): void {
        bootstrap::reclaimHandlers();

        $this->addToAssertionCount(1); // reaching this line without a fatal is the assertion
    }

    /**
     * enable() must hide every actual $CFG property, not just ones the
     * pattern guesses are sensitive — see scrubber.php's $extraExactKeys.
     *
     * @runInSeparateProcess
     */
    public function test_enable_hides_every_actual_cfg_key(): void {
        global $CFG;
        $this->assertTrue(property_exists($CFG, 'wwwroot'), 'sanity check: a real $CFG is available in this test');

        bootstrap::enable('development');

        $scrubber = \Tracy\Debugger::getBlueScreen()->scrubber;
        $this->assertNotNull($scrubber, 'enable() must install a BlueScreen scrubber');
        $this->assertTrue(
            $scrubber('wwwroot', $CFG->wwwroot),
            'wwwroot does not look sensitive by name, but must be hidden because it is a real $CFG key'
        );

        $this->assertContains('wwwroot', array_map('strtolower', \Tracy\Debugger::$keysToHide));
    }

    /**
     * $CFG only has ~30 properties when config.php calls enable() — the
     * rest arrives later via initialise_cfg(). refreshCfgScrubbing() closes
     * that gap when called again once $CFG is complete.
     *
     * @runInSeparateProcess
     */
    public function test_refreshcfgscrubbing_covers_keys_added_to_cfg_after_enable(): void {
        global $CFG;

        bootstrap::enable('development'); // snapshot taken before the key below exists

        // No "pass"/"secret"/etc substring: tests the exact-key mechanism, not the pattern.
        $CFG->tracy_test_late_marker = 'should-not-leak';

        $scrubberbefore = \Tracy\Debugger::getBlueScreen()->scrubber;
        $this->assertFalse(
            $scrubberbefore('tracy_test_late_marker', 'x'),
            'sanity check: enable() could not have known about a key added after it ran'
        );

        bootstrap::refreshCfgScrubbing();

        $scrubberafter = \Tracy\Debugger::getBlueScreen()->scrubber;
        $this->assertTrue(
            $scrubberafter('tracy_test_late_marker', 'x'),
            'a $CFG key that only existed after enable() ran must be covered once refreshed'
        );

        unset($CFG->tracy_test_late_marker);
    }

    /**
     * Must be safe to call regardless of enabled state — callers can't
     * know it in advance.
     *
     * @runInSeparateProcess
     */
    public function test_refreshcfgscrubbing_does_not_throw_regardless_of_enabled_state(): void {
        bootstrap::refreshCfgScrubbing();
        bootstrap::refreshCfgScrubbing();

        $this->addToAssertionCount(1); // reaching this line without a fatal is the assertion
    }
}
