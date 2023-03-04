<?php
// This file keeps track of upgrades to
// the roleplay module
//
// Sometimes, changes between versions involve
// alterations to database structures and other
// major things that may break installations.
//
// The upgrade function in this file will attempt
// to perform all the necessary actions to upgrade
// your older installation to the current version.
//
// If there's something it cannot do itself, it
// will tell you what you need to do.
//
// The commands in here will all be database-neutral,
// using the methods of database_manager class
//
// Please do not forget to use upgrade_set_timeout()
// before any action that may take longer time to finish.

defined('MOODLE_INTERNAL') || die();

function xmldb_roleplay_upgrade($oldversion) {
    global $DB, $CFG;

    // $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    // if ($oldversion < 2023030302) {
        // $table = new xmldb_table('roleplay');
        // $field1 = new xmldb_field('allowcomment', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        // $field2 = new xmldb_field('oneresponsepergroup', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');

        // if (!$dbman->field_exists($table, $field1)) {
        //     $dbman->add_field($table, $field1);
        // }

        // if (!$dbman->field_exists($table, $field2)) {
        //     $dbman->add_field($table, $field2);
        // }

        // $table = new xmldb_table('roleplay_answers');
        // $field = new xmldb_field('comment', XMLDB_TYPE_TEXT, '4096', null, null, null, null);

        // if (!$dbman->field_exists($table, $field)) {
        //     $dbman->add_field($table, $field);
        // }

        // upgrade_mod_savepoint(true, 2023030302, 'roleplay');
    // }

    return true;
}
