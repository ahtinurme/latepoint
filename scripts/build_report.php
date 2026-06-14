<?php
/**
 * Full Yumefit Timma report pipeline — fetch → aggregate → build XLSX — in one
 * pure-PHP, zero-dependency script (no PhpSpreadsheet / composer; uses the built-in
 * zip + curl extensions). Port of fetch_timma.py + aggregate.py + build_report.py.
 *
 * Pipeline (default): pull live reservations + worktimes from pro.timma.ee, aggregate
 * actuals for 1 Feb .. cutoff (January excluded; current month partial to cutoff day),
 * then write the styled workbook.
 *
 * Usage:
 *   php scripts/build_report.php                 # full: fetch + aggregate + build
 *   php scripts/build_report.php --no-fetch      # use cached timma_bookings.json
 *   php scripts/build_report.php --build-only     # just rebuild xlsx from timma_data.json
 *   php scripts/build_report.php --cutoff=2026-06-10 --token=... --out=report.xlsx
 *
 * Options: --no-fetch --build-only --token= --email= --biz= --from= --to= --cutoff=
 *          --avg= --bookings= --data= --out= --auth=
 * Token falls back to .context/timma/timma_auth.json (x-auth-token / x-auth-email).
 */

if (PHP_SAPI !== 'cli') { exit("CLI only\n"); }

/** Banker's rounding (round-half-to-even) to match Python's round(). */
function rnd($v, int $p = 0) { return round($v, $p, PHP_ROUND_HALF_EVEN); }

$root = dirname(__DIR__);
$ctx  = $root . '/.context/timma';
$opt  = getopt('', ['no-fetch', 'build-only', 'token:', 'email:', 'biz:', 'from:', 'to:', 'cutoff:', 'avg:', 'bookings:', 'data:', 'out:', 'auth:']);

$bookingsPath = $opt['bookings'] ?? $ctx . '/timma_bookings.json';
$dataPath     = $opt['data']     ?? $root . '/timma_data.json';
$outPath      = $opt['out']      ?? $root . '/Yumefit_Timma_Report_2026_YTD.xlsx';
$authPath     = $opt['auth']     ?? $ctx . '/timma_auth.json';
$AVG          = (int) ($opt['avg'] ?? 26);
$cutoff       = $opt['cutoff']   ?? '2026-06-10';

