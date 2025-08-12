<?php

/**
 * index.php
 *
 * bookshelf - a reading list manager
 *
 * @author     Neil Thompson <neil@spokenlikeageek.com>
 * @copyright  2025 Neil Thompson
 * @license    https://www.gnu.org/licenses/gpl-3.0.en.html  GNU General Public License v3.0
 * @link       https://github.com/williamsdb/bookshelf Bookshelf on GitHub
 * @see        https://www.spokenlikeageek.com/tag/booklist-a-reading-list-manager/ Blog post
 *
 * ARGUMENTS
 *
 */

class bookshelfException extends Exception {}

// turn off reporting of notices
error_reporting(0);
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('display_errors', 1);

// session start
session_start();

// Load Composer & parameters
require __DIR__ . '/vendor/autoload.php';
try {
    require __DIR__ . '/config.php';
} catch (\Throwable $th) {
    die('config.php file not found. Have you renamed from config_dummy.php?');
}

use Aws\Credentials\Credentials;
use Aws\Signature\SignatureV4;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;

// have we got a config file?
try {
    require __DIR__ . '/config.php';
} catch (\Throwable $th) {
    throw new bookshelfException("config.php file not found. Have you renamed from config_dummy.php?.");
}

// set up namespaces
use Smarty\Smarty;

$smarty = new Smarty();

$smarty->setTemplateDir('templates');
$smarty->setCompileDir('templates_c');
$smarty->setCacheDir('cache');
$smarty->setConfigDir('configs');
$smarty->registerPlugin("modifier", "date_format_tz", "smarty_modifier_date_format_tz");

// is Amazon Associates enabled?
if (!empty($accessKey)) {
    $smarty->assign('kindleapi', 1);
}

// is Plex API enabled?
if (!empty($plexToken)) {
    $smarty->assign('plexapi', 1);
}

// Get the current path from the requested URL
$current_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Remove leading and trailing slashes
$trimmed_path = trim($current_path, '/');

// Split the path into segments
$path_segments = explode('/', $trimmed_path);

// Get the first segment, which is the command, followed by the activity id and then the action id
$cmd = $path_segments[0];
if (isset($path_segments[1])) {
    $id = $path_segments[1];
}
if (isset($path_segments[2])) {
    $act = $path_segments[2];
}

// any error or information messages
if (!empty($_SESSION['error'])) {
    $smarty->assign('error', $_SESSION['error']);
    unset($_SESSION['error']);
}

