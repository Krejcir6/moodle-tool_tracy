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
 * Tests for standard activation restrictions.
 *
 * @covers ::tool_tracy_client_ip_is_allowed
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class activation_test extends \advanced_testcase {
    /** @var array Snapshot of $_SERVER, restored in tearDown(). */
    private array $server;

    /** @var mixed Snapshot of $CFG->getremoteaddrconf, restored in tearDown(). */
    private mixed $getremoteaddrconf;

    /** @var bool Whether $CFG->getremoteaddrconf existed before the test ran. */
    private bool $hadgetremoteaddrconf;

    protected function setUp(): void {
        parent::setUp();

        global $CFG;
        $this->server = $_SERVER;
        $this->hadgetremoteaddrconf = property_exists($CFG, 'getremoteaddrconf');
        $this->getremoteaddrconf = $CFG->getremoteaddrconf ?? null;
    }

    protected function tearDown(): void {
        global $CFG;
        $_SERVER = $this->server;
        if ($this->hadgetremoteaddrconf) {
            $CFG->getremoteaddrconf = $this->getremoteaddrconf;
        } else {
            unset($CFG->getremoteaddrconf);
        }

        parent::tearDown();
    }

    /**
     * tool_tracy_client_ip_is_allowed() must match single IPs and subnets.
     *
     * @dataProvider ip_allowlist_provider
     */
    public function test_ip_allowlist(string $clientip, string $allowlist, bool $expected): void {
        $_SERVER['REMOTE_ADDR'] = $clientip;
        unset($_SERVER['HTTP_CLIENT_IP'], $_SERVER['HTTP_X_FORWARDED_FOR']);

        $this->assertSame($expected, tool_tracy_client_ip_is_allowed($allowlist));
    }

    /**
     * Data provider for test_ip_allowlist().
     */
    public static function ip_allowlist_provider(): array {
        return [
            'empty list allows any client' => ['203.0.113.10', '', true],
            'exact IPv4 address' => ['203.0.113.10', '203.0.113.10', true],
            'IPv4 subnet' => ['203.0.113.10', '203.0.113.0/24', true],
            'outside IPv4 subnet' => ['198.51.100.10', '203.0.113.0/24', false],
            'IPv6 subnet' => ['2001:db8::10', '2001:db8::/32', true],
        ];
    }

    public function test_ip_allowlist_uses_moodle_proxy_configuration(): void {
        global $CFG;
        $CFG->getremoteaddrconf = GETREMOTEADDR_SKIP_HTTP_CLIENT_IP;
        $_SERVER['REMOTE_ADDR'] = '192.0.2.50';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.10';
        unset($_SERVER['HTTP_CLIENT_IP']);

        $this->assertTrue(tool_tracy_client_ip_is_allowed('203.0.113.0/24'));
        $this->assertFalse(tool_tracy_client_ip_is_allowed('198.51.100.0/24'));
    }
}
