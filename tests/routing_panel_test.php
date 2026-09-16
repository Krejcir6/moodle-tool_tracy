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
 * Tests for \tool_tracy\routing_panel.
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class routing_panel_test extends \basic_testcase {
    private ?array $server;

    protected function setUp(): void {
        parent::setUp();
        $this->server = $_SERVER;
    }

    protected function tearDown(): void {
        $_SERVER = $this->server;
        parent::tearDown();
    }

    public function test_gettab_contains_svg_icon(): void {
        $this->assertStringContainsString('<svg', (new routing_panel())->getTab());
    }

    public function test_masks_sensitive_looking_query_param(): void {
        $_SERVER['REQUEST_URI'] = '/admin/index.php?sesskey=THE_REAL_SESSKEY_VALUE&id=5';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $panel = (new routing_panel())->getPanel();

        $this->assertStringNotContainsString('THE_REAL_SESSKEY_VALUE', $panel);
        $this->assertStringContainsString('sesskey', $panel, 'the param name itself is not secret');
    }

    public function test_leaves_ordinary_query_params_alone(): void {
        $_SERVER['REQUEST_URI'] = '/course/view.php?id=5';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $panel = (new routing_panel())->getPanel();

        $this->assertStringContainsString('id=5', $panel);
    }

    public function test_falls_back_to_cli_label_without_request_uri(): void {
        unset($_SERVER['REQUEST_URI']);

        $panel = (new routing_panel())->getPanel();

        $this->assertStringContainsString('CLI', $panel);
    }
}
