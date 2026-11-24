<?php

/** Small imported thumbnails and references to remotely hosted Open Library covers. */
class CoverService
{
    private PDO $pdo;
    private string $root;
    private string $plexEndpoint;
    private string $plexToken;
    private $transport;
    public bool $plexUnavailable = false;
    public bool $networkUsed = false;
    private string $thumbnailError = '';
    private array $diagnostics = [];
    public const PLACEHOLDER = '/assets/brand/no-cover.png.jpg';

    public function __construct(PDO $pdo, string $root, string $plexEndpoint = '', string $plexToken = '', ?callable $transport = null)
    {
        $this->pdo = $pdo;
        $this->root = $root;
        $this->plexEndpoint = rtrim($plexEndpoint, '/');
        $this->plexToken = $plexToken;
        $this->transport = $transport;
        $pdo->exec('CREATE TABLE IF NOT EXISTS coverCache (
            book_id INTEGER PRIMARY KEY, status TEXT NOT NULL DEFAULT "pending",
            url TEXT, plex_thumb TEXT, signature TEXT, retry_at INTEGER NOT NULL DEFAULT 0,
            FOREIGN KEY(book_id) REFERENCES book(id) ON DELETE CASCADE
        )');
        $columns = $pdo->query('PRAGMA table_info(coverCache)')->fetchAll(PDO::FETCH_COLUMN, 1);
        foreach (['message', 'diagnostics'] as $column) {
            if (!in_array($column, $columns, true)) $pdo->exec('ALTER TABLE coverCache ADD COLUMN ' . $column . ' TEXT');
        }
    }

