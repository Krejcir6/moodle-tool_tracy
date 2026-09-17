<?php

namespace tool_tracy\check;

defined('MOODLE_INTERNAL') || die();

use core\check\check;
use core\check\result;

final class activation extends check {
    public function get_name(): string {
        return get_string('check_activation_name', 'tool_tracy');
    }

    public function get_result(): result {
        global $CFG;

        $mode = get_config('tool_tracy', 'activationmode') ?: 'admins';
        if (!\tool_tracy_deployment_matches($CFG->wwwroot)) {
            return new result(
                result::ERROR,
                get_string('check_activation_mismatch', 'tool_tracy'),
                get_string('check_activation_mismatch_details', 'tool_tracy')
            );
        }

        if ($mode === 'all') {
            return new result(
                result::WARNING,
                get_string('check_activation_all', 'tool_tracy'),
                get_string('check_activation_all_details', 'tool_tracy')
            );
        }

        if ($mode === 'admins') {
            return new result(result::INFO, get_string('check_activation_admins', 'tool_tracy'));
        }

        return new result(result::OK, get_string('check_activation_off', 'tool_tracy'));
    }
}
