<?php
/**
 * YouTube to MP3 Downloader
 * Yêu cầu: yt-dlp + ffmpeg
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

define('OUTPUT_DIR', __DIR__ . '/downloads/');
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
    . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/') . '/downloads/');

if (!is_dir(OUTPUT_DIR)) {
    mkdir(OUTPUT_DIR, 0777, true);
}

// ── Chuyển tên file sang slug không dấu ──────────────────────────────────────
function slugify(string $text): string {
    $from = [
        'à','á','ạ','ả','ã','â','ầ','ấ','ậ','ẩ','ẫ','ă','ằ','ắ','ặ','ẳ','ẵ',
        'è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ',
        'ì','í','ị','ỉ','ĩ',
        'ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ',
        'ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ',
        'ỳ','ý','ỵ','ỷ','ỹ','đ',
        'À','Á','Ạ','Ả','Ã','Â','Ầ','Ấ','Ậ','Ẩ','Ẫ','Ă','Ằ','Ắ','Ặ','Ẳ','Ẵ',
        'È','É','Ẹ','Ẻ','Ẽ','Ê','Ề','Ế','Ệ','Ể','Ễ',
        'Ì','Í','Ị','Ỉ','Ĩ',
        'Ò','Ó','Ọ','Ỏ','Õ','Ô','Ồ','Ố','Ộ','Ổ','Ỗ','Ơ','Ờ','Ớ','Ợ','Ở','Ỡ',
        'Ù','Ú','Ụ','Ủ','Ũ','Ư','Ừ','Ứ','Ự','Ử','Ữ',
        'Ỳ','Ý','Ỵ','Ỷ','Ỹ','Đ',
    ];
    $to = [
        'a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a',
        'e','e','e','e','e','e','e','e','e','e','e',
        'i','i','i','i','i',
        'o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o',
        'u','u','u','u','u','u','u','u','u','u','u',
        'y','y','y','y','y','d',
        'A','A','A','A','A','A','A','A','A','A','A','A','A','A','A','A','A',
        'E','E','E','E','E','E','E','E','E','E','E',
        'I','I','I','I','I',
        'O','O','O','O','O','O','O','O','O','O','O','O','O','O','O','O','O',
        'U','U','U','U','U','U','U','U','U','U','U',
        'Y','Y','Y','Y','Y','D',
    ];
    $text = str_replace($from, $to, $text);
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s\-]/', '', $text);
    $text = preg_replace('/[\s\-]+/', '-', trim($text));
    return trim($text, '-') ?: 'audio';
}

// ── Xóa file MP3 cũ hơn 1 giờ ───────────────────────────────────────────────
function cleanOldFiles(): void {
    foreach (glob(OUTPUT_DIR . '*.mp3') as $file) {
        if (time() - filemtime($file) > 3600) {
            @unlink($file);
        }
    }
}

// ── Kiểm tra yt-dlp ──────────────────────────────────────────────────────────
function checkYtDlp(): bool {
    exec('which yt-dlp 2>/dev/null', $out, $code);
    return $code === 0;
}

// ── Lấy thông tin video ──────────────────────────────────────────────────────
function getVideoInfo(string $url): array {
    $cmd = 'yt-dlp --dump-json --no-playlist ' . escapeshellarg($url) . ' 2>/dev/null';
    exec($cmd, $output, $exitCode);

    if ($exitCode !== 0 || empty($output)) {
        return ['error' => 'Không thể lấy thông tin video. Kiểm tra lại URL.'];
    }

    // Lọc lấy dòng JSON hợp lệ (bắt đầu bằng '{')
    $json = null;
    foreach (array_reverse($output) as $line) {
        $line = trim($line);
        if (str_starts_with($line, '{')) {
            $json = $line;
            break;
        }
    }

    if (!$json) {
        return ['error' => 'Không parse được dữ liệu từ yt-dlp.'];
    }

    $data = json_decode($json, true);
    if (!$data) {
        return ['error' => 'JSON không hợp lệ: ' . json_last_error_msg()];
    }

    return [
        'title'      => $data['title']      ?? 'Unknown',
        'duration'   => $data['duration']   ?? 0,
        'uploader'   => $data['uploader']   ?? 'Unknown',
        'thumbnail'  => $data['thumbnail']  ?? '',
        'view_count' => $data['view_count'] ?? 0,
    ];
}

// ── Download và convert sang MP3 ─────────────────────────────────────────────
function downloadMp3(string $url, string $title = ''): array {
    cleanOldFiles();

    $slug       = $title ? slugify($title) : ('yt-' . md5($url . time()));
    $filename   = $slug . '.mp3';
    $outputPath = OUTPUT_DIR . $filename;

    // Nếu file đã tồn tại (cùng bài) thì trả về luôn
    if (file_exists($outputPath)) {
        return [
            'success'  => true,
            'filename' => $filename,
            'url'      => BASE_URL . $filename,
            'size_mb'  => round(filesize($outputPath) / 1048576, 2),
        ];
    }

    $timeBefore = time() - 2;

    $cmd = sprintf(
        'yt-dlp -x --audio-format mp3 --audio-quality 0 --no-playlist -o %s %s 2>&1',
        escapeshellarg($outputPath),
        escapeshellarg($url)
    );

    exec($cmd, $output, $exitCode);

    // Tìm file MP3 được tạo ra sau khi chạy lệnh (phòng yt-dlp đổi tên)
    $mp3Files = array_filter(
        glob(OUTPUT_DIR . '*.mp3') ?: [],
        fn($f) => filemtime($f) >= $timeBefore
    );

    if (empty($mp3Files)) {
        return ['error' => 'Lỗi khi tải xuống: ' . implode("\n", $output)];
    }

    usort($mp3Files, fn($a, $b) => filemtime($b) - filemtime($a));
    $realFile = array_values($mp3Files)[0];
    $filename = basename($realFile);

    return [
        'success'  => true,
        'filename' => $filename,
        'url'      => BASE_URL . $filename,
        'size_mb'  => round(filesize($realFile) / 1048576, 2),
    ];
}

// ── Validate URL YouTube ──────────────────────────────────────────────────────
function isValidYouTubeUrl(string $url): bool {
    return (bool) preg_match(
        '/^(https?:\/\/)?(www\.)?(youtube\.com\/(watch\?v=|shorts\/)|youtu\.be\/)[\w\-]{11}/',
        $url
    );
}

// ── Router ────────────────────────────────────────────────────────────────────
$body   = [];
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$url    = trim($_GET['url'] ?? $_POST['url'] ?? '');
$title  = trim($_GET['title'] ?? $_POST['title'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$action) {
    $body   = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $body['action'] ?? '';
    $url    = trim($body['url'] ?? '');
    $title  = trim($body['title'] ?? '');
}

// Trả về trang HTML nếu truy cập trực tiếp không có action
if (!$action) {
    header('Content-Type: text/html; charset=utf-8');
    $htmlFile = __DIR__ . '/youtube-mp3.html';
    if (file_exists($htmlFile)) {
        readfile($htmlFile);
    } else {
        echo '<h1>youtube-mp3.html not found</h1>';
    }
    exit;
}

if (!checkYtDlp()) {
    echo json_encode(['error' => 'yt-dlp chưa được cài đặt trên server.']);
    exit;
}

if (empty($url)) {
    echo json_encode(['error' => 'Vui lòng nhập URL YouTube.']);
    exit;
}

if (!isValidYouTubeUrl($url)) {
    echo json_encode(['error' => 'URL YouTube không hợp lệ. Chỉ hỗ trợ youtube.com và youtu.be.']);
    exit;
}

switch ($action) {
    case 'info':
        echo json_encode(getVideoInfo($url));
        break;
    case 'download':
        echo json_encode(downloadMp3($url, $title));
        break;
    default:
        echo json_encode(['error' => 'Action không hợp lệ.']);
}
