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

use core_privacy\local\metadata\collection;
use core_privacy\local\metadata\types\external_location;
use tool_tracy\privacy\provider;

/**
 * Tests for the Privacy API metadata provider.
 *
 * @covers \tool_tracy\privacy\provider
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class privacy_provider_test extends \basic_testcase {
    public function test_file_log_metadata_is_declared(): void {
        $collection = provider::get_metadata(new collection('tool_tracy'));
        $items = $collection->get_collection();

        $this->assertCount(1, $items);
        $item = reset($items);
        $this->assertInstanceOf(external_location::class, $item);
        $this->assertSame('tracy_log_directory', $item->get_name());
        $this->assertArrayHasKey('userid', $item->get_privacy_fields());
        $this->assertArrayHasKey('stacktrace', $item->get_privacy_fields());
    }
}