if (isset($opt['build-only'])) {
    if (!is_file($dataPath)) { fwrite(STDERR, "data file not found: {$dataPath}\n"); exit(1); }
    $d = json_decode((string) file_get_contents($dataPath), true);
    if (!is_array($d)) { fwrite(STDERR, "could not parse {$dataPath}\n"); exit(1); }
    $AVG = $d['avgPriceEur'] ?? $AVG;
} else {
    if (isset($opt['no-fetch'])) {
        if (!is_file($bookingsPath)) { fwrite(STDERR, "bookings cache not found: {$bookingsPath}\n  (drop --no-fetch to pull from Timma)\n"); exit(1); }
        $bookings = json_decode((string) file_get_contents($bookingsPath), true);
        echo 'loaded ' . count($bookings) . " slots from cache\n";
    } else {
        [$token, $email] = timma_auth($authPath, $opt);
        $biz  = $opt['biz']  ?? '673e4475236e6416d9c1fa32';
        $from = $opt['from'] ?? '2024-11-01';
        $to   = $opt['to']   ?? '2026-07-01';
        $bookings = timma_fetch($token, $email, $biz, $from, $to);
        file_put_contents($bookingsPath, json_encode($bookings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        echo 'fetched ' . count($bookings) . " slots -> {$bookingsPath}\n";
    }
    $d = aggregate_data($bookings, $cutoff, $AVG);
    file_put_contents($dataPath, json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    echo "aggregated -> {$dataPath}\n";
}

$monthly = $d['monthly'];
$trainers = $d['trainers'];
$trainerTotals = $d['trainerTotals'];

// month labels (current/partial month tagged with its day-count)
$MONTH_NAMES = [];
foreach ($monthly as $m) {
    $lab = date('M Y', strtotime($m['month'] . '-01'));
    if (!empty($m['partial'])) { $lab .= ' (1–' . $m['days'] . ')*'; }
    $MONTH_NAMES[$m['month']] = $lab;
}
$order = array_column($monthly, 'month');
$partial = null;
foreach ($monthly as $m) { if (!empty($m['partial'])) { $partial = $m; } }
$asOf = date('j M Y', strtotime($cutoff));        // "10 Jun 2026"
$asOfShort = date('j M', strtotime($cutoff));     // "10 Jun"
$pMon  = $partial ? date('F', strtotime($partial['month'] . '-01')) : '';
$pDays = $partial['days'] ?? 0;
$pBk   = $partial['nBookings'] ?? 0;
$pUtil = $partial ? rtrim(rtrim(number_format($partial['utilPct'], 1), '0'), '.') : '';
$pCov  = $partial ? (int) round($partial['unionCovPct']) : 0;

// ---- colours / shared style fragments ----
$HEAD = '1F4E78'; $SUBHEAD = 'DDEBF7'; $TOTAL = 'FCE4D6';
$WHITE = 'FFFFFF'; $TITLEC = '1F4E78'; $NOTEC = '606060';

// =====================================================================
//  Minimal OOXML XLSX writer
// =====================================================================
class Xlsx
{
    private array $sheets = [];
    private array $order = [];
    private array $fonts = [];
    private array $fills = [];
    private array $numFmts = [];
    private array $xfs = [];
    private int $nextFmtId = 164;

    public function __construct()
    {
        $this->fonts[] = '<font><sz val="11"/><name val="Calibri"/></font>';   // 0 default
        $this->fills[] = '<fill><patternFill patternType="none"/></fill>';      // 0
        $this->fills[] = '<fill><patternFill patternType="gray125"/></fill>';   // 1 (reserved)
        $this->xfs[] = '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'; // 0
    }

    public function addSheet(string $name): string
    {
        $this->sheets[$name] = ['rows' => [], 'merges' => [], 'cols' => [], 'freeze' => null, 'cf' => [], 'rowHt' => [], 'maxR' => 1, 'maxC' => 1];
        $this->order[] = $name;
        return $name;
    }

    private function fontId(bool $bold, bool $italic, int $size, ?string $color): int
    {
        $xml = '<font>' . ($bold ? '<b/>' : '') . ($italic ? '<i/>' : '') . '<sz val="' . $size . '"/>'
            . ($color !== null ? '<color rgb="FF' . $color . '"/>' : '') . '<name val="Calibri"/></font>';
        $i = array_search($xml, $this->fonts, true);
        if ($i === false) { $this->fonts[] = $xml; $i = count($this->fonts) - 1; }
        return $i;
    }

    private function fillId(?string $color): int
    {
        if ($color === null) { return 0; }
        $xml = '<fill><patternFill patternType="solid"><fgColor rgb="FF' . $color . '"/></patternFill></fill>';
        $i = array_search($xml, $this->fills, true);
        if ($i === false) { $this->fills[] = $xml; $i = count($this->fills) - 1; }
        return $i;
    }

    private function fmtId(?string $code): int
    {
        if ($code === null) { return 0; }
        if (!isset($this->numFmts[$code])) { $this->numFmts[$code] = $this->nextFmtId++; }
        return $this->numFmts[$code];
    }

    /** opts: bold,italic,size,color,fill,fmt,border,halign,valign,wrap */
    public function style(array $o): int
    {
        $fontId = $this->fontId($o['bold'] ?? false, $o['italic'] ?? false, $o['size'] ?? 11, $o['color'] ?? null);
        $fillId = $this->fillId($o['fill'] ?? null);
        $borderId = !empty($o['border']) ? 1 : 0;
        $fmtId = $this->fmtId($o['fmt'] ?? null);
        $halign = $o['halign'] ?? null; $valign = $o['valign'] ?? null; $wrap = !empty($o['wrap']);
        $align = '';
        if ($halign || $valign || $wrap) {
            $align = '<alignment' . ($halign ? ' horizontal="' . $halign . '"' : '')
                . ($valign ? ' vertical="' . $valign . '"' : '') . ($wrap ? ' wrapText="1"' : '') . '/>';
        }
        $xf = '<xf numFmtId="' . $fmtId . '" fontId="' . $fontId . '" fillId="' . $fillId . '" borderId="' . $borderId . '" xfId="0"'
            . ' applyFont="1"' . ($fillId ? ' applyFill="1"' : '') . ($borderId ? ' applyBorder="1"' : '')
            . ($fmtId ? ' applyNumberFormat="1"' : '') . ($align ? ' applyAlignment="1"' : '') . '>'
            . $align . '</xf>';
        $i = array_search($xf, $this->xfs, true);
        if ($i === false) { $this->xfs[] = $xf; $i = count($this->xfs) - 1; }
        return $i;
    }

    public function set(string $s, int $r, int $c, $v, int $style = 0): void
    {
        if ($v === null || $v === '') { if ($style) { $this->sheets[$s]['rows'][$r][$c] = ['', 'b', $style]; } return; }
        $t = (is_int($v) || is_float($v)) ? 'n' : 's';
        $this->sheets[$s]['rows'][$r][$c] = [$v, $t, $style];
        if ($r > $this->sheets[$s]['maxR']) { $this->sheets[$s]['maxR'] = $r; }
        if ($c > $this->sheets[$s]['maxC']) { $this->sheets[$s]['maxC'] = $c; }
    }

    public function merge(string $s, int $r1, int $c1, int $r2, int $c2): void
    {
        $this->sheets[$s]['merges'][] = self::cell($r1, $c1) . ':' . self::cell($r2, $c2);
    }

    public function cols(string $s, array $widths): void { $this->sheets[$s]['cols'] = $widths; }
    public function freeze(string $s, string $cell): void { $this->sheets[$s]['freeze'] = $cell; }
    public function rowHeight(string $s, int $r, float $h): void { $this->sheets[$s]['rowHt'][$r] = $h; }

    public function colorScale(string $s, string $sqref): void
    {
        $this->sheets[$s]['cf'][] = '<conditionalFormatting sqref="' . $sqref . '"><cfRule type="colorScale" priority="1">'
            . '<colorScale><cfvo type="num" val="0"/><cfvo type="percentile" val="60"/><cfvo type="max"/>'
            . '<color rgb="FFFFFFFF"/><color rgb="FF9DC3E6"/><color rgb="FF1F4E78"/></colorScale></cfRule></conditionalFormatting>';
    }

    public static function colLetter(int $c): string
    {
        $s = '';
        while ($c > 0) { $m = ($c - 1) % 26; $s = chr(65 + $m) . $s; $c = intdiv($c - 1 - $m, 26); }
        return $s;
    }
    public static function cell(int $r, int $c): string { return self::colLetter($c) . $r; }

    private static function esc(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_XML1, 'UTF-8'); }

    private static function num(string $v, $raw): string
    {
        if (is_int($raw)) { return (string) $raw; }
        $s = rtrim(rtrim(sprintf('%.6f', $raw), '0'), '.');
        return ($s === '' || $s === '-0') ? '0' : $s;
    }

    private function sheetXml(string $name): string
    {
        $sh = $this->sheets[$name];
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        // freeze pane
        $pane = '';
        if ($sh['freeze']) {
            preg_match('/^([A-Z]+)(\d+)$/', $sh['freeze'], $mm);
            $col = 0; foreach (str_split($mm[1]) as $ch) { $col = $col * 26 + (ord($ch) - 64); }
            $xs = $col - 1; $ys = (int) $mm[2] - 1;
            $pane = '<pane' . ($xs ? ' xSplit="' . $xs . '"' : '') . ($ys ? ' ySplit="' . $ys . '"' : '')
                . ' topLeftCell="' . $sh['freeze'] . '" activePane="bottomRight" state="frozen"/>'
                . '<selection pane="bottomRight"/>';
        }
        $x .= '<sheetViews><sheetView workbookViewId="0">' . $pane . '</sheetView></sheetViews>';
        $x .= '<sheetFormatPr defaultRowHeight="15"/>';
        if ($sh['cols']) {
            $x .= '<cols>';
            foreach ($sh['cols'] as $i => $w) {
                $n = $i + 1;
                $x .= '<col min="' . $n . '" max="' . $n . '" width="' . $w . '" customWidth="1"/>';
            }
            $x .= '</cols>';
        }
        $x .= '<sheetData>';
        $maxR = $sh['maxR'];
        for ($r = 1; $r <= $maxR; $r++) {
            $cells = $sh['rows'][$r] ?? null;
            $ht = $sh['rowHt'][$r] ?? null;
            if ($cells === null && $ht === null) { continue; }
            $x .= '<row r="' . $r . '"' . ($ht !== null ? ' ht="' . $ht . '" customHeight="1"' : '') . '>';
            if ($cells) {
                ksort($cells);
                foreach ($cells as $c => [$v, $t, $st]) {
                    $ref = self::cell($r, $c);
                    $sAttr = $st ? ' s="' . $st . '"' : '';
                    if ($t === 'n') {
                        $x .= '<c r="' . $ref . '"' . $sAttr . '><v>' . self::num($ref, $v) . '</v></c>';
                    } elseif ($t === 'b') {
                        $x .= '<c r="' . $ref . '"' . $sAttr . '/>';
                    } else {
                        $x .= '<c r="' . $ref . '"' . $sAttr . ' t="inlineStr"><is><t xml:space="preserve">' . self::esc((string) $v) . '</t></is></c>';
                    }
                }
            }
            $x .= '</row>';
        }
        $x .= '</sheetData>';
        if ($sh['merges']) {
            $x .= '<mergeCells count="' . count($sh['merges']) . '">';
            foreach ($sh['merges'] as $m) { $x .= '<mergeCell ref="' . $m . '"/>'; }
            $x .= '</mergeCells>';
        }
        foreach ($sh['cf'] as $cf) { $x .= $cf; }
        $x .= '</worksheet>';
        return $x;
    }

    private function stylesXml(): string
    {
        $x = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">';
        if ($this->numFmts) {
            $x .= '<numFmts count="' . count($this->numFmts) . '">';
            foreach ($this->numFmts as $code => $id) {
                $x .= '<numFmt numFmtId="' . $id . '" formatCode="' . self::esc($code) . '"/>';
            }
            $x .= '</numFmts>';
        }
        $x .= '<fonts count="' . count($this->fonts) . '">' . implode('', $this->fonts) . '</fonts>';
        $x .= '<fills count="' . count($this->fills) . '">' . implode('', $this->fills) . '</fills>';
        $thin = '<border><left style="thin"><color rgb="FFBFBFBF"/></left><right style="thin"><color rgb="FFBFBFBF"/></right>'
            . '<top style="thin"><color rgb="FFBFBFBF"/></top><bottom style="thin"><color rgb="FFBFBFBF"/></bottom><diagonal/></border>';
        $x .= '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>' . $thin . '</borders>';
        $x .= '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>';
        $x .= '<cellXfs count="' . count($this->xfs) . '">' . implode('', $this->xfs) . '</cellXfs>';
        $x .= '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>';
        $x .= '</styleSheet>';
        return $x;
    }

    public function save(string $path): void
    {
        $n = count($this->order);
        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        for ($i = 1; $i <= $n; $i++) {
            $ct .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $ct .= '</Types>';

        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';

        $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $i = 1;
        foreach ($this->order as $name) {
            $wb .= '<sheet name="' . self::esc($name) . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
            $i++;
        }
        $wb .= '</sheets></workbook>';

        $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        for ($i = 1; $i <= $n; $i++) {
            $wbRels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        }
        $wbRels .= '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $wbRels .= '</Relationships>';

        if (is_file($path)) { @unlink($path); }
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE) !== true) { fwrite(STDERR, "cannot create {$path}\n"); exit(1); }
        $zip->addFromString('[Content_Types].xml', $ct);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('xl/workbook.xml', $wb);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wbRels);
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $i = 1;
        foreach ($this->order as $name) {
            $zip->addFromString('xl/worksheets/sheet' . $i . '.xml', $this->sheetXml($name));
            $i++;
        }
        $zip->close();
    }
}

