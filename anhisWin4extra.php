<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Session\Session;

// --- SEO: canonical + hreflang via Joomla Document (more reliable than echoing raw tags) ---
$app = Factory::getApplication();
$doc = Factory::getDocument();

// Canonical URL (current URL normalized through Uri)
$canonicalUrl = Uri::getInstance()->toString();
$doc->addCustomTag('<link rel="canonical" href="' . htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') . '" />');

// Hreflang: English + x-default (site-wide consistent)
$pathWithQuery = Uri::getInstance()->toString(['path', 'query']);
$href = 'https://lottoexpert.net' . $pathWithQuery;

$doc->addCustomTag('<link rel="alternate" hreflang="en" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" />');
$doc->addCustomTag('<link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" />');

// Get user information (Joomla 5+)
$user = Factory::getUser();
$loginStatus = (int) $user->guest;

if ($loginStatus == 1) {
    $get_session  = Factory::getSession(); // CHG: Joomla 5+ (no JFactory)
    $user_Session = $get_session->getId();
} else {
    // Securely get user phone using prepared statements
    $userId = (int) $user->get('id');
    $db     = Factory::getDbo();

    $query = $db->getQuery(true)
        ->select($db->quoteName('profile_value'))
        ->from($db->quoteName('#__user_profiles'))
        ->where($db->quoteName('profile_key') . ' = ' . $db->quote('profile.phone'))
        ->where($db->quoteName('user_id') . ' = :userId');
    $query->bind(':userId', $userId, \Joomla\Database\ParameterType::INTEGER);
    $db->setQuery($query);
    $userPhone = $db->loadResult();

    if (!empty($userPhone)) {
        $userPhone = str_replace(['"', '(', ')'], ['', '', '-'], $userPhone);
    } else {
        $userPhone = 'NULL';
    }
}

/** GET VARIABLES FROM URL WITH VALIDATION (supports legacy params) **/
$input = $app->input;

$gmCodeRaw = (string) $input->getString('gmCode', '');
if ($gmCodeRaw === '') {
    $gmCodeRaw = (string) $input->getString('game_id', '');
}
if ($gmCodeRaw === '') {
    $gmCodeRaw = (string) $input->getString('gid', '');
}

// Normalize (trim + NBSP cleanup + upper)
$gmCodeRaw = str_replace("\xC2\xA0", ' ', $gmCodeRaw);
$gmCode    = strtoupper(trim($gmCodeRaw));

if ($gmCode === '') {
    http_response_code(400);
    echo 'Game code is required.';
    return;
}

// Legacy set includes 3-char IDs (FLA) and 4-char IDs (FLAF/CTAW), plus numeric (120/121).
// Permit 2-5 to avoid breaking variants while still strict alnum.
if (!preg_match('/^[A-Z0-9]{2,5}$/', $gmCode)) {
    http_response_code(400);
    echo 'Invalid game code format.';
    return;
}

// Use the sanitized gmCode
$gId = $gmCode;

// Game table mapping
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

// Consolidated game info mapping
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

// Validate the game ID and get the corresponding table name
if (!array_key_exists($gId, $gameTableMap)) {
    die('Invalid game ID.');
}
$dbCol = $gameTableMap[$gId]; // Get the table name for the selected game ID

// Determine main game ID and extra ball game ID
$gameFound = false;
foreach ($gameInfoMap as $key => $gameInfo) {
    if ($gameInfo['mainGameId'] === $gId || (isset($gameInfo['extraBallGameId']) && $gameInfo['extraBallGameId'] === $gId)) {
        $mainGameId = $gameInfo['mainGameId'];
        $extraBallGId = isset($gameInfo['extraBallGameId']) ? $gameInfo['extraBallGameId'] : null;
        $stateName = $gameInfo['state'];
        $lotteryName = $gameInfo['lottery'];
        $extraBallLabel = isset($gameInfo['extraBallLabel']) ? $gameInfo['extraBallLabel'] : 'Extra Ball';
        $gameFound = true;
        break;
    }
}
if (!$gameFound) {
    http_response_code(404);
    echo 'Invalid game ID.';
    return;
}

/**
 * ---------------------------------------------
 * SEO: CTR-first meta title + description (brand-safe)
 * - Dynamic per URL (uses $stateName + $lotteryName)
 * - "Today" + "Latest Results" to match high-intent searches
 * - No hype, no guarantees
 * ---------------------------------------------
 */
$stateSafe   = trim((string) $stateName);
$lotterySafe = trim((string) $lotteryName);

// Meta Title (SERP-facing)
$metaTitle = $lotterySafe . ' Winning Numbers Today - Latest Results & Analysis Tools | LottoExpert';

// Meta Description (SERP-facing; calm, analytical, trust-forward)
$metaDescription = 'See today\'s ' . $lotterySafe . ' winning numbers and recent draw context, plus SKAI-style digit frequency analysis for clarity. No hype, just transparent data.';

// Keep state context without bloating the title (safer for truncation)
if ($stateSafe !== '' && $stateSafe !== 'Lottery') {
    $metaDescription = 'See today\'s ' . $lotterySafe . ' winning numbers for ' . $stateSafe . ', plus SKAI-style digit frequency analysis for clarity. No hype, just transparent data.';
}

// Apply to Joomla Document head
$doc->setTitle($metaTitle);
$doc->setDescription($metaDescription);

// Social meta (helps share previews & snippet testing)
$doc->addCustomTag('<meta property="og:title" content="' . htmlspecialchars((string) $metaTitle, ENT_QUOTES, 'UTF-8') . '" />');
$doc->addCustomTag('<meta property="og:description" content="' . htmlspecialchars((string) $metaDescription, ENT_QUOTES, 'UTF-8') . '" />');
$doc->addCustomTag('<meta property="og:url" content="' . htmlspecialchars((string) $canonicalUrl, ENT_QUOTES, 'UTF-8') . '" />');
$doc->addCustomTag('<meta name="twitter:card" content="summary" />');
$doc->addCustomTag('<meta name="twitter:title" content="' . htmlspecialchars((string) $metaTitle, ENT_QUOTES, 'UTF-8') . '" />');
$doc->addCustomTag('<meta name="twitter:description" content="' . htmlspecialchars((string) $metaDescription, ENT_QUOTES, 'UTF-8') . '" />');

/**
 * Lotto logo URL (server format):
 * /images/lottodb/us/{state}/{lottery-slug}.png
 * Example: /images/lottodb/us/fl/pick-4-midday.png
 */
$stateCode = 'us'; // fallback
if (!empty($dbCol) && preg_match('/#__lotterydb_([a-z0-9]+)/i', (string) $dbCol, $m)) {
    $stateCode = strtolower($m[1]); // e.g. "fl"
}

$lotterySlug = strtolower((string) $lotteryName);
$lotterySlug = str_replace(['&', '+'], 'and', $lotterySlug);
$lotterySlug = preg_replace('/\s+/', '-', $lotterySlug);          // spaces -> hyphens
$lotterySlug = preg_replace('/[^a-z0-9\-]/', '', $lotterySlug);   // keep a-z 0-9 -
$lotterySlug = preg_replace('/\-+/', '-', $lotterySlug);          // collapse hyphens
$lotterySlug = trim($lotterySlug, '-');

// Small normalization helpers (improves hit rate for names like "Play3")
$lotterySlug = preg_replace('/([a-z])([0-9])/', '$1-$2', $lotterySlug); // play3 -> play-3

$logoRel = '/images/lottodb/us/' . $stateCode . '/' . $lotterySlug . '.png';
$logoAbs = defined('JPATH_ROOT') ? (JPATH_ROOT . $logoRel) : '';

$logoUrl = '';
if ($logoAbs !== '' && is_file($logoAbs)) {
    $logoUrl = Uri::root(true) . $logoRel;
}

// Connect to the database and get total drawings for the selected game
$db = Factory::getDbo();

$query = $db->getQuery(true)
    ->select('COUNT(*)')
    ->from($db->quoteName($dbCol))
    ->where($db->quoteName('game_id') . ' = :gameId');
$query->bind(':gameId', $mainGameId, \Joomla\Database\ParameterType::STRING);
$db->setQuery($query);
$totalDrawings = $db->loadResult();

// Sanitize and validate POST inputs with CSRF protection
$totalDrawingsInt = max(10, (int) $totalDrawings);
$drawRange = 100;
if (!empty($_POST) && isset($_POST['drawRange'])) {
    if (Session::checkToken('post')) {
        $drawRange = filter_var($_POST['drawRange'], FILTER_VALIDATE_INT, [
            'options' => ['default' => 100, 'min_range' => 10, 'max_range' => $totalDrawingsInt]
        ]);
    }
}

// Functions for getting frequencies and last draw details
function getDigitFrequencies($db, $dbCol, $position, $drawRange, $gameId) {
    $query = $db->getQuery(true)
        ->select($db->quoteName($position))
        ->from($db->quoteName($dbCol))
        ->where($db->quoteName('game_id') . ' = :gameId')
        ->order($db->quoteName('draw_date') . ' DESC')
        ->setLimit($drawRange);
    $query->bind(':gameId', $gameId, \Joomla\Database\ParameterType::STRING);
    $db->setQuery($query);
    return array_count_values($db->loadColumn());
}

function getLastDrawnAndCount($db, $dbCol, $digit, $position, $gameId, $drawRange) {
    $query = $db->getQuery(true)
        ->select('MAX(' . $db->quoteName('draw_date') . ') AS last_draw_date')
        ->from($db->quoteName($dbCol))
        ->where($db->quoteName($position) . ' = :digit')
        ->where($db->quoteName('game_id') . ' = :gameId');
    $query->bind(':digit', $digit, \Joomla\Database\ParameterType::INTEGER);
    $query->bind(':gameId', $gameId, \Joomla\Database\ParameterType::STRING);
    $db->setQuery($query);
    $lastDrawDate = $db->loadResult();

    if ($lastDrawDate) {
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName($dbCol))
            ->where($db->quoteName('draw_date') . ' > :lastDrawDate')
            ->where($db->quoteName('game_id') . ' = :gameId')
            ->where($db->quoteName('draw_date') . ' <= NOW()');
        $query->bind(':lastDrawDate', $lastDrawDate, \Joomla\Database\ParameterType::STRING);
        $query->bind(':gameId', $gameId, \Joomla\Database\ParameterType::STRING);
        $db->setQuery($query);
        return $db->loadResult() . ' drws ago';
    } else {
        return "Not in last $drawRange dr.";
    }
}

function getOverallFrequencies($db, $dbCol, $drawRange, $gameId) {
    $frequencies = [];
    $query = $db->getQuery(true)
        ->select($db->quoteName(['first', 'second', 'third']))
        ->from($db->quoteName($dbCol))
        ->where($db->quoteName('game_id') . ' = :gameId')
        ->order($db->quoteName('draw_date') . ' DESC')
        ->setLimit($drawRange);
    $query->bind(':gameId', $gameId, \Joomla\Database\ParameterType::STRING);
    $db->setQuery($query);
    $results = $db->loadObjectList();

    foreach ($results as $result) {
        // Validate each position is a single digit 0-9 before concatenation
        $d1 = (string) $result->first;
        $d2 = (string) $result->second;
        $d3 = (string) $result->third;
        if (!preg_match('/^[0-9]$/', $d1) || !preg_match('/^[0-9]$/', $d2) || !preg_match('/^[0-9]$/', $d3)) {
            continue;
        }
        $threeDigitNumber = $d1 . $d2 . $d3;

        if (!isset($frequencies[$threeDigitNumber])) {
            $frequencies[$threeDigitNumber] = 0;
        }
        $frequencies[$threeDigitNumber]++;
    }

    arsort($frequencies); // Sort by frequency, highest first
    return $frequencies;
}

