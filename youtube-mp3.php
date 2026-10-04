<?php
/**
 * YouTube to MP3 Downloader
 * Yêu cầu: yt-dlp và ffmpeg cài sẵn trên server
 * Cài đặt: pip install yt-dlp / sudo apt install ffmpeg
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Thư mục lưu file MP3 tạm thời
define('OUTPUT_DIR', __DIR__ . '/downloads/');
define('BASE_URL', 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/downloads/');

// Tạo thư mục nếu chưa có
if (!is_dir(OUTPUT_DIR)) {
    mkdir(OUTPUT_DIR, 0755, true);
}

// Xóa file cũ hơn 1 giờ để tiết kiệm dung lượng
function cleanOldFiles() {
    $files = glob(OUTPUT_DIR . '*.mp3');
    foreach ($files as $file) {
        if (time() - filemtime($file) > 3600) {
            unlink($file);
        }
    }
}

// Kiểm tra yt-dlp có cài chưa
function checkYtDlp(): bool {
    exec('which yt-dlp 2>/dev/null', $out, $code);
    return $code === 0;
}

// Lấy thông tin video
function getVideoInfo(string $url): array {
    // Tách stderr ra riêng để tránh lẫn vào JSON
    $cmd = 'yt-dlp --dump-json --no-playlist ' . escapeshellarg($url) . ' 2>/dev/null';
    exec($cmd, $output, $exitCode);

    if ($exitCode !== 0 || empty($output)) {
        return ['error' => 'Không thể lấy thông tin video. Kiểm tra lại URL.'];
    }

    // Lọc lấy dòng nào là JSON hợp lệ (bắt đầu bằng '{')
    $json = null;
    foreach (array_reverse($output) as $line) {
        $line = trim($line);
        if (str_starts_with($line, '{')) {
            $json = $line;
            break;
        }
    }

    if (!$json) {
        return ['error' => 'Không thể lấy thông tin video. Kiểm tra lại URL.'];
    }

    $data = json_decode($json, true);

    if (!$data) {
        return ['error' => 'Định dạng dữ liệu không hợp lệ.'];
    }

    return [
        'title'     => $data['title'] ?? 'Unknown',
        'duration'  => $data['duration'] ?? 0,
        'uploader'  => $data['uploader'] ?? 'Unknown',
        'thumbnail' => $data['thumbnail'] ?? '',
        'view_count'=> $data['view_count'] ?? 0,
    ];
}

// Download và convert sang MP3
function downloadMp3(string $url): array {
    cleanOldFiles();

    $filename = 'yt_' . md5($url . time()) . '.mp3';
    $outputPath = OUTPUT_DIR . $filename;

    $cmd = sprintf(
        'yt-dlp -x --audio-format mp3 --audio-quality 0 --no-playlist ' .
        '-o %s %s 2>&1',
        escapeshellarg($outputPath),
        escapeshellarg($url)
    );

    exec($cmd, $output, $exitCode);

    if ($exitCode !== 0 || !file_exists($outputPath)) {
        return [
            'error' => 'Lỗi khi tải xuống. Chi tiết: ' . implode("\n", $output)
        ];
    }

    $fileSize = filesize($outputPath);
    $fileSizeMB = round($fileSize / 1048576, 2);

    return [
        'success'   => true,
        'filename'  => $filename,
        'url'       => BASE_URL . $filename,
        'size_mb'   => $fileSizeMB,
    ];
}

// Validate URL YouTube
function isValidYouTubeUrl(string $url): bool {
    return (bool) preg_match(
        '/^(https?:\/\/)?(www\.)?(youtube\.com\/(watch\?v=|shorts\/)|youtu\.be\/)[\w\-]{11}/',
        $url
    );
}

// ─── Router ────────────────────────────────────────────────────────────────

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$url    = trim($_GET['url'] ?? $_POST['url'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$action) {
    $body   = json_decode(file_get_contents('php://input'), true);
    $action = $body['action'] ?? '';
    $url    = trim($body['url'] ?? '');
}

if (!$action) {
    // Trả về trang HTML nếu truy cập trực tiếp
    header('Content-Type: text/html; charset=utf-8');
    echo file_get_contents(__DIR__ . '/youtube-mp3.html');
    exit;
}

if (!checkYtDlp()) {
    echo json_encode(['error' => 'yt-dlp chưa được cài đặt trên server. Chạy: pip install yt-dlp']);
    exit;
}

if (empty($url)) {
    echo json_encode(['error' => 'Vui lòng nhập URL YouTube.']);
    exit;
}

if (!isValidYouTubeUrl($url)) {
    echo json_encode(['error' => 'URL YouTube không hợp lệ.']);
    exit;
}

switch ($action) {
    case 'info':
        echo json_encode(getVideoInfo($url));
        break;

    case 'download':
        echo json_encode(downloadMp3($url));
        break;

    default:
        echo json_encode(['error' => 'Action không hợp lệ.']);
}
