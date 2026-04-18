<?php
/**
 * LottoExpert.net — SKAI World-Class Results Intelligence Page
 * Joomla 5.x + PHP 8.1+
 *
 * PURPOSE
 * - Premium, agency-grade results and analysis experience
 * - Calm, Apple/Stripe-style presentation
 * - Brand-safe, ethical, non-hype tone
 * - WCAG-aware contrast and structured hierarchy
 * - Safer query handling than the original
 *
 * ASSUMES UPSTREAM VARIABLES EXIST:
 * - $stateName
 * - $stateAbrev
 * - $gName
 * - $dbCol
 *
 * GAME CONFIG IN THIS FILE:
 * - game_id = 128
 * - non-daily game
 * - main balls = 5
 * - main number range = 01..41
 * - bonus range = 01..06 (Star Ball)
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;

$app   = Factory::getApplication();
$doc   = Factory::getDocument();
$input = $app->input;
$db    = Factory::getDbo();
$user  = Factory::getUser();

$gmCodeRaw = (string) $input->getString('gmCode', '');
if ($gmCodeRaw === '') {
    $gmCodeRaw = (string) $input->getString('game_id', '');
}
if ($gmCodeRaw === '') {
    $gmCodeRaw = (string) $input->getString('gid', '');
}
$gmCodeRaw = str_replace("\xC2\xA0", ' ', $gmCodeRaw);
$gId = strtoupper(trim($gmCodeRaw));

if ($gId === '' || !preg_match('/^[A-Z0-9]{2,5}$/', $gId)) {
    http_response_code(400);
    echo 'Invalid game code format.';
    return;
}

$gameTableMap = [
    'CTC' => '#__lotterydb_ct', 'CTCW' => '#__lotterydb_ct', 'CTD' => '#__lotterydb_ct', 'CTDW' => '#__lotterydb_ct',
    'FLB' => '#__lotterydb_fl', 'FLBF' => '#__lotterydb_fl', 'FLD' => '#__lotterydb_fl', 'FLDF' => '#__lotterydb_fl',
    '122' => '#__lotterydb_il', '123' => '#__lotterydb_il', 'ILI' => '#__lotterydb_il', 'ILJ' => '#__lotterydb_il',
    'INC' => '#__lotterydb_in', 'INCF' => '#__lotterydb_in', 'IND' => '#__lotterydb_in', 'INDF' => '#__lotterydb_in',
    'MSC' => '#__lotterydb_ms', 'MSCF' => '#__lotterydb_ms', 'MSD' => '#__lotterydb_ms', 'MSDF' => '#__lotterydb_ms',
    'NJC' => '#__lotterydb_nj', 'NJCF' => '#__lotterydb_nj', 'NJD' => '#__lotterydb_nj', 'NJDF' => '#__lotterydb_nj',
    'NCC' => '#__lotterydb_nc', 'NCCF' => '#__lotterydb_nc', 'NCD' => '#__lotterydb_nc', 'NCDF' => '#__lotterydb_nc',
    'PAC' => '#__lotterydb_pa', 'PACW' => '#__lotterydb_pa', 'PAD' => '#__lotterydb_pa', 'PADW' => '#__lotterydb_pa',
    'SCC' => '#__lotterydb_sc', 'SCCF' => '#__lotterydb_sc', 'SCD' => '#__lotterydb_sc', 'SCDF' => '#__lotterydb_sc',
    'TNB' => '#__lotterydb_tn', 'TNBW' => '#__lotterydb_tn', 'TND' => '#__lotterydb_tn', 'TNDW' => '#__lotterydb_tn',
    'TNF' => '#__lotterydb_tn', 'TNFW' => '#__lotterydb_tn',
    'TXB' => '#__lotterydb_tx', 'TXBF' => '#__lotterydb_tx', 'TXD' => '#__lotterydb_tx', 'TXDF' => '#__lotterydb_tx',
    'TXL' => '#__lotterydb_tx', 'TXLF' => '#__lotterydb_tx', 'TXM' => '#__lotterydb_tx', 'TXMF' => '#__lotterydb_tx',
    'VAC' => '#__lotterydb_va', 'VACF' => '#__lotterydb_va', 'VAD' => '#__lotterydb_va', 'VADF' => '#__lotterydb_va'
];

$gameInfoMap = [
    'CTC' => ['state' => 'Connecticut', 'lottery' => 'Play4 Day', 'mainGameId' => 'CTC', 'extraBallGameId' => 'CTCW', 'extraBallLabel' => 'Wild Ball'],
    'CTD' => ['state' => 'Connecticut', 'lottery' => 'Play4 Night', 'mainGameId' => 'CTD', 'extraBallGameId' => 'CTDW', 'extraBallLabel' => 'Wild Ball'],
    'FLB' => ['state' => 'Florida', 'lottery' => 'Pick 4 Evening', 'mainGameId' => 'FLB', 'extraBallGameId' => 'FLBF', 'extraBallLabel' => 'Fireball'],
    'FLD' => ['state' => 'Florida', 'lottery' => 'Pick 4 Midday', 'mainGameId' => 'FLD', 'extraBallGameId' => 'FLDF', 'extraBallLabel' => 'Fireball'],
    '122' => ['state' => 'Illinois', 'lottery' => 'Pick 4 Midday', 'mainGameId' => '122', 'extraBallGameId' => 'ILI', 'extraBallLabel' => 'Fireball'],
    '123' => ['state' => 'Illinois', 'lottery' => 'Pick 4 Evening', 'mainGameId' => '123', 'extraBallGameId' => 'ILJ', 'extraBallLabel' => 'Fireball'],
    'INC' => ['state' => 'Indiana', 'lottery' => 'Daily 4 Midday', 'mainGameId' => 'INC', 'extraBallGameId' => 'INCF', 'extraBallLabel' => 'Super Ball'],
    'IND' => ['state' => 'Indiana', 'lottery' => 'Daily 4 Evening', 'mainGameId' => 'IND', 'extraBallGameId' => 'INDF', 'extraBallLabel' => 'Super Ball'],
    'MSC' => ['state' => 'Mississippi', 'lottery' => 'Cash 4 Evening', 'mainGameId' => 'MSC', 'extraBallGameId' => 'MSCF', 'extraBallLabel' => 'Fireball'],
    'MSD' => ['state' => 'Mississippi', 'lottery' => 'Cash 4 Midday', 'mainGameId' => 'MSD', 'extraBallGameId' => 'MSDF', 'extraBallLabel' => 'Fireball'],
    'NJC' => ['state' => 'New Jersey', 'lottery' => 'Pick 4 Midday', 'mainGameId' => 'NJC', 'extraBallGameId' => 'NJCF', 'extraBallLabel' => 'Fireball'],
    'NJD' => ['state' => 'New Jersey', 'lottery' => 'Pick 4 Evening', 'mainGameId' => 'NJD', 'extraBallGameId' => 'NJDF', 'extraBallLabel' => 'Fireball'],
    'NCC' => ['state' => 'North Carolina', 'lottery' => 'Pick 4 Evening', 'mainGameId' => 'NCC', 'extraBallGameId' => 'NCCF', 'extraBallLabel' => 'Fireball'],
    'NCD' => ['state' => 'North Carolina', 'lottery' => 'Pick 4 Day', 'mainGameId' => 'NCD', 'extraBallGameId' => 'NCDF', 'extraBallLabel' => 'Fireball'],
    'PAC' => ['state' => 'Pennsylvania', 'lottery' => 'Pick 4 Evening', 'mainGameId' => 'PAC', 'extraBallGameId' => 'PACW', 'extraBallLabel' => 'Wild Ball'],
    'PAD' => ['state' => 'Pennsylvania', 'lottery' => 'Pick 4 Day', 'mainGameId' => 'PAD', 'extraBallGameId' => 'PADW', 'extraBallLabel' => 'Wild Ball'],
    'SCC' => ['state' => 'South Carolina', 'lottery' => 'Pick 4 Midday', 'mainGameId' => 'SCC', 'extraBallGameId' => 'SCCF', 'extraBallLabel' => 'Fireball'],
    'SCD' => ['state' => 'South Carolina', 'lottery' => 'Pick 4 Evening', 'mainGameId' => 'SCD', 'extraBallGameId' => 'SCDF', 'extraBallLabel' => 'Fireball'],
    'TNB' => ['state' => 'Tennessee', 'lottery' => 'Cash 4 Midday', 'mainGameId' => 'TNB', 'extraBallGameId' => 'TNBW', 'extraBallLabel' => 'Wild Ball'],
    'TND' => ['state' => 'Tennessee', 'lottery' => 'Cash 4 Evening', 'mainGameId' => 'TND', 'extraBallGameId' => 'TNDW', 'extraBallLabel' => 'Wild Ball'],
    'TNF' => ['state' => 'Tennessee', 'lottery' => 'Cash 4 Morning', 'mainGameId' => 'TNF', 'extraBallGameId' => 'TNFW', 'extraBallLabel' => 'Wild Ball'],
    'TXB' => ['state' => 'Texas', 'lottery' => 'Daily 4 Day', 'mainGameId' => 'TXB', 'extraBallGameId' => 'TXBF', 'extraBallLabel' => 'Fireball'],
    'TXD' => ['state' => 'Texas', 'lottery' => 'Daily 4 Night', 'mainGameId' => 'TXD', 'extraBallGameId' => 'TXDF', 'extraBallLabel' => 'Fireball'],
    'TXL' => ['state' => 'Texas', 'lottery' => 'Daily 4 Morning', 'mainGameId' => 'TXL', 'extraBallGameId' => 'TXLF', 'extraBallLabel' => 'Fireball'],
    'TXM' => ['state' => 'Texas', 'lottery' => 'Daily 4 Evening', 'mainGameId' => 'TXM', 'extraBallGameId' => 'TXMF', 'extraBallLabel' => 'Fireball'],
    'VAC' => ['state' => 'Virginia', 'lottery' => 'Pick 4 Day', 'mainGameId' => 'VAC', 'extraBallGameId' => 'VACF', 'extraBallLabel' => 'Fireball'],
    'VAD' => ['state' => 'Virginia', 'lottery' => 'Pick 4 Night', 'mainGameId' => 'VAD', 'extraBallGameId' => 'VADF', 'extraBallLabel' => 'Fireball']
];

if (!array_key_exists($gId, $gameTableMap)) {
    http_response_code(404);
    echo 'Invalid game ID.';
    return;
}

$dbCol = (string) $gameTableMap[$gId];
$mainGameId = '';
$extraBallGameId = null;
$extraBallLabel = 'Extra Ball';
$stateName = '';
$gName = '';

foreach ($gameInfoMap as $info) {
    $main = (string) $info['mainGameId'];
    $extra = isset($info['extraBallGameId']) ? (string) $info['extraBallGameId'] : '';
    if ($gId === $main || ($extra !== '' && $gId === $extra)) {
        $mainGameId = $main;
        $extraBallGameId = ($extra !== '') ? $extra : null;
        $extraBallLabel = isset($info['extraBallLabel']) ? (string) $info['extraBallLabel'] : 'Extra Ball';
        $stateName = (string) $info['state'];
        $gName = (string) $info['lottery'];
        break;
    }
}

if ($mainGameId === '') {
    http_response_code(404);
    echo 'Invalid game ID.';
    return;
}

if ($extraBallGameId === null) {
    $fCandidate = $mainGameId . 'F';
    $wCandidate = $mainGameId . 'W';

    if (array_key_exists($fCandidate, $gameTableMap)) {
        $extraBallGameId = $fCandidate;
    } elseif (array_key_exists($wCandidate, $gameTableMap)) {
        $extraBallGameId = $wCandidate;
    }
}

$gameId = $mainGameId;
$stateAbrev = '';
if (preg_match('/_([a-z]{2})$/i', (string) $dbCol, $sm)) {
    $stateAbrev = strtoupper((string) $sm[1]);
}

/**
 * --------------------------------------------------------------------------
 * Document / SEO
 * --------------------------------------------------------------------------
 */
