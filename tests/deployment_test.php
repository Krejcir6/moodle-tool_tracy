<?php

namespace tool_tracy;

class deployment_test extends \advanced_testcase {
    public function test_normalize_root_ignores_trailing_slash_and_host_case(): void {
        $this->assertSame(
            'https://example.test/moodle',
            tool_tracy_normalize_root('HTTPS://EXAMPLE.TEST/moodle/')
        );
    }

    public function test_deployment_matches_current_wwwroot(): void {
        global $DB;
        $this->resetAfterTest();
        $DB->delete_records('tool_tracy_deploy');

        $DB->insert_record('tool_tracy_deploy', (object) [
            'wwwroot' => 'https://dev.example.test',
            'timecreated' => time(),
        ]);

        $this->assertTrue(tool_tracy_deployment_matches('https://dev.example.test/'));
        $this->assertFalse(tool_tracy_deployment_matches('https://www.example.test/'));
    }

    public function test_mismatched_deployment_is_persistently_disabled(): void {
        global $DB;
        $this->resetAfterTest();
        $DB->delete_records('tool_tracy_deploy');
        set_config('activationmode', 'admins', 'tool_tracy');

        $DB->insert_record('tool_tracy_deploy', (object) [
            'wwwroot' => 'https://dev.example.test',
            'timecreated' => time(),
        ]);

        $this->assertTrue(tool_tracy_disable_if_deployment_mismatch('https://www.example.test/'));

        $this->assertSame('off', get_config('tool_tracy', 'activationmode'));
    }
}
