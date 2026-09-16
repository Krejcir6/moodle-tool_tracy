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
 * Note on scope: this does NOT (and cannot) test Tracy's own AJAX-safety
 * mechanism (Tracy\Helpers::isHtmlMode()) — that method has a hard
 * `!isCli()` condition and always returns false under PHPUnit, which runs
 * as CLI SAPI, regardless of what headers/superglobals a test fakes. That
 * mechanism can only be verified against a real HTTP request/response
 * cycle — see tests/ajax_smoke.sh for that.
 *
 * What IS meaningfully testable here is our own PHP_SAPI check in
 * bootstrap::enable(), which is a different, narrower safety property:
 * never show the debug bar for CLI/cron scripts.
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
            // 'detect'/null/an unrecognised string all fall through to
            // Tracy's own detectDebugMode(), which resolves immediately to
            // a concrete bool (never stays null) — under CLI, with no
            // REMOTE_ADDR to match against 127.0.0.1/::1, that's always
            // true (production).
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
        $marker = static fn () => 'still me';
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
}