// =====================================================================
//  Report
// =====================================================================
$X = new Xlsx();
$EUR = '#,##0 €'; $PCT1 = '0.0%'; $PCT0 = '0%';

// reusable styles
$sTitle = $X->style(['bold' => true, 'size' => 15, 'color' => $TITLEC]);
$sNote  = $X->style(['italic' => true, 'size' => 9, 'color' => $NOTEC, 'halign' => 'left', 'valign' => 'center', 'wrap' => true]);
$sHeadL = ['bold' => true, 'color' => $WHITE, 'fill' => $HEAD, 'border' => true, 'halign' => 'center', 'valign' => 'center', 'wrap' => true];
$sHead  = $X->style($sHeadL);
$cBorderCen = $X->style(['border' => true, 'halign' => 'center', 'valign' => 'center']);
$cBorderLeft = $X->style(['border' => true, 'halign' => 'left', 'valign' => 'center', 'wrap' => true]);

function headerRow(Xlsx $X, string $s, int $row, array $labels, int $headStyle, float $ht): void
{
    foreach ($labels as $j => $lab) { $X->set($s, $row, $j + 1, $lab, $headStyle); }
    $X->rowHeight($s, $row, $ht);
}

// helper to make a styled cell quickly with caching by signature
$styleCache = [];
$st = function (array $o) use ($X, &$styleCache) {
    $k = json_encode($o);
    return $styleCache[$k] ?? ($styleCache[$k] = $X->style($o));
};

// =====================================================================
// SHEET 1 — SUMMARY
// =====================================================================
$s1 = $X->addSheet('Summary (Feb–Jun)');
$X->set($s1, 1, 1, "Yumefit Stuudio — Performance 1 Feb – {$asOf}", $sTitle);
$X->set($s1, 2, 1, "Source: live pro.timma.ee calendar (serviceslots), pulled {$asOf}. Actuals only. January excluded (onboarding ramp-up). Open hours assumed 09:00–20:00 (11 h/day). Income uses assumed avg {$AVG} €/booking. *{$pMon} = 1–{$pDays} only ({$pDays} days); all its metrics cover that window so are comparable.", $sNote);
$X->merge($s1, 2, 1, 2, 13); $X->rowHeight($s1, 2, 32);

$hdr = ["Month", "Days", "Open h\n(11h×days)", "Trainer\nsched. h",
    "Schedule cover %\n(Q2: ≥1 trainer\nin 9-20)", "Booked h",
    "Booking util %\n(Q3: booked /\nscheduled)", "Bookings",
    "  • Sold", "  • Reserved", "Est. income €\n(Q4: 26×bookings)",
    "Active\ntrainers", "Actual €\n(slot price, partial)"];
$r0 = 4; headerRow($X, $s1, $r0, $hdr, $sHead, 58);

$pctCen = $st(['border' => true, 'halign' => 'center', 'valign' => 'center', 'fmt' => $PCT1]);
$eurCen = $st(['border' => true, 'halign' => 'center', 'valign' => 'center', 'fmt' => $EUR]);
$totC = ['border' => true, 'halign' => 'center', 'valign' => 'center', 'fill' => $TOTAL, 'bold' => true];
$totCen = $st($totC);
$totPct = $st($totC + ['fmt' => $PCT1]);
$totEur = $st($totC + ['fmt' => $EUR]);

$tot = ['days' => 0, 'open' => 0, 'sched' => 0, 'booked' => 0, 'nb' => 0, 'ns' => 0, 'nr' => 0, 'inc' => 0, 'act' => 0, 'union' => 0.0];
$r = $r0 + 1;
foreach ($monthly as $m) {
    $inc = $m['nBookings'] * $AVG;
    $vals = [$MONTH_NAMES[$m['month']], $m['days'], $m['openHrs'], $m['schedHrs'],
        $m['unionCovPct'] / 100, $m['bookedHrs'], $m['utilPct'] / 100, $m['nBookings'],
        $m['nSold'], $m['nReserved'], $inc, $m['nActiveTrainers'], $m['actualRevEur']];
    foreach ($vals as $j => $v) {
        $col = $j + 1; $sty = $cBorderCen;
        if ($col === 5 || $col === 7) { $sty = $pctCen; }
        if ($col === 11 || $col === 13) { $sty = $eurCen; }
        $X->set($s1, $r, $col, $v, $sty);
    }
    $tot['days'] += $m['days']; $tot['open'] += $m['openHrs']; $tot['sched'] += $m['schedHrs'];
    $tot['booked'] += $m['bookedHrs']; $tot['nb'] += $m['nBookings']; $tot['ns'] += $m['nSold'];
    $tot['nr'] += $m['nReserved']; $tot['inc'] += $inc; $tot['act'] += $m['actualRevEur'];
    $tot['union'] += $m['unionCovHrs'];
    $r++;
}
$trow = ["Period total (Feb–{$asOfShort})", $tot['days'], $tot['open'], rnd($tot['sched'], 1),
    $tot['union'] / $tot['open'], rnd($tot['booked'], 1), $tot['booked'] / $tot['sched'],
    $tot['nb'], $tot['ns'], $tot['nr'], $tot['inc'], '', rnd($tot['act'])];
foreach ($trow as $j => $v) {
    $col = $j + 1; $sty = $totCen;
    if ($col === 5 || $col === 7) { $sty = $totPct; }
    if ($col === 11 || $col === 13) { $sty = $totEur; }
    $X->set($s1, $r, $col, $v, $sty);
}
$r += 2;

$comp = array_values(array_filter($monthly, fn($m) => empty($m['partial'])));
$avg_b = array_sum(array_column($comp, 'nBookings')) / count($comp);
$avg_inc = $avg_b * $AVG;
$bold = $st(['bold' => true]);
$boldCen = $st(['bold' => true, 'halign' => 'center', 'valign' => 'center']);
$boldEur = $st(['bold' => true, 'fmt' => $EUR]);
$X->set($s1, $r, 1, 'Avg complete month (Feb–May)', $bold);
$X->set($s1, $r, 8, rnd($avg_b, 1), $boldCen);
$X->set($s1, $r, 11, rnd($avg_inc), $boldEur);
$r += 2;
$notes1 = [
    "Q2 — Schedule coverage: % of the 09:00–20:00 open window that had at least one trainer scheduled (interval union, equipment & test users excluded).",
    "Q3 — Booking utilisation: booked hours ÷ total scheduled trainer-hours. Shows how much of staffed time actually sold.",
    "Q4 — Estimated income = number of bookings × 26 € (client assumption). 'Actual €' = sum of slot service prices, but incomplete (memberships/packages book at 0 €), so the 26 € estimate is the headline.",
    "{$pMon} (1–{$pDays}) is a {$pDays}-day window: its booking count is naturally ~⅓ of a full month, but its utilisation ({$pUtil}%) and coverage ({$pCov}%) are like-for-like with the full months.",
];
foreach ($notes1 as $ln) { $X->set($s1, $r, 1, $ln, $sNote); $X->merge($s1, $r, 1, $r, 13); $X->rowHeight($s1, $r, 28); $r++; }
$X->cols($s1, [24, 7, 11, 11, 15, 10, 14, 10, 10, 11, 14, 9, 15]);
$X->freeze($s1, 'A5');