function getDrawingsAgo($db, $dbCol, $threeDigitNumber, $gameId, $drawRange) {
    $query = $db->getQuery(true)
        ->select('MAX(' . $db->quoteName('draw_date') . ')')
        ->from($db->quoteName($dbCol))
        ->where('CONCAT(' . $db->quoteName('first') . ', ' . $db->quoteName('second') . ', ' . $db->quoteName('third') . ') = :threeDigitNumber')
        ->where($db->quoteName('game_id') . ' = :gameId');
    $query->bind(':threeDigitNumber', $threeDigitNumber, \Joomla\Database\ParameterType::STRING);
    $query->bind(':gameId', $gameId, \Joomla\Database\ParameterType::STRING);
    $db->setQuery($query);
    $lastDrawDate = $db->loadResult();

    if ($lastDrawDate) {
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName($dbCol))
            ->where($db->quoteName('draw_date') . ' > :lastDrawDate')
            ->where($db->quoteName('game_id') . ' = :gameId');
        $query->bind(':lastDrawDate', $lastDrawDate, \Joomla\Database\ParameterType::STRING);
        $query->bind(':gameId', $gameId, \Joomla\Database\ParameterType::STRING);
        $db->setQuery($query);
        $drawsAgo = $db->loadResult() . ' drws ago';
    } else {
        $drawsAgo = "Not in last $drawRange dr.";
    }

    return $drawsAgo;
}

// Function to get the latest result, including the extra ball if available
function getLatestResult($db, $dbCol, $mainGameId, $extraBallGameId = null, $fallbackGameId = null) {
    // CHG: Try main game_id first, then fallback game_id (URL gmCode) if needed.
    $tryIds = [];
    $tryIds[] = (string) $mainGameId;

    if ($fallbackGameId !== null && (string) $fallbackGameId !== (string) $mainGameId) {
        $tryIds[] = (string) $fallbackGameId;
    }

    $result = null;

    foreach ($tryIds as $tryGameId) {
        $query = $db->getQuery(true)
            ->select($db->quoteName(['first', 'second', 'third', 'draw_date', 'next_draw_date', 'next_jackpot']))
            ->from($db->quoteName($dbCol))
            ->where($db->quoteName('game_id') . ' = :gameId')
            ->order($db->quoteName('draw_date') . ' DESC')
            ->setLimit(1);

        // CHG: bind() requires a variable (passed by reference), not an expression.
        if (ctype_digit($tryGameId)) {
            $gameIdInt = (int) $tryGameId;
            $query->bind(':gameId', $gameIdInt, \Joomla\Database\ParameterType::INTEGER);
        } else {
            $gameIdStr = (string) $tryGameId;
            $query->bind(':gameId', $gameIdStr, \Joomla\Database\ParameterType::STRING);
        }

        $db->setQuery($query);
        $result = $db->loadObject();

        if ($result) {
            break;
        }
    }

    // If there is no main result even after fallback, stop early (prevents notices)
    if (!$result) {
        return null;
    }

// Align extra-ball to the SAME draw_date as the main game
    if (!empty($extraBallGameId)) {

        // bind() requires variables (passed by reference)
        $extraBallGameIdStr = (string) $extraBallGameId;
        $drawDateStr        = (string) $result->draw_date;

        $query = $db->getQuery(true)
            ->select($db->quoteName('first')) // Extra ball digit stored in first column for the extra-ball "game_id" row
            ->from($db->quoteName($dbCol))
            ->where($db->quoteName('game_id') . ' = :extraBallGameId')
            ->where($db->quoteName('draw_date') . ' = :drawDate')
            ->setLimit(1);

        $query->bind(':extraBallGameId', $extraBallGameIdStr, \Joomla\Database\ParameterType::STRING);
        $query->bind(':drawDate', $drawDateStr, \Joomla\Database\ParameterType::STRING);

        $db->setQuery($query);
        $extraBall = $db->loadResult();

        if ($extraBall !== null && $extraBall !== '') {
            $result->extra_ball = $extraBall;
        }
    }

    // ? CHG: Always return the assembled row (main + optional extra ball)
    return $result;
}
// CHG: Call once OUTSIDE the function (supports fallback to URL gmCode if needed).
$latestResult = getLatestResult($db, $dbCol, $mainGameId, $extraBallGId, $gId);

if ($latestResult) {
    $draw_date      = $latestResult->draw_date;
    $next_draw_date = $latestResult->next_draw_date;
    $next_jackpot   = $latestResult->next_jackpot;

    $p1 = $latestResult->first;
    $p2 = $latestResult->second;
    $p3 = $latestResult->third;

    $pb = isset($latestResult->extra_ball) ? $latestResult->extra_ball : null;
}

/**
 * ---------------------------------------------
 * Tool links (SKAI AI Prediction link per-game)
 * REQUIRED format: .../skai-lottery-prediction?gameId=XXX
 * ---------------------------------------------
 */
$skaiAiBase = '/picking-winning-numbers/artificial-intelligence/skai-lottery-prediction?gameId=';

/**
 * Map gmCode ($gId) -> SKAI AI gameId (the value you want in ?gameId=...).
 * If your SKAI AI endpoint expects a DIFFERENT code than $mainGameId, set it here.
 */
$aiLinkMap = [
    // Virginia
    'VAD' => 'VAD',
    'VAC' => 'VAC',

    // Texas
    'TXM' => 'TXM',
    'TXL' => 'TXL',
    'TXD' => 'TXD',
    'TXB' => 'TXB',

    // Tennessee
    'TNF' => 'TNF',
    'TND' => 'TND',
    'TNB' => 'TNB',

    // South Carolina
    'SCD' => 'SCD',
    'SCC' => 'SCC',

    // Pennsylvania
    'PAD' => 'PAD',
    'PAC' => 'PAC',

    // New Jersey
    'NJD' => 'NJD',
    'NJC' => 'NJC',

    // North Carolina
    'NCD' => 'NCD',
    'NCC' => 'NCC',

    // Mississippi
    'MSD' => 'MSD',
    'MSC' => 'MSC',

    // Indiana
    'IND' => 'IND',
    'INC' => 'INC',

    // Florida
    'FLD' => 'FLD',
    'FLB' => 'FLB',

    // Connecticut
    'CTD' => 'CTD',
    'CTC' => 'CTC',

    // Illinois
    '123' => '123',
    '122' => '122',
];

// CHG: Compute hero AI URL using MAIN game id so extra variants (CTAW/ILG/etc.) still map correctly
$aiAnalysisUrl = '';
$aiKey = isset($mainGameId) ? (string) $mainGameId : (string) $gId;

if (isset($aiLinkMap[$aiKey])) {
    $aiAnalysisUrl = $skaiAiBase . rawurlencode((string) $aiLinkMap[$aiKey]);
}

/**
 * -------------------------------------------------
 * Generic game-aware analysis preparation
 * -------------------------------------------------
 */
$stateAbrev = strtoupper((string) $stateCode);

function leOrderedPositionColumns(): array
{
    return [
        'first', 'second', 'third', 'fourth', 'fifth', 'sixth', 'seventh', 'eighth', 'ninth', 'tenth',
        'eleventh', 'twelfth', 'thirteenth', 'fourteenth', 'fifteenth', 'sixteenth', 'seventeenth',
        'eighteenth', 'nineteenth', 'twentieth'
    ];
}

function leLabelFromPosition(string $position): string
{
    $labels = [
        'first' => 'First', 'second' => 'Second', 'third' => 'Third', 'fourth' => 'Fourth', 'fifth' => 'Fifth',
        'sixth' => 'Sixth', 'seventh' => 'Seventh', 'eighth' => 'Eighth', 'ninth' => 'Ninth', 'tenth' => 'Tenth',
        'eleventh' => 'Eleventh', 'twelfth' => 'Twelfth', 'thirteenth' => 'Thirteenth', 'fourteenth' => 'Fourteenth',
        'fifteenth' => 'Fifteenth', 'sixteenth' => 'Sixteenth', 'seventeenth' => 'Seventeenth', 'eighteenth' => 'Eighteenth',
        'nineteenth' => 'Nineteenth', 'twentieth' => 'Twentieth'
    ];
    return isset($labels[$position]) ? $labels[$position] : ucfirst($position);
}

function lePad2(string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    if (ctype_digit($value) && strlen($value) === 1) {
        return '0' . $value;
    }
    return $value;
}

function leFmtDateLong(?string $date): string
{
    if (!$date) {
        return '--';
    }
    $ts = strtotime($date);
    return ($ts === false) ? '--' : date('F j, Y', $ts);
}

function leInitDigitMap(int $value = 0): array
{
    $map = [];
    for ($i = 0; $i <= 9; $i++) {
        $map[(string) $i] = $value;
    }
    return $map;
}

