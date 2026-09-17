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
 * Global tool_tracy_log_query() — target of an OPTIONAL, dev-only core
 * patch (dev/moodle_database.patch) to query_log() in
 * lib/dml/moodle_database.php, which exposes no public per-query hook of
 * its own (query_start()/query_end()/query_log() are all protected).
 *
 * SQL text and timing only, deliberately no params: params would need
 * masking (a second core hook in fix_sql_params() to catch them before
 * losing their names — see git history/ROADMAP.md), more complexity than
 * it was worth. Correctly parameterised SQL normally contains placeholders,
 * but literal values written directly into SQL remain visible and must be
 * treated as potentially sensitive development output.
 *
 * function_exists()-guarded like bdump.php's replacements: inert unless
 * the patch is applied.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!function_exists('tool_tracy_log_query')) {
    function tool_tracy_log_query(?string $sql, float $time, mixed $error = false): void
    {
        \tool_tracy\query_log::add($sql, $time, $error);
    }
}
