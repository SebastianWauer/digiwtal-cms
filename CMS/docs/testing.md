# Tests und PHPUnit

Das CMS enthält **keine** PHPUnit-Tests im Repository. Das ist Absicht.

## Warum keine Test-Dateien?

Tests, Composer und vendor/ wurden entfernt, um das Repo schlank zu halten.
Auf dem Live-Server werden keine Tests benötigt.

## Fehlende Dateien sind kein Problem

Folgende Dateien und Ordner sind **absichtlich nicht vorhanden**:

| Datei / Ordner     | Grund                                      |
|--------------------|--------------------------------------------|
| `vendor/`          | Wird via Composer erzeugt, nicht committed |
| `composer.json`    | Nur für lokale Entwicklung benötigt        |
| `composer.lock`    | Nur für lokale Entwicklung benötigt        |
| `phpunit.xml`      | Nur für lokale Entwicklung benötigt        |
| `Tests/`           | Nur für lokale Entwicklung benötigt        |
| `composer`         | Composer-Binary, lokal installieren        |

## PHPUnit lokal einrichten (optional)

```bash
# 1. Composer herunterladen (einmalig)
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --filename=composer
rm composer-setup.php

# 2. composer.json anlegen
cat > composer.json <<'EOF'
{
    "require-dev": {
        "phpunit/phpunit": "^10.5"
    },
    "autoload": {
        "psr-4": { "App\\": "app/" }
    },
    "autoload-dev": {
        "psr-4": { "Tests\\": "Tests/" }
    }
}
EOF

# 3. Abhängigkeiten installieren
php composer install

# 4. Tests ausführen
./vendor/bin/phpunit
```
