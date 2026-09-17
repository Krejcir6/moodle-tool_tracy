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
 * Tests for \tool_tracy\scrubber.
 *
 * Pure functions, no Tracy/global state involved — forLevel() and
 * keysToHide() just return a closure/array, so no @runInSeparateProcess
 * needed here (unlike bootstrap_test.php).
 *
 * @package    tool_tracy
 * @copyright  2026 Radek Krejci
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scrubber_test extends \basic_testcase {
    /** @dataProvider strict_sensitive_key_provider */
    public function test_strict_level_hides_sensitive_looking_keys(string $key): void {
        $scrub = scrubber::forLevel(scrubber::LEVEL_STRICT);

        $this->assertTrue($scrub($key, 'whatever'), "expected '$key' to be treated as sensitive");
    }

    public static function strict_sensitive_key_provider(): array {
        // The concrete case this whole feature exists for: $CFG->dbpass
        // showing up as a stack-frame local via `global $CFG;`.
        return [
            'dbpass' => ['dbpass'],
            'smtppass' => ['smtppass'],
            'proxypassword' => ['proxypassword'],
            'passwordsaltmain' => ['passwordsaltmain'],
            'password' => ['password'],
            'secret' => ['apisecret'],
            'token' => ['sesskeytoken'],
            'authorization header' => ['HTTP_AUTHORIZATION'],
            'cookie' => ['sessioncookie'],
        ];
    }

    /** @dataProvider strict_safe_key_provider */
    public function test_strict_level_leaves_ordinary_keys_alone(string $key): void {
        $scrub = scrubber::forLevel(scrubber::LEVEL_STRICT);

        $this->assertFalse($scrub($key, 'whatever'), "did not expect '$key' to be treated as sensitive");
    }

    public static function strict_safe_key_provider(): array {
        return [
            'wwwroot' => ['wwwroot'],
            'fullname' => ['fullname'],
            'id' => ['id'],
            'debug' => ['debug'],
        ];
    }

    public function test_moderate_level_is_narrower_than_strict(): void {
        $strict = scrubber::forLevel(scrubber::LEVEL_STRICT);
        $moderate = scrubber::forLevel(scrubber::LEVEL_MODERATE);

        // A key strict flags that moderate should not (moderate has no
        // generic "key"/"auth"/"cookie"/"salt" catch-all).
        $this->assertTrue($strict('recaptchaprivatekey', 'x'));
        $this->assertFalse($moderate('recaptchaprivatekey', 'x'));

        // Both must still catch the unambiguous, literal cases.
        $this->assertTrue($strict('password', 'x'));
        $this->assertTrue($moderate('password', 'x'));
    }

    public function test_unknown_level_falls_back_to_strict(): void {
        $unknown = scrubber::forLevel('bogus-level');
        $strict = scrubber::forLevel(scrubber::LEVEL_STRICT);

        $this->assertSame($strict('recaptchaprivatekey', 'x'), $unknown('recaptchaprivatekey', 'x'));
    }

    public function test_keystohide_contains_known_moodle_secrets_exactly(): void {
        $keys = array_map('strtolower', scrubber::keysToHide(scrubber::LEVEL_STRICT));

        foreach (['dbpass', 'smtppass', 'proxypassword', 'passwordsaltmain', 'password', 'secret'] as $expected) {
            $this->assertContains($expected, $keys, "expected '$expected' in keysToHide()");
        }
    }

    public function test_keystohide_moderate_is_a_subset_of_strict(): void {
        $moderate = scrubber::keysToHide(scrubber::LEVEL_MODERATE);
        $strict = scrubber::keysToHide(scrubber::LEVEL_STRICT);

        foreach ($moderate as $key) {
            $this->assertContains($key, $strict);
        }
        $this->assertLessThan(count($strict), count($moderate));
    }

    /**
     * The actual requirement this covers: bootstrap::enable() passes every
     * real $CFG property name in as $extraExactKeys, so an innocuous-looking
     * key like 'wwwroot' must still be hidden once it's a *known* $CFG key —
     * not just the ones the pattern happens to guess are sensitive.
     */
    public function test_extraexactkeys_hides_keys_the_pattern_would_miss(): void {
        $scrub = scrubber::forLevel(scrubber::LEVEL_STRICT, ['wwwroot', 'siteadmins']);

        $this->assertFalse(
            scrubber::forLevel(scrubber::LEVEL_STRICT)('wwwroot', 'x'),
            'sanity check: the plain pattern must not already flag "wwwroot" on its own'
        );
        $this->assertTrue($scrub('wwwroot', 'x'));
        $this->assertTrue($scrub('siteadmins', 'x'));
        $this->assertTrue($scrub('WWWROOT', 'x'), 'exact-key matching must be case-insensitive');
        $this->assertFalse($scrub('fullname', 'x'), 'keys not passed in must be untouched');
    }

    public function test_extraexactkeys_also_applies_to_keystohide(): void {
        $keys = array_map('strtolower', scrubber::keysToHide(scrubber::LEVEL_STRICT, ['wwwroot']));

        $this->assertContains('wwwroot', $keys);
    }

    public function test_scrubarray_masks_sensitive_keys_recursively(): void {
        $values = [
            'id' => '5',
            'data' => [
                'token' => 'secret-token',
                'label' => 'visible',
            ],
        ];

        $scrubbed = scrubber::scrubArray($values);

        $this->assertSame('5', $scrubbed['id']);
        $this->assertSame('***', $scrubbed['data']['token']);
        $this->assertSame('visible', $scrubbed['data']['label']);
    }

    public function test_scrubeventdata_hides_unstructured_strings_in_other(): void {
        $scrubbed = scrubber::scrubEventData([
            'eventname' => '\\example\\event\\created',
            'other' => ['value' => 'SECRET_UNDER_GENERIC_KEY', 'count' => 3],
        ]);

        $this->assertSame('***', $scrubbed['other']['value']);
        $this->assertSame(3, $scrubbed['other']['count']);
    }

    public function test_scrubeventdata_decodes_and_scrubs_json_in_other(): void {
        $scrubbed = scrubber::scrubEventData([
            'other' => ['payload' => '{"token":"SECRET_TOKEN","enabled":true}'],
        ]);

        $this->assertSame('***', $scrubbed['other']['payload']['token']);
        $this->assertTrue($scrubbed['other']['payload']['enabled']);
    }
}
