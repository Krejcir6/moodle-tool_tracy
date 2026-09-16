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
 * Tracy Bar panel warning that Moodle's bootstrap reclaimed the exception
 * and/or error handler from Tracy — see bootstrap::hijackedHandlers().
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace tool_tracy;

final class handler_check_panel implements \Tracy\IBarPanel
{
    /** @var string[] Non-empty; see bootstrap::hijackedHandlers(). */
    private array $hijacked;

    /** @param string[] $hijacked Non-empty; see bootstrap::hijackedHandlers(). */
    public function __construct(array $hijacked)
    {
        $this->hijacked = $hijacked;
    }

    public function getTab(): string
    {
        return '<span title="Moodle\'s bootstrap took the exception/error handler back from Tracy">'
            . '<svg viewBox="0 0 2048 2048"><path fill="#c00" d="M1024 128l960 1664H64L1024 128zm0 341L306 1664h1436L1024 469zm-96 611h192v192H928v-192zm0-384h192v448H928V696z"/></svg>'
            . '<span class="tracy-label">Tracy handlers hijacked</span>'
            . '</span>';
    }

    public function getPanel(): string
    {
        $list = implode(' and ', array_map(
            static fn (string $handler) => htmlspecialchars($handler, ENT_QUOTES),
            $this->hijacked
        ));

        return '<h1>Tracy handlers hijacked</h1>'
            . '<div class="tracy-inner">'
            . '<p>Moodle\'s <code>lib/setup.php</code> re-registered its own '
            . $list
            . ' handler(s) (<code>default_exception_handler</code> / '
            . '<code>default_error_handler</code>) after Tracy was enabled.</p>'
            . '<p>PHP only ever has one active handler at a time — there is '
            . 'no stacking — so this happens whenever Tracy is enabled from '
            . '<code>config.php</code> (the low-level path), since that runs '
            . 'before <code>lib/setup.php</code> claims the handlers back. '
            . 'From this point in the request, uncaught exceptions and PHP '
            . 'errors go to Moodle\'s own error page, not Tracy\'s '
            . 'bluescreen.</p>'
            . '<p>The low-level path still catches genuine crashes during '
            . 'bootstrap itself (before line ~587 of <code>lib/setup.php</code>). '
            . 'If you need the bluescreen for errors during normal page '
            . 'rendering, enable Tracy from the admin settings instead (the '
            . 'high-level path) — it runs after Moodle claims the handlers, '
            . 'so it wins them back.</p>'
            . '</div>';
    }
}
