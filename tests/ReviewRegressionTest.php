<?php
// Exercise the actual route bodies against a disposable database, without booting the application.
$source = file_get_contents(__DIR__ . '/../src/index.php');
function expectReview(bool $ok, string $description): void {
    if (!$ok) throw new RuntimeException($description);
}
function routeBody(string $source, string $from, string $until): string {
    $start = strpos($source, $from) + strlen($from);
    $body = substr($source, $start, strpos($source, $until, $start) - $start);
    $body = preg_replace('/exit\([^;]*\);/', 'return;', $body);
    return str_replace(['exit;', 'break;'], ['return;', 'return;'], $body);
}
$deleteBody = routeBody($source, "    case 'deleteReview':", "    case 'addReview':");
$saveBody = routeBody($source, "    case 'updateReview':", "    case 'manualAdd':");
$legacyBody = routeBody($source, "    case 'dateChange':", "    case 'stats':");
$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('CREATE TABLE review(id INTEGER PRIMARY KEY, dateRead TEXT, rating FLOAT, review TEXT);
    CREATE TABLE bookReview(book_id INT, review_id INT, PRIMARY KEY(book_id,review_id));');
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SESSION = ['coverToken'=>'test-token'];
$save = function (string $cmd) use ($pdo, $saveBody) { eval($saveBody); };
$delete = function () use ($pdo, $deleteBody) { eval($deleteBody); };
$date = function () use ($pdo, $legacyBody) { eval($legacyBody); };
$_POST = ['bookId'=>1,'date'=>date('Y-m-d'),'rating'=>'0','comment'=>'  '];
$save('addReview');
expectReview((int)$pdo->query('SELECT COUNT(*) FROM review')->fetchColumn() === 0, 'Reject blank manual reviews');
$_POST['rating'] = '4.5';
$save('addReview');
expectReview((int)$pdo->query('SELECT COUNT(*) FROM review')->fetchColumn() === 1, 'A rating-only review is valid');
$_POST['reviewId'] = 1; $_POST['rating'] = '0';
$save('updateReview');
expectReview((float)$pdo->query('SELECT rating FROM review WHERE id=1')->fetchColumn() === 4.5, 'Reject blank edits without erasing the saved review');
$_POST = ['name'=>2,'value'=>date('Y-m-d')];
ob_start(); $date(); ob_end_clean();
expectReview((int)$pdo->query('SELECT COUNT(*) FROM review')->fetchColumn() === 1, 'Old date-blur endpoint cannot add a review');
// Existing blank review plus a second book's legitimate review.
$pdo->exec("INSERT INTO review VALUES(2,'2026-11-23',0,''),(3,'2026-11-23',5,'Keep');
    INSERT INTO bookReview VALUES(1,2),(2,3);");
$_POST = ['bookId'=>1,'reviewId'=>3,'token'=>'test-token']; $delete();
expectReview((int)$pdo->query('SELECT COUNT(*) FROM review WHERE id=3')->fetchColumn() === 1, 'Cannot delete another book\'s review');
$_POST = ['bookId'=>1,'reviewId'=>2,'token'=>'wrong']; ob_start(); $delete(); ob_end_clean();
expectReview((int)$pdo->query('SELECT COUNT(*) FROM review WHERE id=2')->fetchColumn() === 1, 'Deletion requires valid form token');
$_POST['token'] = 'test-token'; $delete();
expectReview((int)$pdo->query('SELECT COUNT(*) FROM review WHERE id=2')->fetchColumn() === 0, 'Delete chosen blank review');
expectReview((int)$pdo->query('SELECT COUNT(*) FROM bookReview WHERE review_id=2')->fetchColumn() === 0, 'Delete review link');
// Review shared by two books is only removed from the chosen book.
$pdo->exec('INSERT INTO bookReview VALUES(2,1)');
$_POST = ['bookId'=>1,'reviewId'=>1,'token'=>'test-token']; $delete();
expectReview((int)$pdo->query('SELECT COUNT(*) FROM review WHERE id=1')->fetchColumn() === 1, 'Keep a review still linked to another book');
expectReview(!str_contains(file_get_contents(__DIR__ . '/../src/assets/js/custom.js'), 'url: "/dateChange"'), 'No date-blur autosave in the browser');
// Deleting a book also removes stale joins, so reused IDs cannot inherit reviews or genres.
require_once __DIR__ . '/../src/lib/CoverService.php';
$pdo->exec('CREATE TABLE book(id INTEGER PRIMARY KEY);
    CREATE TABLE bookGenre(book_id INT,genre_id INT);
    CREATE TABLE bookSource(book INT, source INT);
    CREATE TABLE bookList(book INT, list INT);
    INSERT INTO book VALUES(1),(2);
    INSERT INTO review VALUES(4,"2026-11-23",4,"For deleted book");
    INSERT INTO bookReview VALUES(1,4);
    INSERT INTO bookGenre VALUES(1,5);
    INSERT INTO bookSource VALUES(1,6);
    INSERT INTO bookList VALUES(1,7);');
$covers = new CoverService($pdo, sys_get_temp_dir() . '/unused-covers-' . bin2hex(random_bytes(8)));
$deleteBookBody = routeBody($source, "    case 'deleteBook':", "    case 'fetchPlex':");
$deleteBook = function () use ($pdo, $covers, $deleteBookBody) { eval($deleteBookBody); };
$_REQUEST = ['id'=>1]; $deleteBook();
expectReview((int)$pdo->query('SELECT COUNT(*) FROM bookReview WHERE book_id=1')->fetchColumn() === 0, 'Book deletion removes review links');
expectReview((int)$pdo->query('SELECT COUNT(*) FROM review WHERE id=4')->fetchColumn() === 0, 'Book deletion removes orphaned reviews');
expectReview((int)$pdo->query('SELECT COUNT(*) FROM review WHERE id=1')->fetchColumn() === 1, 'Book deletion preserves another book\'s linked review');
expectReview((int)$pdo->query('SELECT COUNT(*) FROM bookGenre WHERE book_id=1')->fetchColumn() === 0, 'Book deletion removes genre links');
echo "PASS: blank-review validation, rating-only reviews, legacy date endpoint, scoped deletion, form token and shared-review preservation.\n";
