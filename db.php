<?php
// db.php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
 
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            // Create database file in the same directory
            $dbPath = __DIR__ . '/database.sqlite';
            $pdo = new PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            initDB($pdo);
        } catch (PDOException $e) {
            // If permission error, show it clearly
            die("Database Connection Error: " . $e->getMessage() . "<br>Check folder permissions.");
        }
    }
    return $pdo;
}
 
function initDB($pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT,
        email TEXT UNIQUE,
        password TEXT,
        headline TEXT DEFAULT 'Student at University',
        bio TEXT DEFAULT 'Add a short bio.',
        avatar TEXT DEFAULT 'https://picsum.photos/seed/user/100/100'
    )");
 
    $pdo->exec("CREATE TABLE IF NOT EXISTS posts (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        content TEXT,
        type TEXT DEFAULT 'update',
        job_title TEXT,
        job_company TEXT,
        article_title TEXT,
        article_link TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
 
    $pdo->exec("CREATE TABLE IF NOT EXISTS comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        post_id INTEGER,
        user_id INTEGER,
        content TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
 
    $pdo->exec("CREATE TABLE IF NOT EXISTS connections (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        friend_id INTEGER,
        status TEXT DEFAULT 'pending'
    )");
 
    $pdo->exec("CREATE TABLE IF NOT EXISTS experience (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        title TEXT,
        company TEXT,
        duration TEXT
    )");
 
    $pdo->exec("CREATE TABLE IF NOT EXISTS education (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        school TEXT,
        degree TEXT
    )");
 
    $pdo->exec("CREATE TABLE IF NOT EXISTS skills (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER,
        name TEXT
    )");
 
    // Create default user if empty
    try {
        $stmt = $pdo->query("SELECT count(*) FROM users");
        if ($stmt->fetchColumn() == 0) {
            $pass = password_hash('password', PASSWORD_DEFAULT);
            $pdo->exec("INSERT INTO users (name, email, password) VALUES ('New User', 'user@example.com', '$pass')");
        }
    } catch (Exception $e) {
        // Ignore errors during seed check
    }
}
 
function loginUser($email, $password) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    // For demo: if user exists, log in. If not, register automatically.
    if ($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        return true;
    }
    return false;
}
 
function registerUser($name, $email, $password) {
    $pdo = getDB();
    $hash = password_hash($password, PASSWORD_DEFAULT);
    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
        $stmt->execute([$name, $email, $hash]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}
 
function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) return null;
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}
 
function getPosts() {
    $pdo = getDB();
    $sql = "SELECT posts.*, users.name as author_name, users.avatar as author_avatar, users.headline as author_headline
            FROM posts 
            JOIN users ON posts.user_id = users.id 
            ORDER BY posts.created_at DESC";
    return $pdo->query($sql)->fetchAll();
}
 
function addPost($user_id, $content, $type, $job_title, $job_company, $article_title, $article_link) {
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO posts (user_id, content, type, job_title, job_company, article_title, article_link) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $content, $type, $job_title, $job_company, $article_title, $article_link]);
}
 
function addComment($post_id, $user_id, $content) {
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO comments (post_id, user_id, content) VALUES (?, ?, ?)");
    $stmt->execute([$post_id, $user_id, $content]);
}
 
function getComments($post_id) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT comments.*, users.name, users.avatar FROM comments JOIN users ON comments.user_id = users.id WHERE post_id = ? ORDER BY created_at ASC");
    $stmt->execute([$post_id]);
    return $stmt->fetchAll();
}
 
function getSuggestions($user_id) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id != ? AND id NOT IN (SELECT friend_id FROM connections WHERE user_id = ?) LIMIT 8");
    $stmt->execute([$user_id, $user_id]);
    return $stmt->fetchAll();
}
 
function getRequests($user_id) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT users.* FROM connections JOIN users ON connections.user_id = users.id WHERE connections.friend_id = ? AND connections.status = 'pending'");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}
 
function sendRequest($from_id, $to_id) {
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO connections (user_id, friend_id, status) VALUES (?, ?, 'pending')");
    $stmt->execute([$from_id, $to_id]);
}
 
function acceptRequest($user_id, $requester_id) {
    $pdo = getDB();
    $stmt = $pdo->prepare("UPDATE connections SET status = 'accepted' WHERE user_id = ? AND friend_id = ?");
    $stmt->execute([$requester_id, $user_id]);
    $stmt = $pdo->prepare("INSERT INTO connections (user_id, friend_id, status) VALUES (?, ?, 'accepted')");
    $stmt->execute([$user_id, $requester_id]);
}
 
function getConnections($user_id) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT users.* FROM connections JOIN users ON connections.friend_id = users.id WHERE connections.user_id = ? AND connections.status = 'accepted'");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}
 
function updateProfile($user_id, $headline, $bio, $avatar = null) {
    $pdo = getDB();
    if ($avatar) {
        $stmt = $pdo->prepare("UPDATE users SET headline = ?, bio = ?, avatar = ? WHERE id = ?");
        $stmt->execute([$headline, $bio, $avatar, $user_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE users SET headline = ?, bio = ? WHERE id = ?");
        $stmt->execute([$headline, $bio, $user_id]);
    }
}
 
function addExperience($user_id, $title, $company, $duration) {
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO experience (user_id, title, company, duration) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $title, $company, $duration]);
}
 
function getExperience($user_id) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM experience WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}
 
function addEducation($user_id, $school, $degree) {
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO education (user_id, school, degree) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $school, $degree]);
}
 
function getEducation($user_id) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM education WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}
 
function addSkill($user_id, $name) {
    $pdo = getDB();
    $stmt = $pdo->prepare("INSERT INTO skills (user_id, name) VALUES (?, ?)");
    $stmt->execute([$user_id, $name]);
}
 
function getSkills($user_id) {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM skills WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}
?>
