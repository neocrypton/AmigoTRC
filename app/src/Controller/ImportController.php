<?php
declare(strict_types=1);

namespace Amigo\Controller;

use Amigo\Core\Auth;
use Amigo\Core\RateLimiter;
use Amigo\Core\View;
use Amigo\Service\Catalog;
use Amigo\Service\CsvImport;

/**
 * CSV-Import in drei Schritten: Hochladen → Spalten zuordnen → Vorschau & Plattformen → Import.
 * Der Zwischenstand liegt in der Session des Nutzers (nur seine eigene Datei).
 */
final class ImportController
{
    private const TARGETS = ['collection' => 'Sammlung', 'plays' => 'Durchgespielt-Liste'];

    /** Schritt 1: Datei hochladen */
    public function upload(): void
    {
        if (is_post()) {
            $target = input_enum('target', self::TARGETS) ?? 'collection';
            $bucket = 'import:' . Auth::id();
            if (RateLimiter::tooMany($bucket, 30, 3600)) {
                flash('error', 'Zu viele Importe – bitte später erneut.');
                redirect('import');
            }
            RateLimiter::hit($bucket);
            try {
                $this->discard();
                $file = CsvImport::store(is_array($_FILES['csv'] ?? null) ? $_FILES['csv'] : []);
                $hasHeader = isset($_POST['has_header']);
                [$cols, $rows] = CsvImport::read($file['file'], $file['delimiter'], $hasHeader);
                if (!$rows) {
                    CsvImport::remove($file['file']);
                    throw new \RuntimeException('Die Datei enthält keine Datenzeilen.');
                }
                $_SESSION['import'] = $file + [
                    'target'   => $target,
                    'header'   => $hasHeader,
                    'name'     => mb_substr(basename((string)($_FILES['csv']['name'] ?? 'import.csv')), 0, 80),
                    'mapping'  => CsvImport::suggest($target, $cols),
                    'platforms' => [],
                ];
                redirect('import/map');
            } catch (\RuntimeException $e) {
                flash('error', $e->getMessage());
                redirect('import', ['target' => $target]);
            }
        }
        View::render('import/upload', [
            'title'   => 'CSV-Import',
            'nav'     => 'account',
            'targets' => self::TARGETS,
            'target'  => input_enum('target', self::TARGETS, $_GET) ?? 'collection',
            'fields'  => CsvImport::FIELDS,
        ]);
    }

    /** Schritt 2: Spalten den AMIGO-Feldern zuordnen */
    public function map(): void
    {
        $state = $this->state();
        [$cols, $rows] = CsvImport::read($state['file'], $state['delimiter'], $state['header']);
        $fields = CsvImport::FIELDS[$state['target']];

        if (is_post()) {
            if (isset($_POST['delimiter']) && in_array($_POST['delimiter'], [';', ',', "\t", '|'], true) && $_POST['delimiter'] !== $state['delimiter']) {
                // Anderes Trennzeichen gewählt → neu einlesen und neu vorschlagen
                $state['delimiter'] = $_POST['delimiter'];
                [$cols] = CsvImport::read($state['file'], $state['delimiter'], $state['header']);
                $state['mapping'] = CsvImport::suggest($state['target'], $cols);
                $_SESSION['import'] = $state;
                redirect('import/map');
            }
            $mapping = [];
            $posted = is_array($_POST['map'] ?? null) ? $_POST['map'] : [];
            foreach (array_keys($cols) as $i) {
                $f = (string)($posted[$i] ?? '');
                if (isset($fields[$f]) && !in_array($f, $mapping, true)) {
                    $mapping[$i] = $f;
                }
            }
            $state['mapping'] = $mapping;
            $_SESSION['import'] = $state;
            if (!in_array('title', $mapping, true)) {
                flash('error', 'Bitte mindestens eine Spalte dem Feld „Titel“ zuordnen.');
                redirect('import/map');
            }
            redirect('import/preview');
        }

        View::render('import/map', [
            'title'   => 'CSV-Import · Spalten zuordnen',
            'nav'     => 'account',
            'state'   => $state,
            'cols'    => $cols,
            'samples' => array_slice($rows, 0, 3),
            'total'   => min(count($rows), CsvImport::MAX_ROWS),
            'tooMany' => count($rows) > CsvImport::MAX_ROWS,
            'fields'  => $fields,
            'targetLabel' => self::TARGETS[$state['target']],
        ]);
    }

