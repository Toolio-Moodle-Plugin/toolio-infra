<?php
/**
 * Demo-Setup für Toolio: Nutzer + Kachelkurs + Einschreibungen.
 * Ausführung im Container:
 *   docker exec moodle-dev-server php /tmp/setup-demo.php
 */

define('CLI_SCRIPT', true);
require('/var/www/moodle/public/config.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/enrol/locallib.php');
require_once($CFG->libdir  . '/enrollib.php');

// Umlaute in Loginnamen erlauben (für "sören").
set_config('extendedusernamechars', 1);

// ── Nutzer anlegen ───────────────────────────────────────────────────────────

$students = ['Robert', 'Josef', 'Timo', 'Stefan', 'Denise', 'Jambor', 'Krugel', 'Tim', 'Fabienne'];
$teachers = ['Michel', 'Sören', 'Phillipp', 'Christoph'];

function toolio_make_user(string $name, string $lastname): int {
    global $DB, $CFG;
    $login = core_text::strtolower($name);          // Loginname klein
    $password = $name . '123!';                     // Passwort: Name + 123!

    if ($existing = $DB->get_record('user', ['username' => $login, 'mnethostid' => $CFG->mnet_localhost_id])) {
        echo "  = Nutzer existiert: {$login}\n";
        return (int)$existing->id;
    }

    $u = new stdClass();
    $u->auth         = 'manual';
    $u->confirmed    = 1;
    $u->mnethostid   = $CFG->mnet_localhost_id;
    $u->username     = $login;
    $u->password     = $password;   // wird von user_create_user gehasht
    $u->firstname    = $name;
    $u->lastname     = $lastname;
    $u->email        = $login . '@demo.local';
    $u->lang         = 'de';
    $u->timezone     = 'Europe/Berlin';

    $id = user_create_user($u, true, false);
    echo "  + Nutzer angelegt: {$login} / {$password}\n";
    return (int)$id;
}

echo "Lege Nutzer an...\n";
$studentIds = [];
foreach ($students as $s) { $studentIds[] = toolio_make_user($s, 'Schüler:in'); }
$teacherIds = [];
foreach ($teachers as $t) { $teacherIds[] = toolio_make_user($t, 'Lehrkraft'); }

// ── Kurs anlegen (Kachelformat) ──────────────────────────────────────────────

$coursedata = (object)[
    'fullname'      => 'Demokratie und Grundgesetz – Klasse 10',
    'shortname'     => 'demo-demoGG10',
    'category'      => 1,
    'format'        => 'tiles',
    'numsections'   => 5,
    'visible'       => 1,
    'startdate'     => time(),
    'summary'       => '<p>Beispielkurs im Kachelformat. Thema: Demokratie & Grundgesetz, Klasse 10.</p>',
    'summaryformat' => FORMAT_HTML,
    'lang'          => 'de',
];

if ($existing = $DB->get_record('course', ['shortname' => $coursedata->shortname])) {
    delete_course($existing->id, false);
    echo "Alter Demokurs gelöscht.\n";
}

$course = create_course($coursedata);
echo "Kurs angelegt: id={$course->id} (Format: {$course->format})\n";

// ── Sektionen benennen ────────────────────────────────────────────────────────

$sections = [
    1 => ['name' => 'Grundgesetz – Aufbau & Entstehung', 'summary' => '<p>Entstehung, Struktur und Grundprinzipien des Grundgesetzes von 1949.</p>'],
    2 => ['name' => 'Grundrechte',                        'summary' => '<p>Die Grundrechte (Art. 1–19 GG).</p>'],
    3 => ['name' => 'Demokratieprinzip',                  'summary' => '<p>Freie Wahlen, Parlamentarismus, Gewaltenteilung.</p>'],
    4 => ['name' => 'Fallanalyse & Urteilsbildung',       'summary' => '<p>Aktuelle Fälle des Bundesverfassungsgerichts analysieren.</p>'],
    5 => ['name' => 'Prüfungsvorbereitung',               'summary' => '<p>Zusammenfassung, Begriffssicherung, Klausurvorbereitung.</p>'],
];
foreach ($sections as $secnum => $info) {
    if ($sec = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $secnum])) {
        $DB->update_record('course_sections', (object)[
            'id' => $sec->id, 'name' => $info['name'],
            'summary' => $info['summary'], 'summaryformat' => FORMAT_HTML,
        ]);
    }
}
echo "Sektionen benannt.\n";