// =====================================================================
// SHEET 2 — BY TRAINER (PERIOD TOTALS)
// =====================================================================
$s2 = $X->addSheet('By Trainer (Feb–Jun)');
$X->set($s2, 1, 1, "Numbers by Trainer — 1 Feb to {$asOf}", $sTitle);
$X->set($s2, 2, 1, "Each active trainer's totals over the period. Sorted by bookings. Util % = booked hrs ÷ scheduled hrs. Equipment & test users excluded. (Triin Kalda & Signet Krüner were active through Jan only; Lisbeth Luik / Mariliis Kalda left in 2025 — none appear here.)", $sNote);
$X->merge($s2, 2, 1, 2, 9); $X->rowHeight($s2, 2, 32);
$hdrt = ["Trainer", "Category", "Months\nactive", "Scheduled h", "Booked h", "Util %", "Bookings", "Sold", "Reserved", "Est. income €\n(26×bookings)"];
$r0 = 4; headerRow($X, $s2, $r0, $hdrt, $sHead, 30);
$leftB = $st(['border' => true, 'halign' => 'left', 'valign' => 'center']);
$pctCenT = $st(['border' => true, 'halign' => 'center', 'valign' => 'center', 'fmt' => $PCT1]);
$r = $r0 + 1;
$sumf = ['sh' => 0.0, 'bh' => 0.0, 'nb' => 0, 'ns' => 0, 'nr' => 0, 'inc' => 0];
foreach ($trainerTotals as $t) {
    $vals = [$t['name'], $t['cat'], $t['monthsActive'], $t['schedHrs'], $t['bookedHrs'],
        $t['utilPct'] / 100, $t['nBookings'], $t['nSold'], $t['nReserved'], $t['estIncomeEur']];
    foreach ($vals as $j => $v) {
        $col = $j + 1;
        $sty = ($col === 1 || $col === 2) ? $leftB : $cBorderCen;
        if ($col === 6) { $sty = $pctCenT; }
        if ($col === 10) { $sty = $eurCen; }
        $X->set($s2, $r, $col, $v, $sty);
    }
    $sumf['sh'] += $t['schedHrs']; $sumf['bh'] += $t['bookedHrs']; $sumf['nb'] += $t['nBookings'];
    $sumf['ns'] += $t['nSold']; $sumf['nr'] += $t['nReserved']; $sumf['inc'] += $t['estIncomeEur'];
    $r++;
}
$totLeft = $st(['border' => true, 'fill' => $TOTAL, 'bold' => true, 'halign' => 'left', 'valign' => 'center']);
$vals = ['All trainers', '', '', rnd($sumf['sh'], 1), rnd($sumf['bh'], 1),
    ($sumf['sh'] ? $sumf['bh'] / $sumf['sh'] : 0), $sumf['nb'], $sumf['ns'], $sumf['nr'], $sumf['inc']];
foreach ($vals as $j => $v) {
    $col = $j + 1; $sty = ($col === 1 || $col === 2) ? $totLeft : $totCen;
    if ($col === 6) { $sty = $totPct; }
    if ($col === 10) { $sty = $totEur; }
    $X->set($s2, $r, $col, $v, $sty);
}
$X->cols($s2, [22, 22, 8, 12, 11, 9, 10, 8, 10, 14]);
$X->freeze($s2, 'A5');

// =====================================================================
// SHEET 3 — BY TRAINER x MONTH
// =====================================================================
$s3 = $X->addSheet('By Trainer x Month');
$X->set($s3, 1, 1, 'Bookings by Month & Trainer', $sTitle);
$hdr2 = ["Month", "Trainer", "Category", "Scheduled h", "Booked h", "Util %", "Bookings", "Sold", "Reserved", "Est. income €\n(26×bookings)"];
$r0 = 3; headerRow($X, $s3, $r0, $hdr2, $sHead, 30);
$catorder = ["Personal/EMS trainer" => 0, "Yoga (group)" => 1, "Massage" => 2, "Other" => 3];
$rowsSorted = $trainers;
usort($rowsSorted, function ($a, $b) use ($order, $catorder) {
    return [array_search($a['month'], $order), $catorder[$a['cat']] ?? 9, -$a['nBookings'], $a['name']]
       <=> [array_search($b['month'], $order), $catorder[$b['cat']] ?? 9, -$b['nBookings'], $b['name']];
});
$subhead = $st(['fill' => $SUBHEAD, 'bold' => true, 'color' => $TITLEC, 'halign' => 'left', 'valign' => 'center']);
$pctCen3 = $pctCenT;
$leftB3 = $st(['border' => true, 'halign' => 'left', 'valign' => 'center', 'wrap' => true]);
$r = $r0 + 1; $curMonth = null; $mtot = null;
$flush = function ($r, $mtot) use ($X, $s3, $MONTH_NAMES, $AVG, $totCen, $totPct, $totEur) {
    if (!$mtot) { return $r; }
    $vals = ["", "— " . $MONTH_NAMES[$mtot['m']] . " total —", "", rnd($mtot['s'], 1), rnd($mtot['b'], 1),
        ($mtot['s'] ? $mtot['b'] / $mtot['s'] : 0), $mtot['nb'], $mtot['ns'], $mtot['nr'], $mtot['nb'] * $AVG];
    foreach ($vals as $j => $v) {
        $col = $j + 1; $sty = $totCen;
        if ($col === 6) { $sty = $totPct; }
        if ($col === 10) { $sty = $totEur; }
        $X->set($s3, $r, $col, $v, $sty);
    }
    return $r + 1;
};
foreach ($rowsSorted as $t) {
    if ($curMonth !== $t['month']) {
        $r = $flush($r, $mtot);
        $curMonth = $t['month'];
        $mtot = ['m' => $t['month'], 's' => 0.0, 'b' => 0.0, 'nb' => 0, 'ns' => 0, 'nr' => 0];
        $X->set($s3, $r, 1, $MONTH_NAMES[$t['month']], $subhead);
        $X->merge($s3, $r, 1, $r, 10);
        $r++;
    }
    $inc = $t['nBookings'] * $AVG;
    $util = $t['schedHrs'] ? $t['bookedHrs'] / $t['schedHrs'] : 0;
    $vals = [$MONTH_NAMES[$t['month']], $t['name'], $t['cat'], $t['schedHrs'], $t['bookedHrs'],
        $util, $t['nBookings'], $t['nSold'], $t['nReserved'], $inc];
    foreach ($vals as $j => $v) {
        $col = $j + 1;
        $sty = ($col === 2 || $col === 3) ? $leftB3 : $cBorderCen;
        if ($col === 6) { $sty = $pctCen3; }
        if ($col === 10) { $sty = $eurCen; }
        $X->set($s3, $r, $col, $v, $sty);
    }
    $mtot['s'] += $t['schedHrs']; $mtot['b'] += $t['bookedHrs']; $mtot['nb'] += $t['nBookings'];
    $mtot['ns'] += $t['nSold']; $mtot['nr'] += $t['nReserved'];
    $r++;
}
$r = $flush($r, $mtot);
$X->cols($s3, [16, 24, 22, 12, 11, 9, 10, 8, 10, 14]);
$X->freeze($s3, 'A4');

