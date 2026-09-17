<?php

defined('MOODLE_INTERNAL') || die();

function xmldb_tool_tracy_install(): void {
    global $CFG, $DB;

    $DB->insert_record('tool_tracy_deploy', (object) [
        'wwwroot' => tool_tracy_install_normalize_root($CFG->wwwroot),
        'timecreated' => time(),
    ]);
}

function tool_tracy_install_normalize_root(string $root): string {
    $parts = parse_url(rtrim($root, '/'));
    if ($parts === false || empty($parts['host'])) {
        return '';
    }

    return strtolower(($parts['scheme'] ?? '') . '://' . $parts['host']
        . (isset($parts['port']) ? ':' . $parts['port'] : '')
        . ($parts['path'] ?? ''));
}
