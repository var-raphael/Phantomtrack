<?php
session_start();
include "../includes/functions.php";

$type = $_GET["type"] ?? "countries";
$limit = (int)($_GET["limit"] ?? 5);
$website_id = $_SESSION["website_id"] ?? 1;

// Country flag emoji mapping (fallback)
$countryFlagsEmoji = [
    'US' => '🇺🇸', 'GB' => '🇬🇧', 'CA' => '🇨🇦', 'AU' => '🇦🇺', 'DE' => '🇩🇪',
    'FR' => '🇫🇷', 'IN' => '🇮🇳', 'BR' => '🇧🇷', 'JP' => '🇯🇵', 'CN' => '🇨🇳',
    'MX' => '🇲🇽', 'IT' => '🇮🇹', 'ES' => '🇪🇸', 'KR' => '🇰🇷', 'ID' => '🇮🇩',
    'NL' => '🇳🇱', 'SA' => '🇸🇦', 'TR' => '🇹🇷', 'CH' => '🇨🇭', 'PL' => '🇵🇱',
    'SE' => '🇸🇪', 'BE' => '🇧🇪', 'AR' => '🇦🇷', 'NO' => '🇳🇴', 'AT' => '🇦🇹',
    'AE' => '🇦🇪', 'IL' => '🇮🇱', 'IE' => '🇮🇪', 'SG' => '🇸🇬', 'DK' => '🇩🇰',
    'MY' => '🇲🇾', 'PH' => '🇵🇭', 'CO' => '🇨🇴', 'PK' => '🇵🇰', 'CL' => '🇨🇱',
    'FI' => '🇫🇮', 'BD' => '🇧🇩', 'EG' => '🇪🇬', 'VN' => '🇻🇳', 'CZ' => '🇨🇿',
    'RO' => '🇷🇴', 'PT' => '🇵🇹', 'GR' => '🇬🇷', 'NZ' => '🇳🇿', 'HU' => '🇭🇺',
    'UA' => '🇺🇦', 'DZ' => '🇩🇿', 'TH' => '🇹🇭', 'NG' => '🇳🇬', 'ZA' => '🇿🇦',
    'XX' => '🏳️'
];

function getFlagHTML($countryCode, $countryName, $emojiFlags) {
    $lowerCode = strtolower($countryCode);
    $emoji = $emojiFlags[$countryCode] ?? '🏳️';
    
    // Use flagcdn.com API with emoji fallback via onerror
    return <<<HTML
<img 
    src="https://flagcdn.com/16x12/{$lowerCode}.png" 
    srcset="https://flagcdn.com/32x24/{$lowerCode}.png 2x, https://flagcdn.com/48x36/{$lowerCode}.png 3x"
    width="16" 
    height="12" 
    alt="{$countryName}" 
    class="country-flag-img"
    onerror="this.style.display='none'; this.nextElementSibling.style.display='inline';"
>
<span class="country-flag-emoji" style="display:none;">{$emoji}</span>
HTML;
}

function getCountryData($type, $limit, $website_id, $emojiFlags) {
    // Get total count for percentage calculation
    $totalResult = fetchOne(
        "SELECT COUNT(*) as total FROM analytics WHERE website_id = ?",
        [$website_id]
    );
    $totalVisits = $totalResult['total'] ?? 1;
    
    // Determine limit
    $queryLimit = ($type === 'countries-all') ? 100 : $limit;
    
    // Get country statistics with tier info
    $countries = fetchAll(
        "SELECT 
            a.country,
            COUNT(*) as visit_count,
            COALESCE(ct.tier, 3) as tier,
            COALESCE(ct.country_name, a.country) as country_name
        FROM analytics a
        LEFT JOIN country_tiers ct ON a.country = ct.country_code
        WHERE a.website_id = ?
        GROUP BY a.country
        ORDER BY visit_count DESC
        LIMIT " . (int)$queryLimit,
        [$website_id]
    );
    
    $result = [];
    foreach ($countries as $country) {
        $percent = round(($country['visit_count'] / $totalVisits) * 100, 1);
        $flagHTML = getFlagHTML($country['country'], $country['country_name'], $emojiFlags);
        
        $result[] = [
            'name' => $country['country_name'],
            'country_code' => $country['country'],
            'flag' => $flagHTML,
            'tier' => $country['tier'],
            'value' => number_format($country['visit_count']),
            'percent' => $percent . '%'
        ];
    }
    
    return $result;
}

$data = getCountryData($type, $limit, $website_id, $countryFlagsEmoji);

// Generate country items
if (empty($data)) {
    echo '<div class="country-item"><div class="country-info">No data available</div></div>';
} else {
    foreach ($data as $country) {
        $tierClass = "tier-{$country['tier']}";
        echo <<<HTML
        <div class="country-item">
          <div class="country-info">
            <span class="country-flag">{$country['flag']}</span>
            <div class="country-details">
              <span class="country-name">{$country['name']}</span>
              <span class="country-tier {$tierClass}">Tier {$country['tier']}</span>
            </div>
          </div>
          <div class="country-stats">
            <span class="country-value">{$country['value']}</span>
            <span class="country-percent">{$country['percent']}</span>
          </div>
        </div>
        
        HTML;
    }
}
?>