<?php
require_once __DIR__ . '/env.php';
function db() {
    static $pdo;

    if ($pdo === null) {
        $host = env('DB_HOST');
        $db   = env('DB_NAME');
        $user = env('DB_USERNAME');
        $pass = env('DB_PASSWORD');

        $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    return $pdo;
}

function execQuery($sql, $params = []) {
    $stmt = db()->prepare($sql);
    return $stmt->execute($params);
}

function fetchOne($sql, $params = []) {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function fetchAll($sql, $params = []) {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function selectColumns($table, $columns = ["*"], $where = "1", $params = []) {
    $cols = implode(",", $columns);
    $sql = "SELECT {$cols} FROM {$table} WHERE {$where}";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function quickInsert($table, $data) {
    $keys = array_keys($data);
    $columns = implode(",", $keys);
    $placeholders = ":" . implode(",:", $keys);
    $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
    $stmt = db()->prepare($sql);
    return $stmt->execute($data);
}

function clean($value) {
    return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
}

function randomStr($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

function getIP() {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) return $_SERVER['HTTP_CF_CONNECTING_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}



function getGeoIPData($ip) {
    // Default fallback
    $defaultGeo = [
        'country' => 'XX',
        'country_tier' => 'tier3'
    ];
    
    // If localhost, get the actual public IP
    if ($ip === '::1' || $ip === '127.0.0.1' || $ip === '0.0.0.0') {
        $publicIp = @file_get_contents('https://api.ipify.org');
        if ($publicIp) {
            $ip = $publicIp;
            error_log("GeoIP: Localhost detected, using public IP: " . $ip);
        } else {
            error_log("GeoIP: Localhost detected but couldn't fetch public IP");
            return $defaultGeo;
        }
    }
    
    error_log("GeoIP Lookup for IP: " . $ip);
    
    // Don't lookup private IPs
    if (strpos($ip, '192.168.') === 0 || 
        strpos($ip, '10.') === 0 || 
        strpos($ip, '172.16.') === 0 ||
        strpos($ip, 'fe80:') === 0) {
        error_log("GeoIP: Skipping private IP");
        return $defaultGeo;
    }
    
    // Use ip-api.com (free, no key required, 45 requests/minute)
    $apiUrl = "http://ip-api.com/json/{$ip}?fields=status,countryCode";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    error_log("GeoIP API Response: HTTP $httpCode - " . $response);
    if ($curlError) {
        error_log("GeoIP cURL Error: " . $curlError);
    }
    
    if ($httpCode === 200 && $response) {
        $geoData = json_decode($response, true);
        if ($geoData && $geoData['status'] === 'success' && isset($geoData['countryCode'])) {
            $countryCode = $geoData['countryCode'];
            
            // Get tier from database
            $tierData = fetchOne(
                "SELECT tier FROM country_tiers WHERE country_code = ? LIMIT 1",
                [$countryCode]
            );
            
            $tier = $tierData ? 'tier' . $tierData['tier'] : 'tier3';
            
            error_log("GeoIP Success: Country=$countryCode, Tier=$tier");
            
            return [
                'country' => $countryCode,
                'country_tier' => $tier
            ];
        } else {
            error_log("GeoIP: Invalid response - " . json_encode($geoData));
        }
    }
    
    error_log("GeoIP: Returning default fallback (XX)");
    return $defaultGeo;
}


function ipHash() {
    return hash("sha256", getIP());
}

function formatTime($seconds) {
    return $seconds . "s";
}



function jsonResponse($success, $message, $data = []) {
    echo json_encode([
        "success" => $success,
        "message" => $message,
        "data" => $data
    ]);
    exit;
}

?>