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
 * Tests for \tool_tracy\bootstrap::abortOpenTransactions().
 *
 * Separate file from bootstrap_test.php: needs a real $DB transaction, so
 * \advanced_testcase, unlike the rest of that file's \basic_testcase.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reclaim_handlers_test extends \advanced_testcase {
    public function test_aborts_a_genuinely_open_transaction(): void {
        $this->resetAfterTest();
        global $DB;

        $transaction = $DB->start_delegated_transaction();
        $this->assertTrue($DB->is_transaction_started());

        bootstrap::abortOpenTransactions();

        $this->assertFalse($DB->is_transaction_started());

        unset($transaction); // now defunct — force-rolled-back out from under it
    }

    public function test_is_a_harmless_noop_without_an_open_transaction(): void {
        global $DB;

        $this->assertFalse($DB->is_transaction_started());

        bootstrap::abortOpenTransactions();

        $this->assertFalse($DB->is_transaction_started());
    }
}