    public function rememberPlex(int $id, string $thumb): void
    {
        if (!preg_match('#^/library/metadata/\d+/thumb(?:/\d+)?$#', $thumb)) return;
        $stmt = $this->pdo->prepare('INSERT INTO coverCache(book_id, plex_thumb) VALUES (?, ?)
            ON CONFLICT(book_id) DO UPDATE SET
            signature = CASE WHEN coverCache.plex_thumb IS NOT excluded.plex_thumb THEN NULL ELSE coverCache.signature END,
            retry_at = CASE WHEN coverCache.plex_thumb IS NOT excluded.plex_thumb THEN 0 ELSE coverCache.retry_at END,
            plex_thumb = excluded.plex_thumb');
        $stmt->execute([$id, $thumb]);
    }

    private function signature(array $book): string
    {
        return hash('sha256', json_encode([$book['title'], $book['author'], $book['isbn'] ?? '', $book['url'] ?? '']));
    }

    public function cachedUrl(array $book, array $state): string
    {
        if (($state['status'] ?? '') !== 'ready' || ($state['signature'] ?? '') !== $this->signature($book)) return '';
        $url = $state['url'] ?? '';
        if (str_starts_with($url, 'https://covers.openlibrary.org/')) return $url;
        $uploaded = str_starts_with($url, '/cache/covers/upload-' . (int)$book['id'] . '-');
        return ($uploaded || !empty($book['has_imported_artwork']))
            && str_starts_with($url, '/cache/') && is_file($this->root . $url) ? $url : '';
    }

    public function forgetBook(int $id): void
    {
        $delete = $this->pdo->prepare('DELETE FROM coverCache WHERE book_id = ?');
        $delete->execute([$id]);
        $files = [$this->root . '/cache/' . $id . '.jpg',
            $this->root . '/cache/covers/' . $id . '.jpg', $this->root . '/cache/covers/' . $id . '.webp'];
        $files = array_merge($files, glob($this->root . '/cache/covers/upload-' . $id . '-*') ?: []);
        foreach ($files as $file) if (is_file($file)) @unlink($file);
    }

    private function state(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM coverCache WHERE book_id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function result(array $book, string $status, ?string $url = null, ?string $message = null): array
    {
        $delay = $status === 'deferred' ? 300 : 7 * 86400;
        $stmt = $this->pdo->prepare('INSERT INTO coverCache(book_id, status, url, signature, retry_at, message, diagnostics)
            VALUES (?, ?, ?, ?, ?, ?, ?) ON CONFLICT(book_id) DO UPDATE SET
            status = excluded.status, url = excluded.url, signature = excluded.signature, retry_at = excluded.retry_at, message = excluded.message, diagnostics = excluded.diagnostics');
        $stmt->execute([(int)$book['id'], $status, $url, $this->signature($book), time() + $delay, $message, json_encode($this->diagnostics)]);
        return ['status' => $status, 'url' => $url ?: self::PLACEHOLDER, 'message' => $message, 'cached' => false, 'diagnostics' => $this->diagnostics];
    }

    private function request(string $url, bool $plex = false): array
    {
        $this->networkUsed = true;
        if ($this->transport) {
            $response = ($this->transport)($url, $plex);
            $this->recordDiagnostics($url, $plex, $response);
            return $response;
        }
        $ch = curl_init($url);
        $headers = ['Accept: application/json'];
        if ($plex) $headers[] = 'X-Plex-Token: ' . $this->plexToken;
        $body = '';
        curl_setopt_array($ch, [
            CURLOPT_IPRESOLVE => PHP_OS_FAMILY === 'Darwin' ? CURL_IPRESOLVE_V4 : CURL_IPRESOLVE_WHATEVER,
            CURLOPT_FOLLOWLOCATION => !$plex, CURLOPT_MAXREDIRS => 3,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => $plex ? 2 : 10, CURLOPT_TIMEOUT => $plex ? 6 : 20,
            CURLOPT_USERAGENT => 'BookshelfApp/1.0', CURLOPT_HTTPHEADER => $headers,
            // Cap downloads before decoding artwork or JSON.
            CURLOPT_WRITEFUNCTION => static function ($ch, $chunk) use (&$body) {
                if (strlen($body) + strlen($chunk) > 8 * 1024 * 1024) return 0;
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $started = hrtime(true);
        $ok = curl_exec($ch);
        $elapsed = (hrtime(true) - $started) / 1e9;
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($ok === false || $status !== 200) {
            // Never log Plex URLs or tokens.
            error_log('Bookshelf cover lookup failed (' . ($plex ? 'Plex' : 'Open Library') . '): HTTP ' . $status . ', cURL ' . curl_errno($ch) . ', elapsed ' . number_format($elapsed, 3)
                . 's, DNS ' . curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME)
                . 's, connection ' . curl_getinfo($ch, CURLINFO_CONNECT_TIME) . 's');
        }
        $response = ['status' => $ok === false ? 0 : $status, 'body' => $body,
            'errno' => curl_errno($ch), 'elapsed' => $elapsed,
            'dns' => curl_getinfo($ch, CURLINFO_NAMELOOKUP_TIME),
            'connect' => curl_getinfo($ch, CURLINFO_CONNECT_TIME),
            'tls' => curl_getinfo($ch, CURLINFO_APPCONNECT_TIME)];
        $this->recordDiagnostics($url, $plex, $response);
        return $response;
    }

    private function recordDiagnostics(string $url, bool $plex, array $response): void
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        if ($plex) {
            $operation = $path === '/identity' ? 'Plex availability check'
                : (str_contains($path, '/thumb') ? 'Plex cover image download' : 'Plex title/author search');
        } else {
            $operation = $path === '/search.json' ? 'Open Library title/author search'
                : (str_starts_with($path, '/isbn/') ? 'Open Library ISBN lookup' : 'Open Library work/edition lookup');
        }
        $errno = (int)($response['errno'] ?? 0);
        $timing = static fn ($name) => isset($response[$name]) ? round((float)$response[$name], 3) : null;
        $this->diagnostics[] = [
            'time_utc' => gmdate('Y-m-d H:i:s'), 'operation' => $operation,
            'ip_resolution' => PHP_OS_FAMILY === 'Darwin' ? 'IPv4 only (macOS workaround)' : 'IPv4/IPv6 automatic',
            'http_status' => (int)$response['status'], 'curl_code' => $errno,
            'curl_reason' => $errno ? curl_strerror($errno) : null,
            'elapsed_seconds' => $timing('elapsed'), 'dns_seconds' => $timing('dns'),
            'connection_seconds' => $timing('connect'), 'tls_seconds' => $timing('tls'),
            'connection_limit_seconds' => $plex ? 2 : 10,
            'overall_limit_seconds' => $plex ? 6 : 20,
        ];
    }

    private function deferred(array $book, string $source, string $resource, array $response): array
    {
        $status = (int)$response['status'];
        if (($response['errno'] ?? 0) === CURLE_OPERATION_TIMEDOUT) {
            $duration = isset($response['elapsed']) ? ' after ' . number_format($response['elapsed'], $response['elapsed'] < 0.1 ? 3 : 1) . ' seconds' : '';
            $reason = $source . ' timed out' . $duration . ' while retrieving ' . $resource;
        } elseif (($response['errno'] ?? 0) === CURLE_COULDNT_RESOLVE_HOST) {
            $reason = 'DNS lookup failed for ' . $source . ' while retrieving ' . $resource;
        } elseif ($status === 0) {
            $reason = 'Could not retrieve ' . $resource . ' from ' . $source;
        } elseif ($source === 'Plex' && in_array($status, [401, 403], true)) {
            $reason = 'Plex rejected access to ' . $resource . ' (HTTP ' . $status . ')—check the Plex token';
        } elseif ($status === 429) {
            $reason = $source . ' rate limit reached while retrieving ' . $resource;
        } else {
            $reason = $source . ' could not provide ' . $resource . ' (HTTP ' . $status . ')';
        }
        return $this->result($book, 'deferred', null, $reason . '. Cover deferred; retry on the next run.');
    }

    /** Returns null on decode/write failure, leaving existing artwork intact. */
    public function thumbnail(int $id, string $bytes, bool $uploaded = false): ?string
    {
        $this->thumbnailError = '';
        // User-selected artwork takes precedence over future imports and automatic lookups.
        if (!$uploaded && ($manual = $this->uploaded($id)) && ($this->state($id)['url'] ?? '') === $manual) return $manual;
        if (!function_exists('imagecreatefromstring')) {
            $this->thumbnailError = 'PHP GD is unavailable, so Bookshelf cannot create a thumbnail';
            return null;
        }
        if (strlen($bytes) > 8 * 1024 * 1024) {
            $this->thumbnailError = 'the source image exceeds the 8 MB processing limit';
            return null;
        }
        $info = @getimagesizefromstring($bytes);
        if (!$info || $info[0] < 2 || $info[1] < 2 || $info[0] * $info[1] > 16000000) {
            $this->thumbnailError = 'the source image is invalid or exceeds the 16 megapixel processing limit';
            return null;
        }
        $image = @imagecreatefromstring($bytes);
        if (!$image) {
            $this->thumbnailError = 'PHP could not decode the source image';
            return null;
        }
        $scale = min(100 / $info[0], 150 / $info[1], 1);
        $width = max(1, (int)round($info[0] * $scale));
        $height = max(1, (int)round($info[1] * $scale));
        $small = imagecreatetruecolor($width, $height);
        imagefill($small, 0, 0, imagecolorallocate($small, 255, 255, 255));
        imagecopyresampled($small, $image, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);
        $directory = $this->root . '/cache/covers';
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            $this->thumbnailError = 'Bookshelf could not create cache/covers—check folder write permissions';
            return null;
        }
        $webp = function_exists('imagewebp');
        $name = $uploaded ? 'upload-' . $id . '-' . substr(hash('sha256', $bytes), 0, 12) : (string)$id;
        $relative = '/cache/covers/' . $name . ($webp ? '.webp' : '.jpg');
        $temporary = tempnam($directory, 'cover-');
        if ($temporary === false) {
            $this->thumbnailError = 'Bookshelf could not write to cache/covers—check folder write permissions';
            return null;
        }
        $ok = $webp ? imagewebp($small, $temporary, 72) : imagejpeg($small, $temporary, 75);
        unset($image, $small);
        if (!$ok || !filesize($temporary) || !@rename($temporary, $this->root . $relative)) {
            @unlink($temporary);
            $this->thumbnailError = 'Bookshelf could not encode or save the thumbnail in cache/covers—check GD support, free space and folder write permissions';
            return null;
        }
        @chmod($this->root . $relative, 0644);
        if ($uploaded) {
            foreach (glob($directory . '/upload-' . $id . '-*') ?: [] as $old) {
                if ($old !== $this->root . $relative) @unlink($old);
            }
        }
        // The legacy file is a regenerable cache, removed only after a successful conversion.
        $legacy = $this->root . '/cache/' . $id . '.jpg';
        if (is_file($legacy)) @unlink($legacy);
        return $relative;
    }

    private function uploaded(int $id): ?string
    {
        foreach (glob($this->root . '/cache/covers/upload-' . $id . '-*') ?: [] as $file) {
            if (is_file($file) && in_array(pathinfo($file, PATHINFO_EXTENSION), ['jpg', 'webp'], true)) {
                return '/cache/covers/' . basename($file);
            }
        }
        return null;
    }

    public function uploadCover(array $book, string $bytes): array
    {
        if (!function_exists('imagecreatefromstring')) throw new RuntimeException('Cover uploads require the PHP GD image extension.');
        $info = @getimagesizefromstring($bytes);
        if (strlen($bytes) > 5 * 1024 * 1024 || !$info
            || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            || $info[0] < 2 || $info[1] < 2 || $info[0] * $info[1] > 16000000) {
            throw new RuntimeException('Choose a JPEG, PNG or WebP image up to 5 MB and 16 megapixels.');
        }
        $url = $this->thumbnail((int)$book['id'], $bytes, true);
        if (!$url) throw new RuntimeException('Unable to save the cover. Check that PHP can write to cache/covers.');
        return $this->result($book, 'ready', $url);
    }

    private function local(array $book, array $state): ?string
    {
        $id = (int)$book['id'];
        if (($manual = $this->uploaded($id)) && ($state['url'] ?? '') === $manual) return $manual;
        // Only imported books can adopt numbered artwork. An ID alone is not provenance.
        if (empty($book['has_imported_artwork'])) return null;
        foreach (['webp', 'jpg'] as $extension) {
            $url = '/cache/covers/' . $id . '.' . $extension;
            if (is_file($this->root . $url)) return $url;
        }
        $legacy = '/cache/' . $id . '.jpg';
        if (is_file($this->root . $legacy)) {
            $bytes = @file_get_contents($this->root . $legacy, false, null, 0, 8 * 1024 * 1024 + 1);
            if ($bytes === false) return null;
            return $this->thumbnail($id, $bytes) ?: (@getimagesize($this->root . $legacy) ? $legacy : null);
        }
        return null;
    }

    private static function normalized(string $value): string
    {
        return preg_replace('/[^\pL\pN]+/u', '', mb_strtolower($value, 'UTF-8'));
    }

    public function resolve(array $book, bool $retryDeferred = false): array
    {
        $this->diagnostics = [];
        $this->networkUsed = false;
        $id = (int)$book['id'];
        $state = $this->state($id);
        if ($local = $this->local($book, $state)) return $this->result($book, 'ready', $local);
        if (($state['signature'] ?? '') === $this->signature($book)) {
            if (($state['status'] ?? '') === 'ready' && str_starts_with($state['url'] ?? '', 'https://covers.openlibrary.org/')) {
                return ['status' => 'ready', 'url' => $state['url']];
            }
            if (($state['status'] ?? '') !== 'ready' && ($state['retry_at'] ?? 0) > time()
                && !($retryDeferred && ($state['status'] ?? '') === 'deferred')) {
                $message = $state['message'] ?? (($state['status'] ?? '') === 'deferred'
                    ? (!empty($book['is_plex']) ? 'Plex' : 'Open Library') . ' cover lookup deferred. Retry catch-up to get the current failure reason.' : null);
                if ($state['status'] === 'deferred') $message = 'Previous attempt: ' . $message . ' No new request was made; this lookup is waiting for retry.';
                return ['status' => $state['status'], 'url' => self::PLACEHOLDER, 'cached' => true, 'message' => $message,
                    'diagnostics' => json_decode($state['diagnostics'] ?? '[]', true) ?: []];
            }
        }
        $isPlex = !empty($book['is_plex']);
        if ($isPlex) {
            if (!$this->plexEndpoint || !$this->plexToken) {
                $this->plexUnavailable = true;
                return $this->result($book, 'deferred', null, 'Plex cover lookup is not configured—set the Plex server address and token, then retry.');
            }
            if ($this->plexUnavailable) {
                return $this->result($book, 'deferred', null, 'Plex availability check failed earlier in this run. Cover lookup skipped; retry when Plex is available.');
            }
            $probe = $this->request($this->plexEndpoint . '/identity', true);
            if ($probe['status'] !== 200) {
                $this->plexUnavailable = true;
                return $this->deferred($book, 'Plex', 'server availability information', $probe);
            }
            $thumb = $state['plex_thumb'] ?? '';
            if (!$thumb) {
                $response = $this->request($this->plexEndpoint . '/hubs/search?' . http_build_query(['query' => $book['title'], 'limit' => 10]), true);
                if ($response['status'] !== 200) return $this->deferred($book, 'Plex', 'cover metadata', $response);
                $data = json_decode($response['body'], true);
                if (!is_array($data)) return $this->result($book, 'deferred', null, 'Plex returned invalid cover metadata. Cover deferred; retry on the next run.');
                $matches = [];
                foreach ($data['MediaContainer']['Hub'] ?? [] as $hub) {
                    foreach ($hub['Metadata'] ?? [] as $item) {
                        if (self::normalized($item['title'] ?? '') === self::normalized($book['title'])
                            && self::normalized($item['parentTitle'] ?? '') === self::normalized($book['author'])
                            && !empty($item['thumb'])) $matches[$item['thumb']] = $item['thumb'];
                    }
                }
                if (count($matches) !== 1) return $this->result($book, 'review');
                $thumb = array_values($matches)[0];
                $this->rememberPlex($id, $thumb);
            }
            if (!preg_match('#^/library/metadata/\d+/thumb(?:/\d+)?$#', $thumb)) return $this->result($book, 'review');
            $response = $this->request($this->plexEndpoint . $thumb, true);
            if ($response['status'] !== 200) return $this->deferred($book, 'Plex', 'the cover image', $response);
            $local = $this->thumbnail($id, $response['body']);
            return $this->result($book, $local ? 'ready' : 'deferred', $local,
                $local ? null : 'Plex cover image was retrieved, but ' . $this->thumbnailError . '. Cover deferred; retry after correcting this.');
        }

        $url = $book['url'] ?? '';
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?: '');
        if (in_array($host, ['openlibrary.org', 'www.openlibrary.org'], true)
            && preg_match('#/(works|books)/(OL\d+[WM])(?:/|$)#', parse_url($url, PHP_URL_PATH) ?: '', $match)) {
            $lookupMethod = 'Open Library link lookup';
            $lookup = 'https://openlibrary.org/' . $match[1] . '/' . $match[2] . '.json';
        } elseif (preg_match('/^(?:[0-9]{9}[0-9X]|[0-9]{13})$/i', $isbn = preg_replace('/[\s-]/', '', $book['isbn'] ?? ''))) {
            $lookupMethod = 'ISBN lookup';
            $lookup = 'https://openlibrary.org/isbn/' . rawurlencode($isbn) . '.json';
        } else {
            $lookupMethod = 'title-and-author search (no Open Library link or usable ISBN)';
            $lookup = 'https://openlibrary.org/search.json?' . http_build_query([
                'title' => $book['title'], 'author' => $book['author'], 'limit' => 5,
                'fields' => 'key,title,author_name,cover_i',
            ]);
        }
        $response = $this->request($lookup);
        if ($response['status'] !== 200) {
            return $response['status'] === 404 ? $this->result($book, 'missing')
                : $this->deferred($book, 'Open Library', 'cover metadata via ' . $lookupMethod, $response);
        }
        $data = json_decode($response['body'], true);
        if (!is_array($data)) return $this->result($book, 'deferred', null, 'Open Library returned invalid cover metadata. Cover deferred; retry on the next run.');
        $cover = null;
        if (isset($data['docs'])) {
            $matches = [];
            foreach ($data['docs'] as $doc) {
                if (self::normalized($doc['title'] ?? '') === self::normalized($book['title'])
                    && in_array(self::normalized($book['author']), array_map([self::class, 'normalized'], $doc['author_name'] ?? []), true)
                    && (int)($doc['cover_i'] ?? 0) > 0) $matches[$doc['key']] = (int)$doc['cover_i'];
            }
            if (count($matches) !== 1) return $this->result($book, empty($data['docs']) ? 'missing' : 'review');
            $cover = array_values($matches)[0];
        } else {
            foreach ($data['covers'] ?? [] as $candidate) {
                if ((int)$candidate > 0) { $cover = (int)$candidate; break; }
            }
        }
        if (!$cover) return $this->result($book, 'missing');
        return $this->result($book, 'ready', 'https://covers.openlibrary.org/b/id/' . $cover . '-S.jpg?default=false');
    }

    public function book(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT b.*, EXISTS(SELECT 1 FROM bookSource bs JOIN source s ON s.id = bs.source
            WHERE bs.book = b.id AND s.name = "Plex") OR EXISTS(SELECT 1 FROM source s WHERE s.id = b.sourceId AND s.name = "Plex") AS is_plex
            , EXISTS(SELECT 1 FROM bookSource bs JOIN source s ON s.id = bs.source
                WHERE bs.book = b.id AND s.name IN ("Plex", "Calibre"))
                OR EXISTS(SELECT 1 FROM source s WHERE s.id = b.sourceId AND s.name IN ("Plex", "Calibre")) AS has_imported_artwork
            FROM book b WHERE b.id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