$uri = Uri::getInstance();
$canonicalNoQuery = $uri->toString(['scheme', 'host', 'port', 'path']);
$doc->addCustomTag('<link rel="canonical" href="' . htmlspecialchars($canonicalNoQuery, ENT_QUOTES, 'UTF-8') . '" />');
$doc->addCustomTag('<link rel="alternate" hreflang="en" href="' . htmlspecialchars($canonicalNoQuery, ENT_QUOTES, 'UTF-8') . '" />');
$doc->addCustomTag('<link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($canonicalNoQuery, ENT_QUOTES, 'UTF-8') . '" />');

if (isset($stateName, $gName) && $stateName !== '' && $gName !== '') {
    $doc->setTitle('Results Intelligence — ' . $stateName . ' • ' . $gName . ' | LottoExpert.net');
}

/**
 * --------------------------------------------------------------------------
 * User / session handling
 * --------------------------------------------------------------------------
 */
$loginStatus = (int) ($user->guest ?? 1);

if ($loginStatus === 1) {
    $session = Factory::getSession();
    $userSession = $session->getId();
} else {
    $userId = (int) $user->id;

    $profileQuery = $db->getQuery(true)
        ->select($db->quoteName('profile_value'))
        ->from($db->quoteName('#__user_profiles'))
        ->where($db->quoteName('profile_key') . ' = ' . $db->quote('profile.phone'))
        ->where($db->quoteName('user_id') . ' = ' . (int) $userId);

    $db->setQuery($profileQuery);
    $userPhone = (string) $db->loadResult();

    if ($userPhone !== '') {
        $userPhone = str_replace('"', '', $userPhone);
        $userPhone = str_replace('(', '', $userPhone);
        $userPhone = str_replace(')', '-', $userPhone);
    } else {
        $userPhone = 'NULL';
    }
}

/**
 * --------------------------------------------------------------------------
 * Helpers
 * --------------------------------------------------------------------------
 */
function leFmtDate(?string $date): string
{
    if (!$date) {
        return '';
    }

    $ts = strtotime($date);

    return ($ts === false) ? '' : date('m-d-Y', $ts);
}

function leFmtDateLong(?string $date): string
{
    if (!$date) {
        return '—';
    }

    $ts = strtotime($date);

    return ($ts === false) ? '—' : date('F j, Y', $ts);
}

function lePad2(string $value): string
{
    $value = trim((string) $value);

    if ($value === '') {
        return '';
    }

    if (ctype_digit($value) && (int) $value < 10) {
        return str_pad($value, 2, '0', STR_PAD_LEFT);
    }

    return $value;
}

function leResolveLogo(string $stateAbrev, string $gName): array
{
    $stateSlug   = strtolower(trim($stateAbrev));
    $lotterySlug = strtolower(trim($gName));
    $lotterySlug = str_replace(' ', '-', $lotterySlug);

    $rel = '/images/lottodb/us/' . $stateSlug . '/' . $lotterySlug . '.png';
    $abs = rtrim(JPATH_ROOT, DIRECTORY_SEPARATOR) . $rel;

    if (is_file($abs)) {
        return ['url' => $rel, 'exists' => true];
    }

    return ['url' => '', 'exists' => false];
}

function leInitRange(int $min, int $max): array
{
    $counts = [];
    $lastSeenIndex = [];

    for ($i = $min; $i <= $max; $i++) {
        $key = ($i < 10) ? '0' . $i : (string) $i;
        $counts[$key] = 0;
        $lastSeenIndex[$key] = null;
    }

    return [$counts, $lastSeenIndex];
}

function leInitDigitRange(): array
{
    $counts = [];
    $lastSeenIndex = [];

    for ($i = 0; $i <= 9; $i++) {
        $key = (string) $i;
        $counts[$key] = 0;
        $lastSeenIndex[$key] = null;
    }

    return [$counts, $lastSeenIndex];
}

function leDigitLabels(): array
{
    $labels = [];

    for ($i = 0; $i <= 9; $i++) {
        $labels[] = (string) $i;
    }

    return $labels;
}

function leDrawingsAgoLabel(?int $idx, int $window): array
{
    if ($idx === null) {
        return [$window + 1, 'Not in last ' . $window . ' drws'];
    }

    if ($idx === 0) {
        return [1, 'In last drw'];
    }

    return [$idx + 1, ($idx + 1) . ' drws ago'];
}

function leBuildNaturalLabels(int $min, int $max): array
{
    $labels = [];

    for ($i = $min; $i <= $max; $i++) {
        $labels[] = ($i < 10) ? '0' . $i : (string) $i;
    }

    return $labels;
}

function leTopKeysByValue(array $counts, int $limit, bool $ascending = false): array
{
    $work = $counts;

    if ($ascending) {
        asort($work, SORT_NUMERIC);
    } else {
        arsort($work, SORT_NUMERIC);
    }

    return array_slice(array_keys($work), 0, $limit);
}

function leFindRepeatedFromLatest(array $latestBalls, array $previousRows): array
{
    $repeated = [];

    foreach ($previousRows as $row) {
        $prevBalls = [
            trim((string) ($row['first'] ?? '')),
            trim((string) ($row['second'] ?? '')),
            trim((string) ($row['third'] ?? '')),
            trim((string) ($row['fourth'] ?? '')),
        ];

        foreach ($latestBalls as $ball) {
            if ($ball !== '' && in_array($ball, $prevBalls, true) && !in_array($ball, $repeated, true)) {
                $repeated[] = $ball;
            }
        }
    }

    sort($repeated, SORT_NATURAL);

    return $repeated;
}

function leCommaList(array $items): string
{
    $items = array_values(array_filter(array_map('trim', $items), static function ($v) {
        return $v !== '';
    }));

    if (empty($items)) {
        return '—';
    }

    return implode(', ', $items);
}

function leFetchRecentDraws(\Joomla\Database\DatabaseDriver $db, string $dbCol, string $gameId, int $limit): array
{
    $query = $db->getQuery(true)
        ->select([
            $db->quoteName('id'),
            $db->quoteName('draw_date'),
            $db->quoteName('next_draw_date'),
            $db->quoteName('next_jackpot'),
            $db->quoteName('first'),
            $db->quoteName('second'),
            $db->quoteName('third'),
            $db->quoteName('fourth'),
            $db->quoteName('fifth'),
        ])
        ->from($db->quoteName($dbCol))
        ->where($db->quoteName('game_id') . ' = ' . $db->quote($gameId))
        ->order($db->quoteName('draw_date') . ' DESC');

    $db->setQuery($query, 0, $limit);
    $rows = $db->loadAssocList();

    return is_array($rows) ? $rows : [];
}

/**
 * Fetch bonus-ball rows using the ref2 approach:
 * SELECT *, named parameter binding, ->setLimit() on the query object.
 * This is more reliable in Joomla 5 than $db->quote() + setQuery($q,0,$limit).
 */
function leFetchBonusBallRows(\Joomla\Database\DatabaseDriver $db, string $dbCol, string $gameId, int $limit): array
{
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName($dbCol))
        ->where($db->quoteName('game_id') . ' = :gameId')
        ->order($db->quoteName('draw_date') . ' DESC')
        ->setLimit(max(1, $limit));

    if (is_numeric($gameId) && (string) (int) $gameId === $gameId) {
        $gidInt = (int) $gameId;
        $query->bind(':gameId', $gidInt, \Joomla\Database\ParameterType::INTEGER);
    } else {
        $gidStr = (string) $gameId;
        $query->bind(':gameId', $gidStr, \Joomla\Database\ParameterType::STRING);
    }

    $db->setQuery($query);
    $rows = $db->loadAssocList();

    return is_array($rows) ? $rows : [];
}

function leGetPreviousOccurrenceDate(
    \Joomla\Database\DatabaseDriver $db,
    string $dbCol,
    string $gameId,
    string $drawDate,
    string $ball,
    array $searchColumns
): ?string {
    if ($ball === '' || empty($searchColumns)) {
        return null;
    }

    $query = $db->getQuery(true)
        ->select('MAX(' . $db->quoteName('draw_date') . ')')
        ->from($db->quoteName($dbCol))
        ->where($db->quoteName('game_id') . ' = ' . $db->quote($gameId))
        ->where($db->quoteName('draw_date') . ' < ' . $db->quote($drawDate));

    if (count($searchColumns) === 1) {
        $query->where($db->quoteName($searchColumns[0]) . ' = ' . $db->quote($ball));
    } else {
        $ors = [];
        foreach ($searchColumns as $col) {
            $ors[] = $db->quoteName($col) . ' = ' . $db->quote($ball);
        }
        $query->where('(' . implode(' OR ', $ors) . ')');
    }

    $db->setQuery($query);
    $result = $db->loadResult();

    return $result ? (string) $result : null;
}

function leGetDrawingsSinceDate(
    \Joomla\Database\DatabaseDriver $db,
    string $dbCol,
    string $gameId,
    ?string $previousDate,
    string $currentDate
): ?int {
    if (!$previousDate || !$currentDate) {
        return null;
    }

    $query = $db->getQuery(true)
        ->select('COUNT(' . $db->quoteName('id') . ')')
        ->from($db->quoteName($dbCol))
        ->where($db->quoteName('game_id') . ' = ' . $db->quote($gameId))
        ->where($db->quoteName('draw_date') . ' > ' . $db->quote($previousDate))
        ->where($db->quoteName('draw_date') . ' < ' . $db->quote($currentDate));

    $db->setQuery($query);
    $count = (int) $db->loadResult();

    return $count + 1;
}

function leEscapeJsString(string $value): string
{
    return str_replace(
        ["\\", "'", "\r", "\n", '</'],
        ["\\\\", "\\'", '', '', '<\/'],
        $value
    );
}

/**
 * --------------------------------------------------------------------------
 * Inputs
 * --------------------------------------------------------------------------
 */
$defaultWindowMain = 100;
$defaultWindowStar = 100;

$nodCurrentMain = $defaultWindowMain;
$nodCurrentStar = $defaultWindowStar;

if ($input->getMethod() === 'POST' && Session::checkToken()) {
    if ($input->post->get('fq-search', null, 'cmd') !== null) {
        $nodCurrentMain = (int) $input->post->get('nod', $defaultWindowMain, 'int');
    }

    if ($input->post->get('pfq-search', null, 'cmd') !== null) {
        $nodCurrentStar = (int) $input->post->get('pnod', $defaultWindowStar, 'int');
    }
}

