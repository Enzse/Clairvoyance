<?php

/**
 * Writes the spreadsheets for the two demo scenarios in docs/demo/README.md, dated around a
 * given day so "this month" and "last week" are current when you present.
 *
 *   php docs/demo/make_datasets.php              (dated around today)
 *   php docs/demo/make_datasets.php 2026-11-05   (dated around the presentation day)
 *
 * The figures are made up but follow each scenario's story: the same day always gives the
 * same numbers.
 */

if (PHP_SAPI !== 'cli') {
    exit('Command line only.');
}

$day = new DateTimeImmutable($argv[1] ?? 'today');
mt_srand(2026);

/** A factor around 1, e.g. jitter(0.05) is between 0.95 and 1.05. */
function jitter(float $spread): float
{
    return 1 + mt_rand(-1000, 1000) / 1000 * $spread;
}

function write(string $name, array $rows): void
{
    $file = fopen(__DIR__ . '/' . $name, 'w');
    fwrite($file, "\xEF\xBB\xBF"); // so Excel reads it as UTF-8
    foreach ($rows as $row) {
        fputcsv($file, $row, ',', '"', '');
    }
    fclose($file);
    echo 'Wrote docs/demo/' . $name . ' (' . (count($rows) - 1) . " rows)\n";
}

// ---------------------------------------------------------------------------------------
// Scenario 1: Kapehan Coffee Co., five branches, monthly figures.
// Eleven past months, then this month for every branch except Cubao (typed in live).
// Alabang slips (revenue down, waste and complaints up); BGC opened seven months ago.
// ---------------------------------------------------------------------------------------
$month = $day->modify('first day of this month');
$branches = [
    // name => [revenue, ticket size, satisfaction, food waste kg, monthly trend, opened (months ago)]
    'Katipunan' => [1250000, 145, 92, 104, 0.004, null],
    'Cubao' => [1080000, 150, 91, 116, 0.0, null],
    'Makati' => [1420000, 185, 93, 97, 0.006, null],
    'BGC' => [780000, 175, 91, 112, 0.035, 7],
    'Alabang' => [1060000, 160, 90, 128, -0.014, null],
];
$season = [12 => 1.22, 1 => 0.9, 2 => 0.95, 4 => 1.04, 5 => 1.06];

$header = ['Branch', 'Month', 'Revenue', 'Orders', 'Customer satisfaction', 'Complaints', 'Food waste'];
$history = [$header];
$current = [$header];
for ($ago = 11; $ago >= 0; $ago--) {
    $date = $month->modify("-{$ago} months");
    $step = 11 - $ago; // months since the start of the data
    foreach ($branches as $name => [$revenue, $ticket, $satisfaction, $waste, $trend, $opened]) {
        if ($opened !== null && $ago > $opened) {
            continue; // not open yet
        }
        if ($ago === 0 && $name === 'Cubao') {
            continue; // Cubao's manager types this month in during the demo
        }
        $slipping = $name === 'Alabang' ? $step : 0;
        $sales = $revenue * (1 + $trend * $step) * ($season[(int)$date->format('n')] ?? 1) * jitter(0.04);
        $score = min(99, $satisfaction - 0.45 * $slipping + mt_rand(-15, 15) / 10);
        $row = [
            $name,
            $date->format('Y-m-d'),
            (int)(round($sales / 100) * 100),
            (int)round($sales / $ticket * jitter(0.03)),
            (int)round($score),
            max(0, (int)round((100 - $score) * 0.55 + mt_rand(-1, 2))),
            (int)round(($waste + 3.5 * $slipping) * jitter(0.08)),
        ];
        if ($ago === 0) {
            $current[] = $row;
        } else {
            $history[] = $row;
        }
    }
}
write('kapehan-history.csv', $history);
write('kapehan-this-month.csv', $current);

// ---------------------------------------------------------------------------------------
// Scenario 2: Bayanihan Logistics, five depots in three regions, weekly figures.
// Fifteen past weeks, then last week in a second file with the same columns.
// Iloilo's on-time delivery drops in the last four weeks (road works); Davao's fuel runs high.
// Cold-chain breaches only apply to the two depots with cold storage.
// ---------------------------------------------------------------------------------------
$week = $day->modify('monday this week');
$depots = [
    // [region, depot, parcels, on-time %, fuel per parcel, damaged, cold storage]
    ['Luzon', 'Manila Hub', 2400, 96.6, 71, 14, true],
    ['Luzon', 'Clark', 1300, 96.2, 68, 8, false],
    ['Visayas', 'Cebu', 1800, 95.8, 70, 11, true],
    ['Visayas', 'Iloilo', 900, 95.6, 75, 7, false],
    ['Mindanao', 'Davao', 1200, 95.1, 112, 9, false],
];

$header = ['Region', 'Depot', 'Week', 'Parcels delivered', 'On-time delivery', 'Fuel cost', 'Damaged parcels', 'Cold-chain breaches'];
$history = [$header];
$latest = [$header];
for ($ago = 16; $ago >= 1; $ago--) {
    $date = $week->modify("-{$ago} weeks");
    $step = 16 - $ago;
    foreach ($depots as [$region, $name, $parcels, $onTime, $fuel, $damaged, $cold]) {
        $count = (int)round($parcels * (1 + 0.004 * $step) * jitter(0.06));
        $late = $name === 'Iloilo' && $ago <= 4 ? 5.5 : 0; // road works on the main route
        $row = [
            $region,
            $name,
            $date->format('Y-m-d'),
            $count,
            round(min(99.9, $onTime - $late + mt_rand(-8, 8) / 10), 1),
            (int)(round($count * $fuel * jitter(0.05) / 10) * 10),
            max(0, (int)round($damaged * jitter(0.35))),
            $cold ? ($name === 'Manila Hub' && $ago === 6 ? 4 : mt_rand(0, 2)) : '',
        ];
        if ($ago === 1) {
            $latest[] = $row;
        } else {
            $history[] = $row;
        }
    }
}
write('bayanihan-history.csv', $history);
write('bayanihan-last-week.csv', $latest);
