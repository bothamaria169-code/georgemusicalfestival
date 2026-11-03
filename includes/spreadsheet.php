<?php
/**
 * Reads the first worksheet of an .xlsx file, or a .csv file, into rows of strings.
 * No libraries: .xlsx is a zip of XML parts, read with ZipArchive and SimpleXML.
 */
declare(strict_types=1);

/** Rows of cell strings from an uploaded .xlsx or .csv. $name is the original file name (for the extension). */
function spreadsheet_rows(string $path, string $name): array
{
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if ($ext === 'xlsx') {
        return xlsx_rows($path);
    }
    if ($ext === 'xls') {
        throw new RuntimeException('Old .xls files cannot be read. In Excel use File → Save As → Excel Workbook (.xlsx) or CSV.');
    }
    return csv_file_rows($path);
}

/** CSV with comma or semicolon separators (Excel saves semicolons in some regions). */
function csv_file_rows(string $path): array
{
    $fh = fopen($path, 'r');
    if (!$fh) {
        return [];
    }
    $first = (string) fgets($fh);
    $sep = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';
    rewind($fh);
    $rows = [];
    while (($line = fgetcsv($fh, 0, $sep)) !== false) {
        $rows[] = array_map(fn ($v) => (string) $v, $line);
    }
    fclose($fh);
    if ($rows) {
        $rows[0][0] = preg_replace('/^\xEF\xBB\xBF/', '', $rows[0][0] ?? '');
    }
    return $rows;
}

function xlsx_rows(string $path): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('This server cannot read .xlsx files (PHP zip extension missing). Save the sheet as CSV and upload that.');
    }
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('That file is not a valid .xlsx workbook.');
    }
    $xml = function (string $part) use ($zip): ?SimpleXMLElement {
        $raw = $zip->getFromName($part);
        return $raw === false ? null : (simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NONET) ?: null);
    };

    // Shared strings: most text cells point into this list.
    $strings = [];
    if ($ss = $xml('xl/sharedStrings.xml')) {
        foreach ($ss->si as $si) {
            $strings[] = xlsx_text($si);
        }
    }

    // The first sheet in the workbook, via its relationship id.
    $sheetPath = 'xl/worksheets/sheet1.xml';
    $wb = $xml('xl/workbook.xml');
    $rels = $xml('xl/_rels/workbook.xml.rels');
    if ($wb && $rels && isset($wb->sheets->sheet[0])) {
        $rid = (string) $wb->sheets->sheet[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
        foreach ($rels->Relationship as $rel) {
            if ((string) $rel['Id'] === $rid) {
                $target = ltrim((string) $rel['Target'], '/');
                $sheetPath = str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
            }
        }
    }
    $sheet = $xml($sheetPath);
    $zip->close();
    if (!$sheet) {
        throw new RuntimeException('The workbook has no readable first sheet.');
    }

    $rows = [];
    foreach ($sheet->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $c) {
            $col = xlsx_col_index(preg_replace('/\d+/', '', (string) $c['r']));
            $type = (string) $c['t'];
            $value = match ($type) {
                's'         => $strings[(int) $c->v] ?? '',
                'inlineStr' => xlsx_text($c->is),
                default     => (string) $c->v,
            };
            $cells[$col] = $value;
        }
        if ($cells) {
            $line = array_fill(0, max(array_keys($cells)) + 1, '');
            foreach ($cells as $i => $v) {
                $line[$i] = $v;
            }
            $rows[] = $line;
        }
    }
    return $rows;
}

/** Text of a shared-string or inline-string item, including rich-text runs. */
function xlsx_text(?SimpleXMLElement $node): string
{
    if (!$node) {
        return '';
    }
    if (isset($node->t)) {
        return (string) $node->t;
    }
    $out = '';
    foreach ($node->r as $run) {
        $out .= (string) $run->t;
    }
    return $out;
}

/** "A" → 0, "Z" → 25, "AA" → 26. */
function xlsx_col_index(string $letters): int
{
    $n = 0;
    foreach (str_split(strtoupper($letters)) as $ch) {
        $n = $n * 26 + (ord($ch) - 64);
    }
    return max(0, $n - 1);
}
