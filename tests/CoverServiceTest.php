<?php
require __DIR__ . '/../src/lib/CoverService.php';

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
$root = sys_get_temp_dir() . '/bookshelf-covers-' . bin2hex(random_bytes(6));
mkdir($root . '/cache', 0755, true);
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE book(id INTEGER PRIMARY KEY, title TEXT, author TEXT, isbn TEXT, url TEXT, sourceId INT);
    CREATE TABLE source(id INTEGER PRIMARY KEY, name TEXT);
    CREATE TABLE bookSource(book INT, source INT);
    INSERT INTO source VALUES(1,"Plex"),(2,"Manual");');
$calls = [];
$responses = [];
$transport = function ($url, $plex) use (&$calls, &$responses) {
    $calls[] = [$url, $plex];
    if (!$responses) throw new RuntimeException('Unexpected network request: ' . $url);
    return array_shift($responses);
};
$service = new CoverService($pdo, $root, 'http://plex.test:32400', 'private-token', $transport);
$insert = $pdo->prepare('INSERT INTO book VALUES(?,?,?,?,?,?)');
for ($id = 1; $id <= 18; $id++) $insert->execute([$id, 'A Christmas Carol', 'Charles Dickens', '', '', 2]);
$pdo->exec('UPDATE book SET sourceId=1 WHERE id IN (1,2,3,4)');

