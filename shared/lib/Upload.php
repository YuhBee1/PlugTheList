<?php
declare(strict_types=1);

namespace PTL;

   
                                                                                                 
                                                                                                                        
   
final class Upload
{
    private const MAX_BYTES = 4 * 1024 * 1024;

                                                          
    public static function image(string $field, int $ownerId, string $kind, ?int $refId = null): array
    {
        $f = $_FILES[$field] ?? null;
        if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [null, null];
        }
        if (is_array($f['error']) || $f['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string)$f['tmp_name']) && !defined('PTL_TESTING')) {
            return [null, 'The upload failed. Try a smaller image.'];
        }
        if ((int)$f['size'] > self::MAX_BYTES) {
            return [null, 'Images must be 4 MB or smaller.'];
        }
        if (RateLimiter::over('upload', (string)$ownerId, 30, 3600)) {
            return [null, 'Too many uploads. Try again later.'];
        }
        RateLimiter::hit('upload', (string)$ownerId, 3600);
        $info = @getimagesize((string)$f['tmp_name']);
        if ($info === false || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true) || $info[0] > 6000 || $info[1] > 6000 || $info[0] < 50) {
            return [null, 'Use a JPG, PNG or WebP image (at least 50 pixels wide).'];
        }
        if (!extension_loaded('gd')) {
            return [null, 'Image processing is not available on this server.'];
        }
        $src = match ($info[2]) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg((string)$f['tmp_name']),
            IMAGETYPE_PNG => @imagecreatefrompng((string)$f['tmp_name']),
            default => @imagecreatefromwebp((string)$f['tmp_name']),
        };
        if (!$src) {
            return [null, 'That image could not be read.'];
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $max = 1600;
        if ($w > $max || $h > $max) {
            $r = min($max / $w, $max / $h);
            $nw = max(1, (int)floor($w * $r));
            $nh = max(1, (int)floor($h * $r));
            $dst = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            $src = $dst;
        }
        $dir = PTL_STORAGE . '/uploads/' . date('Y/m');
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            return [null, 'Storage is not writable.'];
        }
        $name = bin2hex(random_bytes(16)) . '.jpg';
        $bg = imagecreatetruecolor(imagesx($src), imagesy($src));
        imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
        imagecopy($bg, $src, 0, 0, 0, 0, imagesx($src), imagesy($src));
        $ok = imagejpeg($bg, $dir . '/' . $name, 85);
        if (!$ok) {
            return [null, 'Could not save the image.'];
        }
        $id = DB::insert('files', ['owner_id' => $ownerId, 'kind' => $kind, 'ref_id' => $refId, 'path' => date('Y/m') . '/' . $name, 'mime' => 'image/jpeg', 'size' => (int)filesize($dir . '/' . $name)]);
        return [$id, null];
    }

                                                                                                            
    public static function canView(array $file, ?array $user): bool
    {
        if ($file['kind'] === 'cover' || $file['kind'] === 'proof') {
            $l = DB::one('SELECT status FROM listings WHERE cover_file_id = ? OR id = ?', [$file['id'], (int)$file['ref_id']]);
            if ($file['kind'] === 'cover' && $l !== null && $l['status'] === 'approved') {
                return true;
            }
            if ($file['kind'] === 'proof') {
                $pl = DB::one('SELECT l.status FROM listing_proofs p JOIN listings l ON l.id = p.listing_id WHERE p.file_id = ?', [$file['id']]);
                if ($pl !== null && $pl['status'] === 'approved') {
                    return true;
                }
            }
        }
        if ($user === null) {
            return false;
        }
        if ($user['role'] === 'admin' || (int)$file['owner_id'] === (int)$user['id']) {
            return true;
        }
        if ($file['kind'] === 'delivery') {
            return DB::one('SELECT id FROM orders WHERE delivery_file_id = ? AND (creative_id = ? OR curator_id = ?)', [$file['id'], $user['id'], $user['id']]) !== null;
        }
        return false;
    }

    public static function serve(int $id, ?array $user): never
    {
        $f = DB::one('SELECT * FROM files WHERE id = ?', [$id]);
        if ($f === null || !self::canView($f, $user)) {
            abort(404);
        }
        $path = PTL_STORAGE . '/uploads/' . $f['path'];
        if (!preg_match('#^\d{4}/\d{2}/[a-f0-9]{32}\.jpg$#', (string)$f['path']) || !is_file($path)) {
            abort(404);
        }
        header_remove('Content-Type');
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: ' . ($f['kind'] === 'cover' ? 'public, max-age=3600' : 'private, max-age=600'));
        header("Content-Security-Policy: default-src 'none'; img-src 'self'");
        readfile($path);
        exit;
    }
}
