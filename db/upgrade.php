<?php

defined('MOODLE_INTERNAL') || die();

function xmldb_tool_tracy_upgrade(int $oldversion): bool {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026091702) {
        $table = new xmldb_table('tool_tracy_deploy');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('wwwroot', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $dbman->create_table($table);
        }

        $record = $DB->get_record('tool_tracy_deploy', [], '*', IGNORE_MULTIPLE);
        if (!$record) {
            $DB->insert_record('tool_tracy_deploy', (object) [
                'wwwroot' => tool_tracy_upgrade_normalize_root($CFG->wwwroot),
                'timecreated' => time(),
            ]);
        }
        upgrade_plugin_savepoint(true, 2026091702, 'tool', 'tracy');
    }

    return true;
}

function tool_tracy_upgrade_normalize_root(string $root): string {
    $parts = parse_url(rtrim($root, '/'));
    if ($parts === false || empty($parts['host'])) {
        return '';
    }

    return strtolower(($parts['scheme'] ?? '') . '://' . $parts['host']
        . (isset($parts['port']) ? ':' . $parts['port'] : '')
        . ($parts['path'] ?? ''));
}
