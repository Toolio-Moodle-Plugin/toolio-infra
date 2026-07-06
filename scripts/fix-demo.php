<?php
/**
 * Ergänzt das Demo-Setup:
 *  - fügt den block_toolio zum Demokurs hinzu (damit Block ↔ mod_toolio zusammenspielen)
 *  - schreibt alle Site-Admins als Trainer/in (editingteacher) ein
 *  - listet die im Kurs zuweisbaren Rollen auf
 *
 * Ausführung: docker exec moodle-dev-server php /tmp/fix-demo.php
 */

define('CLI_SCRIPT', true);
require('/var/www/moodle/public/config.php');
require_once($CFG->libdir . '/enrollib.php');

$course  = $DB->get_record('course', ['shortname' => 'demo-demoGG10'], '*', MUST_EXIST);
$context = context_course::instance($course->id);

// ── 1. block_toolio zum Kurs hinzufügen ──────────────────────────────────────
$exists = $DB->record_exists('block_instances', [
    'blockname'       => 'toolio',
    'parentcontextid' => $context->id,
]);
if ($exists) {
    echo "Block bereits vorhanden.\n";
} else {
    $bi = (object)[
        'blockname'         => 'toolio',
        'parentcontextid'   => $context->id,
        'showinsubcontexts' => 1,       // auch auf Aktivitätsseiten (für Pin-Buttons)
        'requiredbytheme'   => 0,
        'pagetypepattern'   => '*',
        'subpagepattern'    => null,
        'defaultregion'     => 'side-pre',
        'defaultweight'     => -10,
        'configdata'        => '',
        'timecreated'       => time(),
        'timemodified'      => time(),
    ];
    $bi->id = $DB->insert_record('block_instances', $bi);
    context_block::instance($bi->id);
    echo "Block toolio hinzugefügt (id={$bi->id}).\n";
}

// ── 2. Admins einschreiben (editingteacher = Trainer/in) ─────────────────────
$manual   = enrol_get_plugin('manual');
$instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
$teacherRole = $DB->get_record('role', ['archetype' => 'editingteacher'], '*', MUST_EXIST);

foreach (get_admins() as $admin) {
    $manual->enrol_user($instance, $admin->id, $teacherRole->id);
    echo "Admin eingeschrieben: {$admin->username}\n";
}

// ── 3. Zuweisbare Rollen im Kurs anzeigen ────────────────────────────────────
echo "\nIm Kurs zuweisbare Rollen (Name → archetype):\n";
$roles = $DB->get_records('role', null, 'sortorder ASC');
foreach ($roles as $r) {
    $assignable = $DB->record_exists('role_context_levels', ['roleid' => $r->id, 'contextlevel' => CONTEXT_COURSE]);
    $name = role_get_name($r, $context);
    printf("  %-14s archetype=%-16s kurszuweisbar=%s\n", $r->shortname, $r->archetype, $assignable ? 'JA' : 'NEIN');
}

purge_all_caches();
echo "\n✓ Fertig. Kurs-ID: {$course->id}\n";
