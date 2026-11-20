<?php
/**
 * PASCOM — Paróquia São Pedro | Itapetim - PE
 * API Backend para Hospedagem Compartilhada HostGator (PHP 7.4+ / 8.x)
 * Armazenamento em arquivos JSON protegidos com bloqueio de escrita (flock) — sem necessidade de configurar MySQL.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

define('BASE_DIR', __DIR__);
define('DATA_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'data');
define('LITURGY_CACHE_DIR', DATA_DIR . DIRECTORY_SEPARATOR . 'liturgy_cache');
define('UPLOADS_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'artes');
define('STATE_FILE', DATA_DIR . DIRECTORY_SEPARATOR . 'state.json');
define('OBS_FILE', DATA_DIR . DIRECTORY_SEPARATOR . 'obs_live.json');
define('DEFAULT_SCHEDULE_PASSWORD', 'pascom2026');
define('MAX_UPLOAD_BYTES', 15 * 1024 * 1024); // 15 MB

// Garante existência das pastas necessárias
foreach ([DATA_DIR, LITURGY_CACHE_DIR, UPLOADS_DIR] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

function respond(array $payload, int $status = 200): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function get_json_input(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function default_state(): array {
    return [
        'team' => [],
        'schedules' => new stdClass(),
        'customMasses' => [],
        'arts' => [],
        'priests' => [],
        'obsConfig' => null,
        'schedulePasswordHash' => '',
        'updatedAt' => time(),
    ];
}

function read_json_file(string $path, array $fallback): array {
    if (!file_exists($path)) {
        return $fallback;
    }
    $fp = @fopen($path, 'rb');
    if (!$fp) {
        return $fallback;
    }
    $data = $fallback;
    if (flock($fp, LOCK_SH)) {
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        if ($content !== false && trim($content) !== '') {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }
    }
    fclose($fp);
    return $data;
}

function write_json_file(string $path, array $data): bool {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $fp = @fopen($path, 'c+b');
    if (!$fp) {
        return false;
    }
    $ok = false;
    if (flock($fp, LOCK_EX)) {
        ftruncate($fp, 0);
        rewind($fp);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json !== false) {
            $ok = fwrite($fp, $json) !== false;
            fflush($fp);
        }
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    return $ok;
}

function load_full_state(): array {
    $st = read_json_file(STATE_FILE, default_state());
    if (!isset($st['team']) || !is_array($st['team'])) $st['team'] = [];
    if (!isset($st['schedules']) || (!is_array($st['schedules']) && !is_object($st['schedules']))) $st['schedules'] = [];
    if (!isset($st['customMasses']) || !is_array($st['customMasses'])) $st['customMasses'] = [];
    if (!isset($st['arts']) || !is_array($st['arts'])) $st['arts'] = [];
    if (!isset($st['priests']) || !is_array($st['priests'])) $st['priests'] = [];
    if (!array_key_exists('obsConfig', $st)) $st['obsConfig'] = null;
    if (!isset($st['schedulePasswordHash']) || !is_string($st['schedulePasswordHash'])) $st['schedulePasswordHash'] = '';
    return $st;
}

function public_state(array $st): array {
    $schedules = $st['schedules'];
    if (is_array($schedules) && count($schedules) === 0) {
        $schedules = new stdClass();
    }
    return [
        'team' => array_values($st['team']),
        'schedules' => $schedules,
        'customMasses' => array_values($st['customMasses']),
        'arts' => array_values($st['arts']),
        'priests' => array_values($st['priests']),
        'obsConfig' => $st['obsConfig'],
        'hasCustomPassword' => $st['schedulePasswordHash'] !== '',
        'updatedAt' => $st['updatedAt'] ?? time(),
    ];
}

function verify_schedule_password(array $st, string $candidate): bool {
    $candidate = trim($candidate);
    if ($candidate === '') {
        return false;
    }
    $hash = $st['schedulePasswordHash'] ?? '';
    if ($hash === '') {
        return hash_equals(DEFAULT_SCHEDULE_PASSWORD, $candidate);
    }
    return password_verify($candidate, $hash);
}

function fetch_external_url(string $url): ?string {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'PASCOM-SaoPedro-HostGator/1.0',
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (is_string($body) && $code >= 200 && $code < 300) {
            return $body;
        }
        return null;
    }

    if (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 8,
                'header' => "Accept: application/json\r\nUser-Agent: PASCOM-SaoPedro-HostGator/1.0\r\n",
            ],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        return is_string($body) ? $body : null;
    }

    return null;
}

$action = isset($_GET['action']) ? preg_replace('/[^a-z0-9_]/i', '', (string)$_GET['action']) : '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

switch ($action) {
    case 'ping':
        respond([
            'ok' => true,
            'serverTime' => time(),
            'dataWritable' => is_writable(DATA_DIR),
            'uploadsWritable' => is_writable(UPLOADS_DIR),
        ]);

    case 'get_state':
        $st = load_full_state();
        respond([
            'ok' => true,
            'state' => public_state($st),
        ]);

    case 'verify_password':
        if ($method !== 'POST') respond(['ok' => false, 'error' => 'Método não permitido.'], 405);
        $input = get_json_input();
        $pass = (string)($input['password'] ?? '');
        $st = load_full_state();
        $valid = verify_schedule_password($st, $pass);
        respond(['ok' => $valid]);

    case 'change_password':
        if ($method !== 'POST') respond(['ok' => false, 'error' => 'Método não permitido.'], 405);
        $input = get_json_input();
        $current = (string)($input['currentPassword'] ?? '');
        $newPass = trim((string)($input['newPassword'] ?? ''));
        $st = load_full_state();

        if (!verify_schedule_password($st, $current)) {
            respond(['ok' => false, 'error' => 'Senha atual inválida.'], 403);
        }
        if (strlen($newPass) < 3) {
            respond(['ok' => false, 'error' => 'A nova senha deve ter pelo menos 3 caracteres.'], 400);
        }

        $st['schedulePasswordHash'] = password_hash($newPass, PASSWORD_DEFAULT);
        $st['updatedAt'] = time();
        if (!write_json_file(STATE_FILE, $st)) {
            respond(['ok' => false, 'error' => 'Não foi possível salvar a nova senha no servidor.'], 500);
        }
        respond(['ok' => true]);

    case 'save_schedule':
        if ($method !== 'POST') respond(['ok' => false, 'error' => 'Método não permitido.'], 405);
        $input = get_json_input();
        $pass = (string)($input['password'] ?? '');
        $st = load_full_state();

        if (!verify_schedule_password($st, $pass)) {
            respond(['ok' => false, 'error' => 'Senha de edição inválida ou sessão expirada.'], 403);
        }

        if (isset($input['team']) && is_array($input['team'])) {
            $st['team'] = array_values($input['team']);
        }
        if (isset($input['schedules']) && is_array($input['schedules'])) {
            $st['schedules'] = $input['schedules'];
        }
        if (isset($input['customMasses']) && is_array($input['customMasses'])) {
            $st['customMasses'] = array_values($input['customMasses']);
        }
        $st['updatedAt'] = time();

        if (!write_json_file(STATE_FILE, $st)) {
            respond(['ok' => false, 'error' => 'Erro ao gravar dados da escala no servidor.'], 500);
        }
        respond(['ok' => true, 'updatedAt' => $st['updatedAt']]);

    case 'save_arts':
        if ($method !== 'POST') respond(['ok' => false, 'error' => 'Método não permitido.'], 405);
        $input = get_json_input();
        if (!isset($input['arts']) || !is_array($input['arts'])) {
            respond(['ok' => false, 'error' => 'Lista de artes inválida.'], 400);
        }
        $st = load_full_state();
        $st['arts'] = array_values($input['arts']);
        $st['updatedAt'] = time();

        if (!write_json_file(STATE_FILE, $st)) {
            respond(['ok' => false, 'error' => 'Erro ao salvar pendências de artes no servidor.'], 500);
        }
        respond(['ok' => true, 'updatedAt' => $st['updatedAt']]);

    case 'save_obs_meta':
        if ($method !== 'POST') respond(['ok' => false, 'error' => 'Método não permitido.'], 405);
        $input = get_json_input();
        $st = load_full_state();

        if (isset($input['priests']) && is_array($input['priests'])) {
            $st['priests'] = array_values($input['priests']);
        }
        if (array_key_exists('obsConfig', $input) && (is_array($input['obsConfig']) || $input['obsConfig'] === null)) {
            $st['obsConfig'] = $input['obsConfig'];
        }
        $st['updatedAt'] = time();

        if (!write_json_file(STATE_FILE, $st)) {
            respond(['ok' => false, 'error' => 'Erro ao salvar configurações da transmissão.'], 500);
        }
        respond(['ok' => true, 'updatedAt' => $st['updatedAt']]);

    case 'upload_art':
        if ($method !== 'POST') respond(['ok' => false, 'error' => 'Método não permitido.'], 405);
        if (!isset($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
            respond(['ok' => false, 'error' => 'Nenhum arquivo recebido.'], 400);
        }
        $file = $_FILES['file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            respond(['ok' => false, 'error' => 'Falha no envio do arquivo (código ' . $file['error'] . ').'], 400);
        }
        if ($file['size'] > MAX_UPLOAD_BYTES) {
            respond(['ok' => false, 'error' => 'Arquivo excede o limite de 15 MB.'], 400);
        }

        $imgInfo = @getimagesize($file['tmp_name']);
        if ($imgInfo === false) {
            respond(['ok' => false, 'error' => 'O arquivo enviado não é uma imagem válida.'], 400);
        }

        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];
        $mime = $imgInfo['mime'] ?? '';
        if (!isset($mimeToExt[$mime])) {
            respond(['ok' => false, 'error' => 'Formato de imagem não suportado. Use PNG, JPG, WebP ou GIF.'], 400);
        }

        $ext = $mimeToExt[$mime];
        $rand = bin2hex(random_bytes(4));
        $storedName = 'arte_' . date('Ymd_His') . '_' . $rand . '.' . $ext;
        $destPath = UPLOADS_DIR . DIRECTORY_SEPARATOR . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            respond(['ok' => false, 'error' => 'Não foi possível gravar a imagem em uploads/artes/.'], 500);
        }
        @chmod($destPath, 0644);

        $origName = basename((string)($file['name'] ?? $storedName));
        respond([
            'ok' => true,
            'url' => 'uploads/artes/' . $storedName,
            'filename' => $origName,
        ]);

    case 'delete_art_file':
        if ($method !== 'POST') respond(['ok' => false, 'error' => 'Método não permitido.'], 405);
        $input = get_json_input();
        $url = (string)($input['url'] ?? '');
        if (strpos($url, 'uploads/artes/') === 0) {
            $safeName = basename($url);
            $fullPath = UPLOADS_DIR . DIRECTORY_SEPARATOR . $safeName;
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }
        }
        respond(['ok' => true]);

    case 'obs_get':
        $obs = read_json_file(OBS_FILE, [
            'onAir' => false,
            'isPriest' => false,
            'tag' => '',
            'title' => '',
            'sub' => '',
            'color' => 'verde',
            'anim' => 'slide',
            'pos' => 'bottom-left',
            'scalePercent' => 100,
            'ts' => 0,
        ]);
        respond(['ok' => true, 'payload' => $obs]);

    case 'obs_set':
        if ($method !== 'POST') respond(['ok' => false, 'error' => 'Método não permitido.'], 405);
        $input = get_json_input();
        $payload = [
            'onAir' => !empty($input['onAir']),
            'isPriest' => !empty($input['isPriest']),
            'tag' => mb_substr((string)($input['tag'] ?? ''), 0, 120),
            'title' => mb_substr((string)($input['title'] ?? ''), 0, 300),
            'sub' => mb_substr((string)($input['sub'] ?? ''), 0, 400),
            'color' => in_array(($input['color'] ?? 'verde'), ['amarelo', 'verde', 'roxo', 'vermelho', 'azul'], true) ? $input['color'] : 'verde',
            'anim' => in_array(($input['anim'] ?? 'slide'), ['slide', 'fadeup', 'wipe'], true) ? $input['anim'] : 'slide',
            'pos' => in_array(($input['pos'] ?? 'bottom-left'), ['bottom-left', 'bottom-center', 'top-left', 'top-right'], true) ? $input['pos'] : 'bottom-left',
            'scalePercent' => max(50, min(150, (int)($input['scalePercent'] ?? 100))),
            'ts' => isset($input['ts']) ? (int)$input['ts'] : (int)(microtime(true) * 1000),
        ];
        if (!write_json_file(OBS_FILE, $payload)) {
            respond(['ok' => false, 'error' => 'Erro ao atualizar estado do OBS.'], 500);
        }
        respond(['ok' => true, 'payload' => $payload]);

    case 'liturgy':
        $dia = (int)($_GET['dia'] ?? date('j'));
        $mes = (int)($_GET['mes'] ?? date('n'));
        $ano = (int)($_GET['ano'] ?? date('Y'));
        if ($dia < 1 || $dia > 31 || $mes < 1 || $mes > 12 || $ano < 2000 || $ano > 2100) {
            respond(['ok' => false, 'error' => 'Data inválida.'], 400);
        }

        $dateKey = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
        $cacheFile = LITURGY_CACHE_DIR . DIRECTORY_SEPARATOR . $dateKey . '.json';
        $force = !empty($_GET['force']);

        if (!$force && is_file($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
            $cached = @file_get_contents($cacheFile);
            if ($cached !== false) {
                $decoded = json_decode($cached, true);
                if (is_array($decoded)) {
                    respond(['ok' => true, 'cached' => true, 'data' => $decoded]);
                }
            }
        }

        $apiUrl = "https://liturgia.up.railway.app/v2/?dia={$dia}&mes={$mes}&ano={$ano}";
        $raw = fetch_external_url($apiUrl);
        if ($raw !== null) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                write_json_file($cacheFile, $decoded);
                respond(['ok' => true, 'cached' => false, 'data' => $decoded]);
            }
        }

        respond(['ok' => false, 'error' => 'API de liturgia indisponível no momento.'], 502);

    default:
        respond(['ok' => false, 'error' => 'Ação inválida.'], 400);
}
