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
 * @covers \tool_tracy\bootstrap
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class bootstrap_test extends \basic_testcase {
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
        // phpcs:ignore moodle.NamingConventions.ValidVariableName.VariableNameLowerCase
        $this->assertFalse(\Tracy\Debugger::$showBar);
    }

    /**
     * enable() must map each accepted $mode value to the right Tracy
     * productionMode.
     *
     * @runInSeparateProcess
     * @dataProvider mode_provider
     */
    public function test_enable_maps_mode_string_to_debugger_productionmode(
        string|bool|null $mode,
        bool $expectedproductionmode
    ): void {
        bootstrap::enable($mode);

        // phpcs:ignore moodle.NamingConventions.ValidVariableName.VariableNameLowerCase
        $this->assertSame($expectedproductionmode, \Tracy\Debugger::$productionMode);
    }

    /**
     * Data provider for test_enable_maps_mode_string_to_debugger_productionmode().
     */
    public static function mode_provider(): array {
        return [
            'development string' => ['development', false],
            'production string' => ['production', true],
            // Tracy's detectDebugMode() resolves immediately to a bool;
            // under CLI (no REMOTE_ADDR) that's always true (production).
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

        bootstrap::hijacked_handlers();

        // Set_exception_handler() returns the *previous* handler, so calling
        // it again is the only way to read + immediately restore "current".
        $current = set_exception_handler($marker);
        set_exception_handler($current);

        $this->assertSame($marker, $current);
    }

    /**
     * hijacked_handlers() must detect Moodle's own default handlers.
     *
     * @runInSeparateProcess
     */
    public function test_hijackedhandlers_detects_moodles_default_handlers(): void {
        set_exception_handler('default_exception_handler');
        set_error_handler('default_error_handler');

        $this->assertSame(['exception', 'error'], bootstrap::hijacked_handlers());
    }

    /**
     * hijacked_handlers() must report nothing hijacked for non-Moodle handlers.
     *
     * @runInSeparateProcess
     */
    public function test_hijackedhandlers_empty_when_handlers_are_not_moodles(): void {
        set_exception_handler(static fn () => null);
        set_error_handler(static fn () => false);

        $this->assertSame([], bootstrap::hijacked_handlers());
    }

    /**
     * enable() with $keephandlers=false must restore whichever handlers
     * were active before it ran.
     *
     * @runInSeparateProcess
     */
    public function test_enable_can_restore_the_handlers_active_before_tracy(): void {
        $exceptionhandler = static function (\Throwable $e): void {
        };
        $errorhandler = static fn (): bool => false;
        set_exception_handler($exceptionhandler);
        set_error_handler($errorhandler);

        bootstrap::enable('development', null, false);

        $current = bootstrap::capture_handlers();
        $this->assertSame($exceptionhandler, $current['exception']);
        $this->assertSame($errorhandler, $current['error']);
    }

    /**
     * reclaim_handlers() must take the handlers back once Moodle has
     * claimed them.
     *
     * @runInSeparateProcess
     */
    public function test_reclaimhandlers_takes_the_handlers_back_from_moodle(): void {
        bootstrap::enable('development');

        // Simulate lib/setup.php claiming the handlers after enable() ran.
        set_exception_handler('default_exception_handler');
        set_error_handler('default_error_handler');
        $this->assertSame(['exception', 'error'], bootstrap::hijacked_handlers());

        bootstrap::reclaim_handlers();

        $this->assertSame([], bootstrap::hijacked_handlers(), 'Moodle\'s handlers must no longer be active');
    }

    /**
     * reclaim_handlers() must install Tracy's own real error handler.
     *
     * @runInSeparateProcess
     */
    public function test_reclaimhandlers_installs_tracys_real_handlers(): void {
        bootstrap::enable('development');
        set_exception_handler('default_exception_handler');
        set_error_handler('default_error_handler');

        bootstrap::reclaim_handlers();

        $errorhandler = set_error_handler(static fn () => false);
        set_error_handler($errorhandler);
        $this->assertSame([\Tracy\Debugger::class, 'errorHandler'], $errorhandler);
    }

    /**
     * reclaim_handlers() must be a harmless no-op if Tracy was never enabled.
     *
     * @runInSeparateProcess
     */
    public function test_reclaimhandlers_does_not_throw_when_tracy_was_never_enabled(): void {
        bootstrap::reclaim_handlers();

        // Reaching this line without a fatal error is the assertion.
        $this->addToAssertionCount(1);
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

        // phpcs:ignore moodle.NamingConventions.ValidVariableName.VariableNameLowerCase
        $this->assertContains('wwwroot', array_map('strtolower', \Tracy\Debugger::$keysToHide));
    }

    /**
     * $CFG only has ~30 properties when config.php calls enable() — the
     * rest arrives later via initialise_cfg(). refresh_cfg_scrubbing() closes
     * that gap when called again once $CFG is complete.
     *
     * @runInSeparateProcess
     */
    public function test_refreshcfgscrubbing_covers_keys_added_to_cfg_after_enable(): void {
        global $CFG;

        bootstrap::enable('development'); // Snapshot taken before the key below exists.

        // No "pass"/"secret"/etc substring: tests the exact-key mechanism, not the pattern.
        $CFG->tracy_test_late_marker = 'should-not-leak';

        $scrubberbefore = \Tracy\Debugger::getBlueScreen()->scrubber;
        $this->assertFalse(
            $scrubberbefore('tracy_test_late_marker', 'x'),
            'sanity check: enable() could not have known about a key added after it ran'
        );

        bootstrap::refresh_cfg_scrubbing();

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
        bootstrap::refresh_cfg_scrubbing();
        bootstrap::refresh_cfg_scrubbing();

        // Reaching this line without a fatal error is the assertion.
        $this->addToAssertionCount(1);
    }
}