$nodCurrentMain = max(10, min(700, $nodCurrentMain));
$nodCurrentStar = max(10, min(700, $nodCurrentStar));

/**
 * --------------------------------------------------------------------------
 * Fetch latest draw and windows
 * --------------------------------------------------------------------------
 */
$latestRows = leFetchRecentDraws($db, (string) $dbCol, $gameId, 1);
$lr = !empty($latestRows) ? $latestRows[0] : null;

$gId          = $gameId;
$drawDate     = $lr ? (string) ($lr['draw_date'] ?? '') : '';
$nextDrawDate = $lr ? (string) ($lr['next_draw_date'] ?? '') : '';
$nextJackpot  = $lr ? (string) ($lr['next_jackpot'] ?? '') : '';

$p1 = $lr ? trim((string) ($lr['first'] ?? '')) : '';
$p2 = $lr ? trim((string) ($lr['second'] ?? '')) : '';
$p3 = $lr ? trim((string) ($lr['third'] ?? '')) : '';
$p4 = $lr ? trim((string) ($lr['fourth'] ?? '')) : '';
$pb = ''; // will be set after bonus rows are fetched below

$latestMainBalls = [$p1, $p2, $p3, $p4];
$logo = (isset($stateAbrev, $gName)) ? leResolveLogo((string) $stateAbrev, (string) $gName) : ['exists' => false, 'url' => ''];

$rowsMain = leFetchRecentDraws($db, (string) $dbCol, $gameId, $nodCurrentMain);

// Use ref2-style fetch (SELECT *, named param binding, ->setLimit) for bonus rows —
// more reliable in Joomla 5 than the generic leFetchRecentDraws approach.
$rowsBonusSeparate = ($extraBallGameId !== null && $extraBallGameId !== '')
    ? leFetchBonusBallRows($db, (string) $dbCol, (string) $extraBallGameId, $nodCurrentStar)
    : [];

$bonusUseSeparateRows = !empty($rowsBonusSeparate);

if ($bonusUseSeparateRows) {
    $rowsBonus = $rowsBonusSeparate;
    // Per ref2: first try to find the row whose draw_date matches the main draw,
    // then fall back to the most recent row ([0]).
    $pb = '';
    if ($drawDate !== '') {
        foreach ($rowsBonus as $sr) {
            if ((string) ($sr['draw_date'] ?? '') === $drawDate) {
                $pb = trim((string) ($sr['first'] ?? ''));
                break;
            }
        }
    }
    if ($pb === '') {
        $pb = trim((string) ($rowsBonus[0]['first'] ?? ''));
    }
} else {
    // Fallback: extra ball stored in fifth column of main game rows
    $rowsBonus = leFetchRecentDraws($db, (string) $dbCol, $gameId, $nodCurrentStar);
    $pb        = $lr ? trim((string) ($lr['fifth'] ?? '')) : '';
}

[$mainCounts, $mainLastSeenIndex] = leInitDigitRange();
[$starCounts, $starLastSeenIndex] = leInitDigitRange();

foreach ($rowsMain as $idx => $row) {
    $balls = [
        trim((string) ($row['first'] ?? '')),
        trim((string) ($row['second'] ?? '')),
        trim((string) ($row['third'] ?? '')),
        trim((string) ($row['fourth'] ?? '')),
    ];

    foreach ($balls as $ball) {
        if ($ball === '' || !isset($mainCounts[$ball])) {
            continue;
        }

        $mainCounts[$ball]++;

        if ($mainLastSeenIndex[$ball] === null) {
            $mainLastSeenIndex[$ball] = (int) $idx;
        }
    }
}

foreach ($rowsBonus as $idx => $row) {
    // XXXF/XXXW rows store the single extra ball in the 'first' column
    $ball = $bonusUseSeparateRows
        ? trim((string) ($row['first'] ?? ''))
        : trim((string) ($row['fifth'] ?? ''));

    if ($ball === '' || !isset($starCounts[$ball])) {
        continue;
    }

    $starCounts[$ball]++;

    if ($starLastSeenIndex[$ball] === null) {
        $starLastSeenIndex[$ball] = (int) $idx;
    }
}

/**
 * --------------------------------------------------------------------------
 * Insight data
 * --------------------------------------------------------------------------
 */
$mainChartLabels = leDigitLabels();
$mainChartValues = [];
$mainRecencyValues = [];

foreach ($mainChartLabels as $label) {
    $mainChartValues[] = (int) ($mainCounts[$label] ?? 0);
    $mainRecencyValues[] = (int) (($mainLastSeenIndex[$label] ?? null) === null ? ($nodCurrentMain + 1) : ((int) $mainLastSeenIndex[$label] + 1));
}

$starChartLabels = leDigitLabels();
$starChartValues = [];

foreach ($starChartLabels as $label) {
    $starChartValues[] = (int) ($starCounts[$label] ?? 0);
}

$topActiveKeys = leTopKeysByValue($mainCounts, 10, false);
$topQuietKeys  = leTopKeysByValue($mainCounts, 10, true);

$topActiveLabels = $topActiveKeys;
$topActiveValues = [];
foreach ($topActiveKeys as $key) {
    $topActiveValues[] = (int) ($mainCounts[$key] ?? 0);
}

$quietestLabels = [];
$quietestValues = [];
$quietCandidates = [];

foreach ($mainCounts as $key => $count) {
    $quietCandidates[$key] = ($mainLastSeenIndex[$key] === null)
        ? ($nodCurrentMain + 1)
        : ((int) $mainLastSeenIndex[$key] + 1);
}
arsort($quietCandidates, SORT_NUMERIC);
$quietestKeys = array_slice(array_keys($quietCandidates), 0, 10);

foreach ($quietestKeys as $key) {
    $quietestLabels[] = $key;
    $quietestValues[] = (int) $quietCandidates[$key];
}

$repeatedNumbers = leFindRepeatedFromLatest($latestMainBalls, array_slice($rowsMain, 1, 10));
$mostActiveSummary = array_slice($topActiveKeys, 0, 3);
$quietSummary = array_slice($quietestKeys, 0, 3);

$window50 = leFetchRecentDraws($db, (string) $dbCol, $gameId, 50);
$window300 = leFetchRecentDraws($db, (string) $dbCol, $gameId, 300);

[$counts50, ] = leInitDigitRange();
[$counts300, ] = leInitDigitRange();

foreach ($window50 as $row) {
    foreach (['first', 'second', 'third', 'fourth'] as $col) {
        $ball = trim((string) ($row[$col] ?? ''));
        if ($ball !== '' && isset($counts50[$ball])) {
            $counts50[$ball]++;
        }
    }
}

foreach ($window300 as $row) {
    foreach (['first', 'second', 'third', 'fourth'] as $col) {
        $ball = trim((string) ($row[$col] ?? ''));
        if ($ball !== '' && isset($counts300[$ball])) {
            $counts300[$ball]++;
        }
    }
}

$top50 = leTopKeysByValue($counts50, 5, false);
$top300 = leTopKeysByValue($counts300, 5, false);

$windowShiftIn = [];
$windowShiftOut = [];

foreach ($top300 as $number) {
    if (!in_array($number, $top50, true)) {
        $windowShiftIn[] = $number;
    }
}
foreach ($top50 as $number) {
    if (!in_array($number, $top300, true)) {
        $windowShiftOut[] = $number;
    }
}

$windowChangeNarrative = 'In the recent 50-draw view, the leading activity centers on ' . leCommaList(array_slice($top50, 0, 3)) . '. ';
$windowChangeNarrative .= 'In the broader 300-draw view, ' . leCommaList(array_slice($top300, 0, 3)) . ' remains more historically prominent. ';
if (!empty($windowShiftIn)) {
    $windowChangeNarrative .= leCommaList(array_slice($windowShiftIn, 0, 2)) . ' gains prominence when the window broadens. ';
}
if (!empty($windowShiftOut)) {
    $windowChangeNarrative .= leCommaList(array_slice($windowShiftOut, 0, 2)) . ' looks more concentrated in the shorter recent view.';
}

/**
 * --------------------------------------------------------------------------
 * Draw history summary for current draw
 * --------------------------------------------------------------------------
 */
$drawHistoryRows = [];

if ($drawDate !== '') {
    foreach ($latestMainBalls as $ball) {
        $prevDate = leGetPreviousOccurrenceDate($db, (string) $dbCol, $gameId, $drawDate, $ball, ['first', 'second', 'third', 'fourth']);
        $drawsAgo = leGetDrawingsSinceDate($db, (string) $dbCol, $gameId, $prevDate, $drawDate);

        $drawHistoryRows[] = [
            'label'    => $ball,
            'prevDate' => $prevDate,
            'drawsAgo' => $drawsAgo,
            'isBonus'  => false,
        ];
    }

    if ($pb !== '') {
        if ($bonusUseSeparateRows && $extraBallGameId !== null) {
            // XXXF/XXXW rows store the extra ball in the 'first' column
            $prevStarDate = leGetPreviousOccurrenceDate($db, (string) $dbCol, (string) $extraBallGameId, $drawDate, $pb, ['first']);
            $starDrawsAgo = leGetDrawingsSinceDate($db, (string) $dbCol, (string) $extraBallGameId, $prevStarDate, $drawDate);
        } else {
            // Extra ball lives in fifth column of main game rows
            $prevStarDate = leGetPreviousOccurrenceDate($db, (string) $dbCol, $gameId, $drawDate, $pb, ['fifth']);
            $starDrawsAgo = leGetDrawingsSinceDate($db, (string) $dbCol, $gameId, $prevStarDate, $drawDate);
        }
    } else {
        $prevStarDate = null;
        $starDrawsAgo = null;
    }

    $drawHistoryRows[] = [
        'label'    => $extraBallLabel . ' (' . $pb . ')',
        'prevDate' => $prevStarDate,
        'drawsAgo' => $starDrawsAgo,
        'isBonus'  => true,
    ];
}

/**
 * --------------------------------------------------------------------------
 * Copy / CTA hierarchy
 * --------------------------------------------------------------------------
 */