// =====================================================================
// SHEET 4 — BY CATEGORY x MONTH
// =====================================================================
$s4 = $X->addSheet('By Category x Month');
$X->set($s4, 1, 1, 'Bookings by Service Category & Month', $sTitle);
$cats = ["Personal/EMS trainer", "Yoga (group)", "Massage", "Other"];
$agg = [];
foreach ($trainers as $t) { $agg[$t['month']][$t['cat']] = ($agg[$t['month']][$t['cat']] ?? 0) + $t['nBookings']; }
$hdr3 = array_merge(["Category"], array_map(fn($m) => $MONTH_NAMES[$m], $order), ["Period total", "Est. income € (period)"]);
$r0 = 3; headerRow($X, $s4, $r0, $hdr3, $sHead, 26);
$leftB4 = $st(['border' => true, 'halign' => 'left', 'valign' => 'center']);
$ncol = count($hdr3);
$r = $r0 + 1; $coltot = array_fill(0, count($order), 0);
foreach ($cats as $cat) {
    $rowvals = [$cat]; $ytd = 0;
    foreach ($order as $mi => $m) { $v = $agg[$m][$cat] ?? 0; $rowvals[] = $v; $ytd += $v; $coltot[$mi] += $v; }
    $rowvals[] = $ytd; $rowvals[] = $ytd * $AVG;
    foreach ($rowvals as $j => $v) {
        $col = $j + 1;
        $sty = ($col === 1) ? $leftB4 : $cBorderCen;
        if ($col === $ncol) { $sty = $eurCen; }
        $X->set($s4, $r, $col, $v, $sty);
    }
    $r++;
}
$trv = array_merge(["All categories"], $coltot, [array_sum($coltot), array_sum($coltot) * $AVG]);
foreach ($trv as $j => $v) {
    $col = $j + 1; $sty = ($col === 1) ? $totLeft : $totCen;
    if ($col === $ncol) { $sty = $totEur; }
    $X->set($s4, $r, $col, $v, $sty);
}
$X->cols($s4, [22, 12, 12, 12, 12, 13, 12, 18]);
$X->freeze($s4, 'B4');

// =====================================================================
// SHEET 5 — PROJECTIONS (Q5)
// =====================================================================
$s5 = $X->addSheet('Projections (Q5)');
$X->set($s5, 1, 1, 'Income Projection — Bookings needed to cover expenses (Q5)', $sTitle);
$X->set($s5, 2, 1, sprintf("Assumed avg revenue 26 €/booking. Target = expenses ÷ 26. 'Avg complete month' = mean bookings Feb–May = %.1f.", $avg_b), $sNote);
$X->merge($s5, 2, 1, 2, 8);
$sec = $st(['bold' => true, 'size' => 12]);
$X->set($s5, 4, 1, 'Targets at 26 €/booking', $sec);
$hdrA = ["Expense target / mo", "Bookings needed / mo", "Income at target €", "vs avg month\nadditional bookings", "additional %", "vs avg month\nadditional income €"];
$r0 = 5; headerRow($X, $s5, $r0, $hdrA, $sHead, 44);
$pctCen5 = $st(['border' => true, 'halign' => 'center', 'valign' => 'center', 'fmt' => $PCT0]);
$r = $r0 + 1;
foreach ([4000, 5000] as $target) {
    $need = (int) ceil($target / $AVG);
    $add = $need - $avg_b;
    $vals = [$target, $need, $need * $AVG, rnd($add, 1), $add / $avg_b, rnd($target - $avg_inc)];
    foreach ($vals as $j => $v) {
        $col = $j + 1; $sty = $cBorderCen;
        if ($col === 1 || $col === 3 || $col === 6) { $sty = $eurCen; }
        if ($col === 5) { $sty = $pctCen5; }
        $X->set($s5, $r, $col, $v, $sty);
    }
    $r++;
}
$X->set($s5, $r, 1, sprintf("Current avg complete month: %.1f bookings ≈ %s € income", $avg_b, number_format($avg_inc)), $st(['italic' => true]));
$r += 2;
$X->set($s5, $r, 1, 'Gap per month (each month vs targets)', $sec);
$r++;
$need4 = (int) ceil(4000 / $AVG); $need5 = (int) ceil(5000 / $AVG);
$hdrB = ["Month", "Actual bookings", "Est. income €", "Need 4000€\n({$need4})", "Add'l\n4000€", "Add'l %\n4000€", "Need 5000€\n({$need5})", "Add'l\n5000€", "Add'l %\n5000€"];
headerRow($X, $s5, $r, $hdrB, $sHead, 44);
$r++;
$grey = ['border' => true, 'halign' => 'center', 'valign' => 'center', 'italic' => true, 'color' => '999999'];
$greyCen = $st($grey); $greyPct = $st($grey + ['fmt' => $PCT0]); $greyEur = $st($grey + ['fmt' => $EUR]);
foreach ($monthly as $m) {
    $nb = $m['nBookings']; $inc = $nb * $AVG;
    $a4 = $need4 - $nb; $a5 = $need5 - $nb;
    $vals = [$MONTH_NAMES[$m['month']], $nb, $inc, $need4, $a4, $nb ? $a4 / $nb : 0, $need5, $a5, $nb ? $a5 / $nb : 0];
    $partial = !empty($m['partial']);
    foreach ($vals as $j => $v) {
        $col = $j + 1;
        $sty = $partial ? $greyCen : $cBorderCen;
        if ($col === 3) { $sty = $partial ? $greyEur : $eurCen; }
        if ($col === 6 || $col === 9) { $sty = $partial ? $greyPct : $pctCen5; }
        $X->set($s5, $r, $col, $v, $sty);
    }
    $r++;
}
$a4 = $need4 - $avg_b; $a5 = $need5 - $avg_b;
$totPct0 = $st($totC + ['fmt' => $PCT0]);
$vals = ["Avg month (Feb–May)", rnd($avg_b, 1), rnd($avg_inc), $need4, rnd($a4, 1), $a4 / $avg_b, $need5, rnd($a5, 1), $a5 / $avg_b];
foreach ($vals as $j => $v) {
    $col = $j + 1; $sty = $totCen;
    if ($col === 3) { $sty = $totEur; }
    if ($col === 6 || $col === 9) { $sty = $totPct0; }
    $X->set($s5, $r, $col, $v, $sty);
}
$r += 2;
$notes5 = [
    sprintf("To cover 4 000 €/month the studio needs ~%d bookings/month — about +%d more than the current ~%.0f average (+%.0f%%).", $need4, $need4 - $avg_b, $avg_b, ($need4 - $avg_b) / $avg_b * 100),
    sprintf("To cover 5 000 €/month it needs ~%d bookings/month — about +%d more than the current average (+%.0f%%).", $need5, $need5 - $avg_b, ($need5 - $avg_b) / $avg_b * 100),
    "Levers: bookings are only ~17–21% of staffed trainer hours (Q3), so the capacity already exists — the gap is demand/fill-rate, not schedule. {$pMon} (1–{$pDays}, italic) is a partial month — exclude from the comparison.",
];
foreach ($notes5 as $ln) { $X->set($s5, $r, 1, $ln, $sNote); $X->merge($s5, $r, 1, $r, 9); $X->rowHeight($s5, $r, 26); $r++; }
$X->cols($s5, [22, 16, 14, 12, 10, 10, 12, 10, 10]);

