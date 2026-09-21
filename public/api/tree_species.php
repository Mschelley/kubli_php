<?php
header("Content-Type: application/json; charset=UTF-8");

$q = trim((string)($_GET['q'] ?? ''));
if($q === '' || strlen($q) < 2){
    http_response_code(400);
    echo json_encode(['error' => 'Enter at least 2 characters to search tree names.']);
    exit;
}

$q = substr($q, 0, 80);
$limit = min(8, max(1, (int)($_GET['limit'] ?? 8)));
$url = 'https://api.inaturalist.org/v1/taxa?' . http_build_query([
    'q' => $q,
    'rank' => 'species',
    'iconic_taxa' => 'Plantae',
    'is_active' => true,
    'per_page' => $limit,
    'order_by' => 'observations_count',
    'order' => 'desc'
]);

function fetchJson($url){
    if(function_exists('curl_init')){
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'User-Agent: KUBLI/1.0']
        ]);
        $response = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($response === false || $status < 200 || $status >= 300) return null;
        return json_decode($response, true);
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 6,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\nUser-Agent: KUBLI/1.0\r\n"
        ]
    ]);
    $response = @file_get_contents($url, false, $context);
    if($response === false) return null;
    return json_decode($response, true);
}

$data = fetchJson($url);
if(!is_array($data)){
    http_response_code(502);
    echo json_encode(['error' => 'Tree species service unavailable.']);
    exit;
}

$results = [];
$seen = [];
foreach(($data['results'] ?? []) as $item){
    $id = (int)($item['id'] ?? 0);
    $scientificName = trim((string)($item['name'] ?? ''));
    $commonName = trim((string)(($item['preferred_common_name'] ?? '') ?: ($item['common_name'] ?? '')));
    $imageUrl = trim((string)(($item['default_photo']['medium_url'] ?? '') ?: ($item['default_photo']['small_url'] ?? '')));
    if(!$id || $scientificName === '' || $commonName === '' || isset($seen[strtolower($commonName)])) continue;
    $seen[strtolower($commonName)] = true;
    $results[] = [
        'id' => $id,
        'common_name' => $commonName,
        'scientific_name' => $scientificName,
        'image_url' => $imageUrl
    ];
}

$queryLower = strtolower($q);
usort($results, function($a, $b) use ($queryLower){
    $score = function($name) use ($queryLower){
        $name = strtolower($name);
        if($name === $queryLower) return 0;
        if(str_starts_with($name, $queryLower)) return 1;
        if(str_contains($name, $queryLower)) return 2;
        return 3;
    };
    return ($score($a['common_name']) <=> $score($b['common_name'])) ?: strcasecmp($a['common_name'], $b['common_name']);
});

echo json_encode(['results' => $results], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
