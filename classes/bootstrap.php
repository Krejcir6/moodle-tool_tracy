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

/**
 * Enables Tracy before Moodle's own bootstrap runs.
 *
 * This file is required directly from config.php, so it must not depend on
 * Moodle internals (DB, $CFG completeness, autoloader) that are not yet
 * available at that point.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tracy;

use Tracy\Debugger;

final class bootstrap
{
    public static function enable(): void
    {
        $library = __DIR__ . '/../thirdparty/tracy/src/tracy.php';
        if (!is_file($library)) {
            return;
        }
        require_once $library;

        // Debugger::enable() expects bool|null here: false = development,
        // true = production, null = auto-detect. It is NOT a mode name string
        // (a string/array is instead treated as an IP/cookie-secret allowlist
        // for auto-detection), so the env var has to be translated explicitly.
        $mode = match (getenv('MOODLE_TRACY_MODE')) {
            'development' => Debugger::Development,
            'production' => Debugger::Production,
            default => Debugger::Detect,
        };
        $logDirectory = getenv('MOODLE_TRACY_LOG_DIR') ?: null;

        Debugger::enable($mode, $logDirectory ?: null);
        Debugger::$strictMode = true;
        Debugger::$showBar = PHP_SAPI !== 'cli';
    }
}