// =====================================================================
// SHEET 6 — PEAK & EMPTY TIMES
// =====================================================================
$hm = $d['heatmap'];
$hours = $hm['hours']; $DOW = $hm['dow']; $matrix = $hm['matrix'];
$dowtot = $hm['dowTotals']; $hourtot = $hm['hourTotals'];
$s6 = $X->addSheet('Peak & Empty Times');
$X->set($s6, 1, 1, 'When are bookings made? — Peak & empty hours/days', $sTitle);
$X->set($s6, 2, 1, $hm['source'], $sNote);
$X->merge($s6, 2, 1, 2, 9); $X->rowHeight($s6, 2, 32);
$r0 = 4;
$X->set($s6, $r0, 1, 'Hour ╲ Day', $sHead);
foreach ($DOW as $j => $day) { $X->set($s6, $r0, $j + 2, $day, $sHead); }
$X->set($s6, $r0, 9, 'All days', $sHead);
$bcen = $cBorderCen;
$hourTotSty = $st(['border' => true, 'halign' => 'center', 'valign' => 'center', 'bold' => true, 'fill' => $SUBHEAD]);
$hourLab = $st(['bold' => true, 'border' => true, 'halign' => 'center', 'valign' => 'center']);
$r = $r0 + 1; $firstData = $r;
foreach ($hours as $i => $h) {
    $X->set($s6, $r, 1, sprintf('%02d:00', $h), $hourLab);
    for ($j = 0; $j < 7; $j++) { $X->set($s6, $r, $j + 2, $matrix[$i][$j], $bcen); }
    $ht = $hourtot[(string) $h] ?? array_sum($matrix[$i]);
    $X->set($s6, $r, 9, $ht, $hourTotSty);
    $r++;
}
$lastData = $r - 1;
$X->set($s6, $r, 1, 'All hours', $totCen);
for ($j = 0; $j < 7; $j++) { $X->set($s6, $r, $j + 2, $dowtot[$j], $totCen); }
$X->set($s6, $r, 9, array_sum($dowtot), $totCen);
$totalsRow = $r;
$X->colorScale($s6, "B{$firstData}:H{$lastData}");
$X->cols($s6, [11, 7, 7, 7, 7, 7, 7, 7, 9]);
$X->freeze($s6, 'B5');

// rankings text
$r = $totalsRow + 2;
$daysRanked = [];
foreach ($DOW as $i => $nm) { $daysRanked[] = [$nm, $dowtot[$i]]; }
usort($daysRanked, fn($a, $b) => $b[1] <=> $a[1]);
$hoursRanked = [];
foreach ($hours as $h) { $hoursRanked[] = [$h, $hourtot[(string) $h] ?? 0]; }
usort($hoursRanked, fn($a, $b) => $b[1] <=> $a[1]);
$cells = [];
foreach ($hours as $i => $h) { for ($j = 0; $j < 7; $j++) { $cells[] = [$matrix[$i][$j], $DOW[$j], $h]; } }
$busiest = $cells; usort($busiest, fn($a, $b) => $b[0] <=> $a[0]); $busiest = array_slice($busiest, 0, 5);
$empties = array_values(array_filter($cells, fn($c) => $c[0] === 0));

$blockTitle = $st(['bold' => true, 'size' => 12, 'color' => $TITLEC]);
$bt = function ($text) use (&$r, $X, $s6, $blockTitle) { $X->set($s6, $r, 1, $text, $blockTitle); $r++; };
$line = function ($text) use (&$r, $X, $s6, $sNote) { $X->set($s6, $r, 1, $text, $sNote); $X->merge($s6, $r, 1, $r, 9); $X->rowHeight($s6, $r, 26); $r++; };

$bt('1) Peak hours & days');
$line(sprintf("Busiest days: %s %d, %s %d, %s %d.", $daysRanked[0][0], $daysRanked[0][1], $daysRanked[1][0], $daysRanked[1][1], $daysRanked[2][0], $daysRanked[2][1]));
$hl = array_map(fn($x) => sprintf('%02d:00 (%d)', $x[0], $x[1]), array_slice($hoursRanked, 0, 4));
$line("Busiest hours of day: " . implode(', ', $hl) . ".");
$hc = array_map(fn($c) => sprintf('%s %02d:00 (×%d)', $c[1], $c[2], $c[0]), $busiest);
$line("Hottest individual slots: " . implode(', ', $hc) . ".");
$bt('2) Empty / lowest slots');
$line("Totally empty across the whole open window: before 09:00 and from 20:00 onward (0 bookings) — every booking falls inside 09:00–19:59.");
$ql = array_map(fn($x) => sprintf('%02d:00 (%d)', $x[0], $x[1]), array_slice($hoursRanked, -3));
$line("Quietest occupied hours: " . implode(', ', $ql) . ".");
$worst = end($daysRanked);
$worstEmpty = array_map(fn($c) => sprintf('%02d:00', $c[2]), array_values(array_filter($empties, fn($c) => $c[1] === $worst[0])));
$line(sprintf("Weakest day: %s (%d). Empty %s hours: %s.", $worst[0], $worst[1], $worst[0], implode(', ', $worstEmpty)));

// =====================================================================
// SHEET 7 — METHODOLOGY
// =====================================================================
$s7 = $X->addSheet('Methodology & Notes');
$X->set($s7, 1, 1, 'Methodology & Notes', $sTitle);
$notes = [
    ["Period", "1 Feb – {$asOf}, pulled live from pro.timma.ee on {$asOfShort}. Actuals only (future reservations after {$asOfShort} dropped). JANUARY 2026 IS EXCLUDED — it was an onboarding ramp-up month (low schedule, generic 'Vaba kasutaja' bookings) and distorts averages. Times bucketed in Europe/Tallinn."],
    ["{$pMon} note", "{$pMon} is shown as 1–{$pDays} only ({$pDays} days). Because bookings, booked hours and scheduled hours all cover the same {$pDays}-day window, {$pMon}'s utilisation ({$pUtil}%) and coverage ({$pCov}%) are directly comparable to the full months; only its raw booking COUNT ({$pBk}) is naturally smaller (~⅓ of a month)."],
    ["Staff turnover", "The active roster changed. Triin Kalda and Signet Krüner booked through January only (last sessions 12 Jan and 30 Jan); Lisbeth Luik and Mariliis Kalda left in 2025. They do NOT appear in this Feb–Jun report. The current EMS roster is Laura Luks, Marleen, Alexandra Tšaplõgin, Elis Kikas, Carmen, Virge Andre and Ronald Vilbiks, plus group yoga."],
    ["What is a booking", "Calendar slots with status 'sold' (checked out) or 'reserved' (booked). 'notForSale' (breaks/blocked) and day-boundary markers are not bookings."],
    ["Scheduled (worktime) hours", "Each working day a trainer has a 'dayStart' and 'dayEnd' marker; the bookable window = dayStart.end → dayEnd.start. Scheduled hours = sum of these windows across all trainers and days in the period."],
    ["Q2 — Schedule coverage", "For each day, the UNION of all trainers' working windows clipped to 09:00–20:00, divided by the 11 h open window. Answers 'what share of open hours had at least one trainer present' (≤100%)."],
    ["Q3 — Booking utilisation", "Booked hours ÷ total scheduled trainer-hours. Across the period this runs ~17–21% — staffed time is only ~⅕ sold."],
    ["Q4 — Income", "Headline income = bookings × 26 € (client-supplied average). The platform's per-slot price ('Actual €') is shown for reference but is materially incomplete (memberships/packages record 0 €), so it understates real revenue and is not the headline."],
    ["Q5 — Projection", "Bookings needed = monthly expense ÷ 26 €. 4 000 € → 154/mo; 5 000 € → 193/mo. 'Additional' compares to the current average complete month (Feb–May)."],
    ["By Trainer", "The 'By Trainer (Feb–Jun)' sheet totals each active trainer over the period. Util % = booked ÷ scheduled hrs. Categories derived from each user's service mix (Virge Andre reclassified to EMS — service mix is all EMS). Equipment ('Infrapunamatt') and the massage test user are excluded."],
    ["Rounding", "Hours rounded to 0.1; percentages to 0.1%; income to whole euro. Minor month-boundary rounding (±1 booking) may occur and is immaterial."],
];
$noteTitle = $st(['bold' => true, 'color' => $TITLEC, 'valign' => 'top']);
$noteBody = $st(['halign' => 'left', 'valign' => 'center', 'wrap' => true]);
$r = 3;
foreach ($notes as [$title, $body]) {
    $X->set($s7, $r, 1, $title, $noteTitle);
    $X->set($s7, $r, 2, $body, $noteBody);
    $X->merge($s7, $r, 2, $r, 8);
    $X->rowHeight($s7, $r, 54);
    $r++;
}
$X->cols($s7, [22, 18, 14, 14, 14, 14, 14, 14]);