$heroInsight = 'Latest verified draw and recent number behavior at a glance. Review the most active numbers, quiet stretches, and full historical frequency before moving into deeper SKAI analysis.';
$overviewNote = 'Frequency shows historical occurrence within the selected window. It can help identify recent concentration and quiet periods, but it should be interpreted as context rather than prediction.';
?>
<style>
:root{
  --skai-blue:#1C66FF;
  --deep-navy:#0A1A33;
  --sky-gray:#EFEFF5;
  --soft-slate:#7F8DAA;
  --success-green:#20C997;
  --caution-amber:#F5A623;
  --white:#FFFFFF;
  --danger-red:#A61D2D;

  --grad-horizon:linear-gradient(135deg, #0A1A33 0%, #1C66FF 100%);
  --grad-radiant:linear-gradient(135deg, #1C66FF 0%, #7F8DAA 100%);
  --grad-slate:linear-gradient(180deg, #EFEFF5 0%, #FFFFFF 100%);
  --grad-success:linear-gradient(135deg, #20C997 0%, #0A1A33 100%);
  --grad-ember:linear-gradient(135deg, #F5A623 0%, #0A1A33 100%);

  --text:#0A1A33;
  --text-soft:#5F6F8C;
  --line:rgba(10,26,51,.10);
  --line-strong:rgba(10,26,51,.16);
  --shadow-1:0 12px 32px rgba(10,26,51,.08);
  --shadow-2:0 20px 48px rgba(10,26,51,.14);
  --radius-14:14px;
  --radius-18:18px;
  --radius-22:22px;
  --font:Inter, "SF Pro Text", "SF Pro Display", "Helvetica Neue", Arial, sans-serif;
}

*{box-sizing:border-box}

.result-wrapper{display:none !important;}

.skai-page{
  max-width:1180px;
  margin:0 auto;
  padding:20px 14px 32px;
  color:var(--text);
  font-family:var(--font);
}

.skai-page a{
  text-decoration:none;
}

.skai-grid{
  display:grid;
  gap:14px;
}

.skai-hero{
  position:relative;
  overflow:hidden;
  border-radius:var(--radius-22);
  background:
    radial-gradient(900px 420px at -10% -20%, rgba(255,255,255,.13) 0%, rgba(255,255,255,0) 55%),
    radial-gradient(780px 340px at 110% 0%, rgba(255,255,255,.10) 0%, rgba(255,255,255,0) 55%),
    var(--grad-horizon);
  color:#fff;
  box-shadow:var(--shadow-2);
  border:1px solid rgba(255,255,255,.10);
}

.skai-hero-inner{
  padding:22px 20px 18px;
}

.skai-hero-top{
  display:grid;
  grid-template-columns:110px minmax(0,1fr) 280px;
  gap:18px;
  align-items:start;
}

.skai-logo{
  width:110px;
  height:110px;
  border-radius:20px;
  background:rgba(255,255,255,.94);
  display:flex;
  align-items:center;
  justify-content:center;
  box-shadow:0 14px 30px rgba(0,0,0,.16);
  overflow:hidden;
  padding:12px;
}

.skai-logo img{
  width:100%;
  height:100%;
  object-fit:contain;
  display:block;
}

.skai-hero-copy{
  min-width:0;
}

.skai-kicker{
  font-size:12px;
  line-height:1.2;
  letter-spacing:.18em;
  text-transform:uppercase;
  font-weight:800;
  color:rgba(255,255,255,.76);
  margin:2px 0 8px;
}

.skai-title{
  margin:0;
  font-size:30px;
  line-height:1.08;
  font-weight:900;
  letter-spacing:-.02em;
  color:#fff;
}

.skai-hero-summary{
  margin:12px 0 0;
  max-width:68ch;
  font-size:15px;
  line-height:1.65;
  color:rgba(255,255,255,.90);
}

.skai-result-panel{
  background:rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.14);
  border-radius:18px;
  padding:14px;
  backdrop-filter:blur(4px);
}

.skai-panel-label{
  font-size:11px;
  line-height:1.2;
  font-weight:800;
  letter-spacing:.14em;
  text-transform:uppercase;
  color:rgba(255,255,255,.72);
  margin:0 0 10px;
}

.skai-meta-stack{
  display:grid;
  gap:10px;
}

.skai-meta-row{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:10px;
}

.skai-meta-box{
  background:rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.10);
  border-radius:14px;
  padding:10px;
}

.skai-meta-box span{
  display:block;
}

.skai-meta-box .label{
  font-size:11px;
  line-height:1.2;
  font-weight:800;
  letter-spacing:.08em;
  text-transform:uppercase;
  color:rgba(255,255,255,.70);
}

.skai-meta-box .value{
  margin-top:6px;
  font-size:15px;
  line-height:1.35;
  font-weight:850;
  color:#fff;
}

.skai-ball-row{
  display:flex;
  align-items:center;
  flex-wrap:wrap;
  gap:8px;
  margin-top:16px;
}

.skai-ball{
  width:42px;
  height:42px;
  border-radius:999px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  font-size:16px;
  font-weight:900;
  letter-spacing:.02em;
  position:relative;
}

.skai-ball--main{
  background:linear-gradient(180deg, #FFFFFF 0%, #F3F6FF 100%);
  color:var(--deep-navy);
  border:1px solid rgba(10,26,51,.14);
  box-shadow:0 10px 20px rgba(10,26,51,.12), inset 0 1px 0 rgba(255,255,255,.90);
}

.skai-ball--bonus{
  background:radial-gradient(circle at 50% 18%, #C73E4E 0%, #8F1F2D 76%, #4A0911 100%);
  color:#fff;
  border:1px solid rgba(255,255,255,.16);
  box-shadow:0 12px 24px rgba(10,26,51,.18);
}

.skai-ball-gap{
  width:8px;
  height:1px;
}

.skai-hero-actions{
  margin-top:18px;
  display:grid;
  grid-template-columns:1.2fr 1fr 1fr;
  gap:10px;
}

.skai-btn{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  gap:10px;
  border-radius:14px;
  min-height:48px;
  padding:12px 16px;
  font-size:14px;
  line-height:1.2;
  font-weight:850;
  transition:transform .14s ease, box-shadow .14s ease, filter .14s ease;
}

.skai-btn:hover{
  transform:translateY(-1px);
}

.skai-btn:focus,
.skai-btn:focus-visible{
  outline:3px solid rgba(255,255,255,.30);
  outline-offset:3px;
}

.skai-btn--primary{
  background:#fff;
  color:var(--deep-navy);
  box-shadow:0 12px 22px rgba(0,0,0,.14);
}

.skai-btn--secondary{
  background:rgba(255,255,255,.12);
  color:#fff;
  border:1px solid rgba(255,255,255,.18);
}

.skai-advanced-links{
  display:grid;
  grid-template-columns:repeat(3, minmax(0,1fr));
  gap:10px;
  margin-top:10px;
}

.skai-mini-link{
  display:flex;
  align-items:center;
  justify-content:center;
  text-align:center;
  min-height:44px;
  padding:10px 12px;
  border-radius:12px;
  background:rgba(255,255,255,.08);
  border:1px solid rgba(255,255,255,.10);
  color:#fff;
  font-size:13px;
  line-height:1.3;
  font-weight:800;
}

.skai-strip{
  margin-top:14px;
  display:grid;
  grid-template-columns:repeat(4, minmax(0,1fr));
  gap:14px;
}

.skai-stat{
  border-radius:18px;
  overflow:hidden;
  background:var(--grad-slate);
  border:1px solid var(--line);
  box-shadow:var(--shadow-1);
}

.skai-stat-head{
  padding:12px 14px;
  color:#fff;
  font-size:12px;
  line-height:1.25;
  letter-spacing:.12em;
  text-transform:uppercase;
  font-weight:850;
}

.skai-stat-head--horizon{background:var(--grad-horizon)}
.skai-stat-head--radiant{background:var(--grad-radiant)}
.skai-stat-head--success{background:var(--grad-success)}
.skai-stat-head--ember{background:var(--grad-ember)}

.skai-stat-body{
  padding:14px;
  min-height:120px;
  display:flex;
  flex-direction:column;
  justify-content:space-between;
}

.skai-stat-value{
  font-size:24px;
  line-height:1.12;
  font-weight:900;
  letter-spacing:-.02em;
  color:var(--deep-navy);
}

.skai-stat-note{
  margin-top:10px;
  font-size:13px;
  line-height:1.6;
  color:var(--text-soft);
}

.skai-tabs{
  margin-top:18px;
  display:flex;
  flex-wrap:wrap;
  gap:10px;
  padding:6px;
  border-radius:999px;
  background:var(--sky-gray);
  border:1px solid var(--line);
}

.skai-tab{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-height:42px;
  padding:10px 16px;
  border-radius:999px;
  color:var(--deep-navy);
  font-size:13px;
  line-height:1.2;
  font-weight:850;
}

.skai-tab--active{
  background:var(--grad-horizon);
  color:#fff;
  box-shadow:0 10px 20px rgba(10,26,51,.12);
}

.skai-section{
  margin-top:16px;
  background:var(--grad-slate);
  border:1px solid var(--line);
  border-radius:20px;
  box-shadow:var(--shadow-1);
  overflow:hidden;
}

.skai-section-head{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:14px;
  padding:18px 18px 14px;
  border-bottom:1px solid var(--line);
  background:rgba(255,255,255,.55);
}

.skai-section-title{
  margin:0;
  font-size:22px;
  line-height:1.15;
  letter-spacing:-.02em;
  font-weight:900;
  color:var(--deep-navy);
}

.skai-section-sub{
  margin:8px 0 0;
  max-width:76ch;
  font-size:14px;
  line-height:1.65;
  color:var(--text-soft);
}

.skai-section-body{
  padding:16px 18px 18px;
  background:#fff;
}

.skai-overview-grid{
  display:grid;
  grid-template-columns:1.2fr 1fr;
  gap:14px;
}

.skai-overview-grid > *{
  min-width:0;
}

.skai-card{
  background:#fff;
  border:1px solid var(--line);
  border-radius:18px;
  box-shadow:0 10px 24px rgba(10,26,51,.06);
  overflow:hidden;
}

.skai-card-head{
  padding:14px 16px;
  color:#fff;
  font-weight:850;
  font-size:16px;
  line-height:1.25;
}

.skai-card-head--horizon{background:var(--grad-horizon)}
.skai-card-head--radiant{background:var(--grad-radiant)}
.skai-card-head--success{background:var(--grad-success)}
.skai-card-head--ember{background:var(--grad-ember)}

.skai-card-sub{
  display:block;
  margin-top:4px;
  font-size:12px;
  line-height:1.45;
  font-weight:700;
  opacity:.92;
}

.skai-card-body{
  padding:14px 16px 16px;
}

.skai-chart-frame{
  position:relative;
  width:100%;
  height:300px;
  overflow:hidden;
}

.skai-chart-frame--tall{
  height:820px;
}

.skai-note{
  margin-top:14px;
  padding:14px 16px;
  border-radius:16px;
  background:linear-gradient(180deg, #F8FAFE 0%, #FFFFFF 100%);
  border:1px solid var(--line);
  color:var(--text-soft);
  font-size:13px;
  line-height:1.7;
}

.skai-two-col{
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:14px;
}

.skai-two-col > *{
  min-width:0;
}

.skai-history-list{
  display:grid;
  gap:10px;
}

.skai-history-item{
  display:grid;
  grid-template-columns:180px 1fr auto;
  gap:12px;
  align-items:center;
  padding:12px 14px;
  border-radius:14px;
  border:1px solid var(--line);
  background:linear-gradient(180deg, #FFFFFF 0%, #FAFBFF 100%);
}

.skai-history-name{
  font-size:14px;
  line-height:1.35;
  font-weight:850;
  color:var(--deep-navy);
}

.skai-history-date{
  font-size:13px;
  line-height:1.55;
  color:var(--text-soft);
}

.skai-history-badge{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-width:110px;
  min-height:36px;
  padding:8px 12px;
  border-radius:999px;
  background:var(--grad-radiant);
  color:#fff;
  font-size:12px;
  line-height:1.2;
  font-weight:850;
}

.skai-window-shift{
  display:grid;
  gap:12px;
}

.skai-shift-panel{
  border:1px solid var(--line);
  border-radius:16px;
  padding:14px 15px;
  background:linear-gradient(180deg, #FFFFFF 0%, #FAFBFF 100%);
}

.skai-shift-label{
  margin:0 0 8px;
  font-size:12px;
  line-height:1.2;
  letter-spacing:.12em;
  text-transform:uppercase;
  font-weight:850;
  color:var(--soft-slate);
}

.skai-shift-text{
  margin:0;
  font-size:14px;
  line-height:1.7;
  color:var(--text);
}

.skai-controls{
  padding:14px 16px;
  border-bottom:1px solid var(--line);
  background:rgba(255,255,255,.76);
}

.skai-controls form{
  margin:0;
}

.skai-controls-row{
  display:flex;
  flex-wrap:wrap;
  align-items:center;
  justify-content:space-between;
  gap:12px;
}

.skai-controls-left{
  display:flex;
  flex-wrap:wrap;
  align-items:center;
  gap:10px;
}

.skai-controls-right{
  display:flex;
  flex-wrap:wrap;
  align-items:center;
  gap:10px;
}

.skai-controls label{
  font-size:13px;
  line-height:1.2;
  font-weight:850;
  color:var(--deep-navy);
}

.skai-select{
  min-width:122px;
  min-height:44px;
  padding:10px 12px;
  border-radius:12px;
  border:1px solid var(--line-strong);
  background:#fff;
  color:var(--deep-navy);
  font-size:14px;
  line-height:1.2;
  font-weight:800;
}

.skai-button{
  min-height:44px;
  padding:10px 16px;
  border:none;
  border-radius:12px;
  background:var(--grad-horizon);
  color:#fff;
  font-size:13px;
  line-height:1.2;
  font-weight:850;
  cursor:pointer;
  box-shadow:0 10px 20px rgba(10,26,51,.12);
}

.skai-button:hover{
  filter:brightness(1.03);
}

.skai-filter-group{
  display:flex;
  flex-wrap:wrap;
  gap:8px;
}

.skai-filter{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-height:36px;
  padding:8px 12px;
  border-radius:999px;
  border:1px solid var(--line);
  background:#fff;
  color:var(--deep-navy);
  font-size:12px;
  line-height:1.2;
  font-weight:800;
  cursor:pointer;
}

.skai-filter.is-active{
  background:var(--grad-horizon);
  border-color:transparent;
  color:#fff;
}

.skai-table-wrap{
  padding:16px;
  overflow:auto;
}

table.skai-table{
  width:100%;
  min-width:320px;
  border-collapse:separate;
  border-spacing:0;
  background:#fff;
  border:1px solid var(--line);
  border-radius:16px;
  overflow:hidden;
}

table.skai-table thead th{
  position:sticky;
  top:0;
  z-index:1;
  background:var(--grad-horizon);
  color:#fff;
  padding:8px 6px;
  font-size:11px;
  line-height:1.2;
  letter-spacing:.04em;
  text-transform:uppercase;
  font-weight:850;
  text-align:center;
  border-bottom:1px solid rgba(255,255,255,.12);
}

table.skai-table tbody td{
  padding:9px 7px;
  text-align:center;
  border-bottom:1px solid rgba(10,26,51,.06);
  font-size:14px;
  line-height:1.45;
  color:var(--deep-navy);
  vertical-align:middle;
}

table.skai-table tbody tr:hover{
  background:rgba(28,102,255,.04);
}

.skai-pill{
  width:34px;
  height:34px;
  border-radius:999px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  font-size:14px;
  line-height:1;
  font-weight:900;
}

.skai-pill--main{
  background:linear-gradient(180deg, #FFFFFF 0%, #F3F6FF 100%);
  color:var(--deep-navy);
  border:1px solid rgba(10,26,51,.14);
  box-shadow:0 8px 16px rgba(10,26,51,.08);
}

.skai-pill--bonus{
  background:radial-gradient(circle at 50% 18%, #C73E4E 0%, #8F1F2D 76%, #4A0911 100%);
  color:#fff;
  border:1px solid rgba(255,255,255,.14);
}

.skai-checkbox{
  transform:scale(1.25);
  cursor:pointer;
}

.skai-tracked{
  margin-top:14px;
  border:1px solid var(--line);
  border-radius:16px;
  background:var(--grad-slate);
  overflow:hidden;
}

.skai-tracked-head{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:10px;
  padding:12px 14px;
  border-bottom:1px solid var(--line);
}

.skai-tracked-title{
  margin:0;
  font-size:15px;
  line-height:1.2;
  font-weight:850;
  color:var(--deep-navy);
}

.skai-tracked-actions{
  display:flex;
  align-items:center;
  gap:8px;
}

.skai-link-btn{
  border:none;
  background:none;
  color:var(--skai-blue);
  font-size:12px;
  line-height:1.2;
  font-weight:850;
  cursor:pointer;
  padding:0;
}

.skai-chip-wrap{
  padding:12px 14px 14px;
  display:flex;
  flex-wrap:wrap;
  gap:8px;
  min-height:64px;
  align-items:flex-start;
}

.skai-empty{
  font-size:13px;
  line-height:1.6;
  color:var(--text-soft);
}

.skai-chip{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-height:36px;
  padding:8px 12px;
  border-radius:999px;
  font-size:13px;
  line-height:1.2;
  font-weight:850;
}

.skai-chip--main{
  background:linear-gradient(180deg, #FFFFFF 0%, #F3F6FF 100%);
  border:1px solid rgba(10,26,51,.14);
  color:var(--deep-navy);
}

.skai-chip--bonus{
  background:radial-gradient(circle at 50% 18%, #C73E4E 0%, #8F1F2D 76%, #4A0911 100%);
  color:#fff;
  border:1px solid rgba(255,255,255,.14);
}

.skai-tool-grid{
  display:grid;
  grid-template-columns:1.2fr 1fr 1fr;
  gap:14px;
}

.skai-tool{
  border-radius:18px;
  overflow:hidden;
  border:1px solid var(--line);
  background:#fff;
  box-shadow:0 10px 24px rgba(10,26,51,.06);
}

.skai-tool-head{
  padding:14px 16px;
  color:#fff;
  font-size:15px;
  line-height:1.3;
  font-weight:850;
}

.skai-tool-body{
  padding:15px 16px 16px;
}

.skai-tool-copy{
  margin:0 0 14px;
  font-size:14px;
  line-height:1.7;
  color:var(--text-soft);
}

.skai-tool-cta{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  min-height:44px;
  padding:10px 16px;
  border-radius:12px;
  font-size:13px;
  line-height:1.2;
  font-weight:850;
  background:var(--grad-horizon);
  color:#fff;
}

.skai-utility-grid{
  display:grid;
  grid-template-columns:repeat(4, minmax(0,1fr));
  gap:10px;
  margin-top:12px;
}

.skai-utility-link{
  min-height:42px;
  display:flex;
  align-items:center;
  justify-content:center;
  text-align:center;
  padding:10px 12px;
  border-radius:12px;
  border:1px solid var(--line);
  background:var(--grad-slate);
  color:var(--deep-navy);
  font-size:13px;
  line-height:1.3;
  font-weight:850;
}

.skai-method-note{
  padding:16px;
  border-radius:16px;
  border:1px solid var(--line);
  background:linear-gradient(180deg, #FAFBFF 0%, #FFFFFF 100%);
  font-size:14px;
  line-height:1.8;
  color:var(--text-soft);
}

.skai-method-note strong{
  color:var(--deep-navy);
}

@media (max-width:1080px){
  .skai-hero-top{
    grid-template-columns:96px minmax(0,1fr);
  }

  .skai-result-panel{
    grid-column:1 / -1;
  }

  .skai-strip,
  .skai-tool-grid,
  .skai-overview-grid,
  .skai-two-col{
    grid-template-columns:1fr;
  }

  .skai-hero-actions,
  .skai-advanced-links{
    grid-template-columns:1fr;
  }

  .skai-utility-grid{
    grid-template-columns:repeat(2, minmax(0,1fr));
  }
}

@media (max-width:780px){
  .skai-page{
    padding:14px 10px 24px;
  }

  .skai-title{
    font-size:26px;
  }

  .skai-section-head{
    padding:16px 14px 12px;
  }

  .skai-section-body{
    padding:14px;
  }

  .skai-strip{
    grid-template-columns:1fr;
  }

  .skai-history-item{
    grid-template-columns:1fr;
    align-items:start;
  }

  .skai-meta-row{
    grid-template-columns:1fr;
  }

  .skai-tabs{
    border-radius:18px;
  }

  .skai-utility-grid{
    grid-template-columns:1fr 1fr;
  }
}

@media (prefers-reduced-motion: reduce){
  .skai-btn,
  .skai-button{
    transition:none;
  }
}
</style>

<div class="skai-page">

  <section class="skai-hero" aria-label="Results intelligence header">
    <div class="skai-hero-inner">
      <div class="skai-hero-top">
        <div class="skai-logo" aria-hidden="<?php echo $logo['exists'] ? 'false' : 'true'; ?>">
          <?php if ($logo['exists'] && $logo['url'] !== '') : ?>
            <img
              src="<?php echo htmlspecialchars($logo['url'], ENT_QUOTES, 'UTF-8'); ?>"
              alt="<?php echo htmlspecialchars((string) $stateName . ' ' . (string) $gName, ENT_QUOTES, 'UTF-8'); ?>"
              width="110"
              height="110"
              loading="lazy"
              decoding="async"
            >
          <?php else : ?>
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" aria-hidden="true">
              <path d="M12 2l2.7 6.2L21 9l-4.7 4.1L17.6 21 12 17.8 6.4 21l1.3-7.9L3 9l6.3-.8L12 2z" stroke="rgba(10,26,51,.55)" stroke-width="1.6" stroke-linejoin="round"/>
            </svg>
          <?php endif; ?>
        </div>

        <div class="skai-hero-copy">
          <div class="skai-kicker">Results Intelligence &bull; Verified Draw &bull; Calm Analytical View</div>
          <h1 class="skai-title">
            <?php echo htmlspecialchars((string) $stateName, ENT_QUOTES, 'UTF-8'); ?>
            &ndash;
            <?php echo htmlspecialchars((string) $gName, ENT_QUOTES, 'UTF-8'); ?>
          </h1>
          <p class="skai-hero-summary"><?php echo htmlspecialchars($heroInsight, ENT_QUOTES, 'UTF-8'); ?></p>

          <div class="skai-ball-row" aria-label="Latest drawn numbers">
            <span class="skai-ball skai-ball--main"><?php echo htmlspecialchars($p1, ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="skai-ball skai-ball--main"><?php echo htmlspecialchars($p2, ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="skai-ball skai-ball--main"><?php echo htmlspecialchars($p3, ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="skai-ball skai-ball--main"><?php echo htmlspecialchars($p4, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php if ($pb !== '') : ?>
              <span class="skai-ball-gap" aria-hidden="true"></span>
              <span class="skai-ball skai-ball--bonus"><?php echo htmlspecialchars($pb, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
          </div>

          <div class="skai-hero-actions" aria-label="Primary actions">
            <a class="skai-btn skai-btn--primary" href="/picking-winning-numbers/artificial-intelligence/skai-lottery-prediction?gameId=<?php echo rawurlencode((string) $mainGameId); ?>">
              Open SKAI Analysis
            </a>
            <a class="skai-btn skai-btn--secondary" href="/picking-winning-numbers/artificial-intelligence/ai-powered-predictions?game_id=<?php echo rawurlencode((string) $mainGameId); ?>">
              AI Predictions
            </a>
            <a class="skai-btn skai-btn--secondary" href="#frequency-deep-dive">
              View Frequency Deep Dive
            </a>
          </div>

          <div class="skai-advanced-links" aria-label="Advanced tools">
            <a class="skai-mini-link" href="/picking-winning-numbers/artificial-intelligence/skip-and-hit-analysis?game_id=<?php echo rawurlencode((string) $mainGameId); ?>">Skip &amp; Hit Analysis</a>
            <a class="skai-mini-link" href="/picking-winning-numbers/artificial-intelligence/markov-chain-monte-carlo-mcmc-analysis?game_id=<?php echo rawurlencode((string) $mainGameId); ?>">MCMC Markov Analysis</a>
            <a class="skai-mini-link" href="/all-lottery-heatmaps?gId=<?php echo rawurlencode($gId); ?>&amp;stateName=<?php echo rawurlencode((string) $stateName); ?>&amp;gName=<?php echo rawurlencode((string) $gName); ?>&amp;sTn=<?php echo rawurlencode(strtolower((string) $stateAbrev)); ?>">Heatmap Analysis</a>
          </div>
        </div>

        <aside class="skai-result-panel" aria-label="Latest draw details">
          <div class="skai-panel-label">Latest draw summary</div>

          <div class="skai-meta-stack">
            <div class="skai-meta-row">
              <div class="skai-meta-box">
                <span class="label">Draw date</span>
                <span class="value"><?php echo htmlspecialchars(leFmtDateLong($drawDate), ENT_QUOTES, 'UTF-8'); ?></span>
              </div>
              <div class="skai-meta-box">
                <span class="label">Next draw date</span>
                <span class="value"><?php echo htmlspecialchars(leFmtDateLong($nextDrawDate), ENT_QUOTES, 'UTF-8'); ?></span>
              </div>
            </div>

            <div class="skai-meta-box">
              <span class="label">Current perspective</span>
              <span class="value">Recent activity, quiet stretches, and full distribution within your selected analysis window.</span>
            </div>

            <?php if ($nextJackpot !== '' && $nextJackpot !== '0' && $nextJackpot !== 'n/a') : ?>
              <div class="skai-meta-box">
                <span class="label">Next jackpot</span>
                <span class="value">$<?php echo htmlspecialchars(number_format((float) $nextJackpot, 0, '.', ','), ENT_QUOTES, 'UTF-8'); ?></span>
              </div>
            <?php endif; ?>
          </div>
        </aside>
      </div>
    </div>
  </section>

  <section class="skai-strip" aria-label="Key takeaways">
    <article class="skai-stat">
      <div class="skai-stat-head skai-stat-head--horizon">Most active</div>
      <div class="skai-stat-body">
        <div class="skai-stat-value"><?php echo htmlspecialchars(leCommaList($mostActiveSummary), ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="skai-stat-note">Highest appearance counts in the current <?php echo (int) $nodCurrentMain; ?>-draw main-number window.</div>
      </div>
    </article>

    <article class="skai-stat">
      <div class="skai-stat-head skai-stat-head--radiant">Quietest now</div>
      <div class="skai-stat-body">
        <div class="skai-stat-value"><?php echo htmlspecialchars(leCommaList($quietSummary), ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="skai-stat-note">Numbers currently sitting furthest from their most recent appearance in the selected window.</div>
      </div>
    </article>

    <article class="skai-stat">
      <div class="skai-stat-head skai-stat-head--success">Repeated recently</div>
      <div class="skai-stat-body">
        <div class="skai-stat-value"><?php echo htmlspecialchars(!empty($repeatedNumbers) ? leCommaList($repeatedNumbers) : 'None', ENT_QUOTES, 'UTF-8'); ?></div>
        <div class="skai-stat-note">Main numbers from the latest draw that also appeared in the recent trailing draws.</div>
      </div>
    </article>

    <article class="skai-stat">
      <div class="skai-stat-head skai-stat-head--ember">Window analyzed</div>
      <div class="skai-stat-body">
        <div class="skai-stat-value"><?php echo (int) $nodCurrentMain; ?> / <?php echo (int) $nodCurrentStar; ?></div>
        <div class="skai-stat-note">Main-number and <?php echo htmlspecialchars($extraBallLabel, ENT_QUOTES, 'UTF-8'); ?> draw windows currently loaded for this page view.</div>
      </div>
    </article>
  </section>

  <nav class="skai-tabs" aria-label="Page navigation">
    <a class="skai-tab skai-tab--active" href="#overview">Overview</a>
    <a class="skai-tab" href="#frequency-deep-dive">Frequency</a>
    <a class="skai-tab" href="#recency-deep-dive">Recency</a>
    <a class="skai-tab" href="#tables">Tables</a>
    <a class="skai-tab" href="#tools">Advanced Tools</a>
  </nav>

  <section id="overview" class="skai-section" aria-labelledby="overview-title">
    <div class="skai-section-head">
      <div>
        <h2 id="overview-title" class="skai-section-title">Overview</h2>
        <p class="skai-section-sub">
          Start with a clear high-level view. This layer is designed for fast orientation: which numbers are most active, which are quiet, and how the current draw relates to recent history.
        </p>
      </div>
    </div>

    <div class="skai-section-body">
      <div class="skai-overview-grid">
        <div class="skai-card">
          <div class="skai-card-head skai-card-head--horizon">
            Top active numbers
            <span class="skai-card-sub">Highest frequency counts in the last <?php echo (int) $nodCurrentMain; ?> drawings</span>
          </div>
          <div class="skai-card-body">
            <div class="skai-chart-frame">
              <canvas id="topActiveChart" aria-label="Top active numbers chart" role="img"></canvas>
            </div>
          </div>
        </div>

        <div class="skai-card">
          <div class="skai-card-head skai-card-head--ember">
            Quiet stretches
            <span class="skai-card-sub">Numbers with the longest distance from their last appearance</span>
          </div>
          <div class="skai-card-body">
            <div class="skai-chart-frame">
              <canvas id="quietChart" aria-label="Quiet numbers chart" role="img"></canvas>
            </div>
          </div>
        </div>
      </div>

      <div class="skai-note">
        <?php echo htmlspecialchars($overviewNote, ENT_QUOTES, 'UTF-8'); ?>
      </div>
    </div>
  </section>

  <section id="recency-deep-dive" class="skai-section" aria-labelledby="recency-title">
    <div class="skai-section-head">
      <div>
        <h2 id="recency-title" class="skai-section-title">Recency and draw context</h2>
        <p class="skai-section-sub">
          This section makes the current draw easier to interpret. It shows how recently each latest number was previously seen and how the perspective shifts when you compare a short recent window with a broader historical one.
        </p>
      </div>
    </div>

    <div class="skai-section-body">
      <div class="skai-two-col">
        <div class="skai-card">
          <div class="skai-card-head skai-card-head--radiant">
            Current draw history
            <span class="skai-card-sub">Previous appearance date and spacing for each latest drawn value</span>
          </div>
          <div class="skai-card-body">
            <div class="skai-history-list">
              <?php foreach ($drawHistoryRows as $row) : ?>
                <div class="skai-history-item">
                  <div class="skai-history-name"><?php echo htmlspecialchars((string) $row['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                  <div class="skai-history-date">
                    <?php if (!empty($row['prevDate'])) : ?>
                      Previously seen on <?php echo htmlspecialchars(leFmtDateLong((string) $row['prevDate']), ENT_QUOTES, 'UTF-8'); ?>
                    <?php else : ?>
                      No previous appearance found in the loaded historical set
                    <?php endif; ?>
                  </div>
                  <div class="skai-history-badge">
                    <?php echo ($row['drawsAgo'] !== null) ? (int) $row['drawsAgo'] . ' drws ago' : '—'; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="skai-card">
          <div class="skai-card-head skai-card-head--success">
            What changes with the window
            <span class="skai-card-sub">Comparing shorter recent behavior with broader historical behavior</span>
          </div>
          <div class="skai-card-body">
            <div class="skai-window-shift">
              <div class="skai-shift-panel">
                <p class="skai-shift-label">Window shift note</p>
                <p class="skai-shift-text"><?php echo htmlspecialchars($windowChangeNarrative, ENT_QUOTES, 'UTF-8'); ?></p>
              </div>

              <div class="skai-shift-panel">
                <p class="skai-shift-label">Recent 50-draw leaders</p>
                <p class="skai-shift-text"><?php echo htmlspecialchars(leCommaList(array_slice($top50, 0, 5)), ENT_QUOTES, 'UTF-8'); ?></p>
              </div>

              <div class="skai-shift-panel">
                <p class="skai-shift-label">Broader 300-draw leaders</p>
                <p class="skai-shift-text"><?php echo htmlspecialchars(leCommaList(array_slice($top300, 0, 5)), ENT_QUOTES, 'UTF-8'); ?></p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="frequency-deep-dive" class="skai-section" aria-labelledby="frequency-title">
    <div class="skai-section-head">
      <div>
        <h2 id="frequency-title" class="skai-section-title">Frequency deep dive</h2>
        <p class="skai-section-sub">
          Move from summary to full reference. The first panel shows the complete main-digit distribution across digits 0&ndash;9. The second panel shows the complete <?php echo htmlspecialchars($extraBallLabel, ENT_QUOTES, 'UTF-8'); ?> distribution for the selected bonus window.
        </p>
      </div>
    </div>

    <div class="skai-section-body">
      <div class="skai-two-col">
        <div class="skai-card">
          <div class="skai-card-head skai-card-head--horizon">
            Full main-digit distribution
            <span class="skai-card-sub">All digits 0&ndash;9 across the last <?php echo (int) $nodCurrentMain; ?> drawings</span>
          </div>
          <div class="skai-card-body">
            <div class="skai-chart-frame">
              <canvas id="fullMainChart" aria-label="Full main digit distribution chart" role="img"></canvas>
            </div>
          </div>
        </div>

        <div class="skai-grid">
          <div class="skai-card">
            <div class="skai-card-head skai-card-head--radiant">
              Full <?php echo htmlspecialchars($extraBallLabel, ENT_QUOTES, 'UTF-8'); ?> distribution
              <span class="skai-card-sub">All digits 0&ndash;9 across the last <?php echo (int) $nodCurrentStar; ?> drawings</span>
            </div>
            <div class="skai-card-body">
              <div class="skai-chart-frame">
                <canvas id="starChart" aria-label="<?php echo htmlspecialchars($extraBallLabel, ENT_QUOTES, 'UTF-8'); ?> distribution chart" role="img"></canvas>
              </div>
            </div>
          </div>

          <div class="skai-card">
            <div class="skai-card-head skai-card-head--ember">
              Recency distribution
              <span class="skai-card-sub">Distance since last appearance for each main number</span>
            </div>
            <div class="skai-card-body">
              <div class="skai-chart-frame">
                <canvas id="recencyChart" aria-label="Main numbers recency chart" role="img"></canvas>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="skai-note">
        The complete distribution is best used as a reference layer. The overview modules above are optimized for quick reading; this section is optimized for thorough review.
      </div>
    </div>
  </section>

  <section id="tables" class="skai-section" aria-labelledby="tables-title">
    <div class="skai-section-head">
      <div>
        <h2 id="tables-title" class="skai-section-title">Tables and tracked numbers</h2>
        <p class="skai-section-sub">
          Use the tables for exact counts, recency, and personal tracking. Quick filters help narrow the view without losing full access to the complete dataset.
        </p>
      </div>
    </div>

    <div class="skai-controls">
      <form name="fqsearch" method="post" action="/all-us-lotteries/results-analysis?st=<?php echo htmlspecialchars((string) $stateAbrev, ENT_QUOTES, 'UTF-8'); ?>&amp;stn=<?php echo htmlspecialchars((string) $stateName, ENT_QUOTES, 'UTF-8'); ?>&amp;gm=<?php echo htmlspecialchars((string) $gName, ENT_QUOTES, 'UTF-8'); ?>#tables">
        <div class="skai-controls-row">
          <div class="skai-controls-left">
            <label for="nod">Main-number draw window</label>
            <select name="nod" id="nod" class="skai-select">
              <?php foreach (range(10, 700, 5) as $opt) : ?>
                <option value="<?php echo (int) $opt; ?>"<?php echo ((int) $opt === (int) $nodCurrentMain) ? ' selected="selected"' : ''; ?>>
                  <?php echo (int) $opt; ?>
                </option>
              <?php endforeach; ?>
            </select>

            <label for="pnod"><?php echo htmlspecialchars($extraBallLabel, ENT_QUOTES, 'UTF-8'); ?> draw window</label>
            <select name="pnod" id="pnod" class="skai-select">
              <?php foreach (range(10, 700, 5) as $opt) : ?>
                <option value="<?php echo (int) $opt; ?>"<?php echo ((int) $opt === (int) $nodCurrentStar) ? ' selected="selected"' : ''; ?>>
                  <?php echo (int) $opt; ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="skai-controls-right">
            <button class="skai-button" name="fq-search" type="submit" value="1">Update analysis windows</button>
            <input type="hidden" name="pfq-search" value="1">
            <?php echo HTMLHelper::_('form.token'); ?>
          </div>
        </div>
      </form>
    </div>

    <div class="skai-section-body">
      <div class="skai-two-col">
        <div class="skai-card">
          <div class="skai-card-head skai-card-head--horizon">
            Main numbers table
            <span class="skai-card-sub">All digits 0&ndash;9 — frequency across all four positions</span>
          </div>

          <div class="skai-controls">
            <div class="skai-controls-row">
              <div class="skai-controls-left">
                <div class="skai-filter-group" data-filter-group="main">
                  <button class="skai-filter is-active" type="button" data-filter="all">All</button>
                  <button class="skai-filter" type="button" data-filter="active">Most active</button>
                  <button class="skai-filter" type="button" data-filter="quiet">Quietest</button>
                  <button class="skai-filter" type="button" data-filter="recent">Recently seen</button>
                </div>
              </div>
            </div>
          </div>

          <div class="skai-table-wrap">
            <table id="skai-main-table" class="skai-table" aria-label="Main numbers frequency table">
              <thead>
                <tr>
                  <th>Number</th>
                  <th>Drawn Times</th>
                  <th>Last Drawn</th>
                  <th>Track</th>
                </tr>
              </thead>
              <tbody>
                <?php for ($i = 0; $i <= 9; $i++) : ?>
                  <?php
                  $number = (string) $i;
                  $countNumber = (int) ($mainCounts[$number] ?? 0);
                  [$lastDrawSort, $lastDrawLabel] = leDrawingsAgoLabel($mainLastSeenIndex[$number] ?? null, (int) $nodCurrentMain);

                  $rowClass = 'all';
                  if (in_array($number, $topActiveKeys, true)) {
                      $rowClass .= ' active';
                  }
                  if (in_array($number, $quietestKeys, true)) {
                      $rowClass .= ' quiet';
                  }
                  if (($mainLastSeenIndex[$number] ?? null) !== null && (int) $mainLastSeenIndex[$number] <= 4) {
                      $rowClass .= ' recent';
                  }
                  ?>
                  <tr data-tags="<?php echo htmlspecialchars($rowClass, ENT_QUOTES, 'UTF-8'); ?>">
                    <td><span class="skai-pill skai-pill--main"><?php echo htmlspecialchars($number, ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td><?php echo (int) $countNumber; ?> X</td>
                    <td data-sort="<?php echo (int) $lastDrawSort; ?>"><?php echo htmlspecialchars($lastDrawLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                      <input
                        class="skai-checkbox js-track-main"
                        type="checkbox"
                        value="<?php echo htmlspecialchars($number, ENT_QUOTES, 'UTF-8'); ?>"
                        aria-label="Track main number <?php echo htmlspecialchars($number, ENT_QUOTES, 'UTF-8'); ?>"
                      >
                    </td>
                  </tr>
                <?php endfor; ?>
              </tbody>
            </table>
          </div>

          <div class="skai-tracked">
            <div class="skai-tracked-head">
              <h3 class="skai-tracked-title">Tracked main numbers</h3>
              <div class="skai-tracked-actions">
                <button class="skai-link-btn" type="button" id="clearMainTracked">Clear all</button>
              </div>
            </div>
            <div class="skai-chip-wrap" id="mainTrackedWrap">
              <div class="skai-empty">Select numbers to create a short tracked set for comparison across this page.</div>
            </div>
          </div>
        </div>

        <div class="skai-card">
          <div class="skai-card-head skai-card-head--radiant">
            <?php echo htmlspecialchars($extraBallLabel, ENT_QUOTES, 'UTF-8'); ?> table
            <span class="skai-card-sub">Exact counts and recency for digits 0&ndash;9</span>
          </div>

          <div class="skai-table-wrap">
            <table id="skai-star-table" class="skai-table" aria-label="<?php echo htmlspecialchars($extraBallLabel, ENT_QUOTES, 'UTF-8'); ?> frequency table">
              <thead>
                <tr>
                  <th>Number</th>
                  <th>Drawn Times</th>
                  <th>Last Drawn</th>
                  <th>Track</th>
                </tr>
              </thead>
              <tbody>
                <?php for ($i = 0; $i <= 9; $i++) : ?>
                  <?php
                  $number = (string) $i;
                  $countNumber = (int) ($starCounts[$number] ?? 0);
                  [$lastDrawSort, $lastDrawLabel] = leDrawingsAgoLabel($starLastSeenIndex[$number] ?? null, (int) $nodCurrentStar);
                  ?>
                  <tr>
                    <td><span class="skai-pill skai-pill--bonus"><?php echo htmlspecialchars($number, ENT_QUOTES, 'UTF-8'); ?></span></td>
                    <td><?php echo (int) $countNumber; ?> X</td>
                    <td data-sort="<?php echo (int) $lastDrawSort; ?>"><?php echo htmlspecialchars($lastDrawLabel, ENT_QUOTES, 'UTF-8'); ?></td>
                    <td>
                      <input
                        class="skai-checkbox js-track-bonus"
                        type="checkbox"
                        value="<?php echo htmlspecialchars($number, ENT_QUOTES, 'UTF-8'); ?>"
                        aria-label="Track <?php echo htmlspecialchars($extraBallLabel, ENT_QUOTES, 'UTF-8'); ?> number <?php echo htmlspecialchars($number, ENT_QUOTES, 'UTF-8'); ?>"
                      >
                    </td>
                  </tr>
                <?php endfor; ?>
              </tbody>
            </table>
          </div>

          <div class="skai-tracked">
            <div class="skai-tracked-head">
              <h3 class="skai-tracked-title">Tracked <?php echo htmlspecialchars($extraBallLabel, ENT_QUOTES, 'UTF-8'); ?> values</h3>
              <div class="skai-tracked-actions">
                <button class="skai-link-btn" type="button" id="clearBonusTracked">Clear all</button>
              </div>
            </div>
            <div class="skai-chip-wrap" id="bonusTrackedWrap">
              <div class="skai-empty">Use tracking to keep a small working set visible while you compare modules.</div>
            </div>
          </div>

          <div class="skai-note">
            Tracking is local to this page view. It is intended as a lightweight comparison aid while you move between the overview, tables, and advanced SKAI tools.
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="tools" class="skai-section" aria-labelledby="tools-title">
    <div class="skai-section-head">
      <div>
        <h2 id="tools-title" class="skai-section-title">Next steps and advanced tools</h2>
        <p class="skai-section-sub">
          The results page establishes context. These tools take that context into deeper modeling and structured exploration.
        </p>
      </div>
    </div>

    <div class="skai-section-body">
      <div class="skai-tool-grid">
        <article class="skai-tool">
          <div class="skai-tool-head skai-card-head--horizon">SKAI Analysis</div>
          <div class="skai-tool-body">
            <p class="skai-tool-copy">
              Best next step for a broader multi-signal view. Use this after reviewing frequency and recency to move into the main SKAI intelligence workflow.
            </p>
            <a class="skai-tool-cta" href="/picking-winning-numbers/artificial-intelligence/skai-lottery-prediction?gameId=<?php echo rawurlencode((string) $mainGameId); ?>">Open SKAI Analysis</a>
          </div>
        </article>

        <article class="skai-tool">
          <div class="skai-tool-head skai-card-head--radiant">AI Predictions</div>
          <div class="skai-tool-body">
            <p class="skai-tool-copy">
              Use when you want a model-driven complement to the historical view shown on this page.
            </p>
            <a class="skai-tool-cta" href="/picking-winning-numbers/artificial-intelligence/ai-powered-predictions?game_id=<?php echo rawurlencode((string) $mainGameId); ?>">Open AI Predictions</a>
          </div>
        </article>

        <article class="skai-tool">
          <div class="skai-tool-head skai-card-head--success">Skip &amp; Hit Analysis</div>
          <div class="skai-tool-body">
            <p class="skai-tool-copy">
              Useful for users who want to compare appearance spacing and interruption behavior after reviewing current frequency.
            </p>
            <a class="skai-tool-cta" href="/picking-winning-numbers/artificial-intelligence/skip-and-hit-analysis?game_id=<?php echo rawurlencode((string) $mainGameId); ?>">Open Skip &amp; Hit</a>
          </div>
        </article>
      </div>

      <div class="skai-utility-grid">
        <a class="skai-utility-link" href="/picking-winning-numbers/artificial-intelligence/markov-chain-monte-carlo-mcmc-analysis?game_id=<?php echo rawurlencode((string) $mainGameId); ?>">MCMC Markov Analysis</a>
        <a class="skai-utility-link" href="/all-lottery-heatmaps?gId=<?php echo rawurlencode($gId); ?>&amp;stateName=<?php echo rawurlencode((string) $stateName); ?>&amp;gName=<?php echo rawurlencode((string) $gName); ?>&amp;sTn=<?php echo rawurlencode(strtolower((string) $stateAbrev)); ?>">Heatmap Analysis</a>
        <a class="skai-utility-link" href="/lottery-archives?gId=<?php echo rawurlencode($gId); ?>&amp;stateName=<?php echo rawurlencode((string) $stateName); ?>&amp;gName=<?php echo rawurlencode((string) $gName); ?>&amp;sTn=<?php echo rawurlencode(strtolower((string) $stateAbrev)); ?>">Lottery Archives</a>
        <a class="skai-utility-link" href="/lowest-drawn-number-analysis?gId=<?php echo rawurlencode($gId); ?>&amp;stateName=<?php echo rawurlencode((string) $stateName); ?>&amp;gName=<?php echo rawurlencode((string) $gName); ?>&amp;sTn=<?php echo rawurlencode(strtolower((string) $stateAbrev)); ?>">Lowest Number Analysis</a>
      </div>
    </div>
  </section>

  <section class="skai-section" aria-labelledby="method-title">
    <div class="skai-section-head">
      <div>
        <h2 id="method-title" class="skai-section-title">Method note</h2>
        <p class="skai-section-sub">
          This page is designed to help users understand recent and historical behavior more clearly, not to imply certainty.
        </p>
      </div>
    </div>

    <div class="skai-section-body">
      <div class="skai-method-note">
        <strong>Interpretation guidance:</strong> Frequency, recency, and spacing can provide useful context for reviewing draw history, but they should be treated as descriptive signals rather than guarantees. The purpose of this page is to make the recent behavior of the game easier to understand, compare, and carry into deeper SKAI analysis.
      </div>
    </div>
  </section>
</div>

<script type="text/javascript">
(function () {
  'use strict';

  var chartData = {
    topActiveLabels: <?php echo json_encode(array_values($topActiveLabels)); ?>,
    topActiveValues: <?php echo json_encode(array_values($topActiveValues)); ?>,
    quietLabels: <?php echo json_encode(array_values($quietestLabels)); ?>,
    quietValues: <?php echo json_encode(array_values($quietestValues)); ?>,
    mainLabels: <?php echo json_encode(array_values($mainChartLabels)); ?>,
    mainValues: <?php echo json_encode(array_values($mainChartValues)); ?>,
    mainRecencyValues: <?php echo json_encode(array_values($mainRecencyValues)); ?>,
    starLabels: <?php echo json_encode(array_values($starChartLabels)); ?>,
    starValues: <?php echo json_encode(array_values($starChartValues)); ?>
  };

  function loadChartJsIfNeeded(done) {
    if (window.Chart) {
      done();
      return;
    }

    var cdnUrl = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
    var integrity = 'sha384-OLBgp1GsljhM2TJ+sbHjaiH9txEUvgdDTAzHv2P24donTt6/529l+9Ua0vFImLlb';

    function tryLoad(withIntegrity) {
      var script = document.createElement('script');
      script.src = cdnUrl;
      if (withIntegrity) {
        script.integrity = integrity;
        script.crossOrigin = 'anonymous';
      }
      script.async = true;
      script.onload = function () {
        done();
      };
      script.onerror = function () {
        if (withIntegrity) {
          tryLoad(false);
        } else {
          done();
        }
      };
      document.head.appendChild(script);
    }

    tryLoad(true);
  }

  function commonBarOptions(horizontal) {
    return {
      responsive: true,
      maintainAspectRatio: false,
      indexAxis: horizontal ? 'y' : 'x',
      animation: false,
      plugins: {
        legend: { display: false },
        tooltip: { enabled: true }
      },
      scales: horizontal ? {
        x: {
          beginAtZero: true,
          ticks: { precision: 0, font: { weight: '700' } },
          grid: { color: 'rgba(10,26,51,.08)' }
        },
        y: {
          ticks: { autoSkip: false, font: { weight: '700' } },
          grid: { display: false }
        }
      } : {
        x: {
          ticks: { font: { weight: '700' } },
          grid: { display: false }
        },
        y: {
          beginAtZero: true,
          ticks: { precision: 0, font: { weight: '700' } },
          grid: { color: 'rgba(10,26,51,.08)' }
        }
      }
    };
  }

  function renderCharts() {
    if (!window.Chart) {
      return;
    }

    var topActiveCanvas = document.getElementById('topActiveChart');
    var quietCanvas = document.getElementById('quietChart');
    var fullMainCanvas = document.getElementById('fullMainChart');
    var starCanvas = document.getElementById('starChart');
    var recencyCanvas = document.getElementById('recencyChart');

    if (topActiveCanvas) {
      new Chart(topActiveCanvas.getContext('2d'), {
        type: 'bar',
        data: {
          labels: chartData.topActiveLabels,
          datasets: [{
            data: chartData.topActiveValues,
            borderWidth: 0,
            borderRadius: 8,
            backgroundColor: '#1C66FF'
          }]
        },
        options: commonBarOptions(false)
      });
    }

    if (quietCanvas) {
      new Chart(quietCanvas.getContext('2d'), {
        type: 'bar',
        data: {
          labels: chartData.quietLabels,
          datasets: [{
            data: chartData.quietValues,
            borderWidth: 0,
            borderRadius: 8,
            backgroundColor: '#F5A623'
          }]
        },
        options: commonBarOptions(false)
      });
    }

    if (fullMainCanvas) {
      new Chart(fullMainCanvas.getContext('2d'), {
        type: 'bar',
        data: {
          labels: chartData.mainLabels,
          datasets: [{
            data: chartData.mainValues,
            borderWidth: 0,
            borderRadius: 6,
            barThickness: 10,
            maxBarThickness: 12,
            backgroundColor: '#1C66FF'
          }]
        },
        options: commonBarOptions(true)
      });
    }

    if (starCanvas) {
      new Chart(starCanvas.getContext('2d'), {
        type: 'bar',
        data: {
          labels: chartData.starLabels,
          datasets: [{
            data: chartData.starValues,
            borderWidth: 0,
            borderRadius: 8,
            backgroundColor: '#8F1F2D'
          }]
        },
        options: commonBarOptions(false)
      });
    }

    if (recencyCanvas) {
      new Chart(recencyCanvas.getContext('2d'), {
        type: 'bar',
        data: {
          labels: chartData.mainLabels,
          datasets: [{
            data: chartData.mainRecencyValues,
            borderWidth: 0,
            borderRadius: 6,
            backgroundColor: '#F5A623'
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: false,
          plugins: {
            legend: { display: false },
            tooltip: { enabled: true }
          },
          scales: {
            x: {
              ticks: {
                maxRotation: 0,
                autoSkip: true,
                maxTicksLimit: 10,
                font: { weight: '700' }
              },
              grid: { display: false }
            },
            y: {
              beginAtZero: true,
              ticks: {
                precision: 0,
                font: { weight: '700' }
              },
              grid: { color: 'rgba(10,26,51,.08)' }
            }
          }
        }
      });
    }
  }

  function bindTrackers() {
    var mainWrap = document.getElementById('mainTrackedWrap');
    var bonusWrap = document.getElementById('bonusTrackedWrap');
    var clearMain = document.getElementById('clearMainTracked');
    var clearBonus = document.getElementById('clearBonusTracked');

    function renderTracked(selector, wrap, chipClass, emptyText) {
      var inputs = document.querySelectorAll(selector);
      var items = [];

      for (var i = 0; i < inputs.length; i++) {
        if (inputs[i].checked) {
          items.push(inputs[i].value);
        }
      }

      wrap.innerHTML = '';

      if (!items.length) {
        var empty = document.createElement('div');
        empty.className = 'skai-empty';
        empty.textContent = emptyText;
        wrap.appendChild(empty);
        return;
      }

      for (var j = 0; j < items.length; j++) {
        var chip = document.createElement('span');
        chip.className = 'skai-chip ' + chipClass;
        chip.textContent = items[j];
        wrap.appendChild(chip);
      }
    }

    function bindGroup(selector, wrap, chipClass, emptyText) {
      var inputs = document.querySelectorAll(selector);

      for (var i = 0; i < inputs.length; i++) {
        inputs[i].addEventListener('change', function () {
          renderTracked(selector, wrap, chipClass, emptyText);
        });
      }

      renderTracked(selector, wrap, chipClass, emptyText);
    }

    bindGroup('.js-track-main', mainWrap, 'skai-chip--main', 'Select numbers to create a short tracked set for comparison across this page.');
    bindGroup('.js-track-bonus', bonusWrap, 'skai-chip--bonus', 'Use tracking to keep a small working set visible while you compare modules.');

    if (clearMain) {
      clearMain.addEventListener('click', function () {
        var inputs = document.querySelectorAll('.js-track-main');
        for (var i = 0; i < inputs.length; i++) {
          inputs[i].checked = false;
        }
        renderTracked('.js-track-main', mainWrap, 'skai-chip--main', 'Select numbers to create a short tracked set for comparison across this page.');
      });
    }

    if (clearBonus) {
      clearBonus.addEventListener('click', function () {
        var inputs = document.querySelectorAll('.js-track-bonus');
        for (var i = 0; i < inputs.length; i++) {
          inputs[i].checked = false;
        }
        renderTracked('.js-track-bonus', bonusWrap, 'skai-chip--bonus', 'Use tracking to keep a small working set visible while you compare modules.');
      });
    }
  }

  function bindFilters() {
    var group = document.querySelector('[data-filter-group="main"]');
    var table = document.getElementById('skai-main-table');

    if (!group || !table) {
      return;
    }

    var buttons = group.querySelectorAll('.skai-filter');
    var rows = table.querySelectorAll('tbody tr');

    function applyFilter(filter) {
      for (var i = 0; i < rows.length; i++) {
        var tags = rows[i].getAttribute('data-tags') || '';
        if (filter === 'all' || tags.indexOf(filter) !== -1) {
          rows[i].style.display = '';
        } else {
          rows[i].style.display = 'none';
        }
      }

      for (var j = 0; j < buttons.length; j++) {
        buttons[j].classList.remove('is-active');
        if (buttons[j].getAttribute('data-filter') === filter) {
          buttons[j].classList.add('is-active');
        }
      }
    }

    for (var k = 0; k < buttons.length; k++) {
      buttons[k].addEventListener('click', function () {
        applyFilter(this.getAttribute('data-filter'));
      });
    }
  }

  function initAnchors() {
    var tabs = document.querySelectorAll('.skai-tab');
    if (!tabs.length) {
      return;
    }

    for (var i = 0; i < tabs.length; i++) {
      tabs[i].addEventListener('click', function () {
        for (var j = 0; j < tabs.length; j++) {
          tabs[j].classList.remove('skai-tab--active');
        }
        this.classList.add('skai-tab--active');
      });
    }
  }

  function init() {
    bindTrackers();
    bindFilters();
    initAnchors();
    loadChartJsIfNeeded(renderCharts);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>