function leFetchRowsByGameId($db, string $dbCol, $gameId, int $limit): array
{
    $query = $db->getQuery(true)
        ->select('*')
        ->from($db->quoteName($dbCol))
        ->where($db->quoteName('game_id') . ' = :gameId')
        ->order($db->quoteName('draw_date') . ' DESC')
        ->setLimit(max(1, $limit));

    if (is_numeric((string) $gameId) && (string) (int) $gameId === (string) $gameId) {
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

function leDetectPickFromName(string $lotteryName): int
{
    if (preg_match('/(?:pick|daily|cash)\s*([0-9]{1,2})/i', $lotteryName, $m)) {
        $v = (int) $m[1];
        if ($v >= 1 && $v <= 20) {
            return $v;
        }
    }
    if (preg_match('/\b([0-9]{1,2})\b/', $lotteryName, $m2)) {
        $v2 = (int) $m2[1];
        if ($v2 >= 1 && $v2 <= 20) {
            return $v2;
        }
    }
    return 0;
}

function leCountNonEmptyPositions(array $row, array $positions): int
{
    $count = 0;
    foreach ($positions as $pos) {
        if (array_key_exists($pos, $row) && trim((string) $row[$pos]) !== '') {
            $count++;
        }
    }
    return $count;
}

function leResolveLogo(string $stateCode, string $lotteryName): array
{
    $lotterySlug = strtolower((string) $lotteryName);
    $lotterySlug = str_replace(['&', '+'], 'and', $lotterySlug);
    $lotterySlug = preg_replace('/\s+/', '-', $lotterySlug);
    $lotterySlug = preg_replace('/[^a-z0-9\-]/', '', (string) $lotterySlug);
    $lotterySlug = preg_replace('/\-+/', '-', (string) $lotterySlug);
    $lotterySlug = trim((string) $lotterySlug, '-');
    $lotterySlug = preg_replace('/([a-z])([0-9])/', '$1-$2', (string) $lotterySlug);

    $rel = '/images/lottodb/us/' . strtolower((string) $stateCode) . '/' . $lotterySlug . '.png';
    $abs = defined('JPATH_ROOT') ? (JPATH_ROOT . $rel) : '';

    if ($abs !== '' && is_file($abs)) {
        return ['exists' => true, 'url' => Uri::root(true) . $rel];
    }

    return ['exists' => false, 'url' => ''];
}

function leCommaList(array $items): string
{
    $clean = [];
    foreach ($items as $item) {
        $v = trim((string) $item);
        if ($v !== '') {
            $clean[] = $v;
        }
    }
    if (empty($clean)) {
        return '--';
    }
    return implode(', ', $clean);
}

function leGetDrawingsSinceDate($db, string $dbCol, $gameId, ?string $previousDate, string $currentDate): ?int
{
    if (!$previousDate || !$currentDate) {
        return null;
    }

    $query = $db->getQuery(true)
        ->select('COUNT(' . $db->quoteName('id') . ')')
        ->from($db->quoteName($dbCol))
        ->where($db->quoteName('game_id') . ' = :gameId')
        ->where($db->quoteName('draw_date') . ' > :previousDate')
        ->where($db->quoteName('draw_date') . ' < :currentDate');

    if (is_numeric((string) $gameId) && (string) (int) $gameId === (string) $gameId) {
        $gidInt = (int) $gameId;
        $query->bind(':gameId', $gidInt, \Joomla\Database\ParameterType::INTEGER);
    } else {
        $gidStr = (string) $gameId;
        $query->bind(':gameId', $gidStr, \Joomla\Database\ParameterType::STRING);
    }

    $prev = (string) $previousDate;
    $curr = (string) $currentDate;
    $query->bind(':previousDate', $prev, \Joomla\Database\ParameterType::STRING);
    $query->bind(':currentDate', $curr, \Joomla\Database\ParameterType::STRING);

    $db->setQuery($query);
    return ((int) $db->loadResult()) + 1;
}

function leGetPreviousOccurrenceByColumn($db, string $dbCol, $gameId, string $drawDate, string $column, string $digit): ?string
{
    if ($digit === '' || !preg_match('/^[0-9]$/', $digit)) {
        return null;
    }

    $query = $db->getQuery(true)
        ->select('MAX(' . $db->quoteName('draw_date') . ')')
        ->from($db->quoteName($dbCol))
        ->where($db->quoteName('game_id') . ' = :gameId')
        ->where($db->quoteName('draw_date') . ' < :drawDate')
        ->where($db->quoteName($column) . ' = :digit');

    if (is_numeric((string) $gameId) && (string) (int) $gameId === (string) $gameId) {
        $gidInt = (int) $gameId;
        $query->bind(':gameId', $gidInt, \Joomla\Database\ParameterType::INTEGER);
    } else {
        $gidStr = (string) $gameId;
        $query->bind(':gameId', $gidStr, \Joomla\Database\ParameterType::STRING);
    }

    $drawDateStr = (string) $drawDate;
    $digitStr = (string) $digit;
    $query->bind(':drawDate', $drawDateStr, \Joomla\Database\ParameterType::STRING);
    $query->bind(':digit', $digitStr, \Joomla\Database\ParameterType::STRING);

    $db->setQuery($query);
    $r = $db->loadResult();
    return $r ? (string) $r : null;
}

// latest rows (main game first, fallback to original input game id)
$latestMainRows = leFetchRowsByGameId($db, $dbCol, $mainGameId, 1);
if (empty($latestMainRows) && (string) $mainGameId !== (string) $gId) {
    $latestMainRows = leFetchRowsByGameId($db, $dbCol, $gId, 1);
}
$latestMain = !empty($latestMainRows) ? $latestMainRows[0] : [];

$orderedCandidates = leOrderedPositionColumns();
$availableMainPositions = [];
foreach ($orderedCandidates as $colName) {
    if (array_key_exists($colName, $latestMain)) {
        $availableMainPositions[] = $colName;
    }
}
if (empty($availableMainPositions)) {
    $availableMainPositions = ['first', 'second', 'third'];
}

$pickFromName = leDetectPickFromName((string) $lotteryName);
$pickFromRow = leCountNonEmptyPositions($latestMain, $availableMainPositions);
$mainBallCount = $pickFromName > 0 ? $pickFromName : $pickFromRow;
if ($mainBallCount <= 0) {
    $mainBallCount = min(3, count($availableMainPositions));
}
$mainBallCount = max(1, min(20, $mainBallCount));
$mainBallCount = min($mainBallCount, count($availableMainPositions));
$mainPositions = array_slice($availableMainPositions, 0, $mainBallCount);

$bonusSlots = [];
if (!empty($extraBallGId)) {
    $bonusSlots[] = [
        'gameId' => (string) $extraBallGId,
        'column' => 'first',
        'label' => (string) $extraBallLabel
    ];
}

$drawDate = isset($latestMain['draw_date']) ? (string) $latestMain['draw_date'] : '';
$nextDrawDate = isset($latestMain['next_draw_date']) ? (string) $latestMain['next_draw_date'] : '';
$nextJackpot = isset($latestMain['next_jackpot']) ? (string) $latestMain['next_jackpot'] : '';

$latestMainBalls = [];
foreach ($mainPositions as $pos) {
    $v = isset($latestMain[$pos]) ? trim((string) $latestMain[$pos]) : '';
    if ($v !== '' && preg_match('/^[0-9]$/', $v)) {
        $latestMainBalls[] = $v;
    }
}

$latestBonusBalls = [];
foreach ($bonusSlots as $slotIndex => $slotCfg) {
    $slotRows = leFetchRowsByGameId($db, $dbCol, (string) $slotCfg['gameId'], 8);
    $slotValue = '';
    if ($drawDate !== '') {
        foreach ($slotRows as $sr) {
            if ((string) ($sr['draw_date'] ?? '') === $drawDate) {
                $slotValue = trim((string) ($sr[$slotCfg['column']] ?? ''));
                break;
            }
        }
    }
    if ($slotValue === '' && !empty($slotRows)) {
        $slotValue = trim((string) ($slotRows[0][$slotCfg['column']] ?? ''));
    }
    if ($slotValue !== '' && preg_match('/^[0-9]{1,2}$/', $slotValue)) {
        $latestBonusBalls[] = [
            'label' => (string) $slotCfg['label'],
            'value' => $slotValue,
            'gameId' => (string) $slotCfg['gameId'],
            'column' => (string) $slotCfg['column']
        ];
    }
}

// Fallback: recover extra-ball value already fetched by getLatestResult when the
// row-based approach above found nothing (e.g. extra-ball rows use a different
// draw_date format, or the DB has fewer than 8 rows for that game_id).
if (empty($latestBonusBalls) && isset($pb) && $pb !== null && $pb !== '' && !empty($extraBallGId)) {
    $pbVal = trim((string) $pb);
    if ($pbVal !== '') {
        $latestBonusBalls[] = [
            'label'  => (string) $extraBallLabel,
            'value'  => $pbVal,
            'gameId' => (string) $extraBallGId,
            'column' => 'first',
        ];
    }
}

$hasLatestDraw = !empty($latestMain);

// draw range
$drawRange = 100;
if ($totalDrawingsInt < 10) {
    $totalDrawingsInt = 10;
}
if ($drawRange > $totalDrawingsInt) {
    $drawRange = $totalDrawingsInt;
}
if (!empty($_POST) && Session::checkToken('post')) {
    $postedRange = $input->post->getInt('drawRange', $drawRange);
    $drawRange = max(10, min($totalDrawingsInt, (int) $postedRange));
}

$rowsMainWindow = leFetchRowsByGameId($db, $dbCol, $mainGameId, $drawRange);
if (empty($rowsMainWindow) && (string) $mainGameId !== (string) $gId) {
    $rowsMainWindow = leFetchRowsByGameId($db, $dbCol, $gId, $drawRange);
}

$positionFrequency = [];
$positionLastSeen = [];
foreach ($mainPositions as $pos) {
    $positionFrequency[$pos] = leInitDigitMap(0);
    $positionLastSeen[$pos] = leInitDigitMap(-1);
}

$mainDigitCounts = leInitDigitMap(0);
$mainDigitFirstSeenIdx = leInitDigitMap(-1);

foreach ($rowsMainWindow as $idx => $row) {
    foreach ($mainPositions as $pos) {
        $digit = trim((string) ($row[$pos] ?? ''));
        if (!preg_match('/^[0-9]$/', $digit)) {
            continue;
        }
        $positionFrequency[$pos][$digit] = (int) $positionFrequency[$pos][$digit] + 1;
        if ((int) $positionLastSeen[$pos][$digit] < 0) {
            $positionLastSeen[$pos][$digit] = (int) $idx;
        }
        $mainDigitCounts[$digit] = (int) $mainDigitCounts[$digit] + 1;
        if ((int) $mainDigitFirstSeenIdx[$digit] < 0) {
            $mainDigitFirstSeenIdx[$digit] = (int) $idx;
        }
    }
}

$bonusDigitCounts = leInitDigitMap(0);
$bonusDigitFirstSeenIdx = leInitDigitMap(-1);
$bonusPositionFrequency = [];
$bonusPositionLastSeen = [];

foreach ($bonusSlots as $slotCfg) {
    $slotKey = (string) $slotCfg['label'];
    $bonusPositionFrequency[$slotKey] = leInitDigitMap(0);
    $bonusPositionLastSeen[$slotKey] = leInitDigitMap(-1);

    $slotRows = leFetchRowsByGameId($db, $dbCol, (string) $slotCfg['gameId'], $drawRange);
    foreach ($slotRows as $idx => $row) {
        $digit = trim((string) ($row[$slotCfg['column']] ?? ''));
        if (!preg_match('/^[0-9]$/', $digit)) {
            continue;
        }
        $bonusPositionFrequency[$slotKey][$digit] = (int) $bonusPositionFrequency[$slotKey][$digit] + 1;
        if ((int) $bonusPositionLastSeen[$slotKey][$digit] < 0) {
            $bonusPositionLastSeen[$slotKey][$digit] = (int) $idx;
        }
        $bonusDigitCounts[$digit] = (int) $bonusDigitCounts[$digit] + 1;
        if ((int) $bonusDigitFirstSeenIdx[$digit] < 0) {
            $bonusDigitFirstSeenIdx[$digit] = (int) $idx;
        }
    }
}

// Top / quiet summary
$activeCounts = $mainDigitCounts;
arsort($activeCounts, SORT_NUMERIC);
$topActiveLabels = array_slice(array_keys($activeCounts), 0, 10);
$topActiveValues = [];
foreach ($topActiveLabels as $k) {
    $topActiveValues[] = (int) $mainDigitCounts[$k];
}

$quietDistanceMap = [];
for ($d = 0; $d <= 9; $d++) {
    $key = (string) $d;
    $quietDistanceMap[$key] = ((int) $mainDigitFirstSeenIdx[$key] < 0) ? ((int) $drawRange + 1) : ((int) $mainDigitFirstSeenIdx[$key] + 1);
}
arsort($quietDistanceMap, SORT_NUMERIC);
$quietLabels = array_slice(array_keys($quietDistanceMap), 0, 10);
$quietValues = [];
foreach ($quietLabels as $k) {
    $quietValues[] = (int) $quietDistanceMap[$k];
}

$mostActiveSummary = array_slice($topActiveLabels, 0, 3);
$quietSummary = array_slice($quietLabels, 0, 3);

$latestMainUnique = [];
foreach ($latestMainBalls as $v) {
    if (!in_array($v, $latestMainUnique, true)) {
        $latestMainUnique[] = $v;
    }
}
$repeatedNumbers = [];
$prevRows = array_slice($rowsMainWindow, 1, 10);
foreach ($latestMainUnique as $digit) {
    foreach ($prevRows as $row) {
        foreach ($mainPositions as $pos) {
            if ((string) ($row[$pos] ?? '') === $digit) {
                if (!in_array($digit, $repeatedNumbers, true)) {
                    $repeatedNumbers[] = $digit;
                }
                break 2;
            }
        }
    }
}
sort($repeatedNumbers, SORT_NUMERIC);

$window50Rows = leFetchRowsByGameId($db, $dbCol, $mainGameId, 50);
$window300Rows = leFetchRowsByGameId($db, $dbCol, $mainGameId, 300);
$count50 = leInitDigitMap(0);
$count300 = leInitDigitMap(0);

foreach ($window50Rows as $row) {
    foreach ($mainPositions as $pos) {
        $digit = trim((string) ($row[$pos] ?? ''));
        if (preg_match('/^[0-9]$/', $digit)) {
            $count50[$digit] = (int) $count50[$digit] + 1;
        }
    }
}
foreach ($window300Rows as $row) {
    foreach ($mainPositions as $pos) {
        $digit = trim((string) ($row[$pos] ?? ''));
        if (preg_match('/^[0-9]$/', $digit)) {
            $count300[$digit] = (int) $count300[$digit] + 1;
        }
    }
}

$tmp50 = $count50;
$tmp300 = $count300;
arsort($tmp50, SORT_NUMERIC);
arsort($tmp300, SORT_NUMERIC);
$top50 = array_slice(array_keys($tmp50), 0, 5);
$top300 = array_slice(array_keys($tmp300), 0, 5);

$windowShiftIn = [];
$windowShiftOut = [];
foreach ($top300 as $digit) {
    if (!in_array($digit, $top50, true)) {
        $windowShiftIn[] = $digit;
    }
}
foreach ($top50 as $digit) {
    if (!in_array($digit, $top300, true)) {
        $windowShiftOut[] = $digit;
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

$drawHistoryRows = [];
if ($drawDate !== '') {
    foreach ($mainPositions as $idx => $pos) {
        $digit = isset($latestMain[$pos]) ? trim((string) $latestMain[$pos]) : '';
        if (!preg_match('/^[0-9]$/', $digit)) {
            continue;
        }
        $prevDate = leGetPreviousOccurrenceByColumn($db, $dbCol, $mainGameId, $drawDate, $pos, $digit);
        $drawsAgo = leGetDrawingsSinceDate($db, $dbCol, $mainGameId, $prevDate, $drawDate);
        $drawHistoryRows[] = [
            'label' => leLabelFromPosition($pos) . ' Position (' . lePad2($digit) . ')',
            'prevDate' => $prevDate,
            'drawsAgo' => $drawsAgo,
            'isBonus' => false
        ];
    }

    foreach ($latestBonusBalls as $bonusInfo) {
        $prevDate = leGetPreviousOccurrenceByColumn($db, $dbCol, (string) $bonusInfo['gameId'], $drawDate, (string) $bonusInfo['column'], (string) $bonusInfo['value']);
        $drawsAgo = leGetDrawingsSinceDate($db, $dbCol, (string) $bonusInfo['gameId'], $prevDate, $drawDate);
        $drawHistoryRows[] = [
            'label' => (string) $bonusInfo['label'] . ' (' . lePad2((string) $bonusInfo['value']) . ')',
            'prevDate' => $prevDate,
            'drawsAgo' => $drawsAgo,
            'isBonus' => true
        ];
    }
}

// full chart arrays
$digitLabels = [];
$mainValues = [];
$mainRecencyValues = [];
$bonusValues = [];
for ($d = 0; $d <= 9; $d++) {
    $k = (string) $d;
    $digitLabels[] = lePad2($k);
    $mainValues[] = (int) $mainDigitCounts[$k];
    $mainRecencyValues[] = (int) $quietDistanceMap[$k];
    $bonusValues[] = (int) $bonusDigitCounts[$k];
}

// combination frequency (preserve target analytical intent)
$comboFrequency = [];
$comboLastSeenIdx = [];
foreach ($rowsMainWindow as $idx => $row) {
    $parts = [];
    $ok = true;
    foreach ($mainPositions as $pos) {
        $digit = trim((string) ($row[$pos] ?? ''));
        if (!preg_match('/^[0-9]$/', $digit)) {
            $ok = false;
            break;
        }
        $parts[] = $digit;
    }
    if (!$ok || empty($parts)) {
        continue;
    }
    $combo = implode('', $parts);
    if (!isset($comboFrequency[$combo])) {
        $comboFrequency[$combo] = 0;
    }
    $comboFrequency[$combo] = (int) $comboFrequency[$combo] + 1;
    if (!isset($comboLastSeenIdx[$combo])) {
        $comboLastSeenIdx[$combo] = (int) $idx;
    }
}
arsort($comboFrequency, SORT_NUMERIC);

$heroInsight = 'Latest verified draw and recent digit behavior at a glance. Review the most active digits, quiet stretches, and full historical frequency before moving into deeper SKAI analysis.';
$overviewNote = 'Frequency shows historical occurrence within the selected window. It can help identify recent concentration and quiet periods, but it should be interpreted as context rather than prediction.';
$positionCountLabel = (int) $mainBallCount . ' main position' . ((int) $mainBallCount === 1 ? '' : 's');

$logo = leResolveLogo((string) $stateCode, (string) $lotteryName);

// dynamic links preserving target conventions
$skaiAnalysisLink = $aiAnalysisUrl;
$aiPredictionsLink = '/picking-winning-numbers/artificial-intelligence/ai-powered-predictions?game_id=' . rawurlencode((string) $mainGameId);
$skipHitLink = '/picking-winning-numbers/artificial-intelligence/skip-and-hit-analysis?game_id=' . rawurlencode((string) $mainGameId);
$mcmcLink = '/picking-winning-numbers/artificial-intelligence/markov-chain-monte-carlo-mcmc-analysis?game_id=' . rawurlencode((string) $mainGameId);
$heatmapLink = '/all-lottery-heatmaps?gId=' . rawurlencode((string) $gId) . '&stateName=' . rawurlencode((string) $stateName) . '&gName=' . rawurlencode((string) $lotteryName) . '&sTn=' . rawurlencode(strtolower((string) $stateAbrev));
$archivesLink = '/lottery-archives?gId=' . rawurlencode((string) $gId) . '&stateName=' . rawurlencode((string) $stateName) . '&gName=' . rawurlencode((string) $lotteryName) . '&sTn=' . rawurlencode(strtolower((string) $stateAbrev));
$lowestLink = '/lowest-drawn-number-analysis?gId=' . rawurlencode((string) $gId) . '&stateName=' . rawurlencode((string) $stateName) . '&gName=' . rawurlencode((string) $lotteryName) . '&sTn=' . rawurlencode(strtolower((string) $stateAbrev));

$doc->setTitle((string) $lotteryName . ' Results Analysis - ' . (string) $stateName . ' | LottoExpert.net');
$doc->setDescription('Analyze ' . (string) $lotteryName . ' results with transparent frequency, recency, draw history, and bonus ball context for ' . (string) $stateName . '.');

$webPageSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    'name' => (string) $lotteryName . ' results analysis',
    'description' => 'Lottery results analysis with frequency, recency, draw history context, and bonus ball tracking.',
    'url' => (string) $canonicalUrl
];
$datasetSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'Dataset',
    'name' => (string) $lotteryName . ' draw frequency dataset',
    'description' => 'Frequency and recency metrics derived from the selected draw window for main and bonus digit pools.',
    'keywords' => [(string) $lotteryName, 'results analysis', 'frequency', 'recency', 'draw history', 'bonus ball'],
    'isAccessibleForFree' => true
];
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
.result-wrapper {display: none !important;}
.skai-page{max-width:1180px;margin:0 auto;padding:20px 14px 32px;color:var(--text);font-family:var(--font)}
.skai-page a{text-decoration:none}
.skai-grid{display:grid;gap:14px}
.skai-hero{position:relative;overflow:hidden;border-radius:var(--radius-22);background:radial-gradient(900px 420px at -10% -20%, rgba(255,255,255,.13) 0%, rgba(255,255,255,0) 55%),radial-gradient(780px 340px at 110% 0%, rgba(255,255,255,.10) 0%, rgba(255,255,255,0) 55%),var(--grad-horizon);color:#fff;box-shadow:var(--shadow-2);border:1px solid rgba(255,255,255,.10)}
.skai-hero-inner{padding:22px 20px 18px}
.skai-hero-top{display:grid;grid-template-columns:110px minmax(0,1fr) 280px;gap:18px;align-items:start}
.skai-logo{width:110px;height:110px;border-radius:20px;background:rgba(255,255,255,.94);display:flex;align-items:center;justify-content:center;box-shadow:0 14px 30px rgba(0,0,0,.16);overflow:hidden;padding:12px}
.skai-logo img{width:100%;height:100%;object-fit:contain;display:block}
.skai-hero-copy{min-width:0}
.skai-kicker{font-size:12px;line-height:1.2;letter-spacing:.18em;text-transform:uppercase;font-weight:800;color:rgba(255,255,255,.76);margin:2px 0 8px}
.skai-title{margin:0;font-size:30px;line-height:1.08;font-weight:900;letter-spacing:-.02em;color:#fff}
.skai-hero-summary{margin:12px 0 0;max-width:68ch;font-size:15px;line-height:1.65;color:rgba(255,255,255,.90)}
.skai-result-panel{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);border-radius:18px;padding:14px;backdrop-filter:blur(4px)}
.skai-panel-label{font-size:11px;line-height:1.2;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:rgba(255,255,255,.72);margin:0 0 10px}
.skai-meta-stack{display:grid;gap:10px}.skai-meta-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.skai-meta-box{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.10);border-radius:14px;padding:10px}
.skai-meta-box .label{display:block;font-size:11px;line-height:1.2;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.70)}
.skai-meta-box .value{display:block;margin-top:6px;font-size:15px;line-height:1.35;font-weight:850;color:#fff}
.skai-ball-row{display:flex;align-items:center;flex-wrap:wrap;gap:8px;margin-top:16px}
.skai-ball{width:42px;height:42px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;font-size:16px;font-weight:900;letter-spacing:.02em;position:relative}
.skai-ball--main{background:linear-gradient(180deg, #FFFFFF 0%, #F3F6FF 100%);color:var(--deep-navy);border:1px solid rgba(10,26,51,.14);box-shadow:0 10px 20px rgba(10,26,51,.12), inset 0 1px 0 rgba(255,255,255,.90)}
.skai-ball--bonus{background:radial-gradient(circle at 50% 18%, #C73E4E 0%, #8F1F2D 76%, #4A0911 100%);color:#fff;border:1px solid rgba(255,255,255,.16);box-shadow:0 12px 24px rgba(10,26,51,.18)}
.skai-ball-gap{width:8px;height:1px}
.skai-hero-actions{margin-top:18px;display:grid;grid-template-columns:1.2fr 1fr 1fr;gap:10px}
.skai-btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;border-radius:14px;min-height:48px;padding:12px 16px;font-size:14px;line-height:1.2;font-weight:850;transition:transform .14s ease, box-shadow .14s ease, filter .14s ease}
.skai-btn:hover{transform:translateY(-1px)}
.skai-btn:focus,.skai-btn:focus-visible{outline:3px solid rgba(255,255,255,.30);outline-offset:3px}
.skai-btn--primary{background:#fff;color:var(--deep-navy);box-shadow:0 12px 22px rgba(0,0,0,.14)}
.skai-btn--secondary{background:rgba(255,255,255,.12);color:#fff;border:1px solid rgba(255,255,255,.18)}
.skai-advanced-links{display:grid;grid-template-columns:repeat(3, minmax(0,1fr));gap:10px;margin-top:10px}
.skai-mini-link{display:flex;align-items:center;justify-content:center;text-align:center;min-height:44px;padding:10px 12px;border-radius:12px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.10);color:#fff;font-size:13px;line-height:1.3;font-weight:800}
.skai-strip{margin-top:14px;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}
.skai-stat{border-radius:18px;overflow:hidden;background:var(--grad-slate);border:1px solid var(--line);box-shadow:var(--shadow-1)}
.skai-stat-head{padding:12px 14px;color:#fff;font-size:12px;line-height:1.25;letter-spacing:.12em;text-transform:uppercase;font-weight:850}
.skai-stat-head--horizon{background:var(--grad-horizon)}.skai-stat-head--radiant{background:var(--grad-radiant)}.skai-stat-head--success{background:var(--grad-success)}.skai-stat-head--ember{background:var(--grad-ember)}
.skai-stat-body{padding:14px;min-height:120px;display:flex;flex-direction:column;justify-content:space-between}
.skai-stat-value{font-size:24px;line-height:1.12;font-weight:900;letter-spacing:-.02em;color:var(--deep-navy)}
.skai-stat-note{margin-top:10px;font-size:13px;line-height:1.6;color:var(--text-soft)}
.skai-tabs{margin-top:18px;display:flex;flex-wrap:wrap;gap:10px;padding:6px;border-radius:999px;background:var(--sky-gray);border:1px solid var(--line)}
.skai-tab{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:10px 16px;border-radius:999px;color:var(--deep-navy);font-size:13px;line-height:1.2;font-weight:850}
.skai-tab--active{background:var(--grad-horizon);color:#fff;box-shadow:0 10px 20px rgba(10,26,51,.12)}
.skai-section{margin-top:16px;background:var(--grad-slate);border:1px solid var(--line);border-radius:20px;box-shadow:var(--shadow-1);overflow:hidden}
.skai-section-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;padding:18px 18px 14px;border-bottom:1px solid var(--line);background:rgba(255,255,255,.55)}
.skai-section-title{margin:0;font-size:22px;line-height:1.15;letter-spacing:-.02em;font-weight:900;color:var(--deep-navy)}
.skai-section-sub{margin:8px 0 0;max-width:76ch;font-size:14px;line-height:1.65;color:var(--text-soft)}
.skai-section-body{padding:16px 18px 18px;background:#fff}
.skai-overview-grid{display:grid;grid-template-columns:1.2fr 1fr;gap:14px}.skai-overview-grid>*{min-width:0}
.skai-card{background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 10px 24px rgba(10,26,51,.06);overflow:hidden;min-width:0}
.skai-card-head{padding:14px 16px;color:#fff;font-weight:850;font-size:16px;line-height:1.25}
.skai-card-head--horizon{background:var(--grad-horizon)}.skai-card-head--radiant{background:var(--grad-radiant)}.skai-card-head--success{background:var(--grad-success)}.skai-card-head--ember{background:var(--grad-ember)}
.skai-card-sub{display:block;margin-top:4px;font-size:12px;line-height:1.45;font-weight:700;opacity:.92}
.skai-card-body{padding:14px 16px 16px}
.skai-chart-frame{position:relative;width:100%;max-width:100%;height:300px;overflow:hidden;box-sizing:border-box}
.skai-chart-frame--tall{height:620px}
.skai-chart-frame canvas{display:block;width:100% !important;max-width:100%;height:100% !important;box-sizing:border-box}
.skai-chart-empty{display:flex;align-items:center;justify-content:center;height:100%;font-size:13px;line-height:1.6;color:var(--text-soft);text-align:center;padding:0 12px}
.skai-note{margin-top:14px;padding:14px 16px;border-radius:16px;background:linear-gradient(180deg, #F8FAFE 0%, #FFFFFF 100%);border:1px solid var(--line);color:var(--text-soft);font-size:13px;line-height:1.7}
.skai-two-col{display:grid;grid-template-columns:1fr 1fr;gap:14px}.skai-two-col>*{min-width:0}
.skai-history-list{display:grid;gap:10px}
.skai-history-item{display:grid;grid-template-columns:220px 1fr auto;gap:12px;align-items:center;padding:12px 14px;border-radius:14px;border:1px solid var(--line);background:linear-gradient(180deg, #FFFFFF 0%, #FAFBFF 100%)}
.skai-history-name{font-size:14px;line-height:1.35;font-weight:850;color:var(--deep-navy)}
.skai-history-date{font-size:13px;line-height:1.55;color:var(--text-soft)}
.skai-history-badge{display:inline-flex;align-items:center;justify-content:center;min-width:110px;min-height:36px;padding:8px 12px;border-radius:999px;background:var(--grad-radiant);color:#fff;font-size:12px;line-height:1.2;font-weight:850}
.skai-window-shift{display:grid;gap:12px}
.skai-shift-panel{border:1px solid var(--line);border-radius:16px;padding:14px 15px;background:linear-gradient(180deg, #FFFFFF 0%, #FAFBFF 100%)}
.skai-shift-label{margin:0 0 8px;font-size:12px;line-height:1.2;letter-spacing:.12em;text-transform:uppercase;font-weight:850;color:var(--soft-slate)}
.skai-shift-text{margin:0;font-size:14px;line-height:1.7;color:var(--text)}
.skai-controls{padding:14px 16px;border-bottom:1px solid var(--line);background:rgba(255,255,255,.76)}
.skai-controls form{margin:0}
.skai-controls-row{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px}
.skai-controls-left{display:flex;flex-wrap:wrap;align-items:center;gap:10px}.skai-controls-right{display:flex;flex-wrap:wrap;align-items:center;gap:10px}
.skai-controls label{font-size:13px;line-height:1.2;font-weight:850;color:var(--deep-navy)}
.skai-select{min-width:122px;min-height:44px;padding:10px 12px;border-radius:12px;border:1px solid var(--line-strong);background:#fff;color:var(--deep-navy);font-size:14px;line-height:1.2;font-weight:800}
.skai-button{min-height:44px;padding:10px 16px;border:none;border-radius:12px;background:var(--grad-horizon);color:#fff;font-size:13px;line-height:1.2;font-weight:850;cursor:pointer;box-shadow:0 10px 20px rgba(10,26,51,.12)}
.skai-button:hover{filter:brightness(1.03)}
.skai-filter-group{display:flex;flex-wrap:wrap;gap:8px}
.skai-filter{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:8px 12px;border-radius:999px;border:1px solid var(--line);background:#fff;color:var(--deep-navy);font-size:12px;line-height:1.2;font-weight:800;cursor:pointer}
.skai-filter.is-active{background:var(--grad-horizon);border-color:transparent;color:#fff}
.skai-table-wrap{padding:16px;overflow-x:auto;overflow-y:visible}
table.skai-table{width:100%;min-width:320px;border-collapse:separate;border-spacing:0;background:#fff;border:1px solid var(--line);border-radius:16px;overflow:hidden}
table.skai-table thead th{position:sticky;top:0;z-index:1;background:var(--grad-horizon);color:#fff;padding:8px 6px;font-size:11px;line-height:1.2;letter-spacing:.04em;text-transform:uppercase;font-weight:850;text-align:center;border-bottom:1px solid rgba(255,255,255,.12)}
table.skai-table tbody td{padding:9px 7px;text-align:center;border-bottom:1px solid rgba(10,26,51,.06);font-size:14px;line-height:1.45;color:var(--deep-navy);vertical-align:middle}
table.skai-table tbody tr:hover{background:rgba(28,102,255,.04)}
.skai-pill{width:34px;height:34px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;font-size:14px;line-height:1;font-weight:900}
.skai-pill--main{background:linear-gradient(180deg, #FFFFFF 0%, #F3F6FF 100%);color:var(--deep-navy);border:1px solid rgba(10,26,51,.14);box-shadow:0 8px 16px rgba(10,26,51,.08)}
.skai-pill--bonus{background:radial-gradient(circle at 50% 18%, #C73E4E 0%, #8F1F2D 76%, #4A0911 100%);color:#fff;border:1px solid rgba(255,255,255,.14)}
.skai-checkbox{transform:scale(1.25);cursor:pointer}
.skai-tracked{margin-top:14px;border:1px solid var(--line);border-radius:16px;background:var(--grad-slate);overflow:hidden}
.skai-tracked-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:12px 14px;border-bottom:1px solid var(--line)}
.skai-tracked-title{margin:0;font-size:15px;line-height:1.2;font-weight:850;color:var(--deep-navy)}
.skai-tracked-actions{display:flex;align-items:center;gap:8px}
.skai-link-btn{border:none;background:none;color:var(--skai-blue);font-size:12px;line-height:1.2;font-weight:850;cursor:pointer;padding:0}
.skai-chip-wrap{padding:12px 14px 14px;display:flex;flex-wrap:wrap;gap:8px;min-height:64px;align-items:flex-start}
.skai-empty{font-size:13px;line-height:1.6;color:var(--text-soft)}
.skai-chip{display:inline-flex;align-items:center;justify-content:center;min-height:36px;padding:8px 12px;border-radius:999px;font-size:13px;line-height:1.2;font-weight:850}
.skai-chip--main{background:linear-gradient(180deg, #FFFFFF 0%, #F3F6FF 100%);border:1px solid rgba(10,26,51,.14);color:var(--deep-navy)}
.skai-chip--bonus{background:radial-gradient(circle at 50% 18%, #C73E4E 0%, #8F1F2D 76%, #4A0911 100%);color:#fff;border:1px solid rgba(255,255,255,.14)}
.skai-tool-grid{display:grid;grid-template-columns:1.2fr 1fr 1fr;gap:14px}
.skai-tool{border-radius:18px;overflow:hidden;border:1px solid var(--line);background:#fff;box-shadow:0 10px 24px rgba(10,26,51,.06)}
.skai-tool-head{padding:14px 16px;color:#fff;font-size:15px;line-height:1.3;font-weight:850}
.skai-tool-body{padding:15px 16px 16px}
.skai-tool-copy{margin:0 0 14px;font-size:14px;line-height:1.7;color:var(--text-soft)}
.skai-tool-cta{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:10px 16px;border-radius:12px;font-size:13px;line-height:1.2;font-weight:850;background:var(--grad-horizon);color:#fff}
.skai-utility-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:12px}
.skai-utility-link{min-height:42px;display:flex;align-items:center;justify-content:center;text-align:center;padding:10px 12px;border-radius:12px;border:1px solid var(--line);background:var(--grad-slate);color:var(--deep-navy);font-size:13px;line-height:1.3;font-weight:850}
.skai-method-note{padding:16px;border-radius:16px;border:1px solid var(--line);background:linear-gradient(180deg, #FAFBFF 0%, #FFFFFF 100%);font-size:14px;line-height:1.8;color:var(--text-soft)}
.skai-method-note strong{color:var(--deep-navy)}
@media (max-width:1080px){.skai-hero-top{grid-template-columns:96px minmax(0,1fr)}.skai-result-panel{grid-column:1 / -1}.skai-strip,.skai-tool-grid,.skai-overview-grid,.skai-two-col{grid-template-columns:1fr}.skai-hero-actions,.skai-advanced-links{grid-template-columns:1fr}.skai-utility-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.skai-chart-frame--tall{height:420px}}
@media (max-width:780px){.skai-page{padding:14px 10px 24px}.skai-title{font-size:26px}.skai-section-head{padding:16px 14px 12px}.skai-section-body{padding:14px}.skai-strip{grid-template-columns:1fr}.skai-history-item{grid-template-columns:1fr;align-items:start}.skai-meta-row{grid-template-columns:1fr}.skai-tabs{border-radius:18px}.skai-utility-grid{grid-template-columns:1fr 1fr}}
@media (prefers-reduced-motion: reduce){.skai-btn,.skai-button{transition:none}}
</style>

<div class="skai-page">
  <section class="skai-hero" aria-label="Results intelligence header">
    <div class="skai-hero-inner">
      <div class="skai-hero-top">
        <div class="skai-logo" aria-hidden="<?php echo $logo['exists'] ? 'false' : 'true'; ?>">
          <?php if ($logo['exists'] && $logo['url'] !== '') : ?>
            <img src="<?php echo htmlspecialchars((string) $logo['url'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) $stateName . ' ' . (string) $lotteryName, ENT_QUOTES, 'UTF-8'); ?>" width="110" height="110" loading="lazy" decoding="async">
          <?php else : ?>
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 2l2.7 6.2L21 9l-4.7 4.1L17.6 21 12 17.8 6.4 21l1.3-7.9L3 9l6.3-.8L12 2z" stroke="rgba(10,26,51,.55)" stroke-width="1.6" stroke-linejoin="round"/></svg>
          <?php endif; ?>
        </div>

        <div class="skai-hero-copy">
          <div class="skai-kicker">Results Intelligence &bull; Verified Draw &bull; Calm Analytical View</div>
          <h1 class="skai-title"><?php echo htmlspecialchars((string) $stateName, ENT_QUOTES, 'UTF-8'); ?> &ndash; <?php echo htmlspecialchars((string) $lotteryName, ENT_QUOTES, 'UTF-8'); ?></h1>
          <p class="skai-hero-summary"><?php echo htmlspecialchars((string) $heroInsight, ENT_QUOTES, 'UTF-8'); ?></p>

          <div class="skai-ball-row" aria-label="Latest drawn numbers">
            <?php if (!empty($latestMainBalls)) : ?>
              <?php foreach ($latestMainBalls as $ball) : ?>
                <span class="skai-ball skai-ball--main"><?php echo htmlspecialchars(lePad2((string) $ball), ENT_QUOTES, 'UTF-8'); ?></span>
              <?php endforeach; ?>
            <?php else : ?>
              <span class="skai-hero-summary">Latest draw values are not currently available.</span>
            <?php endif; ?>
            <?php if (!empty($latestBonusBalls)) : ?>
              <span class="skai-ball-gap" aria-hidden="true"></span>
              <?php foreach ($latestBonusBalls as $b) : ?>
                <span class="skai-ball skai-ball--bonus"><?php echo htmlspecialchars(lePad2((string) $b['value']), ENT_QUOTES, 'UTF-8'); ?></span>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <div class="skai-hero-actions" aria-label="Primary actions">
            <?php if ($skaiAnalysisLink !== '') : ?><a class="skai-btn skai-btn--primary" href="<?php echo htmlspecialchars((string) $skaiAnalysisLink, ENT_QUOTES, 'UTF-8'); ?>">Open SKAI Analysis</a><?php endif; ?>
            <a class="skai-btn skai-btn--secondary" href="<?php echo htmlspecialchars((string) $aiPredictionsLink, ENT_QUOTES, 'UTF-8'); ?>">AI Predictions</a>
            <a class="skai-btn skai-btn--secondary" href="#frequency-deep-dive">View Frequency Deep Dive</a>
          </div>

          <div class="skai-advanced-links" aria-label="Advanced tools">
            <a class="skai-mini-link" href="<?php echo htmlspecialchars((string) $skipHitLink, ENT_QUOTES, 'UTF-8'); ?>">Skip &amp; Hit Analysis</a>
            <a class="skai-mini-link" href="<?php echo htmlspecialchars((string) $mcmcLink, ENT_QUOTES, 'UTF-8'); ?>">MCMC Markov Analysis</a>
            <a class="skai-mini-link" href="<?php echo htmlspecialchars((string) $heatmapLink, ENT_QUOTES, 'UTF-8'); ?>">Heatmap Analysis</a>
          </div>
        </div>

        <aside class="skai-result-panel" aria-label="Latest draw details">
          <div class="skai-panel-label">Latest draw summary</div>
          <div class="skai-meta-stack">
            <div class="skai-meta-row">
              <div class="skai-meta-box"><span class="label">Draw date</span><span class="value"><?php echo htmlspecialchars(leFmtDateLong((string) $drawDate), ENT_QUOTES, 'UTF-8'); ?></span></div>
              <div class="skai-meta-box"><span class="label">Next draw date</span><span class="value"><?php echo htmlspecialchars(leFmtDateLong((string) $nextDrawDate), ENT_QUOTES, 'UTF-8'); ?></span></div>
            </div>
            <div class="skai-meta-box"><span class="label">Game format</span><span class="value"><?php echo (int) $mainBallCount; ?> main positions<?php echo !empty($bonusSlots) ? ' + ' . count($bonusSlots) . ' special ball pool' . (count($bonusSlots) > 1 ? 's' : '') : ''; ?>.</span></div>
            <?php if ($nextJackpot !== '' && $nextJackpot !== '0' && strtolower(trim((string) $nextJackpot)) !== 'n/a') : ?>
              <div class="skai-meta-box"><span class="label">Next jackpot</span><span class="value">$<?php echo htmlspecialchars(number_format((float) $nextJackpot, 0, '.', ','), ENT_QUOTES, 'UTF-8'); ?></span></div>
            <?php endif; ?>
          </div>
        </aside>
      </div>
    </div>
  </section>

  <section class="skai-strip" aria-label="Key takeaways">
    <article class="skai-stat"><div class="skai-stat-head skai-stat-head--horizon">Most active digits</div><div class="skai-stat-body"><div class="skai-stat-value"><?php echo htmlspecialchars(leCommaList($mostActiveSummary), ENT_QUOTES, 'UTF-8'); ?></div><div class="skai-stat-note">Highest appearance counts in the current <?php echo (int) $drawRange; ?>-draw main pool.</div></div></article>
    <article class="skai-stat"><div class="skai-stat-head skai-stat-head--radiant">Quietest now</div><div class="skai-stat-body"><div class="skai-stat-value"><?php echo htmlspecialchars(leCommaList($quietSummary), ENT_QUOTES, 'UTF-8'); ?></div><div class="skai-stat-note">Digits currently sitting furthest from their most recent appearance in the selected window.</div></div></article>
    <article class="skai-stat"><div class="skai-stat-head skai-stat-head--success">Repeated recently</div><div class="skai-stat-body"><div class="skai-stat-value"><?php echo htmlspecialchars(!empty($repeatedNumbers) ? leCommaList($repeatedNumbers) : 'None', ENT_QUOTES, 'UTF-8'); ?></div><div class="skai-stat-note">Latest main digits that also appeared in trailing draws.</div></div></article>
    <article class="skai-stat"><div class="skai-stat-head skai-stat-head--ember">Window analyzed</div><div class="skai-stat-body"><div class="skai-stat-value"><?php echo (int) $drawRange; ?></div><div class="skai-stat-note">Drawings currently loaded for <?php echo htmlspecialchars((string) $positionCountLabel, ENT_QUOTES, 'UTF-8'); ?>.</div></div></article>
  </section>

  <nav class="skai-tabs" aria-label="Page navigation">
    <a class="skai-tab skai-tab--active" href="#overview">Overview</a>
    <a class="skai-tab" href="#frequency-deep-dive">Frequency</a>
    <a class="skai-tab" href="#recency-deep-dive">Recency</a>
    <a class="skai-tab" href="#tables">Tables</a>
    <a class="skai-tab" href="#tools">Advanced Tools</a>
  </nav>

  <section id="overview" class="skai-section" aria-labelledby="overview-title">
    <div class="skai-section-head"><div><h2 id="overview-title" class="skai-section-title">Overview</h2><p class="skai-section-sub">Start with a clear high-level view. This layer is designed for fast orientation: which digits are most active, which are quiet, and how the current draw relates to recent history.</p></div></div>
    <div class="skai-section-body">
      <div class="skai-overview-grid">
        <div class="skai-card"><div class="skai-card-head skai-card-head--horizon">Top active digits<span class="skai-card-sub">Highest frequency counts in the last <?php echo (int) $drawRange; ?> drawings</span></div><div class="skai-card-body"><div class="skai-chart-frame"><canvas id="topActiveChart" aria-label="Top active digits chart" role="img"></canvas></div></div></div>
        <div class="skai-card"><div class="skai-card-head skai-card-head--ember">Quiet stretches<span class="skai-card-sub">Digits with the longest distance from their last appearance</span></div><div class="skai-card-body"><div class="skai-chart-frame"><canvas id="quietChart" aria-label="Quiet digits chart" role="img"></canvas></div></div></div>
      </div>
      <div class="skai-note"><?php echo htmlspecialchars((string) $overviewNote, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>
  </section>

  <section id="recency-deep-dive" class="skai-section" aria-labelledby="recency-title">
    <div class="skai-section-head"><div><h2 id="recency-title" class="skai-section-title">Recency and draw context</h2><p class="skai-section-sub">This section shows how recently each latest value was previously seen and how perspective changes between short and broad windows.</p></div></div>
    <div class="skai-section-body">
      <div class="skai-two-col">
        <div class="skai-card">
          <div class="skai-card-head skai-card-head--radiant">Current draw history<span class="skai-card-sub">Previous appearance date and spacing for each latest value</span></div>
          <div class="skai-card-body">
            <div class="skai-history-list">
              <?php if (!empty($drawHistoryRows)) : ?>
                <?php foreach ($drawHistoryRows as $row) : ?>
                  <div class="skai-history-item">
                    <div class="skai-history-name"><?php echo htmlspecialchars((string) $row['label'], ENT_QUOTES, 'UTF-8'); ?></div>
                    <div class="skai-history-date"><?php if (!empty($row['prevDate'])) : ?>Previously seen on <?php echo htmlspecialchars(leFmtDateLong((string) $row['prevDate']), ENT_QUOTES, 'UTF-8'); ?><?php else : ?>No previous appearance found in the loaded historical set<?php endif; ?></div>
                    <div class="skai-history-badge"><?php echo ($row['drawsAgo'] !== null) ? (int) $row['drawsAgo'] . ' drws ago' : '--'; ?></div>
                  </div>
                <?php endforeach; ?>
              <?php else : ?>
                <div class="skai-note">Current draw history is unavailable for this window.</div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="skai-card"><div class="skai-card-head skai-card-head--success">What changes with the window<span class="skai-card-sub">Comparing shorter recent behavior with broader historical behavior</span></div><div class="skai-card-body"><div class="skai-window-shift"><div class="skai-shift-panel"><p class="skai-shift-label">Window shift note</p><p class="skai-shift-text"><?php echo htmlspecialchars((string) $windowChangeNarrative, ENT_QUOTES, 'UTF-8'); ?></p></div><div class="skai-shift-panel"><p class="skai-shift-label">Recent 50-draw leaders</p><p class="skai-shift-text"><?php echo htmlspecialchars(leCommaList(array_slice($top50, 0, 5)), ENT_QUOTES, 'UTF-8'); ?></p></div><div class="skai-shift-panel"><p class="skai-shift-label">Broader 300-draw leaders</p><p class="skai-shift-text"><?php echo htmlspecialchars(leCommaList(array_slice($top300, 0, 5)), ENT_QUOTES, 'UTF-8'); ?></p></div></div></div></div>
      </div>
    </div>
  </section>

  <section id="frequency-deep-dive" class="skai-section" aria-labelledby="frequency-title">
    <div class="skai-section-head"><div><h2 id="frequency-title" class="skai-section-title">Frequency deep dive</h2><p class="skai-section-sub">The first panel shows complete main-pool digit distribution. The second panel shows bonus or special-pool distribution when available. Recency distribution tracks distance since last appearance.</p></div></div>
    <div class="skai-section-body">
      <div class="skai-two-col">
        <div class="skai-card"><div class="skai-card-head skai-card-head--horizon">Full main digit distribution<span class="skai-card-sub">Digits 0&ndash;9 across the last <?php echo (int) $drawRange; ?> drawings and <?php echo (int) $mainBallCount; ?> positions</span></div><div class="skai-card-body"><div class="skai-chart-frame skai-chart-frame--tall"><canvas id="fullMainChart" aria-label="Full main distribution chart" role="img"></canvas></div></div></div>
        <div class="skai-grid">
          <div class="skai-card"><div class="skai-card-head skai-card-head--radiant">Special ball distribution<span class="skai-card-sub"><?php echo !empty($bonusSlots) ? 'Digits 0-9 in selected special-number pool(s)' : 'No special-number pool configured for this game'; ?></span></div><div class="skai-card-body"><div class="skai-chart-frame"><canvas id="starChart" aria-label="Special ball distribution chart" role="img"></canvas></div></div></div>
          <div class="skai-card"><div class="skai-card-head skai-card-head--ember">Recency distribution<span class="skai-card-sub">Distance since last appearance for each main-pool digit</span></div><div class="skai-card-body"><div class="skai-chart-frame"><canvas id="recencyChart" aria-label="Main recency chart" role="img"></canvas></div></div></div>
        </div>
      </div>
      <div class="skai-note">Frequency and recency are descriptive signals. Use them as context for draw history interpretation, not as certainty.</div>
    </div>
  </section>

  <section id="tables" class="skai-section" aria-labelledby="tables-title">
    <div class="skai-section-head"><div><h2 id="tables-title" class="skai-section-title">Tables and tracked numbers</h2><p class="skai-section-sub">Use exact counts and recency labels by digit and by position. Tracking is local to this page view.</p></div></div>
    <div class="skai-controls">
      <form method="post" action="<?php echo htmlspecialchars(Uri::getInstance()->toString(['path', 'query']), ENT_QUOTES, 'UTF-8'); ?>#tables">
        <div class="skai-controls-row">
          <div class="skai-controls-left">
            <label for="drawRange">Analysis draw window</label>
            <select name="drawRange" id="drawRange" class="skai-select">
              <?php for ($opt = 10; $opt <= $totalDrawingsInt; $opt += 10) : ?>
                <option value="<?php echo (int) $opt; ?>"<?php echo ((int) $opt === (int) $drawRange) ? ' selected="selected"' : ''; ?>><?php echo (int) $opt; ?></option>
              <?php endfor; ?>
            </select>
          </div>
          <div class="skai-controls-right"><button class="skai-button" type="submit">Update analysis window</button><?php echo HTMLHelper::_('form.token'); ?></div>
        </div>
      </form>
    </div>

    <div class="skai-section-body">
      <div class="skai-two-col">
        <div class="skai-card">
          <div class="skai-card-head skai-card-head--horizon">Main digit table<span class="skai-card-sub">Exact counts and recency for digits 0&ndash;9 in the combined main pool</span></div>
          <div class="skai-controls"><div class="skai-controls-row"><div class="skai-controls-left"><div class="skai-filter-group" data-filter-group="main"><button class="skai-filter is-active" type="button" data-filter="all">All</button><button class="skai-filter" type="button" data-filter="active">Most active</button><button class="skai-filter" type="button" data-filter="quiet">Quietest</button><button class="skai-filter" type="button" data-filter="recent">Recently seen</button></div></div></div></div>
          <div class="skai-table-wrap">
            <table id="skai-main-table" class="skai-table" aria-label="Main digit frequency table"><thead><tr><th>Digit</th><th>Drawn Times</th><th>Last Drawn</th><th>Track</th></tr></thead><tbody>
              <?php
              $activeTagSet = array_slice($topActiveLabels, 0, 5);
              $quietTagSet = array_slice($quietLabels, 0, 5);
              for ($d = 0; $d <= 9; $d++) :
                  $digit = (string) $d;
                  $countNumber = (int) $mainDigitCounts[$digit];
                  $lastIdx = (int) $mainDigitFirstSeenIdx[$digit];
                  $drawLabel = ($lastIdx < 0) ? 'Not in last ' . (int) $drawRange . ' drws' : (($lastIdx + 1) . ' drws ago');
                  $rowTags = 'all';
                  if (in_array($digit, $activeTagSet, true)) { $rowTags .= ' active'; }
                  if (in_array($digit, $quietTagSet, true)) { $rowTags .= ' quiet'; }
                  if ($lastIdx >= 0 && $lastIdx <= 4) { $rowTags .= ' recent'; }
              ?>
                <tr data-tags="<?php echo htmlspecialchars((string) $rowTags, ENT_QUOTES, 'UTF-8'); ?>"><td><span class="skai-pill skai-pill--main"><?php echo htmlspecialchars(lePad2($digit), ENT_QUOTES, 'UTF-8'); ?></span></td><td><?php echo (int) $countNumber; ?> X</td><td><?php echo htmlspecialchars((string) $drawLabel, ENT_QUOTES, 'UTF-8'); ?></td><td><input class="skai-checkbox js-track-main" type="checkbox" value="<?php echo htmlspecialchars(lePad2($digit), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Track main digit <?php echo htmlspecialchars((string) $digit, ENT_QUOTES, 'UTF-8'); ?>"></td></tr>
              <?php endfor; ?>
            </tbody></table>
          </div>
          <div class="skai-tracked"><div class="skai-tracked-head"><h3 class="skai-tracked-title">Tracked main digits</h3><div class="skai-tracked-actions"><button class="skai-link-btn" type="button" id="clearMainTracked">Clear all</button></div></div><div class="skai-chip-wrap" id="mainTrackedWrap"><div class="skai-empty">Select digits to create a short tracked set for comparison.</div></div></div>
        </div>

        <div class="skai-grid">
          <?php foreach ($mainPositions as $pos) : ?>
            <div class="skai-card">
              <div class="skai-card-head skai-card-head--radiant"><?php echo htmlspecialchars(leLabelFromPosition((string) $pos), ENT_QUOTES, 'UTF-8'); ?> position table<span class="skai-card-sub">Digit counts for this specific position</span></div>
              <div class="skai-table-wrap"><table class="skai-table" aria-label="<?php echo htmlspecialchars(leLabelFromPosition((string) $pos), ENT_QUOTES, 'UTF-8'); ?> position frequency table"><thead><tr><th>Digit</th><th>Drawn Times</th><th>Last Drawn</th></tr></thead><tbody>
                <?php for ($d = 0; $d <= 9; $d++) : $digit = (string) $d; $countPos = (int) $positionFrequency[$pos][$digit]; $idxSeen = (int) $positionLastSeen[$pos][$digit]; $labelSeen = ($idxSeen < 0) ? 'Not in last ' . (int) $drawRange . ' drws' : (($idxSeen + 1) . ' drws ago'); ?>
                  <tr><td><span class="skai-pill skai-pill--main"><?php echo htmlspecialchars(lePad2($digit), ENT_QUOTES, 'UTF-8'); ?></span></td><td><?php echo (int) $countPos; ?> X</td><td><?php echo htmlspecialchars((string) $labelSeen, ENT_QUOTES, 'UTF-8'); ?></td></tr>
                <?php endfor; ?>
              </tbody></table></div>
            </div>
          <?php endforeach; ?>

          <?php if (!empty($bonusSlots)) : ?>
            <div class="skai-card">
              <div class="skai-card-head skai-card-head--ember">Special-number table<span class="skai-card-sub">Combined special/bonus pool frequencies</span></div>
              <div class="skai-table-wrap"><table id="skai-bonus-table" class="skai-table" aria-label="Special-number frequency table"><thead><tr><th>Digit</th><th>Drawn Times</th><th>Last Drawn</th><th>Track</th></tr></thead><tbody>
                <?php for ($d = 0; $d <= 9; $d++) : $digit = (string) $d; $cnt = (int) $bonusDigitCounts[$digit]; $idxSeen = (int) $bonusDigitFirstSeenIdx[$digit]; $lbl = ($idxSeen < 0) ? 'Not in last ' . (int) $drawRange . ' drws' : (($idxSeen + 1) . ' drws ago'); ?>
                  <tr><td><span class="skai-pill skai-pill--bonus"><?php echo htmlspecialchars(lePad2($digit), ENT_QUOTES, 'UTF-8'); ?></span></td><td><?php echo (int) $cnt; ?> X</td><td><?php echo htmlspecialchars((string) $lbl, ENT_QUOTES, 'UTF-8'); ?></td><td><input class="skai-checkbox js-track-bonus" type="checkbox" value="<?php echo htmlspecialchars(lePad2($digit), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Track bonus digit <?php echo htmlspecialchars((string) $digit, ENT_QUOTES, 'UTF-8'); ?>"></td></tr>
                <?php endfor; ?>
              </tbody></table></div>
              <div class="skai-tracked"><div class="skai-tracked-head"><h3 class="skai-tracked-title">Tracked special digits</h3><div class="skai-tracked-actions"><button class="skai-link-btn" type="button" id="clearBonusTracked">Clear all</button></div></div><div class="skai-chip-wrap" id="bonusTrackedWrap"><div class="skai-empty">Use tracking to keep a small special-number set visible while comparing modules.</div></div></div>
            </div>
          <?php endif; ?>

          <div class="skai-card">
            <div class="skai-card-head skai-card-head--success">Top combinations<span class="skai-card-sub">Most frequent full-number outcomes in the current window</span></div>
            <div class="skai-table-wrap"><table class="skai-table" aria-label="Top combination frequency table"><thead><tr><th>Number</th><th>Drawn Times</th><th>Last Drawn</th></tr></thead><tbody>
              <?php $shown = 0; foreach ($comboFrequency as $combo => $freq) : if ($shown >= 10) { break; } $idxSeen = isset($comboLastSeenIdx[$combo]) ? (int) $comboLastSeenIdx[$combo] : -1; $lbl = ($idxSeen < 0) ? 'Not in last ' . (int) $drawRange . ' drws' : (($idxSeen + 1) . ' drws ago'); $shown++; ?>
                <tr><td><?php foreach (str_split((string) $combo) as $digitChar) : ?><span class="skai-pill skai-pill--main" style="margin:0 2px;"><?php echo htmlspecialchars(lePad2((string) $digitChar), ENT_QUOTES, 'UTF-8'); ?></span><?php endforeach; ?></td><td><?php echo (int) $freq; ?> X</td><td><?php echo htmlspecialchars((string) $lbl, ENT_QUOTES, 'UTF-8'); ?></td></tr>
              <?php endforeach; ?>
            </tbody></table></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section id="tools" class="skai-section" aria-labelledby="tools-title">
    <div class="skai-section-head"><div><h2 id="tools-title" class="skai-section-title">Next steps and advanced tools</h2><p class="skai-section-sub">The results page establishes context. These tools take that context into deeper modeling and structured exploration.</p></div></div>
    <div class="skai-section-body">
      <div class="skai-tool-grid">
        <article class="skai-tool"><div class="skai-tool-head skai-card-head--horizon">SKAI Analysis</div><div class="skai-tool-body"><p class="skai-tool-copy">Best next step for a broader multi-signal view after reviewing frequency and recency.</p><?php if ($skaiAnalysisLink !== '') : ?><a class="skai-tool-cta" href="<?php echo htmlspecialchars((string) $skaiAnalysisLink, ENT_QUOTES, 'UTF-8'); ?>">Open SKAI Analysis</a><?php endif; ?></div></article>
        <article class="skai-tool"><div class="skai-tool-head skai-card-head--radiant">AI Predictions</div><div class="skai-tool-body"><p class="skai-tool-copy">Model-driven complement to the historical view shown on this page.</p><a class="skai-tool-cta" href="<?php echo htmlspecialchars((string) $aiPredictionsLink, ENT_QUOTES, 'UTF-8'); ?>">Open AI Predictions</a></div></article>
        <article class="skai-tool"><div class="skai-tool-head skai-card-head--success">Skip &amp; Hit Analysis</div><div class="skai-tool-body"><p class="skai-tool-copy">Compare appearance spacing and interruption behavior after reviewing current frequency.</p><a class="skai-tool-cta" href="<?php echo htmlspecialchars((string) $skipHitLink, ENT_QUOTES, 'UTF-8'); ?>">Open Skip &amp; Hit</a></div></article>
      </div>
      <div class="skai-utility-grid">
        <a class="skai-utility-link" href="<?php echo htmlspecialchars((string) $mcmcLink, ENT_QUOTES, 'UTF-8'); ?>">MCMC Markov Analysis</a>
        <a class="skai-utility-link" href="<?php echo htmlspecialchars((string) $heatmapLink, ENT_QUOTES, 'UTF-8'); ?>">Heatmap Analysis</a>
        <a class="skai-utility-link" href="<?php echo htmlspecialchars((string) $archivesLink, ENT_QUOTES, 'UTF-8'); ?>">Lottery Archives</a>
        <a class="skai-utility-link" href="<?php echo htmlspecialchars((string) $lowestLink, ENT_QUOTES, 'UTF-8'); ?>">Lowest Number Analysis</a>
      </div>
    </div>
  </section>

  <section class="skai-section" aria-labelledby="method-title">
    <div class="skai-section-head"><div><h2 id="method-title" class="skai-section-title">Method note</h2><p class="skai-section-sub">This page is designed to help users understand recent and historical behavior more clearly, not to imply certainty.</p></div></div>
    <div class="skai-section-body"><div class="skai-method-note"><strong>Interpretation guidance:</strong> Frequency, recency, and spacing can provide useful context for reviewing draw history, but they should be treated as descriptive signals rather than guarantees. The purpose of this page is to make game behavior easier to understand, compare, and carry into deeper SKAI analysis.</div></div>
  </section>
</div>

<script type="application/ld+json"><?php echo json_encode($webPageSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>
<script type="application/ld+json"><?php echo json_encode($datasetSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?></script>

<script type="text/javascript">
(function () {
  'use strict';

  var chartData = {
    topActiveLabels: <?php echo json_encode(array_values($topActiveLabels)); ?>,
    topActiveValues: <?php echo json_encode(array_values($topActiveValues)); ?>,
    quietLabels: <?php echo json_encode(array_values($quietLabels)); ?>,
    quietValues: <?php echo json_encode(array_values($quietValues)); ?>,
    mainLabels: <?php echo json_encode(array_values($digitLabels)); ?>,
    mainValues: <?php echo json_encode(array_values($mainValues)); ?>,
    mainRecencyValues: <?php echo json_encode(array_values($mainRecencyValues)); ?>,
    bonusLabels: <?php echo json_encode(array_values($digitLabels)); ?>,
    bonusValues: <?php echo json_encode(array_values($bonusValues)); ?>
  };

  var chartInstances = {};

  function hasMeaningfulData(arr) {
    if (!arr || !arr.length) {
      return false;
    }
    for (var i = 0; i < arr.length; i++) {
      if (parseInt(arr[i], 10) > 0) {
        return true;
      }
    }
    return false;
  }

  function setChartFallback(canvas, message) {
    if (!canvas || !canvas.parentNode) {
      return;
    }
    var frame = canvas.parentNode;
    canvas.style.display = 'none';
    var existing = frame.querySelector('.skai-chart-empty');
    if (existing) {
      existing.textContent = message;
      return;
    }
    var empty = document.createElement('div');
    empty.className = 'skai-chart-empty';
    empty.textContent = message;
    frame.appendChild(empty);
  }

  function setAllChartFallbacks(message) {
    var ids = ['topActiveChart', 'quietChart', 'fullMainChart', 'starChart', 'recencyChart'];
    for (var i = 0; i < ids.length; i++) {
      setChartFallback(document.getElementById(ids[i]), message);
    }
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

  function createChart(id, config, dataArray) {
    var canvas = document.getElementById(id);
    if (!canvas) {
      return;
    }
    if (chartInstances[id]) {
      return;
    }
    if (!canvas.parentNode || canvas.parentNode.clientWidth < 20) {
      return;
    }
    if (!hasMeaningfulData(dataArray)) {
      setChartFallback(canvas, 'No chart data available for the selected window.');
      return;
    }
    chartInstances[id] = new window.Chart(canvas.getContext('2d'), config);
  }

  function renderCharts() {
    if (!window.Chart) {
      return false;
    }

    createChart('topActiveChart', {
      type: 'bar',
      data: { labels: chartData.topActiveLabels, datasets: [{ data: chartData.topActiveValues, borderWidth: 0, borderRadius: 8, backgroundColor: '#1C66FF' }] },
      options: commonBarOptions(false)
    }, chartData.topActiveValues);

    createChart('quietChart', {
      type: 'bar',
      data: { labels: chartData.quietLabels, datasets: [{ data: chartData.quietValues, borderWidth: 0, borderRadius: 8, backgroundColor: '#F5A623' }] },
      options: commonBarOptions(false)
    }, chartData.quietValues);

    createChart('fullMainChart', {
      type: 'bar',
      data: { labels: chartData.mainLabels, datasets: [{ data: chartData.mainValues, borderWidth: 0, borderRadius: 6, backgroundColor: '#1C66FF' }] },
      options: commonBarOptions(true)
    }, chartData.mainValues);

    createChart('starChart', {
      type: 'bar',
      data: { labels: chartData.bonusLabels, datasets: [{ data: chartData.bonusValues, borderWidth: 0, borderRadius: 8, backgroundColor: '#8F1F2D' }] },
      options: commonBarOptions(false)
    }, chartData.bonusValues);

    createChart('recencyChart', {
      type: 'bar',
      data: { labels: chartData.mainLabels, datasets: [{ data: chartData.mainRecencyValues, borderWidth: 0, borderRadius: 6, backgroundColor: '#F5A623' }] },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        plugins: { legend: { display: false }, tooltip: { enabled: true } },
        scales: {
          x: { ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 10, font: { weight: '700' } }, grid: { display: false } },
          y: { beginAtZero: true, ticks: { precision: 0, font: { weight: '700' } }, grid: { color: 'rgba(10,26,51,.08)' } }
        }
      }
    }, chartData.mainRecencyValues);

    return true;
  }

  function loadChartJsWithRetry(ready) {
    if (window.Chart) {
      ready();
      return;
    }

    var scriptId = 'skai-chartjs-loader';
    var existing = document.getElementById(scriptId);
    if (!existing) {
      var script = document.createElement('script');
      script.id = scriptId;
      script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
      script.integrity = 'sha384-OLBgp1GsljhM2TJ+sbHjaiH9txEUvgdDTAzHv2P24donTt6/529l+9Ua0vFImLlb';
      script.crossOrigin = 'anonymous';
      script.async = true;
      script.onerror = function () {
        var fallback = document.createElement('script');
        fallback.id = scriptId + '-fallback';
        fallback.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
        fallback.async = true;
        document.head.appendChild(fallback);
      };
      document.head.appendChild(script);
    }

    var tries = 0;
    var maxTries = 30;
    var timer = setInterval(function () {
      tries += 1;
      if (window.Chart) {
        clearInterval(timer);
        ready();
        return;
      }
      if (tries >= maxTries) {
        clearInterval(timer);
        setAllChartFallbacks('Chart data is temporarily unavailable. Please refresh to try again.');
      }
    }, 150);
  }

  function renderChartsWithRetries() {
    var attempts = 0;
    var maxAttempts = 20;
    function attempt() {
      attempts += 1;
      var done = renderCharts();
      if (!done && attempts < maxAttempts) {
        setTimeout(attempt, 150);
      }
    }
    attempt();
  }

  function bindTrackers() {
    var mainWrap = document.getElementById('mainTrackedWrap');
    var bonusWrap = document.getElementById('bonusTrackedWrap');
    var clearMain = document.getElementById('clearMainTracked');
    var clearBonus = document.getElementById('clearBonusTracked');

    function renderTracked(selector, wrap, chipClass, emptyText) {
      if (!wrap) {
        return;
      }
      var inputs = document.querySelectorAll(selector);
      var items = [];
      var i;
      for (i = 0; i < inputs.length; i++) {
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
      for (i = 0; i < items.length; i++) {
        var chip = document.createElement('span');
        chip.className = 'skai-chip ' + chipClass;
        chip.textContent = items[i];
        wrap.appendChild(chip);
      }
    }

    function bindGroup(selector, wrap, chipClass, emptyText) {
      var inputs = document.querySelectorAll(selector);
      var i;
      for (i = 0; i < inputs.length; i++) {
        inputs[i].addEventListener('change', function () {
          renderTracked(selector, wrap, chipClass, emptyText);
        });
      }
      renderTracked(selector, wrap, chipClass, emptyText);
    }

    bindGroup('.js-track-main', mainWrap, 'skai-chip--main', 'Select digits to create a short tracked set for comparison.');
    bindGroup('.js-track-bonus', bonusWrap, 'skai-chip--bonus', 'Use tracking to keep a small special-number set visible while comparing modules.');

    if (clearMain) {
      clearMain.addEventListener('click', function () {
        var inputs = document.querySelectorAll('.js-track-main');
        for (var i = 0; i < inputs.length; i++) {
          inputs[i].checked = false;
        }
        renderTracked('.js-track-main', mainWrap, 'skai-chip--main', 'Select digits to create a short tracked set for comparison.');
      });
    }

    if (clearBonus) {
      clearBonus.addEventListener('click', function () {
        var inputs = document.querySelectorAll('.js-track-bonus');
        for (var i = 0; i < inputs.length; i++) {
          inputs[i].checked = false;
        }
        renderTracked('.js-track-bonus', bonusWrap, 'skai-chip--bonus', 'Use tracking to keep a small special-number set visible while comparing modules.');
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
      var i;
      for (i = 0; i < rows.length; i++) {
        var tags = rows[i].getAttribute('data-tags') || '';
        rows[i].style.display = (filter === 'all' || tags.indexOf(filter) !== -1) ? '' : 'none';
      }
      for (i = 0; i < buttons.length; i++) {
        buttons[i].classList.remove('is-active');
        if (buttons[i].getAttribute('data-filter') === filter) {
          buttons[i].classList.add('is-active');
        }
      }
    }

    for (var i = 0; i < buttons.length; i++) {
      buttons[i].addEventListener('click', function () {
        applyFilter(this.getAttribute('data-filter'));
      });
    }
  }

  function initAnchors() {
    var tabs = document.querySelectorAll('.skai-tab');
    var i;
    if (!tabs.length) {
      return;
    }
    for (i = 0; i < tabs.length; i++) {
      tabs[i].addEventListener('click', function () {
        var j;
        for (j = 0; j < tabs.length; j++) {
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
    loadChartJsWithRetry(renderChartsWithRetries);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>