$X->save($outPath);
echo "saved {$outPath}\n";
echo "period total bookings: {$tot['nb']} | est income: {$tot['inc']}\n";
printf("avg complete month: %.1f => income %.0f\n", $avg_b, $avg_inc);


// =====================================================================
//  FETCH  (port of fetch_timma.py)
// =====================================================================
function timma_auth(string $authPath, array $opt): array
{
    $token = $opt['token'] ?? '';
    $email = $opt['email'] ?? '';
    if (($token === '' || $email === '') && is_file($authPath)) {
        $a = json_decode((string) file_get_contents($authPath), true) ?: [];
        if ($token === '') { $token = $a['x-auth-token'] ?? ''; }
        if ($email === '') { $email = $a['x-auth-email'] ?? ''; }
    }
    if ($token === '' || $email === '') {
        fwrite(STDERR, "No Timma token (pass --token=… --email=… or cache {$authPath}); or run with --no-fetch.\n");
        exit(1);
    }
    return [$token, $email];
}

function timma_get(string $url, string $token, string $email)
{
    if (!function_exists('curl_init')) {
        fwrite(STDERR, "PHP curl extension required to fetch; enable it, or run with --no-fetch.\n");
        exit(1);
    }
    $headers = ["x-auth-token: {$token}", "x-auth-email: {$email}", "accept: application/json"];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => $headers]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($code !== 200) {
        fwrite(STDERR, "Timma request failed (HTTP {$code}) — token expired? Re-grab x-auth-token, or use --no-fetch.\n  {$url}\n");
        exit(1);
    }
    return json_decode((string) $body, true);
}

/** Pull every staff member's calendar slots over [from,to]; dedupe by slot id. */
function timma_fetch(string $token, string $email, string $biz, string $from, string $to): array
{
    $s = $from . 'T00:00:00.000Z';
    $e = $to . 'T00:00:00.000Z';
    $users = timma_get("https://pro.timma.ee/api/users/customer/{$biz}?includeRemoved=true", $token, $email);
    $names = [];
    foreach ($users as $u) { $names[$u['id']] = $u['name'] ?? $u['id']; }
    echo count($users) . " staff users\n";
    $slots = [];
    $i = 0;
    $n = count($users);
    foreach ($users as $u) {
        $i++;
        $uid = $u['id'];
        $url = "https://pro.timma.ee/api/serviceslots/combo/timmaprocalendar/user/{$uid}"
            . '?start=' . rawurlencode($s) . '&end=' . rawurlencode($e) . '&userIds[]=' . $uid;
        $data = timma_get($url, $token, $email) ?: [];
        foreach ($data as $sl) {
            if (!isset($sl['userName'])) { $sl['userName'] = $names[$sl['userId'] ?? ''] ?? null; }
            $slots[$sl['id']] = $sl;
        }
        printf("  [%d/%d] %-22s %d slots (unique %d)\n", $i, $n, $names[$uid] ?? $uid, count($data), count($slots));
        usleep(150000);
    }
    $slots = array_values($slots);
    usort($slots, fn($a, $b) => ($a['start'] ?? '') <=> ($b['start'] ?? ''));
    return $slots;
}