// ── Hilfsfunktion: Modul hinzufügen ──────────────────────────────────────────

function add_mod(stdClass $course, int $section, string $modname, string $name, array $extra = []): stdClass {
    global $DB, $CFG;
    require_once($CFG->dirroot . "/mod/{$modname}/lib.php");
    $modrecord = $DB->get_record('modules', ['name' => $modname], '*', MUST_EXIST);
    $base = [
        'modulename' => $modname, 'module' => $modrecord->id, 'course' => $course->id,
        'section' => $section, 'visible' => 1, 'visibleoncoursepage' => 1, 'name' => $name,
        'intro' => '', 'introformat' => FORMAT_HTML, 'cmidnumber' => '',
        'groupmode' => NOGROUPS, 'groupingid' => 0, 'availability' => null,
        'completion' => COMPLETION_DISABLED, 'completionview' => COMPLETION_VIEW_NOT_REQUIRED,
        'completionexpected' => 0, 'completionpassgrade' => 0, 'completiongradeitemnumber' => null,
        'completionunlocked' => 1, 'showdescription' => 0,
        'downloadcontent' => DOWNLOAD_COURSE_CONTENT_ENABLED, 'lang' => '', 'tags' => [],
    ];
    $info = (object)array_merge($base, $extra);
    $result = add_moduleinfo($info, $course);
    echo "  + [{$modname}] {$name}\n";
    return $result;
}

$lorem = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.';

// Inhalte je Sektion (Pages)
add_mod($course, 1, 'page', 'Entstehung des Grundgesetzes', [
    'content' => "<h3>Entstehung 1948/49</h3><p>{$lorem}</p><ul><li>Präambel</li><li>Art. 1–19: Grundrechte</li><li>Art. 20–37: Bund und Länder</li></ul>",
    'contentformat' => FORMAT_HTML, 'display' => 5,
]);
add_mod($course, 2, 'page', 'Grundrechte im Überblick', [
    'content' => "<h3>Freiheitsrechte</h3><p>{$lorem}</p><h3>Gleichheitsrechte</h3><p>{$lorem}</p>",
    'contentformat' => FORMAT_HTML, 'display' => 5,
]);
add_mod($course, 3, 'page', 'Gewaltenteilung', [
    'content' => "<h3>Legislative – Exekutive – Judikative</h3><p>{$lorem}</p>",
    'contentformat' => FORMAT_HTML, 'display' => 5,
]);
add_mod($course, 4, 'page', 'BVerfG – Methodik der Urteilsanalyse', [
    'content' => "<h3>Analyseschema</h3><ol><li>Sachverhalt</li><li>Rechtsfragen</li><li>Abwägung</li><li>Ergebnis</li></ol>",
    'contentformat' => FORMAT_HTML, 'display' => 5,
]);
add_mod($course, 5, 'page', 'Zusammenfassung & Begriffsregister', [
    'content' => "<h3>Wichtige Begriffe</h3><dl><dt>Grundgesetz</dt><dd>{$lorem}</dd><dt>Grundrecht</dt><dd>{$lorem}</dd></dl>",
    'contentformat' => FORMAT_HTML, 'display' => 5,
]);

// Toolio-Live-Aktivität in jede Sektion (falls mod_toolio installiert)
if ($toolioMod = $DB->get_record('modules', ['name' => 'toolio'])) {
    for ($s = 1; $s <= 5; $s++) {
        add_mod($course, $s, 'toolio', 'Live-Unterricht');
    }
} else {
    echo "  ! mod_toolio nicht gefunden, übersprungen.\n";
}

// ── Einschreibungen ────────────────────────────────────────────────────────────

$manual = enrol_get_plugin('manual');
$instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);

$studentRole = $DB->get_record('role', ['archetype' => 'student'], '*', MUST_EXIST);
$teacherRole = $DB->get_record('role', ['archetype' => 'editingteacher'], '*', MUST_EXIST);

foreach ($studentIds as $uid) { $manual->enrol_user($instance, $uid, $studentRole->id); }
foreach ($teacherIds as $uid) { $manual->enrol_user($instance, $uid, $teacherRole->id); }
echo "Eingeschrieben: " . count($studentIds) . " Schüler:innen, " . count($teacherIds) . " Lehrkräfte.\n";

purge_all_caches();
echo "\n✓ Demo-Setup fertig. Kurs-ID: {$course->id}\n";
