# Toolio – Moodle Plugin Development Guidelines

Diese Regeln gelten für alle Plugins in der Organisation **Toolio-Moodle-Plugin**.
Sie sind bindend für alle Entwickler:innen und für Copilot/AI-Assistenten.

---

## 1. Component Name vs. Short Name

Jedes Moodle-Plugin hat einen **Component Name** (Frankenstyle) und einen **Short Name**:

| Component Name     | Short Name     | Typ      |
|--------------------|----------------|----------|
| `mod_kollabboard`  | `kollabboard`  | Activity |
| `mod_toolio`       | `toolio`       | Activity |
| `block_toolio`     | `block_toolio` | Block    |
| `format_tiles`     | `format_tiles` | Format   |

> **Regel:** Für `mod_*`-Plugins ist der Short Name der Teil **nach** `mod_`.
> Für `block_*` und `format_*` wird der **gesamte** Component Name als Short Name verwendet.

---

## 2. Sprachdatei-Benennung (KRITISCH)

```
lang/en/{shortname}.php
```

| Plugin-Typ | Beispiel Component  | Sprachdatei              | ❌ FALSCH                    |
|------------|---------------------|--------------------------|------------------------------|
| `mod_`     | `mod_kollabboard`   | `lang/en/kollabboard.php`| `lang/en/mod_kollabboard.php`|
| `block_`   | `block_toolio`      | `lang/en/block_toolio.php` | `lang/en/toolio.php`       |
| `format_`  | `format_tiles`      | `lang/en/format_tiles.php` | `lang/en/tiles.php`        |

> **Achtung:** `mod_` ist das einzige Plugin-Typ dessen Prefix in der Sprachdatei wegfällt.
> Fehlt die korrekte Sprachdatei → Moodle wirft `detectedbrokenplugin` und blockiert ALLE Plugin-Upgrades.

---

## 3. PHP API-Hook-Funktionen (`lib.php`)

Für `mod_` Plugins: Funktionsnamen verwenden **immer den Short Name** (ohne `mod_`):

```php
// ✅ RICHTIG
function kollabboard_add_instance($data, $mform = null) { ... }
function kollabboard_update_instance($data, $mform = null) { ... }
function kollabboard_delete_instance($id) { ... }
function kollabboard_supports($feature) { ... }

// ❌ FALSCH
function mod_kollabboard_add_instance($data, $mform = null) { ... }
```

---

## 4. `view.php` – Moodle API-Aufrufe

```php
// ✅ RICHTIG – Short Name ohne mod_
$cm = get_coursemodule_from_id('kollabboard', $id, 0, false, MUST_EXIST);
$PAGE->set_url('/mod/kollabboard/view.php', ['id' => $id]);

// ❌ FALSCH – mod_ Prefix
$cm = get_coursemodule_from_id('mod_kollabboard', $id, 0, false, MUST_EXIST);
$PAGE->set_url('/mod/mod_kollabboard/view.php', ['id' => $id]);
```

---

## 5. `db/install.xml` – XMLDB Regeln

**PATH-Attribut** muss dem Dateisystempfad des Plugins entsprechen:

```xml
<!-- ✅ RICHTIG -->
<XMLDB PATH="mod/kollabboard/db" ...>

<!-- ❌ FALSCH (doppelter mod_ Prefix) -->
<XMLDB PATH="mod/mod_kollabboard/db" ...>
```

**Kein `UNSIGNED="true"`** – das ist seit Moodle 4.x deprecated:

```xml
<!-- ✅ RICHTIG -->
<FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" SEQUENCE="true"/>

<!-- ❌ FALSCH -->
<FIELD NAME="id" TYPE="int" LENGTH="10" NOTNULL="true" UNSIGNED="true" SEQUENCE="true"/>
```

**Pflichtfelder** für `mod_*` Tabellen:

```xml
<FIELD NAME="id"           TYPE="int"  LENGTH="10" NOTNULL="true" SEQUENCE="true"/>
<FIELD NAME="course"       TYPE="int"  LENGTH="10" NOTNULL="true" DEFAULT="0"/>
<FIELD NAME="name"         TYPE="char" LENGTH="255" NOTNULL="true"/>
<FIELD NAME="intro"        TYPE="text"              NOTNULL="false"/>
<FIELD NAME="introformat"  TYPE="int"  LENGTH="4"  NOTNULL="true" DEFAULT="0"/>
<FIELD NAME="timecreated"  TYPE="int"  LENGTH="10" NOTNULL="true" DEFAULT="0"/>
<FIELD NAME="timemodified" TYPE="int"  LENGTH="10" NOTNULL="true" DEFAULT="0"/>
```

---

## 6. `mod_form.php` – Formular-Klasse

```php
<?php
defined('MOODLE_INTERNAL') || die();                          // ← Pflicht!
require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_kollabboard_mod_form extends moodleform_mod {       // mod_{shortname}_mod_form
    public function definition() {
        $mform = $this->_form;
        $mform->addElement('text', 'name', get_string('activityname', 'mod_kollabboard'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        $this->standard_coursemodule_elements();  // ← IMMER einbinden
        $this->add_action_buttons();              // ← IMMER einbinden
    }
}
```

---

## 7. PHP-Datei Boilerplate

**Alle PHP-Dateien die KEIN direkter Web-Einstiegspunkt sind** (`lib.php`, `db/access.php`, etc.):

```php
<?php
defined('MOODLE_INTERNAL') || die();
```

**Web-Einstiegspunkte** (`view.php`, `index.php`):

```php
<?php
require('../../config.php');
```

---

## 8. Pflichtdateien pro Plugin-Typ

### `mod_*` (Activity Module)
```
version.php
lib.php
mod_form.php
view.php
index.php
lang/en/{shortname}.php
db/install.xml       ← MUSS in Git committed sein!
db/access.php        ← MUSS in Git committed sein!
```

### `block_*`
```
version.php
block_{name}.php
lang/en/block_{name}.php
db/access.php
```

### `format_*`
```
version.php
format.php
lib.php
lang/en/format_{name}.php
db/access.php
```

---

## 9. `version.php`

```php
<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'mod_kollabboard';   // Voller Component Name
$plugin->version   = 2026070800;          // Format: YYYYMMDDNN
$plugin->requires  = 2024042200;          // Moodle 5.1+
$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1';
```

> **Version erhöhen** wenn sich `db/install.xml` oder `db/upgrade.php` ändert,
> damit Moodle das Upgrade-Script ausführt.

---

## 10. Git-Regeln

- **ALLE Dateien in `db/`** müssen committed sein – insbesondere `install.xml` und `access.php`
- `.gitattributes` muss in jedem Repo vorhanden sein (LF-Zeilenenden, kein BOM)
- Nach jedem Commit auf `main` wird automatisch deployed (GitHub Actions)

---

## 11. Deploy-Pipeline

```
git push → GitHub Actions → rsync → /opt/toolio/staging/{plugin}/ → deploy.sh → upgrade.php
```

- `upgrade.php` scannt **alle** installierten Plugins – ein defektes Plugin blockiert alle
- Bei `detectedbrokenplugin`: Sprachdatei fehlt oder falsch benannt (→ Regel 2)
- Bei `ddlxmlfileerror`: `install.xml` falsch oder nicht deployed (→ Regel 5)
- Bei `Invalid modulename parameter`: `mod_` Prefix an falscher Stelle (→ Regel 4)
