<?php
declare(strict_types=1);

// ──────────────────────────────────────────────────────────────────
// Cron entry (add to VPS via crontab -e):
// */10 * * * * php /var/www/sg-portal.nirix.online/api/notify-cron.php >> /var/log/nirix-cron.log 2>&1
// ──────────────────────────────────────────────────────────────────

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/AlertEvaluator.php';
require_once __DIR__ . '/Notifier.php';

const SPREADSHEET_ID = '1qul0ee5Ioh526zXw-dXBakCMFZk3emJhM6lRdM6pEKA';
const DEDUP_FILE = '/tmp/nirix_notified.json';
const LOOKBACK_MINUTES = 20;

/**
 * Get current SGT time string (UTC+8).
 */
function sgtNow(): string
{
    return date('Y-m-d H:i', time() + 8 * 3600);
}

/**
 * Get current SGT date string.
 */
function sgtToday(): string
{
    return date('Y-m-d', time() + 8 * 3600);
}

/**
 * Load dedup state from file. Resets if stored date != today.
 *
 * @return array{date: string, keys: string[]}
 */
function loadDedup(): array
{
    $today = sgtToday();

    if (!file_exists(DEDUP_FILE)) {
        return ['date' => $today, 'keys' => []];
    }

    $raw = file_get_contents(DEDUP_FILE);
    if ($raw === false) {
        return ['date' => $today, 'keys' => []];
    }

    $data = json_decode($raw, true);
    if (!is_array($data) || ($data['date'] ?? '') !== $today) {
        return ['date' => $today, 'keys' => []];
    }

    return ['date' => $today, 'keys' => $data['keys'] ?? []];
}

/**
 * Save dedup state to file.
 *
 * @param array{date: string, keys: string[]} $dedup
 */
function saveDedup(array $dedup): void
{
    file_put_contents(DEDUP_FILE, json_encode($dedup, JSON_UNESCAPED_UNICODE));
}

/**
 * Check if a Submitted At timestamp is within the last N minutes (SGT).
 */
function isWithinLookback(string $submittedAt): bool
{
    if ($submittedAt === '') {
        return false;
    }

    $ts = strtotime($submittedAt);
    if ($ts === false) {
        return false;
    }

    $nowSGT = time() + 8 * 3600;
    $tsSGT = $ts + 8 * 3600;
    $diffMinutes = ($nowSGT - $tsSGT) / 60;

    return $diffMinutes >= 0 && $diffMinutes <= LOOKBACK_MINUTES;
}

// ── Main logic ───────────────────────────────────────────────────

try {
    $client = getGoogleClient();
    $service = new Google\Service\Sheets($client);

    $range = 'Sheet1!A:ZZ';
    $response = $service->spreadsheets_values->get(SPREADSHEET_ID, $range);
    $values = $response->getValues() ?? [];

    if (count($values) < 2) {
        error_log('[nirix-cron] No data rows found');
        exit(0);
    }

    $headers = $values[0];
    $rows = [];
    for ($i = 1, $count = count($values); $i < $count; $i++) {
        $row = [];
        foreach ($headers as $idx => $header) {
            $row[$header] = $values[$i][$idx] ?? '';
        }
        $rows[] = $row;
    }

    $dedup = loadDedup();
    $evaluator = new AlertEvaluator();
    $notifier = new Notifier();
    $recipient = getenv('WA_RECIPIENT') ?: '';

    $checked = 0;
    $sent = 0;

    foreach ($rows as $row) {
        $submittedAt = $row['Submitted At'] ?? '';
        if (!isWithinLookback($submittedAt)) {
            continue;
        }

        $checked++;
        $alerts = $evaluator->evaluate($row);

        if ($alerts === []) {
            continue;
        }

        $boatName = $row['Boat'] ?? 'Unknown';
        $dateStr = substr($submittedAt, 0, 10);
        $dedupKey = $boatName . '|' . $dateStr;

        if (in_array($dedupKey, $dedup['keys'], true)) {
            continue;
        }

        $engineer = $row['Engineer'] ?? $row['Reported By'] ?? '—';
        $alertList = implode("\n", array_map(static fn(string $a): string => "  • {$a}", $alerts));

        $message = "\xF0\x9F\x9A\xA8 NIRIX ALERT\n"
            . "Vessel: {$boatName}\n"
            . "Date: {$dateStr}\n"
            . "Submitted by: {$engineer}\n"
            . "Alerts:\n{$alertList}";

        if ($recipient !== '' && $notifier->sendWhatsApp($recipient, $message)) {
            $sent++;
        }

        $dedup['keys'][] = $dedupKey;
    }

    saveDedup($dedup);
    error_log("[nirix-cron] Checked {$checked} recent rows, sent {$sent} alerts");
} catch (Throwable $e) {
    error_log('[nirix-cron] ' . get_class($e) . ': ' . $e->getMessage());
    exit(1);
}
