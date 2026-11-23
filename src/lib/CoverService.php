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
    }

    public function rememberPlex(int $id, string $thumb): void
    {
        if (!preg_match('#^/library/metadata/\d+/thumb(?:/\d+)?$#', $thumb)) return;
        $stmt = $this->pdo->prepare('INSERT INTO coverCache(book_id, plex_thumb) VALUES (?, ?)
            ON CONFLICT(book_id) DO UPDATE SET plex_thumb = excluded.plex_thumb');
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
        return str_starts_with($url, '/cache/') && is_file($this->root . $url) ? $url : '';
    }

    private function state(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM coverCache WHERE book_id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function result(array $book, string $status, ?string $url = null): array
    {
        $delay = $status === 'deferred' ? 300 : 7 * 86400;
        $stmt = $this->pdo->prepare('INSERT INTO coverCache(book_id, status, url, signature, retry_at)
            VALUES (?, ?, ?, ?, ?) ON CONFLICT(book_id) DO UPDATE SET
            status = excluded.status, url = excluded.url, signature = excluded.signature, retry_at = excluded.retry_at');
        $stmt->execute([(int)$book['id'], $status, $url, $this->signature($book), time() + $delay]);
        return ['status' => $status, 'url' => $url ?: self::PLACEHOLDER];
    }

    private function request(string $url, bool $plex = false): array
    {
        $this->networkUsed = true;
        if ($this->transport) return ($this->transport)($url, $plex);
        $ch = curl_init($url);
        $headers = ['Accept: application/json'];
        if ($plex) $headers[] = 'X-Plex-Token: ' . $this->plexToken;
        $body = '';
        curl_setopt_array($ch, [
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_FOLLOWLOCATION => !$plex, CURLOPT_MAXREDIRS => 3,
            CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 6,
            CURLOPT_USERAGENT => 'BookshelfApp/1.0', CURLOPT_HTTPHEADER => $headers,
            // Cap downloads before decoding artwork or JSON.
            CURLOPT_WRITEFUNCTION => static function ($ch, $chunk) use (&$body) {
                if (strlen($body) + strlen($chunk) > 8 * 1024 * 1024) return 0;
                $body .= $chunk;
                return strlen($chunk);
            },
        ]);
        $ok = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($ok === false || $status !== 200) {
            // Never log Plex URLs or tokens.
            error_log('Bookshelf cover lookup failed (' . ($plex ? 'Plex' : 'Open Library') . '): HTTP ' . $status . ', cURL ' . curl_errno($ch));
        }
        return ['status' => $ok === false ? 0 : $status, 'body' => $body];
    }

    /** Returns null on decode/write failure, leaving existing artwork intact. */
    public function thumbnail(int $id, string $bytes): ?string
    {
        if (!function_exists('imagecreatefromstring') || strlen($bytes) > 8 * 1024 * 1024) return null;
        $info = @getimagesizefromstring($bytes);
        if (!$info || $info[0] < 2 || $info[1] < 2 || $info[0] * $info[1] > 16000000) return null;
        $image = @imagecreatefromstring($bytes);
        if (!$image) return null;
        $scale = min(100 / $info[0], 150 / $info[1], 1);
        $width = max(1, (int)round($info[0] * $scale));
        $height = max(1, (int)round($info[1] * $scale));
        $small = imagecreatetruecolor($width, $height);
        imagefill($small, 0, 0, imagecolorallocate($small, 255, 255, 255));
        imagecopyresampled($small, $image, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);
        $directory = $this->root . '/cache/covers';
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) return null;
        $webp = function_exists('imagewebp');
        $relative = '/cache/covers/' . $id . ($webp ? '.webp' : '.jpg');
        $temporary = tempnam($directory, 'cover-');
        if ($temporary === false) return null;
        $ok = $webp ? imagewebp($small, $temporary, 72) : imagejpeg($small, $temporary, 75);
        unset($image, $small);
        if (!$ok || !filesize($temporary) || !@rename($temporary, $this->root . $relative)) {
            @unlink($temporary);
            return null;
        }
        @chmod($this->root . $relative, 0644);
        // The legacy file is a regenerable cache, removed only after a successful conversion.
        $legacy = $this->root . '/cache/' . $id . '.jpg';
        if (is_file($legacy)) @unlink($legacy);
        return $relative;
    }

    private function local(int $id): ?string
    {
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
        $id = (int)$book['id'];
        $state = $this->state($id);
        if ($local = $this->local($id)) return $this->result($book, 'ready', $local);
        if (($state['signature'] ?? '') === $this->signature($book)) {
            if (($state['status'] ?? '') === 'ready' && str_starts_with($state['url'] ?? '', 'https://covers.openlibrary.org/')) {
                return ['status' => 'ready', 'url' => $state['url']];
            }
            if (($state['retry_at'] ?? 0) > time()
                && !($retryDeferred && ($state['status'] ?? '') === 'deferred')) {
                return ['status' => $state['status'], 'url' => self::PLACEHOLDER];
            }
        }
        $isPlex = !empty($book['is_plex']);
        if ($isPlex) {
            if ($this->plexUnavailable || !$this->plexEndpoint || !$this->plexToken) {
                $this->plexUnavailable = true;
                return $this->result($book, 'deferred');
            }
            $probe = $this->request($this->plexEndpoint . '/identity', true);
            if ($probe['status'] !== 200) {
                $this->plexUnavailable = true;
                return $this->result($book, 'deferred');
            }
            $thumb = $state['plex_thumb'] ?? '';
            if (!$thumb) {
                $response = $this->request($this->plexEndpoint . '/hubs/search?' . http_build_query(['query' => $book['title'], 'limit' => 10]), true);
                if ($response['status'] !== 200) return $this->result($book, 'deferred');
                $data = json_decode($response['body'], true);
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
            if ($response['status'] !== 200) return $this->result($book, 'deferred');
            $local = $this->thumbnail($id, $response['body']);
            return $this->result($book, $local ? 'ready' : 'deferred', $local);
        }

        $url = $book['url'] ?? '';
        $host = strtolower(parse_url($url, PHP_URL_HOST) ?: '');
        if (in_array($host, ['openlibrary.org', 'www.openlibrary.org'], true)
            && preg_match('#/(works|books)/(OL\d+[WM])(?:/|$)#', parse_url($url, PHP_URL_PATH) ?: '', $match)) {
            $lookup = 'https://openlibrary.org/' . $match[1] . '/' . $match[2] . '.json';
        } elseif ($isbn = preg_replace('/[^0-9X]/i', '', $book['isbn'] ?? '')) {
            $lookup = 'https://openlibrary.org/isbn/' . rawurlencode($isbn) . '.json';
        } else {
            $lookup = 'https://openlibrary.org/search.json?' . http_build_query([
                'title' => $book['title'], 'author' => $book['author'], 'limit' => 5,
                'fields' => 'key,title,author_name,cover_i',
            ]);
        }
        $response = $this->request($lookup);
        if ($response['status'] !== 200) return $this->result($book, $response['status'] === 404 ? 'missing' : 'deferred');
        $data = json_decode($response['body'], true);
        if (!is_array($data)) return $this->result($book, 'deferred');
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
            FROM book b WHERE b.id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}