    /** Schritt 3: Plattformen zuordnen, Vorschau, Import starten */
    public function preview(): void
    {
        $state = $this->state();
        if (!in_array('title', $state['mapping'], true)) {
            redirect('import/map');
        }
        [$cols, $rows] = CsvImport::read($state['file'], $state['delimiter'], $state['header']);
        $rows = array_slice($rows, 0, CsvImport::MAX_ROWS);
        $index = CsvImport::platformIndex();
        $platformCol = array_search('platform', $state['mapping'], true);
        $unknown = CsvImport::unknownPlatforms($rows, $platformCol === false ? null : (int)$platformCol, $index);
        $platforms = Catalog::platforms();
        $validIds = array_flip(array_map(fn ($p) => (int)$p['id'], $platforms));

        if (is_post()) {
            // Plattform-Zuordnung übernehmen
            $posted = is_array($_POST['pmap'] ?? null) ? $_POST['pmap'] : [];
            $keys = array_keys($unknown);
            $pmap = [];
            foreach ($keys as $n => $value) {
                $choice = (string)($posted[$n] ?? 'note');
                $pmap[$value] = ctype_digit($choice) && isset($validIds[(int)$choice]) ? (int)$choice : 'note';
            }
            $state['platforms'] = $pmap;
            $state['skip_duplicates'] = isset($_POST['skip_duplicates']);
            $_SESSION['import'] = $state;

            if (input('action', 10) === 'run') {
                $result = CsvImport::run(Auth::id(), $state['target'], $rows, $state['mapping'], $pmap, $state['skip_duplicates']);
                CsvImport::remove($state['file']);
                unset($_SESSION['import']);
                View::render('import/done', [
                    'title'  => 'CSV-Import abgeschlossen',
                    'nav'    => 'account',
                    'result' => $result,
                    'target' => $state['target'],
                    'offset' => $state['header'] ? 2 : 1,
                ]);
                return;
            }
            redirect('import/preview');
        }

        $preview = [];
        $warnCount = 0;
        $errCount = 0;
        foreach ($rows as $i => $row) {
            $rec = CsvImport::buildRecord($state['target'], $row, $state['mapping'], $index, $state['platforms']);
            $warnCount += $rec['warnings'] ? 1 : 0;
            $errCount += $rec['error'] ? 1 : 0;
            if (count($preview) < 12) {
                $preview[$i] = $rec;
            }
        }
        $platformNames = array_column($platforms, 'name', 'id');

        View::render('import/preview', [
            'title'     => 'CSV-Import · Vorschau',
            'nav'       => 'account',
            'state'     => $state,
            'preview'   => $preview,
            'total'     => count($rows),
            'warnCount' => $warnCount,
            'errCount'  => $errCount,
            'unknown'   => $unknown,
            'platforms' => $platforms,
            'platformNames' => $platformNames,
            'offset'    => $state['header'] ? 2 : 1,
            'targetLabel' => self::TARGETS[$state['target']],
        ]);
    }

    public function cancel(): void
    {
        if (is_post()) {
            $this->discard();
            flash('info', 'Import abgebrochen.');
        }
        redirect('import');
    }

    private function state(): array
    {
        $state = $_SESSION['import'] ?? null;
        if (!is_array($state) || !CsvImport::path((string)($state['file'] ?? ''))) {
            unset($_SESSION['import']);
            flash('info', 'Bitte zuerst eine CSV-Datei hochladen.');
            redirect('import');
        }
        return $state;
    }

    private function discard(): void
    {
        if (!empty($_SESSION['import']['file'])) {
            CsvImport::remove((string)$_SESSION['import']['file']);
        }
        unset($_SESSION['import']);
    }
}