try {
    // One offline probe, then skip remaining Plex books without repeated timeouts.
    $responses = [['status' => 0, 'body' => '']];
    check($service->resolve($service->book(1))['status'] === 'deferred', 'Offline Plex must defer');
    check($service->resolve($service->book(2))['status'] === 'deferred', 'Next offline Plex book must defer');
    check(count($calls) === 1, 'Offline Plex must only be probed once per batch run');
    check($pdo->query('SELECT status FROM coverCache WHERE book_id=1')->fetchColumn() === 'deferred', 'Offline is not missing');

    // A new run can retry a deferred Plex book immediately using a saved reference.
    $service = new CoverService($pdo, $root, 'http://plex.test:32400', 'private-token', $transport);
    $service->rememberPlex(1, '/library/metadata/123/thumb/456');
    if (function_exists('imagecreatetruecolor')) {
        $image = imagecreatetruecolor(600, 600);
        imagefill($image, 0, 0, imagecolorallocate($image, 50, 90, 180));
        ob_start(); imagejpeg($image); $art = ob_get_clean(); unset($image);
        $responses = [['status'=>200,'body'=>'{}'], ['status'=>200,'body'=>$art]];
        $resolved = $service->resolve($service->book(1), true);
        check($resolved['status'] === 'ready', 'Online retry must produce artwork');
        $info = getimagesize($root . $resolved['url']);
        check($info[0] === 100 && $info[1] === 100, 'Square audiobooks keep proportions');
        check(filesize($root . $resolved['url']) < 25000, 'Small thumbnail size');
        $before = count($calls);
        $service->plexUnavailable = true;
        check($service->resolve($service->book(1))['status'] === 'ready', 'Cached Plex works offline');
        check(count($calls) === $before, 'Cached artwork must not contact Plex');

        // Legacy cache migration replaces only after thumbnail is successfully written.
        file_put_contents($root . '/cache/3.jpg', $art);
        $legacy = $service->resolve($service->book(3));
        check($legacy['status'] === 'ready' && !file_exists($root . '/cache/3.jpg'), 'Legacy artwork compacted');
        file_put_contents($root . '/cache/4.jpg', 'invalid image');
        $service->resolve($service->book(4));
        check(file_exists($root . '/cache/4.jpg'), 'Failed conversion preserves original');
        check($service->thumbnail(5, 'invalid image') === null, 'Reject non-images');
        $image = imagecreatetruecolor(400, 800);
        ob_start(); imagejpeg($image); $portrait = ob_get_clean(); unset($image);
        $thumb = $service->thumbnail(12, $portrait);
        $info = getimagesize($root . $thumb);
        check($info[0] === 75 && $info[1] === 150, 'Portrait artwork keeps proportions');
    }

    // Open Library work links resolve to cover IDs without downloading the cover image.
    $pdo->exec("UPDATE book SET url='https://openlibrary.org/works/OL45029299W/A_Christmas_Carol' WHERE id=5");
    $responses = [['status'=>200,'body'=>'{"covers":[-1,15196667]}']];
    $found = $service->resolve($service->book(5));
    check($found['url'] === 'https://covers.openlibrary.org/b/id/15196667-S.jpg?default=false', 'Use stable cover ID');
    check(!is_dir($root . '/cache/covers/5'), 'No remote cover downloaded');
    $before = count($calls);
    $service->resolve($service->book(5));
    check(count($calls) === $before, 'Persisted Open Library reference reused');

    // Conservative matching: several works with the same name are ambiguous.
    $doc = ['key'=>'/works/OL1W','title'=>'A Christmas Carol','author_name'=>['Charles Dickens'],'cover_i'=>42];
    $responses = [['status'=>200,'body'=>json_encode(['docs'=>[$doc]])]];
    check($service->resolve($service->book(6))['status'] === 'ready', 'Unique exact match accepted');
    $doc2 = $doc; $doc2['key'] = '/works/OL2W';
    $responses = [['status'=>200,'body'=>json_encode(['docs'=>[$doc,$doc2]])]];
    check($service->resolve($service->book(7))['status'] === 'review', 'Ambiguous match needs review');
    $before = count($calls);
    $service->resolve($service->book(7));
    check(count($calls) === $before, 'Failed lookups negatively cached');
    // Editing identifying fields invalidates an earlier failed lookup.
    $pdo->exec("UPDATE book SET isbn='9781234567890' WHERE id=7");
    $responses = [['status'=>200,'body'=>'{"covers":[99]}']];
    check($service->resolve($service->book(7))['status'] === 'ready', 'Changed ISBN triggers new lookup');
    check(str_contains(end($calls)[0], '/isbn/9781234567890.json'), 'ISBN metadata lookup');
    $responses = [['status'=>200,'body'=>'{"docs":[]}']];
    check($service->resolve($service->book(8))['status'] === 'missing', 'No matches recorded');
    $responses = [['status'=>503,'body'=>'busy']];
    check($service->resolve($service->book(9))['status'] === 'deferred', 'Transient OL failure deferred');
    if (function_exists('imagecreatetruecolor')) {
        $before = count($calls);
        $uploaded = $service->uploadCover($service->book(7), $art);
        check(str_contains($uploaded['url'], '/upload-7-'), 'Uploaded artwork has a persistent override path');
        check($service->resolve($service->book(7))['url'] === $uploaded['url'], 'Upload takes priority over Open Library');
        check($service->thumbnail(7, $portrait) === $uploaded['url'], 'Imports cannot overwrite an uploaded cover');
        check(count($calls) === $before, 'Uploaded covers need no remote lookup');
        $rejected = false;
        try {
            $service->uploadCover($service->book(7), '<script>invalid image</script>');
        } catch (RuntimeException $e) {
            $rejected = true;
        }
        check($rejected, 'Non-images must be rejected');
        check($service->resolve($service->book(7))['url'] === $uploaded['url'], 'Invalid upload preserves existing artwork');
        $replacement = $service->uploadCover($service->book(7), $portrait);
        check($replacement['url'] !== $uploaded['url'], 'New upload uses a fresh URL to avoid stale browser caches');
        check(!file_exists($root . $uploaded['url']), 'Superseded uploaded thumbnail is cleaned up');
        $pdo->exec("UPDATE book SET title='Edited book title' WHERE id=7");
        check($service->resolve($service->book(7))['url'] === $replacement['url'], 'Book edits preserve the uploaded cover');
    }
    $responses = [['status'=>0,'body'=>'','errno'=>CURLE_OPERATION_TIMEDOUT]];
    $timedOut = $service->resolve($service->book(13));
    check(str_contains($timedOut['message'], 'Open Library timed out'), 'Timeout names Open Library');
    $before = count($calls);
    check($service->resolve($service->book(13))['message'] === $timedOut['message'], 'Cached deferral retains its failure reason');
    check(count($calls) === $before, 'Displaying cached failure does not perform another request');
    $responses = [['status'=>503,'body'=>'busy']];
    check(str_contains($service->resolve($service->book(14))['message'], 'Open Library could not provide cover metadata (HTTP 503)'), 'HTTP failures name source and resource');
    $responses = [['status'=>429,'body'=>'busy']];
    check(str_contains($service->resolve($service->book(15))['message'], 'Open Library rate limit'), 'Rate limits named');
    $responses = [['status'=>200,'body'=>'invalid']];
    check(str_contains($service->resolve($service->book(16))['message'], 'Open Library returned invalid'), 'Invalid JSON named');
    $pdo->exec('UPDATE book SET sourceId=1 WHERE id IN (17,18)');
    $service = new CoverService($pdo, $root, 'http://plex.test:32400', 'private-token', $transport);
    $responses = [['status'=>401,'body'=>'unauthorized']];
    check(str_contains($service->resolve($service->book(17))['message'], 'Plex rejected access'), 'Plex access rejection named');
    check(str_contains($service->resolve($service->book(18))['message'], 'Plex availability check failed earlier'), 'Skipped Plex requests explain why');
    if (function_exists('imagecreatetruecolor')) {
        $service = new CoverService($pdo, $root, 'http://plex.test:32400', 'private-token', $transport);
        $service->rememberPlex(18, '/library/metadata/456/thumb/789');
        $responses = [['status'=>200,'body'=>'{}'], ['status'=>200,'body'=>'not an image']];
        check(str_contains($service->resolve($service->book(18), true)['message'], 'Plex cover image was retrieved, but the source image is invalid'), 'Image processing errors are distinguished from unavailable servers');
    }
    check($responses === [], 'All expected requests consumed');
    echo "PASS: offline Plex deferral/retry, local cache reuse, thumbnail sizes/aspect ratios, safe legacy conversion, Open Library references, conservative matching, retry/invalidation, validated uploads, source-specific failure reasons and cached messages.\n";
} finally {
    foreach (glob($root . '/cache/covers/*') ?: [] as $file) unlink($file);
    foreach (glob($root . '/cache/*.jpg') ?: [] as $file) unlink($file);
    if (is_dir($root . '/cache/covers')) rmdir($root . '/cache/covers');
    rmdir($root . '/cache'); rmdir($root);
}