// create and connect to the SQLite database to hold the cached data
try {
    // Specify the path and filename for the SQLite database
    $databasePath = __DIR__ . '/cache.sqlite';

    if (!file_exists($databasePath)) {
        // Create a new SQLite database or connect to an existing one
        $pdo = new PDO('sqlite:' . $databasePath);

        // Set error mode to exceptions
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create the necessary tables if they don't already exist
        $sql = "CREATE TABLE IF NOT EXISTS `book` (
                        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                        `author` TEXT NOT NULL,
                        `title` TEXT NOT NULL,
                        `genre` TEXT NULL,
                        `series` TEXT NULL,
                        `seriesPosition` INTEGER NULL,
                        `isbn` TEXT NULL,
                        `formatId` TEXT NULL,
                        `sourceId` TEXT NULL,
                        `read` INTEGER NULL,
                        `priority` INTEGER NULL,
                        `dateAdded` TEXT NULL,
                        `dateRead` TEXT NULL,
                        `rating` FLOAT NULL,
                        `review` TEXT NULL,
                        `notes` TEXT NULL,
                        `list` INTEGER NULL,
                        `url` TEXT NULL
                    );

                CREATE TABLE IF NOT EXISTS `format` (
                        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                        `name` TEXT NOT NULL UNIQUE
                    );

                INSERT INTO `format` (`name`)
                VALUES ('Physical'), ('Ebook'), ('Audiobook');

                CREATE TABLE IF NOT EXISTS `source` (
                        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                        `name` TEXT NOT NULL UNIQUE
                    );

                INSERT INTO `source` (`name`)
                VALUES ('Kindle'), ('Audible'), ('Scan'), ('Search'), ('CSV'), ('Plex');

                CREATE TABLE IF NOT EXISTS `list` (
                        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                        `name` TEXT NOT NULL UNIQUE,
                        `default` INTEGER DEFAULT 0
                    );

                INSERT INTO `list` (`name`)
                VALUES ('Priority');

                UPDATE `list` SET `default` = 1 WHERE `id` = 1;

                CREATE TABLE IF NOT EXISTS `bookList` (
                        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                        `book` INTEGER NOT NULL,
                        `list` INTEGER NOT NULL
                    );

                CREATE TABLE IF NOT EXISTS `bookSource` (
                        `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                        `book` INTEGER NOT NULL,
                        `source` INTEGER NOT NULL
                    );
        ";


        $pdo->exec($sql);
    } else {
        // Connect to an existing database
        $pdo = new PDO('sqlite:' . $databasePath);

        // Set error mode to exceptions
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}


// execute command
switch ($cmd) {

    case 'addFile':

        $smarty->assign('header', 'Upload a file');
        $smarty->display('addFile.tpl');
        break;

    case 'processFile':

        $targetDir = "uploads/";
        $targetFile = $targetDir . basename($_FILES["file"]["name"]);
        $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

        // Check if file already exists
        if (file_exists($targetFile)) {
            $_SESSION['error'] = 'File already exists.';
            unlink($targetFile);
            header('Location: /addFile');
            exit;
        }

        // Check file size (5MB limit)
        if ($_FILES["file"]["size"] > 5000000) {
            $_SESSION['error'] = 'Sorry, your file is too large.';
            unlink($targetFile);
            header('Location: /addFile');
            exit;
        }

        // Allow certain file formats
        $allowedTypes = array('csv');
        if (!in_array($fileType, $allowedTypes)) {
            $_SESSION['error'] = 'Sorry, only CSV files are allowed.';
            unlink($targetFile);
            header('Location: /addFile');
            exit;
        }

        if (move_uploaded_file($_FILES["file"]["tmp_name"], $targetFile)) {
            if (($handle = fopen($targetFile, "r")) !== false) {
                // Assuming the first row is the header
                $header = fgetcsv($handle);
                // Define import formats
                $importFormats = [
                    // Openaudible CSV
                    'Key' => [
                        'author' => 2,
                        'title' => 1,
                        'series' => 9,
                        'seriesPosition' => 10,
                        'genre' => 8,
                        'isbn' => null,
                        'formatId' => 3,
                        'sourceId' => 2,
                        'url' => 13
                    ],
                    // Kindle CSV
                    'ISBN / ASIN (Amazon ID)' => [
                        'author' => 14,
                        'title' => 6,
                        'series' => 12,
                        'seriesPosition' => 11,
                        'genre' => 9,
                        'isbn' => null,
                        'formatId' => 2,
                        'sourceId' => 1,
                        'url' => 1,
                        'rating' => null,
                        'review' => null,
                        'dateRead' => null
                    ],
                    // Simple CSV
                    'Title' => [
                        'author' => 1,
                        'title' => 0,
                        'series' => null,
                        'seriesPosition' => null,
                        'genre' => 2,
                        'isbn' => null,
                        'formatId' => 1,
                        'sourceId' => 5,
                        'url' => 3,
                        'rating' => null,
                        'review' => null,
                        'dateRead' => null
                    ],
                    // Helen's file
                    'Date' => [
                        'author' => 1,
                        'title' => 3,
                        'series' => 2,
                        'seriesPosition' => null,
                        'genre' => null,
                        'isbn' => null,
                        'formatId' => 4,
                        'sourceId' => 5,
                        'url' => null,
                        'rating' => 5,
                        'review' => 6,
                        'dateRead' => 0
                    ],
                    // Neil's file
                    'Author' => [
                        'author' => 0,
                        'title' => 1,
                        'series' => null,
                        'seriesPosition' => null,
                        'genre' => null,
                        'isbn' => null,
                        'formatId' => 4,
                        'sourceId' => 5,
                        'url' => null,
                        'rating' => null,
                        'review' => 6,
                        'dateRead' => 2
                    ]
                ];

                $formatKey = $header[0];
                $cleanKey = preg_replace('/^\xEF\xBB\xBF/', '', $formatKey); // Remove BOM if present
                $formatKey = trim($cleanKey);

                if (isset($importFormats[$formatKey])) {

                    $map = $importFormats[$formatKey];
                    while (($data = fgetcsv($handle)) !== false) {

                        $author = isset($map['author']) && isset($data[$map['author']]) ? trim($data[$map['author']]) : '';
                        $title = isset($map['title']) && isset($data[$map['title']]) ? trim($data[$map['title']]) : '';
                        if (empty($author) && empty($title)) {
                            continue;
                        }
                        $series = isset($map['series']) && isset($data[$map['series']]) ? trim($data[$map['series']]) : null;
                        $seriesPosition = isset($map['seriesPosition']) && isset($data[$map['seriesPosition']]) ? trim($data[$map['seriesPosition']]) : null;
                        $genre = isset($map['genre']) && isset($data[$map['genre']]) ? trim($data[$map['genre']]) : null;
                        $isbn = isset($map['isbn']) && $map['isbn'] !== null && isset($data[$map['isbn']]) ? trim($data[$map['isbn']]) : null;
                        $rating = isset($map['rating']) && isset($data[$map['rating']]) ? trim($data[$map['rating']]) : null;
                        $review = isset($map['review']) && isset($data[$map['review']]) ? trim($data[$map['review']]) : null;
                        if ($formatKey === 'Date') {
                            $format = isset($map['formatId']) && isset($data[$map['formatId']]) ? trim($data[$map['formatId']]) : null;
                            if (strtolower($format) === 'kindle') {
                                $formatId = 2; // Kindle
                            } else {
                                $formatId = 1; // Physical
                            }
                            $read = 2;
                            $dateRead = null;
                            if (isset($map['dateRead']) && isset($data[$map['dateRead']]) && !empty($data[$map['dateRead']])) {
                                $dateReadRaw = trim($data[$map['dateRead']]);
                                // Try to parse "MMMM YYYY" format
                                $dateObj = DateTime::createFromFormat('F Y', $dateReadRaw);
                                if ($dateObj !== false) {
                                    $dateRead = $dateObj->format('Y-m-01');
                                } else {
                                    $dateRead = null;
                                }
                            }
                        } elseif ($formatKey === 'Author') {
                            $format = isset($map['formatId']) && isset($data[$map['formatId']]) ? trim($data[$map['formatId']]) : null;
                            if (strtolower($format) === 'ebook') {
                                $formatId = 2; // Kindle
                            } elseif (strtolower($format) === 'audio') {
                                $formatId = 3; // Audiobook
                            } else {
                                $formatId = 1; // Physical
                            }
                            $read = 2;
                            $dateRead = null;
                            if (isset($map['dateRead']) && isset($data[$map['dateRead']]) && !empty($data[$map['dateRead']])) {
                                $dateParts = explode('/', $data[$map['dateRead']]);
                                if (count($dateParts) === 3) {
                                    // dd/mm/yyyy to yyyy-mm-dd
                                    $dateRead = sprintf('%04d-%02d-%02d', $dateParts[2], $dateParts[1], $dateParts[0]);
                                }
                            }
                        } else {
                            $formatId = $map['formatId'];
                            $read = 0;
                            $dateRead = isset($map['dateRead']) && isset($data[$map['dateRead']]) ? trim($data[$map['dateRead']]) : null;
                        }
                        $sourceId = $map['sourceId'];
                        $priority = 0;
                        $dateAdded = date('Y-m-d H:i:s');
                        $notes = null;
                        $list = 0;
                        $url = isset($map['url']) && isset($data[$map['url']]) ? trim($data[$map['url']]) : null;
                        // Optionally rewrite Amazon .com links to .co.uk
                        if (!empty($rewriteAmazonLinks) && $rewriteAmazonLinks && !empty($url)) {
                            $parts = parse_url($url);
                            if (isset($parts['host']) && preg_match('/(^|\.)amazon\.com$/i', $parts['host'])) {
                                $parts['host'] = 'www.amazon.co.uk';
                                $scheme = $parts['scheme'] ?? 'https';
                                $path = $parts['path'] ?? '';
                                $query = isset($parts['query']) ? '?' . $parts['query'] : '';
                                $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';
                                $url = $scheme . '://' . $parts['host'] . $path . $query . $fragment;
                            }
                        }
                        // Check if book already exists (by author and title)
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM `book` WHERE `author` = :author AND `title` = :title AND `formatId` = :formatId");
                        $stmt->execute([':author' => $author, ':title' => $title, ':formatId' => $formatId]);
                        $exists = $stmt->fetchColumn();

                        if (!$exists) {
                            $insert = $pdo->prepare("INSERT INTO `book` (`author`, `title`,  `genre`, `isbn`, `formatId`, `sourceId`, `read`, `priority`, `dateAdded`, `dateRead`, `notes`, `list`, `url`, `series`, `seriesPosition`, `rating`, `review`)
                                                        VALUES (:author, :title, :genre, :isbn, :formatId, :sourceId, :read, :priority, :dateAdded, :dateRead, :notes, :list, :url, :series, :seriesPosition, :rating, :review)");
                            $insert->execute([
                                ':author' => $author,
                                ':title' => $title,
                                ':series' => $series,
                                ':seriesPosition' => $seriesPosition,
                                ':genre' => $genre,
                                ':isbn' => $isbn,
                                ':formatId' => $formatId,
                                ':sourceId' => $sourceId,
                                ':read' => $read,
                                ':priority' => $priority,
                                ':dateAdded' => $dateAdded,
                                ':dateRead' => $dateRead,
                                ':notes' => $notes,
                                ':list' => $list,
                                ':url' => $url,
                                ':rating' => $rating,
                                ':review' => $review
                            ]);

                            $bookId = $pdo->lastInsertId();
                            $insertBookSource = $pdo->prepare("INSERT INTO `bookSource` (`book`, `source`) VALUES (:bookId, :sourceId)");
                            $insertBookSource->execute([
                                ':bookId' => $bookId,
                                ':sourceId' => $sourceId
                            ]);
                        } else {
                            $stmtSource = $pdo->prepare("SELECT COUNT(*) FROM `bookSource` WHERE `book` = (SELECT id FROM `book` WHERE `author` = :author AND `title` = :title AND `formatId` = :formatId) AND `source` = :sourceId");
                            $stmtSource->execute([
                                ':author' => $author,
                                ':title' => $title,
                                ':formatId' => $formatId,
                                ':sourceId' => $sourceId
                            ]);
                            $sourceExists = $stmtSource->fetchColumn();

                            if (!$sourceExists) {
                                // Get the book id
                                $stmtBookId = $pdo->prepare("SELECT id FROM `book` WHERE `author` = :author AND `title` = :title AND `formatId` = :formatId");
                                $stmtBookId->execute([
                                    ':author' => $author,
                                    ':title' => $title,
                                    ':formatId' => $formatId
                                ]);
                                $existingBookId = $stmtBookId->fetchColumn();

                                if ($existingBookId) {
                                    $insertBookSource = $pdo->prepare("INSERT INTO `bookSource` (`book`, `source`) VALUES (:bookId, :sourceId)");
                                    $insertBookSource->execute([
                                        ':bookId' => $existingBookId,
                                        ':sourceId' => $sourceId
                                    ]);
                                }
                            }
                        }
                    }
                }
                fclose($handle);
            }
        } else {
            $_SESSION['error'] = 'Sorry, there was an error uploading your file.';
            header('Location: /addFile');
            exit;
        }

        // Remove the uploaded file
        if (file_exists($targetFile)) {
            if (!unlink($targetFile)) {
                $_SESSION['error'] = "Warning: Unable to delete uploaded file.";
                header('Location: /addFile');
                exit;
            }
        }

        // Redirect to the home page with a success message
        $_SESSION['error'] = 'File processed successfully.';
        header('Location: /');
        break;

    case 'allBooks':

        $author = isset($_REQUEST['author']) ? trim($_REQUEST['author']) : '';
        $title = isset($_REQUEST['title']) ? trim($_REQUEST['title']) : '';
        $format = isset($_REQUEST['format']) ? trim($_REQUEST['format']) : '';

        // get all books with optional filters
        $sql = "SELECT 
                    book.id, 
                    book.author, 
                    book.title,
                    book.series,
                    book.seriesPosition,
                    book.genre, 
                    book.isbn, 
                    format.name AS format, 
                    source.name AS source,
                    book.read,
                    book.priority,
                    book.dateAdded,
                    book.notes,
                    book.rating,
                    list.name AS list,
                    book.url
                FROM book
                LEFT JOIN format ON book.formatId = format.id
                LEFT JOIN source ON book.sourceId = source.id
                LEFT JOIN list ON book.list = list.id
                ORDER BY book.author ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get all lists
        $listStmt = $pdo->prepare("SELECT id, name, `default` FROM list ORDER BY name ASC");
        $listStmt->execute();
        $lists = $listStmt->fetchAll(PDO::FETCH_ASSOC);

        $smarty->assign('lists', $lists);
        $smarty->assign('books', $books);
        $smarty->assign('title', $title);
        $smarty->assign('author', $author);
        $smarty->assign('format', $format);
        $smarty->display('allBooks.tpl');
        break;

    case 'viewDetails':

        if (!isset($id) || !is_numeric($id)) {
            $smarty->assign('error', 'Invalid book ID');
            $smarty->display('home.tpl');
            break;
        }

        $sql = "SELECT
                    book.id, 
                    book.author, 
                    book.title, 
                    book.series, 
                    book.seriesPosition, 
                    book.genre, 
                    book.isbn, 
                    format.name AS format, 
                    (
                        SELECT GROUP_CONCAT(source.name, ', ')
                        FROM bookSource
                        JOIN source ON bookSource.source = source.id
                        WHERE bookSource.book = book.id
                    ) AS source,
                    book.read,
                    book.priority,
                    book.dateAdded,
                    book.dateRead,
                    book.notes,
                    list.name AS list,
                    book.url,
                    book.rating,
                    book.review
                FROM book 
                LEFT JOIN format ON book.formatId = format.id
                LEFT JOIN source ON book.sourceId = source.id
                LEFT JOIN list ON book.list = list.id
            WHERE book.id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $book = $stmt->fetch(PDO::FETCH_ASSOC);

        // Get all lists
        $listStmt = $pdo->prepare("
            SELECT 
            id, 
            name, 
            `default`, 
            CASE 
                WHEN EXISTS (
                SELECT 1 FROM bookList WHERE book = :bookId AND list = list.id
                ) THEN 1 
                ELSE 0 
            END AS selected
            FROM list
            ORDER BY name ASC
        ");
        $listStmt->bindParam(':bookId', $id, PDO::PARAM_INT);
        $listStmt->execute();
        $lists = $listStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$book) {
            $_SESSION['error'] = 'Book not found.';
            header('Location: /');
            exit;
        }

        $smarty->assign('book', $book);
        $smarty->assign('lists', $lists);
        $smarty->display('viewDetails.tpl');
        break;

    case 'lists':

        // Get all lists
        $listStmt = $pdo->prepare("SELECT id, name, `default`, (SELECT COUNT(*) FROM book JOIN `bookList` ON book.id = bookList.book WHERE bookList.list = list.id) AS book_count FROM list ORDER BY name ASC");
        $listStmt->execute();
        $lists = $listStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$lists) {
            $_SESSION['error'] = 'No lists found.';
            header('Location: /');
            exit;
        }

        $smarty->assign('lists', $lists);
        $smarty->display('lists.tpl');
        break;

    case 'addList':

        $smarty->display('addList.tpl');
        break;

    case 'createList':

        // Check if the list name is set
        if (!isset($_REQUEST['listName']) || empty(trim($_REQUEST['listName']))) {
            $_SESSION['error'] = 'List name is required.';
            header('Location: /addList');
            exit;
        }

        $listName = trim($_REQUEST['listName']);
        $default = isset($_REQUEST['default']) ? 1 : 0; // Default to 0 if not set

        // Check if the list already exists
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM list WHERE name = :listName");
        $checkStmt->bindParam(':listName', $listName, PDO::PARAM_STR);
        $checkStmt->execute();
        $exists = $checkStmt->fetchColumn();
        if ($exists) {
            $_SESSION['error'] = 'List already exists.';
            header('Location: /addList');
            exit;
        }

        if ($default == 1) {
            // Set all other lists to not default
            $updateStmt = $pdo->prepare("UPDATE list SET `default` = 0 WHERE `default` = 1");
            $updateStmt->execute();
        }

        // Insert the new list into the database
        $insertStmt = $pdo->prepare("INSERT INTO list (name, `default`) VALUES (:listName, :default)");
        $insertStmt->bindParam(':listName', $listName, PDO::PARAM_STR);
        $insertStmt->bindParam(':default', $default, PDO::PARAM_INT);
        $insertStmt->execute();

        // Redirect to the relevant page
        $_SESSION['error'] = 'List created';
        Header('Location: /lists');

        break;

    case 'editList':

        // Check if the id is set and is a valid number
        if (!isset($_REQUEST['id']) || !is_numeric($_REQUEST['id'])) {
            $_SESSION['error'] = 'Invalid book ID';
            header('Location: /');
            exit;
        } else {
            $id = intval($_REQUEST['id']);
        }

        $listStmt = $pdo->prepare("SELECT id, name, `default`, (SELECT COUNT(*) FROM book JOIN `bookList` ON book.id = bookList.book WHERE bookList.list = list.id) AS book_count FROM list WHERE id = :id");
        $listStmt->bindParam(':id', $id, PDO::PARAM_INT);
        $listStmt->execute();
        $list = $listStmt->fetch(PDO::FETCH_ASSOC);

        if (!$list) {
            $_SESSION['error'] = 'List not found.';
            header('Location: /lists');
            exit;
        }

        $smarty->assign('listName', $list['name']);
        $smarty->assign('default', $list['default']);
        $smarty->assign('id', $id);
        $smarty->assign('bookCount', $list['book_count']);
        $smarty->display('editList.tpl');
        break;

    case 'updateList':

        $listName = $_REQUEST['listName'];
        $default = isset($_REQUEST['default']) ? 1 : 0;

        if ($default == 1) {
            // Set all other lists to not default
            $updateStmt = $pdo->prepare("UPDATE list SET `default` = 0 WHERE `default` = 1");
            $updateStmt->execute();
        }

        $stmt = $pdo->prepare("UPDATE list SET name = :name, `default` = :default WHERE id = :id");
        $stmt->bindParam(':name', $listName);
        $stmt->bindParam(':default', $default);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        // Redirect to the relevant page
        $_SESSION['error'] = 'List updated';
        Header('Location: /lists');

        break;

    case 'deleteList':

        // Check if the id is set and is a valid number
        if (!isset($_REQUEST['id']) || !is_numeric($_REQUEST['id'])) {
            $_SESSION['error'] = 'Invalid list ID';
            header('Location: /lists');
            exit;
        } else {
            $id = intval($_REQUEST['id']);
        }

        // Check if the list exists
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM list WHERE id = :id");
        $checkStmt->bindParam(':id', $id, PDO::PARAM_INT);
        $checkStmt->execute();
        $exists = $checkStmt->fetchColumn();

        if (!$exists) {
            $_SESSION['error'] = 'List not found.';
            header('Location: /lists');
            exit;
        }

        // Delete the list
        $deleteStmt = $pdo->prepare("DELETE FROM list WHERE id = :id");
        $deleteStmt->bindParam(':id', $id, PDO::PARAM_INT);
        $deleteStmt->execute();

        // Redirect to the lists page with a success message
        $_SESSION['error'] = 'List deleted successfully.';
        header('Location: /lists');
        exit;

    case 'listChange':

        // Expecting a POST request with 'name' (e.g., 'list_12') and 'value'
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bookId'], $_POST['listId'], $_POST['action'])) {
            $bookId = $_POST['bookId'];
            $listId = intval($_POST['listId']);
            $action = $_POST['action'];

            if ($action === 'add' && is_numeric($bookId) && $listId > 0) {
                // Add the book to the list
                $insert = $pdo->prepare("INSERT INTO bookList (book, list) VALUES (:bookId, :listId)");
                $insert->execute([':bookId' => $bookId, ':listId' => $listId]);

                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            } else if ($action === 'remove' && is_numeric($bookId) && $listId > 0) {
                // Remove the book from the list
                $delete = $pdo->prepare("DELETE FROM bookList WHERE book = :bookId AND list = :listId");
                $delete->execute([':bookId' => $bookId, ':listId' => $listId]);

                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            } else {
                // Invalid book id or list id.
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Invalid book or list identifier.']);
                exit;
            }
        } else {
            // Invalid request.
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid request.']);
            exit;
        }
        break;

    case 'statusChange':

        // Expecting a POST request with 'name' (e.g., 'status_12') and 'value'
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'], $_POST['value'])) {
            $name = $_POST['name'];
            $readStatus = intval($_POST['value']);

            // Extract book id from the name (e.g., 'status_12' => 12)
            if (preg_match('/^status_(\d+)$/', $name, $matches)) {
                $bookId = intval($matches[1]);

                // Update the read status for the given book id
                if ($readStatus === 2) {
                    $update = $pdo->prepare("UPDATE book SET read = :readStatus, dateRead = :dateRead WHERE id = :bookId");
                    $update->execute([
                        ':readStatus' => $readStatus,
                        ':dateRead' => date('Y-m-d'),
                        ':bookId' => $bookId
                    ]);
                } else {
                    $update = $pdo->prepare("UPDATE book SET read = :readStatus WHERE id = :bookId");
                    $update->execute([':readStatus' => $readStatus, ':bookId' => $bookId]);
                }
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            } else {
                // Invalid book identifier.
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Invalid book identifier.']);
                exit;
            }
        } else {
            // Invalid request.
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid request.']);
            exit;
        }
        break;

    case 'ratingChange':

        // Expecting a POST request with 'name' (e.g., 'book_12') and 'value'
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'], $_POST['value'])) {
            $name = $_POST['name'];
            $rating = $_POST['value'];

            // Extract book id from the name (e.g., 'book_12' => 12)
            if (preg_match('/^book_(\d+)$/', $name, $matches)) {
                $bookId = intval($matches[1]);

                // Update the rating for the given book id
                $update = $pdo->prepare("UPDATE book SET rating = :rating WHERE id = :bookId");
                $update->execute([':rating' => $rating, ':bookId' => $bookId]);

                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
            } else {
                // Invalid book identifier.
                http_response_code(400);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Invalid book identifier.']);
                exit;
            }
        } else {
            // Invalid request.
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Invalid request.']);
            exit;
        }
        break;

    case 'reviewChange':

        // Expecting a POST request with 'name' (e.g., 'book_12') and 'value'
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'], $_POST['value'])) {
            $name = $_POST['name'];
            $review = $_POST['value'];

            // Extract book id from the name (e.g., 'book_12' => 12)
            if (preg_match('/^book_(\d+)$/', $name, $matches)) {
                $bookId = intval($matches[1]);

                // Update the review for the given book id
                $update = $pdo->prepare("UPDATE book SET review = :review WHERE id = :bookId");
                $update->execute([':review' => $review, ':bookId' => $bookId]);

                header('Content-Type: application/json');
                $_SESSION['error'] = 'Book updated successfully.';
                echo json_encode(['success' => true]);
                exit;
            } else {
                // Invalid book identifier.
                http_response_code(400);
                header('Content-Type: application/json');
                $_SESSION['error'] = 'Sorry, there was a problem updating the book details.';
                echo json_encode(['error' => 'Invalid book identifier.']);
                exit;
            }
        } else {
            // Invalid request.
            http_response_code(400);
            header('Content-Type: application/json');
            $_SESSION['error'] = 'Sorry, there was a problem updating the book details.';
            echo json_encode(['error' => 'Invalid request.']);
            exit;
        }
        break;


    case 'dateChange':

        // Expecting a POST request with 'name' (e.g., '12') and 'value'
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'], $_POST['value'])) {
            $name = $_POST['name'];
            $date = $_POST['value'];
            // change date format from yyyy-mm-dd to yyyy/mm/dd
            $date = date('Y/m/d', strtotime($date));

            // Extract book id from the name (e.g., '12' => 12)
            $bookId = intval($name);

            // Update the date for the given book id
            $update = $pdo->prepare("UPDATE book SET dateRead = :date WHERE id = :bookId");
            $update->execute([':date' => $date, ':bookId' => $bookId]);

            header('Content-Type: application/json');
            $_SESSION['error'] = 'Book updated successfully.' . $date;
            echo json_encode(['success' => true]);
            exit;
        } else {
            // Invalid request.
            http_response_code(400);
            header('Content-Type: application/json');
            $_SESSION['error'] = 'Sorry, there was a problem updating the book details.';
            echo json_encode(['error' => 'Invalid request.']);
            exit;
        }

        break;


    case 'stats':

        // Get the total number of books
        $totalBooksStmt = $pdo->prepare("SELECT COUNT(*) FROM book");
        $totalBooksStmt->execute();
        $totalBooks = $totalBooksStmt->fetchColumn();

        // Get the number of books read
        $readBooksStmt = $pdo->prepare("SELECT COUNT(*) FROM book WHERE read = 2");
        $readBooksStmt->execute();
        $readBooks = $readBooksStmt->fetchColumn();

        // Get the number of books unread
        $unreadBooksStmt = $pdo->prepare("SELECT COUNT(*) FROM book WHERE read = 0");
        $unreadBooksStmt->execute();
        $unreadBooks = $unreadBooksStmt->fetchColumn();

        $yearStatsStmt = $pdo->prepare("
            SELECT SUBSTR(`book`.`dateRead`,1,4) AS year, COUNT(*) AS count
            FROM `book`
            WHERE `book`.`dateRead` IS NOT NULL
            GROUP BY year
            ORDER BY year ASC
        ");
        $yearStatsStmt->execute();
        $yearStats = $yearStatsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Prepare data for the chart
        $labels = [];
        $data = [];
        for ($i = 0; $i < count($yearStats); $i++) {
            $labels[] = $yearStats[$i]['year'];
            $data[] = $yearStats[$i]['count'];
        }

        // Assign data to Smarty
        $smarty->assign('labels', json_encode($labels));
        $smarty->assign('data', json_encode($data));

        // Get the number of books by author
        $authorStatsStmt = $pdo->prepare("
            SELECT `book`.`author` as 'name', count(*) as 'count'
            FROM `book`
            WHERE `book`.`dateRead` is not NULL
            GROUP BY `book`.`author`
            ORDER BY count desc limit 10
        ");
        $authorStatsStmt->execute();
        $authorStats = $authorStatsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Assign data to Smarty
        $smarty->assign('topAuthors', $authorStats);

        // Get the number of books in each format
        $formatStatsStmt = $pdo->prepare("
            WITH years AS (
            SELECT DISTINCT SUBSTR(dateRead,1,4) AS year
            FROM book
            WHERE dateRead IS NOT NULL AND TRIM(dateRead) <> ''
            ),
            fmt AS (
            SELECT id, name FROM format
            ),
            counts AS (
            SELECT 
                SUBSTR(b.dateRead,1,4) AS year,
                b.formatId,
                COUNT(b.id) AS cnt
            FROM book b
            WHERE b.dateRead IS NOT NULL AND TRIM(b.dateRead) <> ''
            GROUP BY SUBSTR(b.dateRead,1,4), b.formatId
            )
            SELECT 
            y.year AS year,
            f.name AS name,
            COALESCE(c.cnt, 0) AS count
            FROM years y
            CROSS JOIN fmt f
            LEFT JOIN counts c
            ON c.year = y.year AND c.formatId = f.id
            ORDER BY y.year ASC, f.name ASC
        ");
        $formatStatsStmt->execute();
        $formatStats = $formatStatsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Build per-format year/count arrays
        $physical = [];
        $ebooks = [];
        $audiobooks = [];

        $i = 0;
        foreach ($formatStats as $row) {

            $year = $row['year'];
            if ($year === null || $year === '') {
                continue;
            }
            $count = (int)$row['count'];
            switch (strtolower($row['name'])) {
                case 'physical':
                    $physical[] = $count;
                    break;
                case 'ebook':
                case 'ebooks':
                    $ebooks[] = $count;
                    break;
                case 'audiobook':
                case 'audiobooks':
                    $audiobooks[] = $count;
                    break;
            }
        }

        /*
        // Ensure arrays are ordered by year ASC
        $sortByYear = function (&$arr) {
            usort($arr, function ($a, $b) {
                return strcmp((string)$a['year'], (string)$b['year']);
            });
        };
        $sortByYear($physical);
        $sortByYear($ebooks);
        $sortByYear($audiobooks);

*/
        // Expose to templates
        $smarty->assign('physical', json_encode($physical));
        $smarty->assign('ebooks', json_encode($ebooks));
        $smarty->assign('audiobooks', json_encode($audiobooks));
        // Assign stats to Smarty
        $smarty->assign('totalBooks', $totalBooks);
        $smarty->assign('readBooks', $readBooks);
        $smarty->assign('unreadBooks', $unreadBooks);
        $smarty->assign('formatStats', $formatStats);
        $smarty->display('stats.tpl');
        break;

    case '':

        // Check if the id is set and is a valid number
        if (!isset($_REQUEST['id']) || !is_numeric($_REQUEST['id'])) {
            $listStmt = $pdo->prepare("SELECT id FROM list WHERE `default` = 1 LIMIT 1");
            $listStmt->execute();
            $defaultListId = $listStmt->fetchColumn();
        } else {
            $defaultListId = intval($_REQUEST['id']);
        }


        $sql = "SELECT 
                    `book`.`id`, 
                    `book`.`author`, 
                    `book`.`title`, 
                    `book`.`genre`, 
                    `book`.`isbn`, 
                    `format`.`name` AS `format`, 
                    `source`.`name` AS `source`,
                    `book`.`read`,
                    `book`.`priority`,
                    `book`.`dateAdded`,
                    `book`.`notes`,
                    `list`.`name` AS `list`,
                    `book`.`url`
                FROM `book`
                LEFT JOIN `format` ON `book`.`formatId` = `format`.`id`
                LEFT JOIN `source` ON `book`.`sourceId` = `source`.`id`
                LEFT JOIN `list` ON `book`.`list` = `list`.`id`
                LEFT JOIN `bookList` ON `book`.`id` = `bookList`.`book`
                WHERE `bookList`.`list` = :defaultListId";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':defaultListId' => $defaultListId]);
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $listStmt = $pdo->prepare("SELECT id, name, `default` FROM list ORDER BY id ASC");
        $listStmt->execute();
        $lists = $listStmt->fetchAll(PDO::FETCH_ASSOC);

        $smarty->assign('defaultListId', $defaultListId);
        $smarty->assign('lists', $lists);
        $smarty->assign('books', $books);
        $smarty->display('home.tpl');
        break;

    case 'scan':

        $smarty->assign('header', 'Scan a book');
        $smarty->display('scan.tpl');
        break;

    case 'fetch-book':

        if (!isset($_REQUEST['isbn']) || empty($_REQUEST['isbn'])) {
            $smarty->assign('error', 'No ISBN provided');
            $smarty->display('home.tpl');
            break;
        }

        $isbn = trim($_REQUEST['isbn']);
        $url = "https://openlibrary.org/api/books?bibkeys=ISBN:$isbn&format=json&jscmd=data";

        $response = file_get_contents($url);
        if ($response === FALSE) {
            echo json_encode(['error' => 'Unable to fetch data from Open Library API']);
            exit;
        }

        $data = json_decode($response, true);
        if (isset($data["ISBN:$isbn"])) {
            $bookData = $data["ISBN:$isbn"];
            echo json_encode([
                'title' => $bookData['title'],
                'authors' => array_map(function ($author) {
                    return $author['name'];
                }, $bookData['authors']),
                'publisher' => $bookData['publishers'][0]['name'],
                'publish_date' => $bookData['publish_date'],
                'url' => $bookData['url'],
                'subject' => (
                    isset($bookData['subjects']) &&
                    is_array($bookData['subjects']) &&
                    isset($bookData['subjects'][0]['name'])
                ) ? $bookData['subjects'][0]['name'] : 'Unknown',
                'isbn' => $isbn,
                'cover' => $bookData['cover']['large'] ?? null,
            ]);
            die;
        } else {
            echo json_encode(['error' => 'No book found with the provided ISBN']);
        }
        break;

    case 'recordCsv':

        // Check if the request method is POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Get the raw POST data
            $input = file_get_contents('php://input');
            $data = json_decode($input, true);

            // Validate the received data
            if (isset($data['title'], $data['authors'], $data['url'], $data['isbn'])) {
                $title = $data['title'];
                $authors = $data['authors'];
                $url = $data['url'];
                $subject = $data['subject'];
                $isbn = $data['isbn'];
                $formatId = 1;
                $sourceId = 3;
                $read = 0;
                $priority = 0;
                $dateAdded = date('Y-m-d H:i:s');
                $notes = null;
                $list = 0;

                // Check if book already exists (by author and title)
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM `book` WHERE `author` = :author AND `title` = :title AND `formatId` = :formatId");
                $stmt->execute([':author' => $authors, ':title' => $title, ':formatId' => $formatId]);
                $exists = $stmt->fetchColumn();

                if (!$exists) {
                    $insert = $pdo->prepare("INSERT INTO `book` (`author`, `title`, `genre`, `isbn`, `formatId`, `sourceId`, `read`, `priority`, `dateAdded`, `notes`, `list`, `url`)
                                                        VALUES (:author, :title, :genre, :isbn, :formatId, :sourceId, :read, :priority, :dateAdded, :notes, :list, :url)");
                    $insert->execute([
                        ':author' => $authors,
                        ':title' => $title,
                        ':genre' => $subject,
                        ':isbn' => $isbn,
                        ':formatId' => $formatId,
                        ':sourceId' => $sourceId,
                        ':read' => $read,
                        ':priority' => $priority,
                        ':dateAdded' => $dateAdded,
                        ':notes' => $notes,
                        ':list' => $list,
                        ':url' => $url
                    ]);

                    $bookId = $pdo->lastInsertId();
                    $insertBookSource = $pdo->prepare("INSERT INTO `bookSource` (`book`, `source`) VALUES (:bookId, :sourceId)");
                    $insertBookSource->execute([
                        ':bookId' => $bookId,
                        ':sourceId' => $sourceId
                    ]);

                    // Return a success response
                    $_SESSION['error'] = 'Book recorded successfully.';
                    echo json_encode(['success' => true]);
                } else {
                    $stmtSource = $pdo->prepare("SELECT COUNT(*) FROM `bookSource` WHERE `book` = (SELECT id FROM `book` WHERE `author` = :author AND `title` = :title AND `formatId` = :formatId) AND `source` = :sourceId");
                    $stmtSource->execute([
                        ':author' => $author,
                        ':title' => $title,
                        ':formatId' => $formatId,
                        ':sourceId' => $sourceId
                    ]);
                    $sourceExists = $stmtSource->fetchColumn();

                    if (!$sourceExists) {
                        // Get the book id
                        $stmtBookId = $pdo->prepare("SELECT id FROM `book` WHERE `author` = :author AND `title` = :title AND `formatId` = :formatId");
                        $stmtBookId->execute([
                            ':author' => $author,
                            ':title' => $title,
                            ':formatId' => $formatId
                        ]);
                        $existingBookId = $stmtBookId->fetchColumn();

                        if ($existingBookId) {
                            $insertBookSource = $pdo->prepare("INSERT INTO `bookSource` (`book`, `source`) VALUES (:bookId, :sourceId)");
                            $insertBookSource->execute([
                                ':bookId' => $existingBookId,
                                ':sourceId' => $sourceId
                            ]);
                        }
                    }
                    // Return a success response
                    $_SESSION['error'] = 'Book already in database.';
                    echo json_encode(['success' => true]);
                }
            } else {
                // Return an error response if the data is invalid
                echo json_encode(['success' => false, 'error' => 'Invalid data received.']);
            }
        } else {
            // Return an error response if the request method is not POST
            echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
        }

        break;

    case 'searchISBN':

        $smarty->assign('header', 'Scan for a book by ISBN');
        $smarty->display('searchISBN.tpl');
        break;

    case 'searchOBL':

        $smarty->assign('header', 'Search the Open Library Catalogue');
        $smarty->display('search.tpl');
        break;

    case 'searchAmazon':

        $payload = json_encode([
            "Keywords" => "The Silent Patient Alex Michaelides",
            "SearchIndex" => "KindleStore",
            "Resources" => [
                "ItemInfo.Title",
                "ItemInfo.ByLineInfo",
                "Offers.Listings.Price"
            ],
            "PartnerTag" => $associateTag,
            "PartnerType" => "Associates",
            "Marketplace" => "www.amazon.co.uk"
        ]);

        $credentials = new Credentials($accessKey, $secretKey);
        $request = new Request(
            'POST',
            "https://{$host}{$uri}",
            [
                'content-encoding' => 'amz-1.0',
                'content-type' => 'application/json; charset=utf-8',
                'host' => $host,
                'x-amz-target' => 'com.amazon.paapi5.v1.ProductAdvertisingAPIv1.SearchItems'
            ],
            $payload
        );

        // Sign the request
        $sigV4 = new SignatureV4('ProductAdvertisingAPI', $region);
        $signedRequest = $sigV4->signRequest($request, $credentials);

        $client = new Client();

        try {
            $response = $client->send($signedRequest);
            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            if (isset($data['Errors'])) {
                echo "Amazon API returned an error:\n";
                foreach ($data['Errors'] as $error) {
                    echo "- [{$error['Code']}] {$error['Message']}\n";
                }
            } else {
                echo json_encode($data, JSON_PRETTY_PRINT);
            }
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $body = (string) $response->getBody();
            $data = json_decode($body, true);

            if (isset($data['Errors'])) {
                foreach ($data['Errors'] as $error) {
                    $_SESSION['error'] = "[{$error['Code']}] {$error['Message']}";
                    header('Location: /');
                    exit;
                }
            } else {
                echo "Raw response: " . $body . "\n";
            }
        }
        break;

    case 'getBook':

        // Display the form to get book details
        if (!isset($_REQUEST['url']) || empty($_REQUEST['url'])) {
            $_SESSION['error'] = 'No URL provided';
            header('Location: /');
            exit;
        } else {
            $decodedUrl = urldecode($_REQUEST['url']);
        }

        // Extract the Works ID using a regular expression
        if (preg_match('#/works/(OL\d+W)#', $decodedUrl, $matches)) {
            $workId = $matches[1];  // OL25924734W
        } else {
            $_SESSION['error'] = 'No Work ID found in URL';
            header('Location: /');
            exit;
        }

        $url = "https://openlibrary.org/works/$workId.json";

        $response = file_get_contents($url);
        if ($response === FALSE) {
            $_SESSION['error'] = 'Unable to fetch data from Open Library API';
            header('Location: /');
            exit;
        }

        $data = json_decode($response, true);

        if (!isset($data['authors']) || !is_array($data['authors'])) {
            die("No authors found in work data.");
        }

        $authorNames = [];

        foreach ($data['authors'] as $authorRef) {
            // Each authorRef should have a key like '/authors/OLxxxxA'
            if (!isset($authorRef['author']['key'])) {
                continue;
            }

            $authorKey = $authorRef['author']['key']; // e.g., "/authors/OL12345A"
            $authorUrl = "https://openlibrary.org$authorKey.json";

            $authorJson = file_get_contents($authorUrl);
            if (!$authorJson) {
                continue; // skip if fetch fails
            }

            $authorData = json_decode($authorJson, true);
            if (isset($authorData['name'])) {
                $authorNames[] = $authorData['name'];
            }
        }

        // Step 3: Concatenate names
        $authorString = implode(', ', $authorNames);

        $smarty->assign('header', 'Book Details');
        $smarty->assign('title', $data['title'] ?? 'Unknown Title');
        $smarty->assign('author', $authorString);
        $smarty->assign('url', $_REQUEST['url']);
        $smarty->display('getBook.tpl');
        break;

    case 'deleteBook':

        // Check if the id is set and is a valid number
        if (!isset($_REQUEST['id']) || !is_numeric($_REQUEST['id'])) {
            $_SESSION['error'] = 'Invalid book ID';
            header('Location: /');
            exit;
        } else {
            $id = intval($_REQUEST['id']);
        }

        // Prepare the SQL statement to delete the book
        $deleteStmt = $pdo->prepare("DELETE FROM book WHERE id = :id");
        $deleteStmt->bindParam(':id', $id, PDO::PARAM_INT);

        // Execute the deletion
        if ($deleteStmt->execute()) {
            $_SESSION['error'] = 'Book deleted successfully.';
        } else {
            $_SESSION['error'] = 'Error deleting book.';
        }

        // Redirect to the home page
        header('Location: /');
        exit;

    case 'fetchPlex':

        $smarty->assign('header', 'Scan a Plex library for books');
        $smarty->display('fetchPlex.tpl');
        break;

    case 'fetchPlexAction':

        // get the Plex token
        $headers = [
            "X-Plex-Token: " . $plexToken,
            "Accept: application/json"
        ];

        // get the sections
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $plexEndpoint . "/library/sections/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode != 200) {
            $_SESSION['error'] = "Unable to connect to the Plex server. " . $httpCode . ' ' . $response;
            header('Location: /');
            exit;
        }

        $dets = json_decode($response);

        // cycle through the sections to find the audiobooks library
        foreach ($dets->MediaContainer->Directory as $section) {
            if ($section->title == $plexTitle) {
                $sectionKey = $section->key;
            }
        }

        // if we haven't found the Audiobooks library then throw an error
        if (!isset($sectionKey)) {
            $_SESSION['error'] = "Unable to find the Audiobooks library. Please check the Plex server and the library name.";
            header('Location: /');
            exit;
        }

        // get all the items in the library
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $plexEndpoint . "/library/sections/" . $sectionKey . "/all");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode != 200) {
            $_SESSION['error'] = "Unable to get the library items. $httpCode - $response";
            header('Location: /');
            exit;
        }
        $dets = json_decode($response);

        // cycle through all the audiobooks in the Plex library
        $audiobooks = $dets->MediaContainer->Metadata;

        foreach ($audiobooks as $audiobook) {
            if (isset($audiobook->key)) $key = $audiobook->key;

            // Fetch full metadata
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "$plexEndpoint$key?X-Plex-Token=$plexToken");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            $metaResponse = curl_exec($ch);
            $metaCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($metaCode != 200) {
                $_SESSION['error'] = "Failed to get metadata for key $key";
                header('Location: /');
                exit;
            }

            $dets = json_decode($metaResponse);

            if (isset($dets->MediaContainer->Metadata)) {
                $books = $dets->MediaContainer->Metadata;

                // Support for both array and single metadata entries
                foreach ($books as $item) {

                    if (isset($item->title)) $title = $item->title;
                    if (isset($item->parentTitle)) $authors = $item->parentTitle;
                    if (isset($item->studio)) $publisher = $item->studio;

                    // Gather genres
                    if (isset($item->Genre)) {
                        $genres = [];
                        foreach ($item->Genre as $genre) {
                            $genres[] = $genre->tag;
                        }
                        $genreList = implode(', ', $genres);
                    }

                    $url = null;
                    $subject = $genreList ?? 'Unknown';
                    $isbn = null;
                    $formatId = 3;
                    $sourceId = 6;
                    $read = 0;
                    $priority = 0;
                    $dateAdded = date('Y-m-d H:i:s');
                    $notes = null;
                    $list = 0;

                    // Check if book already exists (by author and title)
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM `book` WHERE `author` = :author AND `title` = :title AND `formatId` = :formatId");
                    $stmt->execute([':author' => $authors, ':title' => $title, ':formatId' => $formatId]);
                    $exists = $stmt->fetchColumn();

                    if (!$exists) {
                        $insert = $pdo->prepare("INSERT INTO `book` (`author`, `title`, `genre`, `isbn`, `formatId`, `sourceId`, `read`, `priority`, `dateAdded`, `notes`, `list`, `url`)
                                                        VALUES (:author, :title, :genre, :isbn, :formatId, :sourceId, :read, :priority, :dateAdded, :notes, :list, :url)");
                        $insert->execute([
                            ':author' => $authors,
                            ':title' => $title,
                            ':genre' => $subject,
                            ':isbn' => $isbn,
                            ':formatId' => $formatId,
                            ':sourceId' => $sourceId,
                            ':read' => $read,
                            ':priority' => $priority,
                            ':dateAdded' => $dateAdded,
                            ':notes' => $notes,
                            ':list' => $list,
                            ':url' => $url
                        ]);

                        $bookId = $pdo->lastInsertId();
                        $insertBookSource = $pdo->prepare("INSERT INTO `bookSource` (`book`, `source`) VALUES (:bookId, :sourceId)");
                        $insertBookSource->execute([
                            ':bookId' => $bookId,
                            ':sourceId' => $sourceId
                        ]);
                    } else {
                        $stmtSource = $pdo->prepare("SELECT COUNT(*) FROM `bookSource` WHERE `book` = (SELECT id FROM `book` WHERE `author` = :author AND `title` = :title AND `formatId` = :formatId) AND `source` = :sourceId");
                        $stmtSource->execute([
                            ':author' => $authors,
                            ':title' => $title,
                            ':formatId' => $formatId,
                            ':sourceId' => $sourceId
                        ]);
                        $sourceExists = $stmtSource->fetchColumn();

                        if (!$sourceExists) {
                            // Get the book id
                            $stmtBookId = $pdo->prepare("SELECT id FROM `book` WHERE `author` = :author AND `title` = :title AND `formatId` = :formatId");
                            $stmtBookId->execute([
                                ':author' => $authors,
                                ':title' => $title,
                                ':formatId' => $formatId
                            ]);
                            $existingBookId = $stmtBookId->fetchColumn();

                            if ($existingBookId) {
                                $insertBookSource = $pdo->prepare("INSERT INTO `bookSource` (`book`, `source`) VALUES (:bookId, :sourceId)");
                                $insertBookSource->execute([
                                    ':bookId' => $existingBookId,
                                    ':sourceId' => $sourceId
                                ]);
                            }
                        }
                    }
                }
            }
        }

        // Redirect to the home page
        $_SESSION['error'] = 'Plex library successfully updated.';
        header('Location: /');
        exit;

        break;

    default:

        $_SESSION['error'] = 'Command not recognised';
        header('Location: /');
        break;
}

function smarty_modifier_date_format_tz($input, $format = "Y-m-d H:i:s", $timezone = 'UTC')
{
    try {
        if ($input instanceof DateTime) {
            // If $input is already a DateTime object, use it directly
            $dateTime = $input;
        } else {
            // Assume $input is a Unix timestamp
            $dateTime = new DateTime();
            $dateTime->setTimestamp((int)$input); // Cast to int to avoid errors
        }

        // Set the timezone
        $dateTime->setTimezone(new DateTimeZone($timezone));

        // Return the formatted date
        return $dateTime->format($format);
    } catch (Exception $e) {
        // Handle any exceptions, e.g., invalid timezone or timestamp
        return '';
    }
}

function array_to_html($val, $var = FALSE)
{
    $do_nothing = true;
    $indent_size = 20;
    $out = '';
    $colors = array(
        "Teal",
        "YellowGreen",
        "Tomato",
        "Navy",
        "MidnightBlue",
        "FireBrick",
        "DarkGreen"
    );

    // Get string structure
    ob_start();
    print_r($val);
    $val = ob_get_contents();
    ob_end_clean();

    // Color counter
    $current = 0;

    // Split the string into character array
    $array = preg_split('//', $val, -1, PREG_SPLIT_NO_EMPTY);
    foreach ($array as $char) {
        if ($char == "[")
            if (!$do_nothing)
                if ($var) {
                    $out .= "</div>";
                } else {
                    echo "</div>";
                }
            else $do_nothing = false;
        if ($char == "[")
            if ($var) {
                $out .= "<div>";
            } else {
                echo "<div>";
            }
        if ($char == ")") {
            if ($var) {
                $out .= "</div></div>";
            } else {
                echo "</div></div>";
            }
            $current--;
        }

        if ($var) {
            $out .= $char;
        } else {
            echo $char;
        }

        if ($char == "(") {
            if ($var) {
                $out .= "<div class='indent' style='padding-left: {$indent_size}px; color: " . ($colors[$current % count($colors)]) . ";'>";
            } else {
                echo "<div class='indent' style='padding-left: {$indent_size}px; color: " . ($colors[$current % count($colors)]) . ";'>";
            }
            $do_nothing = true;
            $current++;
        }
    }

    return $out;
}