// =====================================================================
//  AGGREGATE  (port of aggregate.py) — actuals 1 Feb .. cutoff
// =====================================================================
function aggregate_data(array $b, string $cutoffStr, int $AVG): array
{
    $tz = new DateTimeZone('Europe/Tallinn');
    $cutoff = new DateTimeImmutable($cutoffStr . ' 00:00:00', $tz);
    $cutoffYmd = $cutoff->format('Y-m-d');
    $year = (int) $cutoff->format('Y');
    $cutMonth = (int) $cutoff->format('n');
    $partialKey = $cutoff->format('Y-m');
    $isPartial = ((int) $cutoff->format('j')) < ((int) $cutoff->format('t'));
    $EXCL = ['Infrapunamatt' => 1, 'Massaaži test kasutaja' => 1];

    $MONTHS = [];
    for ($mo = 2; $mo <= $cutMonth; $mo++) { $MONTHS[] = sprintf('%04d-%02d', $year, $mo); }
    $DAYS_IN = [];
    foreach ($MONTHS as $mk) {
        $DAYS_IN[$mk] = ($mk === $partialKey && $isPartial)
            ? (int) $cutoff->format('j')
            : (int) (new DateTimeImmutable($mk . '-01'))->format('t');
    }

    $loc = fn(string $iso) => (new DateTimeImmutable($iso))->setTimezone($tz);
    $hrs = fn(DateTimeImmutable $a, DateTimeImmutable $c) => ($c->getTimestamp() - $a->getTimestamp()) / 3600.0;
    $svc = fn(array $x) => $x['reservation']['serviceName'] ?? '';
    $excluded = fn(?string $n) => $n !== null && isset($EXCL[$n]);
    $inferCat = function (?string $name, array $services) {
        if ($name === 'Virge Andre') { return 'Personal/EMS trainer'; }
        $s = mb_strtolower(implode(' ', $services));
        if (str_contains($s, 'ems')) { return 'Personal/EMS trainer'; }
        if (str_contains($s, 'jooga') || str_contains($s, 'yoga')) { return 'Yoga (group)'; }
        foreach (['massaaž', 'massage', 'kobido', 'massöör'] as $k) { if (str_contains($s, $k)) { return 'Massage'; } }
        return 'Other';
    };

    // schedule markers per (uid|date), excluding equipment/test and anything after cutoff
    $ds = []; $de = [];
    foreach ($b as $x) {
        $name = $x['userName'] ?? null;
        if ($excluded($name)) { continue; }
        $lt = $loc($x['start']);
        if ($lt->format('Y-m-d') > $cutoffYmd) { continue; }
        $key = ($x['userId'] ?? '') . '|' . $lt->format('Y-m-d');
        if ($x['status'] === 'dayStart') { $ds[$key] = $x; }
        elseif ($x['status'] === 'dayEnd') { $de[$key] = $x; }
    }

    $schedHours = function (string $month, ?string $uid = null) use ($ds, $de, $loc, $hrs) {
        $tot = 0.0;
        foreach ($ds as $key => $s) {
            [$u, $dt] = explode('|', $key);
            if (substr($dt, 0, 7) !== $month) { continue; }
            if ($uid !== null && $u !== $uid) { continue; }
            if (!isset($de[$key])) { continue; }
            $w = $hrs($loc($s['end']), $loc($de[$key]['start']));
            if ($w > 0) { $tot += $w; }
        }
        return $tot;
    };
    $coverageHours = function (string $month) use ($ds, $de, $loc) {
        $byday = [];
        foreach ($ds as $key => $s) {
            $dt = explode('|', $key)[1];
            if (substr($dt, 0, 7) !== $month) { continue; }
            if (!isset($de[$key])) { continue; }
            $a = $loc($s['end']); $c = $loc($de[$key]['start']);
            $lo = $a->setTime(9, 0, 0); $hi = $a->setTime(20, 0, 0);
            if ($a < $lo) { $a = $lo; }
            if ($c > $hi) { $c = $hi; }
            if ($c > $a) { $byday[$dt][] = [$a->getTimestamp(), $c->getTimestamp()]; }
        }
        $tot = 0;
        foreach ($byday as $iv) {
            sort($iv);
            [$cs, $ce] = $iv[0];
            for ($i = 1; $i < count($iv); $i++) {
                [$s2, $e2] = $iv[$i];
                if ($s2 <= $ce) { $ce = max($ce, $e2); }
                else { $tot += $ce - $cs; $cs = $s2; $ce = $e2; }
            }
            $tot += $ce - $cs;
        }
        return $tot / 3600.0;
    };

    // bookings per (month|uid)
    $uid2name = [];
    foreach ($b as $x) { if (!empty($x['userId'])) { $uid2name[$x['userId']] = $x['userName'] ?? null; } }
    $inPeriod = function (DateTimeImmutable $lt) use ($year, $cutoffYmd) {
        return (int) $lt->format('Y') === $year && (int) $lt->format('n') >= 2 && $lt->format('Y-m-d') <= $cutoffYmd;
    };
    $tm = []; $smix = [];
    foreach ($b as $x) {
        if (!in_array($x['status'] ?? '', ['sold', 'reserved'], true) || !empty($x['deletedOn'])) { continue; }
        $lt = $loc($x['start']);
        if (!$inPeriod($lt)) { continue; }
        $name = $x['userName'] ?? null;
        if ($excluded($name)) { continue; }
        $uid = $x['userId']; $k = $lt->format('Y-m') . '|' . $uid;
        if (!isset($tm[$k])) { $tm[$k] = ['nB' => 0, 'nS' => 0, 'nR' => 0, 'bh' => 0.0, 'rev' => 0.0]; }
        $tm[$k]['nB']++;
        if ($x['status'] === 'sold') { $tm[$k]['nS']++; } else { $tm[$k]['nR']++; }
        $tm[$k]['bh'] += $hrs($lt, $loc($x['end']));
        $tm[$k]['rev'] += (($x['reservation']['servicePrice'] ?? 0) / 100);
        $smix[$uid][$svc($x)] = true;
    }

    // scheduled hours per (month|uid)
    $schedUm = [];
    foreach ($ds as $key => $s) {
        [$u, $dt] = explode('|', $key);
        $m = substr($dt, 0, 7);
        if (!in_array($m, $MONTHS, true) || !isset($de[$key])) { continue; }
        $w = $hrs($loc($s['end']), $loc($de[$key]['start']));
        if ($w > 0) { $schedUm[$m . '|' . $u] = ($schedUm[$m . '|' . $u] ?? 0) + $w; }
    }

    // monthly rollup
    $monthly = [];
    foreach ($MONTHS as $m) {
        $bh = $nB = $nS = $nR = 0; $rev = 0.0; $active = [];
        foreach ($tm as $k => $v) {
            if (substr($k, 0, 7) !== $m) { continue; }
            $bh += $v['bh']; $nB += $v['nB']; $nS += $v['nS']; $nR += $v['nR']; $rev += $v['rev'];
            $active[explode('|', $k)[1]] = 1;
        }
        foreach ($schedUm as $k => $v) { if (substr($k, 0, 7) === $m) { $active[explode('|', $k)[1]] = 1; } }
        $sched = $schedHours($m); $cov = $coverageHours($m);
        $days = $DAYS_IN[$m]; $openh = $days * 11;
        $monthly[] = [
            'month' => $m, 'days' => $days, 'openHrs' => $openh,
            'schedHrs' => rnd($sched, 1), 'unionCovHrs' => rnd($cov, 1),
            'unionCovPct' => rnd($cov / $openh * 100, 1),
            'bookedHrs' => rnd($bh, 1), 'utilPct' => $sched ? rnd($bh / $sched * 100, 1) : 0,
            'nBookings' => $nB, 'nSold' => $nS, 'nReserved' => $nR,
            'actualRevEur' => (int) rnd($rev), 'nActiveTrainers' => count($active),
            'partial' => ($m === $partialKey && $isPartial),
        ];
    }

    // per-trainer per-month rows (every month a trainer booked OR was scheduled)
    $keys = array_unique(array_merge(array_keys($tm), array_keys($schedUm)));
    $zero = ['nB' => 0, 'nS' => 0, 'nR' => 0, 'bh' => 0.0, 'rev' => 0.0];
    $trainers = [];
    foreach ($keys as $k) {
        [$m, $uid] = explode('|', $k);
        $name = $uid2name[$uid] ?? null;
        if ($excluded($name)) { continue; }
        $v = $tm[$k] ?? $zero;
        $trainers[] = [
            'month' => $m, 'name' => $name, 'cat' => $inferCat($name, array_keys($smix[$uid] ?? [])),
            'excluded' => false, 'schedHrs' => rnd($schedUm[$k] ?? 0.0, 1), 'bookedHrs' => rnd($v['bh'], 1),
            'nBookings' => $v['nB'], 'nSold' => $v['nS'], 'nReserved' => $v['nR'], 'actualRevEur' => (int) rnd($v['rev']),
        ];
    }

    // per-trainer totals
    $tt = [];
    foreach ($trainers as $t) {
        $n = $t['name'];
        if (!isset($tt[$n])) { $tt[$n] = ['nB' => 0, 'nS' => 0, 'nR' => 0, 'bh' => 0.0, 'sh' => 0.0, 'rev' => 0, 'months' => [], 'cat' => 'Other']; }
        $tt[$n]['nB'] += $t['nBookings']; $tt[$n]['nS'] += $t['nSold']; $tt[$n]['nR'] += $t['nReserved'];
        $tt[$n]['bh'] += $t['bookedHrs']; $tt[$n]['sh'] += $t['schedHrs']; $tt[$n]['rev'] += $t['actualRevEur'];
        $tt[$n]['months'][$t['month']] = 1; $tt[$n]['cat'] = $t['cat'];
    }
    $trainerTotals = [];
    foreach ($tt as $n => $a) {
        $trainerTotals[] = [
            'name' => $n, 'cat' => $a['cat'], 'monthsActive' => count($a['months']),
            'schedHrs' => rnd($a['sh'], 1), 'bookedHrs' => rnd($a['bh'], 1),
            'utilPct' => $a['sh'] ? rnd($a['bh'] / $a['sh'] * 100, 1) : 0,
            'nBookings' => $a['nB'], 'nSold' => $a['nS'], 'nReserved' => $a['nR'],
            'estIncomeEur' => $a['nB'] * $AVG, 'actualRevEur' => (int) rnd($a['rev']),
        ];
    }
    usort($trainerTotals, fn($x, $y) => [$y['nBookings'], $x['name']] <=> [$x['nBookings'], $y['name']]);

    // heatmap (hour x weekday) over the period
    $hours = range(9, 19);
    $matrix = array_fill(0, count($hours), array_fill(0, 7, 0));
    $nheat = 0;
    foreach ($b as $x) {
        if (!in_array($x['status'] ?? '', ['sold', 'reserved'], true) || !empty($x['deletedOn'])) { continue; }
        if ($excluded($x['userName'] ?? null)) { continue; }
        $lt = $loc($x['start']);
        if (!$inPeriod($lt)) { continue; }
        $h = (int) $lt->format('G');
        if ($h >= 9 && $h < 20) { $matrix[$h - 9][(int) $lt->format('N') - 1]++; $nheat++; }
    }
    $dow = [];
    for ($dd = 0; $dd < 7; $dd++) { $sum = 0; foreach ($hours as $i => $h) { $sum += $matrix[$i][$dd]; } $dow[] = $sum; }
    $hr = [];
    foreach ($hours as $i => $h) { $hr[(string) $h] = array_sum($matrix[$i]); }

    return [
        'generatedFor' => 'Yumefit Stuudio',
        'period' => "{$year}-02-01..{$cutoffYmd} (Feb–cutoff; Jan excluded)",
        'avgPriceEur' => $AVG,
        'monthly' => $monthly,
        'trainers' => $trainers,
        'trainerTotals' => $trainerTotals,
        'heatmap' => [
            'source' => "Fresh Timma pull, actuals {$year}-02-01..{$cutoffYmd} (real bookings, equipment/test excluded), bucketed by START time Europe/Tallinn.",
            'nTotal' => $nheat, 'hours' => array_values($hours),
            'dow' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'matrix' => $matrix, 'dowTotals' => $dow, 'hourTotals' => $hr,
        ],
    ];
}
