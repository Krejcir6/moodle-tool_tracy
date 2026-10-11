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
 * Tests for the global bdump() shim in bdump.php.
 *
 * Regression coverage: unpatched bdump($CFG) rendered $CFG->dbpass in
 * plain text — see bdump.php.
 *
 * @covers ::bdump
 * @covers ::bdump_unsafe
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class bdump_test extends \basic_testcase {
    /** @var array{exception:callable|null,error:callable|null} */
    private array $originalhandlers;

    protected function setUp(): void {
        parent::setUp();
        $this->originalhandlers = bootstrap::capture_handlers();
    }

    /**
     * Every test here calls bootstrap::enable(), which installs Tracy's own
     * exception/error handlers — see bootstrap_test.php's tearDown() for why
     * this has to unwind to a known target rather than pop a fixed count.
     */
    protected function tearDown(): void {
        for ($i = 0; $i < 20; $i++) {
            $current = set_exception_handler(static fn () => null);
            restore_exception_handler();
            if ($current === $this->originalhandlers['exception']) {
                break;
            }
            restore_exception_handler();
        }
        for ($i = 0; $i < 20; $i++) {
            $current = set_error_handler(static fn () => false);
            restore_error_handler();
            if ($current === $this->originalhandlers['error']) {
                break;
            }
            restore_error_handler();
        }

        parent::tearDown();
    }

    /**
     * Reads back the Tracy bar's "Tracy:dumps" panel data, as populated by
     * bdump()/bdump_unsafe().
     *
     * @return array<int,array{title:?string,dump:string}>
     */
    private function dumps_panel_data(): array {
        $panel = \Tracy\Debugger::getBar()->getPanel('Tracy:dumps');
        $this->assertInstanceOf(\Tracy\DefaultBarPanel::class, $panel, 'bdump() must add a dumps panel to the bar');

        return $panel->data;
    }

    /**
     * The most recently recorded dump panel entry.
     *
     * @param array $data
     * phpcs:ignore moodle.Commenting.ValidTags.Invalid
     * @phpstan-param array<int,array{title:?string,dump:string}> $data
     * @return array{title:?string,dump:string}
     */
    private function last_dump(array $data): array {
        $entry = end($data);
        if ($entry === false) {
            $this->fail('No dump panel entry was recorded.');
        }

        return $entry;
    }

    /**
     * bdump() must mask a key that looks sensitive by name.
     *
     * @runInSeparateProcess
     */
    public function test_bdump_masks_a_known_cfg_key(): void {
        bootstrap::enable('development');

        // Built at runtime, not a literal: Tracy's dump previews the
        // calling source line, which would otherwise leak it too.
        $secret = 'super-secret-value-' . bin2hex(random_bytes(4));
        $array = ['dbpass' => $secret, 'wwwroot' => 'http://example.test'];
        bdump($array);

        $data = $this->dumps_panel_data();
        $html = $this->last_dump($data)['dump'];
        $this->assertStringNotContainsString($secret, $html);
        $this->assertStringContainsString('dbpass', $html, 'the key name itself is not secret and should still show');
    }

    /**
     * bdump() must mask the real $CFG->dbpass, not just a name-based guess.
     *
     * @runInSeparateProcess
     */
    public function test_bdump_masks_real_cfg_dbpass(): void {
        global $CFG;
        if ((string) $CFG->dbpass === '') {
            // Nothing to mask on a passwordless DB connection (e.g. CI's
            // trust-authenticated Postgres); an empty needle would trivially
            // "match" any haystack and defeat the point of this test.
            $this->markTestSkipped('This test environment has no real $CFG->dbpass to check against.');
        }

        bootstrap::enable('development');
        bootstrap::refresh_cfg_scrubbing();

        bdump($CFG);

        $data = $this->dumps_panel_data();
        $html = $this->last_dump($data)['dump'];
        $this->assertStringNotContainsString((string) $CFG->dbpass, $html);
    }

    /**
     * bdump() must still return its argument unchanged, like Tracy's own.
     *
     * @runInSeparateProcess
     */
    public function test_bdump_still_returns_the_original_value(): void {
        bootstrap::enable('development');

        $this->assertSame('unchanged', bdump('unchanged'));
    }

    /**
     * bdump_unsafe() must show the real, unmasked value by design.
     *
     * @runInSeparateProcess
     */
    public function test_bdump_unsafe_does_not_mask_anything(): void {
        bootstrap::enable('development');

        $secret = 'super-secret-value-' . bin2hex(random_bytes(4));
        bdump_unsafe(['dbpass' => $secret], 'my title');

        $entry = $this->last_dump($this->dumps_panel_data());
        $title = $entry['title'];
        if ($title === null) {
            $this->fail('Expected the dump panel entry to have a title.');
        }

        $this->assertStringContainsString($secret, $entry['dump'], 'bdump_unsafe() must show the real, unmasked value');
        $this->assertStringContainsString('UNSAFE', $title);
        $this->assertStringContainsString('my title', $title);
    }

    /**
     * bdump_unsafe() must add the "UNSAFE" title marker even with no title.
     *
     * @runInSeparateProcess
     */
    public function test_bdump_unsafe_marks_title_even_when_none_given(): void {
        bootstrap::enable('development');

        bdump_unsafe('x');

        $title = $this->last_dump($this->dumps_panel_data())['title'];
        if ($title === null) {
            $this->fail('Expected the dump panel entry to have a title.');
        }
        $this->assertStringContainsString('UNSAFE', $title);
    }
}
