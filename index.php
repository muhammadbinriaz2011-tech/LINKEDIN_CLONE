<?php
// index.php
// Enable Error Reporting to see exact errors instead of 500
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
 
require_once 'db.php';
 
// Handle AJAX Actions
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $user = getCurrentUser();
 
    if (!$user) { echo json_encode(['status' => 'error', 'message' => 'Not logged in']); exit; }
 
    if ($action === 'like') {
        echo json_encode(['status' => 'success']);
        exit;
    }
    if ($action === 'comment') {
        $post_id = $_POST['post_id'];
        $content = $_POST['content'];
        addComment($post_id, $user['id'], $content);
        echo json_encode(['status' => 'success']);
        exit;
    }
    if ($action === 'connect') {
        $target_id = $_POST['target_id'];
        sendRequest($user['id'], $target_id);
        echo json_encode(['status' => 'success']);
        exit;
    }
    if ($action === 'accept_request') {
        $requester_id = $_POST['requester_id'];
        acceptRequest($user['id'], $requester_id);
        echo json_encode(['status' => 'success']);
        exit;
    }
    if ($action === 'ignore_request') {
        echo json_encode(['status' => 'success']);
        exit;
    }
    exit;
}
 
// Handle Form Submissions
$user = getCurrentUser();
$message = "";
 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['form_action'] ?? '';
 
    if ($action === 'login') {
        $name = $_POST['auth-name'];
        $email = $_POST['email'];
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existing = $stmt->fetch();
 
        if ($existing) {
             $_SESSION['user_id'] = $existing['id'];
             $_SESSION['user_name'] = $existing['name'];
        } else {
            registerUser($name, $email, 'password');
            loginUser($email, 'password');
        }
        header("Location: index.php"); exit;
    }
 
    if ($user) {
        if ($action === 'create_post') {
            addPost($user['id'], $_POST['post-text'], $_POST['post-type'], $_POST['job-title'], $_POST['job-company'], $_POST['article-title'], $_POST['article-link']);
            header("Location: index.php"); exit;
        }
        if ($action === 'update_profile') {
            $avatar = null;
            if (isset($_FILES['profile-photo']) && $_FILES['profile-photo']['error'] == 0) {
                $imgData = file_get_contents($_FILES['profile-photo']['tmp_name']);
                $avatar = 'image/jpeg;base64,' . base64_encode($imgData);
            }
            updateProfile($user['id'], $_POST['edit-headline'], $_POST['edit-about'], $avatar);
            header("Location: index.php?view=profile"); exit;
        }
        if ($action === 'add_exp') {
            addExperience($user['id'], $_POST['exp-title'], $_POST['exp-company'], $_POST['exp-duration']);
            header("Location: index.php?view=profile"); exit;
        }
        if ($action === 'add_edu') {
            addEducation($user['id'], $_POST['edu-school'], $_POST['edu-degree']);
            header("Location: index.php?view=profile"); exit;
        }
        if ($action === 'add_skill') {
            addSkill($user['id'], $_POST['skill-name']);
            header("Location: index.php?view=profile"); exit;
        }
    }
}
 
// Fetch Data
$posts = $user ? getPosts() : [];
$suggestions = $user ? getSuggestions($user['id']) : [];
$requests = $user ? getRequests($user['id']) : [];
$connections = $user ? getConnections($user['id']) : [];
$experience = $user ? getExperience($user['id']) : [];
$education = $user ? getEducation($user['id']) : [];
$skills = $user ? getSkills($user['id']) : [];
 
$view = $_GET['view'] ?? 'home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ProConnect</title>
<style>
/* CSS SAME AS BEFORE */
:root { --primary: #0a66c2; --bg-body: #f3f2ef; --bg-card: #ffffff; --text-main: #191919; --text-sub: #666666; --border: #e0e0e0; }
* { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
body { background-color: var(--bg-body); color: var(--text-main); padding-top: 55px; }
a { text-decoration: none; color: inherit; cursor: pointer; }
ul { list-style: none; }
.btn { display: inline-block; padding: 6px 16px; border-radius: 24px; font-weight: 600; font-size: 1rem; cursor: pointer; border: none; transition: 0.2s; }
.btn-primary { background: var(--primary); color: white; }
.btn-primary:hover { background: #004182; }
.btn-outline { border: 1px solid var(--primary); color: var(--primary); background: transparent; }
.btn-outline:hover { background: rgba(10, 102, 194, 0.1); }
.btn-ghost { color: var(--text-sub); background: transparent; font-weight: 500; }
.btn-ghost:hover { background: rgba(0,0,0,0.08); }
.card { background: var(--bg-card); border-radius: 8px; border: 1px solid var(--border); margin-bottom: 8px; overflow: hidden; }
.input-group { width: 100%; padding: 10px; border: 1px solid var(--border); border-radius: 4px; font-size: 0.9rem; margin-bottom: 10px; }
header { background: var(--bg-card); height: 55px; position: fixed; top: 0; width: 100%; z-index: 100; border-bottom: 1px solid var(--border); display: flex; justify-content: center; }
.header-content { width: 100%; max-width: 1128px; display: flex; align-items: center; padding: 0 20px; }
.logo { color: var(--primary); font-size: 28px; margin-right: 8px; }
.search-bar { background: #eef3f8; padding: 0 10px; height: 35px; border-radius: 4px; display: flex; align-items: center; margin-right: auto; width: 280px; }
.search-bar input { border: none; background: transparent; width: 100%; outline: none; margin-left: 5px; }
.nav-menu { display: flex; height: 100%; }
.nav-item { display: flex; flex-direction: column; align-items: center; justify-content: center; min-width: 70px; color: var(--text-sub); font-size: 12px; cursor: pointer; border-bottom: 2px solid transparent; }
.nav-item.active { color: var(--text-main); border-bottom-color: var(--text-main); }
.nav-item:hover { background: rgba(0,0,0,0.08); }
.nav-icon { font-size: 20px; margin-bottom: 2px; }
.main-layout { display: grid; grid-template-columns: 225px 1fr 300px; gap: 24px; max-width: 1128px; margin: 24px auto; padding: 0 20px; }
.profile-card { text-align: center; padding-bottom: 10px; position: relative; }
.profile-banner { background: linear-gradient(45deg, #0073b1, #0a66c2); height: 60px; width: 100%; }
.profile-pic-lg { width: 72px; height: 72px; border-radius: 50%; border: 2px solid white; margin-top: -38px; object-fit: cover; }
.profile-name { font-weight: 600; font-size: 1rem; margin-top: 4px; }
.profile-headline { font-size: 0.8rem; color: var(--text-sub); margin-bottom: 10px; padding: 0 10px;}
.create-post { padding: 12px 16px; }
.cp-input-area { display: flex; gap: 10px; }
.cp-user-img { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; }
.cp-input { width: 100%; border: none; background: #f3f2ef; border-radius: 30px; padding: 12px 16px; font-weight: 500; font-size: 14px; cursor: pointer; }
.cp-input:hover { background: #e0e0e0; }
.post-card { padding: 0; }
.post-header { display: flex; padding: 12px 16px; align-items: flex-start; gap: 10px; }
.post-info h3 { font-size: 0.9rem; margin-bottom: 2px; }
.post-info p { font-size: 0.75rem; color: var(--text-sub); }
.post-content { padding: 4px 16px 16px; font-size: 0.9rem; line-height: 1.5; }
.post-actions { display: flex; padding: 4px 12px; }
.action-btn { flex: 1; display: flex; align-items: center; justify-content: center; gap: 5px; padding: 10px; border-radius: 4px; color: var(--text-sub); font-weight: 600; font-size: 0.9rem; cursor: pointer; }
.action-btn:hover { background: rgba(0,0,0,0.08); }
.action-btn.liked { color: var(--primary); }
.network-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 10px; margin-top: 20px; }
.network-card { padding: 15px; display: flex; flex-direction: column; align-items: center; text-align: center; border: 1px solid var(--border); border-radius: 8px; background: white; }
.nc-avatar { width: 72px; height: 72px; border-radius: 50%; margin-bottom: 10px; object-fit: cover; }
.nc-name { font-weight: 600; font-size: 1rem; margin-bottom: 2px; }
.nc-info { font-size: 0.8rem; color: var(--text-sub); margin-bottom: 10px; }
.nc-mutual { font-size: 0.75rem; color: var(--text-sub); margin-bottom: 12px; }
.network-list-item { display: flex; gap: 12px; padding: 15px; border-bottom: 1px solid #eee; align-items: center; }
.nli-content { flex-grow: 1; }
.nli-content h4 { font-size: 1rem; font-weight: 600; }
.nli-content p { font-size: 0.85rem; color: var(--text-sub); }
.nli-actions { display: flex; gap: 8px; }
.job-card-preview { background: #f3f2ef; margin: 0 16px 16px; padding: 12px; border-radius: 8px; border: 1px solid var(--border); }
.article-link-preview { margin: 0 16px 16px; border:1px solid var(--border); border-radius: 8px; overflow: hidden; background: #f3f2ef; }
.comments-section { display: none; padding: 0 16px 16px; border-top: 1px solid var(--border); margin-top: 4px;}
.comment-item { display: flex; gap: 8px; margin-top: 12px; }
.comment-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
.comment-bubble { background: #f3f2ef; border-radius: 8px; padding: 8px 12px; font-size: 0.85rem; }
.comment-bubble strong { display: block; margin-bottom: 2px; color: var(--text-main); }
.comment-input-area { padding: 12px 16px; border-top: 1px solid var(--border); display: none; background: #fff; }
.comment-input-area textarea { width: 100%; border:1px solid var(--border); border-radius: 4px; padding: 8px; font-family: inherit; resize: none; outline: none; }
.list-item { display: flex; gap: 12px; margin-bottom: 16px; border-bottom: 1px solid #eee; padding-bottom: 12px; }
.list-item:last-child { border-bottom: none; }
.item-icon { width: 48px; height: 48px; background: #eef3f8; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 24px; color: #666; }
.item-content h4 { font-size: 1rem; margin-bottom: 2px; }
.item-content p { font-size: 0.85rem; color: var(--text-sub); }
.skill-tag { display: inline-block; padding: 6px 12px; background: white; border: 1px solid var(--border); border-radius: 16px; font-size: 0.85rem; color: var(--text-sub); margin-right: 8px; margin-bottom: 8px; }
.toast { visibility: hidden; min-width: 250px; background-color: #333; color: #fff; text-align: center; border-radius: 4px; padding: 16px; position: fixed; z-index: 3000; left: 50%; bottom: 30px; transform: translateX(-50%); font-size: 17px; }
.toast.show { visibility: visible; animation: fadein 0.5s, fadeout 0.5s 2.5s; }
.modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 2000; display: none; justify-content: center; align-items: center; }
.modal-card { background: white; width: 100%; max-width: 550px; border-radius: 8px; padding: 24px; position: relative; box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.modal-header { font-size: 1.2rem; font-weight: 600; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
.form-group { margin-bottom: 12px; }
.form-group label { display: block; margin-bottom: 5px; font-size: 0.85rem; color: var(--text-sub); }
.form-group input, .form-group textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; }
.close-modal { position: absolute; top: 10px; right: 15px; font-size: 24px; cursor: pointer; color: #666; }
.camera-overlay { position: absolute; bottom: 10px; right: 60px; background: white; border: 1px solid #ccc; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 16px; z-index: 10; }
.post-type-selector { display: flex; gap: 10px; margin-bottom: 20px; }
.type-btn { flex: 1; text-align: center; padding: 8px; border-radius: 20px; font-size: 0.9rem; cursor: pointer; background: white; border: 1px solid var(--border); color: var(--text-sub); }
.type-btn.active { background: #eef3f8; border: 1px solid #ccc; color: var(--primary); font-weight: 600; }
.share-options { display: flex; gap: 10px; margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px; }
.share-btn { flex: 1; text-align: center; display: flex; flex-direction: column; align-items: center; gap: 5px; font-size: 0.85rem; color: var(--text-sub); cursor: pointer; padding: 10px; border-radius: 4px; }
.share-btn:hover { background: rgba(0,0,0,0.05); }
.share-icon { font-size: 24px; }
.view-section { display: none; }
.view-section.active { display: block; }
.trending-item { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 12px; cursor: pointer; }
.trending-thumb { width: 40px; height: 40px; border-radius: 4px; object-fit: cover; }
@keyframes fadein { from {bottom: 0; opacity: 0;} to {bottom: 30px; opacity: 1;} }
@keyframes fadeout { from {bottom: 30px; opacity: 1;} to {bottom: 0; opacity: 0;} }
@media (max-width: 768px) {
.main-layout { grid-template-columns: 1fr; }
.left-sidebar, .right-sidebar { display: none !important; }
.network-grid { grid-template-columns: 1fr 1fr; }
}
</style>
</head>
<body>
 
<!-- 1. LOGIN MODAL -->
<?php if (!$user): ?>
<div id="auth-modal" class="modal-overlay" style="display: flex;">
    <div class="modal-card">
        <span class="close-modal" onclick="closeModal('auth-modal')">&times;</span>
        <h2 style="text-align: center; margin-bottom: 20px; color: var(--primary);">ProConnect Login</h2>
        <form method="POST" onsubmit="handleAuth(event)">
            <input type="hidden" name="form_action" value="login">
            <div class="form-group"><label>Name</label><input type="text" name="auth-name" required placeholder="Your Name"></div>
            <div class="form-group"><label>Email</label><input type="email" name="email" required placeholder="email@example.com"></div>
            <button type="submit" class="btn-primary" style="width: 100%;">Login</button>
        </form>
    </div>
</div>
<?php endif; ?>
 
<!-- 2. CREATE POST MODAL -->
<div id="post-modal" class="modal-overlay">
    <div class="modal-card">
        <span class="close-modal" onclick="closeModal('post-modal')">&times;</span>
        <div class="modal-header">Create a post</div>
        <div style="display: flex; gap: 12px; margin-bottom: 15px;">
            <img id="post-modal-img" src="<?= $user['avatar'] ?? 'https://picsum.photos/seed/user/50/50' ?>" style="width: 48px; height: 48px; border-radius: 50%;">
            <div style="width: 100%;">
                <div id="post-modal-user" style="font-weight: 600; font-size: 0.9rem;"><?= $user['name'] ?? 'New User' ?></div>
                <button class="btn-ghost" style="border: 1px solid var(--border); padding: 4px 10px; border-radius: 20px; font-size: 0.8rem;">🌍 Anyone</button>
            </div>
        </div>
        <form method="POST" id="create-post-form">
            <input type="hidden" name="form_action" value="create_post">
            <input type="hidden" name="post-type" id="post-type-input" value="update">
            <div class="post-type-selector">
                <div class="type-btn active" id="btn-update" onclick="setPostType('update')">Update</div>
                <div class="type-btn" id="btn-job" onclick="setPostType('job')">Job</div>
                <div class="type-btn" id="btn-article" onclick="setPostType('article')">Article</div>
            </div>
            <div id="fields-update"><textarea name="post-text" id="post-text" class="input-group" rows="4" placeholder="What do you want to talk about?"></textarea></div>
            <div id="fields-job" style="display: none;"><div class="form-group"><input type="text" name="job-title" id="job-title" placeholder="Job Title"></div><div class="form-group"><input type="text" name="job-company" id="job-company" placeholder="Company Name"></div></div>
            <div id="fields-article" style="display: none;"><div class="form-group"><input type="text" name="article-title" id="article-title" placeholder="Article Title"></div><div class="form-group"><input type="text" name="article-link" id="article-link" placeholder="https://..."></div></div>
            <button type="submit" class="btn-primary" style="width: 100%;">Post</button>
        </form>
    </div>
</div>
 
<!-- 3. SHARE MODAL -->
<div id="share-modal" class="modal-overlay">
    <div class="modal-card">
        <span class="close-modal" onclick="closeModal('share-modal')">&times;</span>
        <div class="modal-header">Share post</div>
        <textarea id="share-input" class="input-group" rows="3" placeholder="Say something about this..."></textarea>
        <div class="share-options">
            <div class="share-btn" onclick="confirmShare()"><span class="share-icon">🔄</span>Post</div>
            <div class="share-btn" onclick="copyLink()"><span class="share-icon">🔗</span>Copy Link</div>
        </div>
    </div>
</div>
 
<!-- 4. ADD EXPERIENCE MODAL -->
<div id="add-exp-modal" class="modal-overlay">
    <div class="modal-card">
        <span class="close-modal" onclick="closeModal('add-exp-modal')">&times;</span>
        <div class="modal-header">Add Experience</div>
        <form method="POST" onsubmit="closeModal('add-exp-modal')">
            <input type="hidden" name="form_action" value="add_exp">
            <div class="form-group"><label>Title</label><input type="text" name="exp-title" id="exp-title" required></div>
            <div class="form-group"><label>Company</label><input type="text" name="exp-company" id="exp-company" required></div>
            <div class="form-group"><label>Duration</label><input type="text" name="exp-duration" id="exp-duration" placeholder="e.g. 2022 - Present"></div>
            <div class="modal-actions" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn-ghost" onclick="closeModal('add-exp-modal')">Cancel</button>
                <button type="submit" class="btn-primary">Add</button>
            </div>
        </form>
    </div>
</div>
 
<!-- 5. ADD EDUCATION MODAL -->
<div id="add-edu-modal" class="modal-overlay">
    <div class="modal-card">
        <span class="close-modal" onclick="closeModal('add-edu-modal')">&times;</span>
        <div class="modal-header">Add Education</div>
        <form method="POST" onsubmit="closeModal('add-edu-modal')">
            <input type="hidden" name="form_action" value="add_edu">
            <div class="form-group"><label>School</label><input type="text" name="edu-school" id="edu-school" required></div>
            <div class="form-group"><label>Degree</label><input type="text" name="edu-degree" id="edu-degree" required></div>
            <div class="modal-actions" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn-ghost" onclick="closeModal('add-edu-modal')">Cancel</button>
                <button type="submit" class="btn-primary">Add</button>
            </div>
        </form>
    </div>
</div>
 
<!-- 6. ADD SKILLS MODAL -->
<div id="add-skill-modal" class="modal-overlay">
    <div class="modal-card">
        <span class="close-modal" onclick="closeModal('add-skill-modal')">&times;</span>
        <div class="modal-header">Add Skill</div>
        <form method="POST" onsubmit="closeModal('add-skill-modal')">
            <input type="hidden" name="form_action" value="add_skill">
            <div class="form-group"><label>Skill Name</label><input type="text" name="skill-name" id="skill-name" required></div>
            <div class="modal-actions" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
                <button type="button" class="btn-ghost" onclick="closeModal('add-skill-modal')">Cancel</button>
                <button type="submit" class="btn-primary">Add Skill</button>
            </div>
        </form>
    </div>
</div>
 
<!-- 7. EDIT PROFILE MODAL -->
<div id="edit-bio-modal" class="modal-overlay">
    <div class="modal-card">
        <span class="close-modal" onclick="closeModal('edit-bio-modal')">&times;</span>
        <div class="modal-header">Edit Intro</div>
        <form method="POST" enctype="multipart/form-data" onsubmit="closeModal('edit-bio-modal')">
            <input type="hidden" name="form_action" value="update_profile">
            <div class="form-group"><label>Headline</label><input type="text" name="edit-headline" id="edit-headline" value="<?= $user['headline'] ?? '' ?>"></div>
            <div class="form-group"><label>About</label><textarea name="edit-about" id="edit-about" rows="4"><?= $user['bio'] ?? '' ?></textarea></div>
            <div class="form-group"><label>Profile Photo</label><input type="file" name="profile-photo" accept="image/*"></div>
            <div class="modal-actions">
                <button type="button" class="btn-ghost" onclick="closeModal('edit-bio-modal')">Cancel</button>
                <button type="submit" class="btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>
 
<!-- TOAST NOTIFICATION -->
<div id="toast" class="toast">Action Successful</div>
 
<!-- NAVIGATION -->
<header>
    <div class="header-content">
        <div class="logo">in</div>
        <div class="search-bar"><input type="text" placeholder="Search"></div>
        <nav class="nav-menu">
            <div class="nav-item <?= $view === 'home' ? 'active' : '' ?>" onclick="switchView('home', this)"><span class="nav-icon">🏠</span><span>Home</span></div>
            <div class="nav-item <?= $view === 'network' ? 'active' : '' ?>" onclick="switchView('network', this)"><span class="nav-icon">👥</span><span>My Network</span></div>
            <div class="nav-item <?= $view === 'profile' ? 'active' : '' ?>" onclick="switchView('profile', this)"><span class="nav-icon">👤</span><span>Me</span></div>
        </nav>
    </div>
</header>
 
<main class="main-layout">
    <!-- LEFT SIDEBAR -->
    <aside class="left-sidebar" id="left-sidebar">
        <div class="card profile-card">
            <div class="profile-banner"></div>
            <img id="sidebar-img" src="<?= $user['avatar'] ?? 'https://picsum.photos/seed/user/100/100' ?>" class="profile-pic-lg" alt="Profile">
            <h2 class="profile-name" id="sidebar-name"><?= $user['name'] ?? 'New User' ?></h2>
            <p class="profile-headline" id="sidebar-headline"><?= $user['headline'] ?? 'Student at University' ?></p>
        </div>
    </aside>
 
    <!-- CENTER CONTENT -->
    <section class="center-content">
        <!-- HOME VIEW -->
        <div id="view-home" class="view-section <?= $view === 'home' ? 'active' : '' ?>">
            <div class="card create-post">
                <div class="cp-input-area">
                    <img id="home-post-img" src="<?= $user['avatar'] ?? 'https://picsum.photos/seed/user/50/50' ?>" class="cp-user-img" alt="Me">
                    <button class="cp-input" onclick="openPostModal()">Start a post</button>
                </div>
            </div>
            <div class="card">
                <div style="padding: 15px 15px 0;"><h3>Jobs for you</h3></div>
                <div style="padding:15px; border-top:1px solid #eee; display:flex; gap:12px; align-items:center;">
                    <div style="width:48px; height:48px; background:#eee; border-radius:4px;"></div>
                    <div><div style="font-weight:600;">Frontend Dev</div><div style="font-size:0.8rem; color:#666;">TechCorp • Remote</div></div>
                    <button class="btn-outline" style="margin-left:auto; font-size:0.8rem;">Apply</button>
                </div>
            </div>
            <div id="feed-stream">
                <?php foreach ($posts as $post): ?>
                <article class="card post-card">
                    <div class="post-header">
                        <img src="<?= $post['author_avatar'] ?>" class="cp-user-img" alt="<?= $post['author_name'] ?>">
                        <div class="post-info"><h3><?= htmlspecialchars($post['author_name']) ?></h3><p><?= htmlspecialchars($post['author_headline']) ?> • <?= $post['created_at'] ?></p></div>
                    </div>
                    <div class="post-content">
                        <?php if ($post['type'] === 'update'): ?>
                            <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>
                        <?php elseif ($post['type'] === 'job'): ?>
                            <div class="job-card-preview"><h4><?= htmlspecialchars($post['job_title']) ?></h4><p><?= htmlspecialchars($post['job_company']) ?></p></div>
                        <?php elseif ($post['type'] === 'article'): ?>
                            <div class="article-link-preview"><div class="article-link-text"><h4><?= htmlspecialchars($post['article_title']) ?></h4></div><div style="height:100px; background:#ccc;"></div></div>
                        <?php endif; ?>
                    </div>
                    <?php 
                        $comments = getComments($post['id']);
                        $commentCount = count($comments);
                    ?>
                    <div style="padding: 8px 16px; border-bottom: 1px solid var(--border); font-size: 0.75rem; color: var(--text-sub);"><span id="stats-<?= $post['id'] ?>">👍 0 • <?= $commentCount ?> comments</span><span>0 shares</span></div>
                    <div class="post-actions">
                        <div class="action-btn" onclick="toggleLike(this, 'stats-<?= $post['id'] ?>')">Like</div>
                        <div class="action-btn" onclick="toggleCommentInput('comment-box-<?= $post['id'] ?>')">Comment</div>
                        <div class="action-btn" onclick="openShareModal('<?= htmlspecialchars($post['author_name']) ?>')">Share</div>
                    </div>
                    <div id="comments-list-<?= $post['id'] ?>" class="comments-section">
                        <?php foreach ($comments as $comment): ?>
                        <div class="comment-item">
                            <img src="<?= $comment['avatar'] ?>" class="comment-avatar">
                            <div class="comment-bubble"><strong><?= htmlspecialchars($comment['name']) ?></strong><?= htmlspecialchars($comment['content']) ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="comment-box-<?= $post['id'] ?>" class="comment-input-area">
                        <textarea rows="2"></textarea>
                        <div style="text-align:right; margin-top:5px;">
                            <button class="btn-primary" onclick="postComment(this, 'comments-list-<?= $post['id'] ?>', 'stats-<?= $post['id'] ?>', 'comment-box-<?= $post['id'] ?>', <?= $post['id'] ?>)">Post</button>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
 
        <!-- NETWORK VIEW -->
        <div id="view-network" class="view-section <?= $view === 'network' ? 'active' : '' ?>">
            <div class="card">
                <div style="padding: 15px 20px; border-bottom: 1px solid #eee;">
                    <h2 style="font-size: 1.2rem; margin-bottom: 5px;">Manage my network</h2>
                    <div style="display: flex; gap: 15px; font-size: 0.9rem;">
                        <a href="#" style="font-weight: 600; color: var(--primary); border-bottom: 2px solid var(--primary);">Suggestions</a>
                        <a href="#" style="color: var(--text-sub);">Invitations (<?= count($requests) ?>)</a>
                        <a href="#" style="color: var(--text-sub);">Sent (0)</a>
                    </div>
                </div>
                <!-- Received Requests Section -->
                <div id="incoming-requests-container">
                    <?php if (count($requests) > 0): ?>
                    <div style="padding: 15px 20px 5px; font-weight: 600; font-size: 0.9rem; border-bottom: 1px solid #eee;">Invitations</div>
                    <?php foreach ($requests as $req): ?>
                    <div class="network-list-item" id="req-<?= $req['id'] ?>">
                        <img src="<?= $req['avatar'] ?>" style="width: 50px; height: 50px; border-radius: 50%;">
                        <div class="nli-content">
                            <h4><?= htmlspecialchars($req['name']) ?></h4>
                            <p><?= htmlspecialchars($req['headline']) ?></p>
                            <div style="font-size: 0.8rem; margin-top: 2px;">0 mutual connections</div>
                        </div>
                        <div class="nli-actions">
                            <button class="btn-outline" style="padding: 6px 16px; font-size: 0.9rem;" onclick="acceptRequest(<?= $req['id'] ?>, <?= $req['id'] ?>)">Accept</button>
                            <button class="btn-ghost" style="padding: 6px 16px; font-size: 0.9rem;" onclick="ignoreRequest(<?= $req['id'] ?>)">Ignore</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <!-- Suggestions Grid -->
                <div id="suggestions-container" class="network-grid">
                    <?php foreach ($suggestions as $sug): ?>
                    <div class="network-card">
                        <img src="<?= $sug['avatar'] ?>" class="nc-avatar">
                        <h4 class="nc-name"><?= htmlspecialchars($sug['name']) ?></h4>
                        <p class="nc-info"><?= htmlspecialchars($sug['headline']) ?></p>
                        <div class="nc-mutual">Similar to you (Industry)</div>
                        <button id="connect-btn-<?= $sug['id'] ?>" class="btn-outline" style="margin-top: 5px;" onclick="sendRequest(<?= $sug['id'] ?>)">Connect</button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card" style="padding: 20px;">
                <h3>Your Connections</h3>
                <p style="color: var(--text-sub); font-size: 0.9rem;">You have <span id="connection-count"><?= count($connections) ?></span> connections.</p>
                <div id="my-connections-list" style="margin-top: 15px; display: grid; grid-template-columns: repeat(auto-fill, minmax(60px, 1fr)); gap: 20px; text-align: center;">
                    <?php foreach ($connections as $conn): ?>
                    <div>
                        <img src="<?= $conn['avatar'] ?>" style="width: 50px; height: 50px; border-radius: 50%; border: 1px solid #eee;">
                        <div style="font-size: 0.7rem; margin-top: 4px; overflow: hidden; white-space: nowrap; text-overflow: ellipsis;"><?= htmlspecialchars($conn['name']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
 
        <!-- PROFILE VIEW -->
        <div id="view-profile" class="view-section <?= $view === 'profile' ? 'active' : '' ?>">
            <div class="card">
                <div style="height: 120px; background: linear-gradient(45deg, #0073b1, #0a66c2);"></div>
                <div style="padding: 0 24px 24px; position: relative;">
                    <div style="position: absolute; top: -70px; left: 24px;">
                        <img id="profile-img-large" src="<?= $user['avatar'] ?? 'https://picsum.photos/seed/user/100/100' ?>" style="width: 140px; height: 140px; border-radius: 50%; border: 4px solid white; background: white; object-fit: cover;">
                        <input type="file" id="photo-upload" style="display: none;" accept="image/*" onchange="handlePhotoUpload(this)">
                        <div class="camera-overlay" onclick="document.getElementById('photo-upload').click()" title="Change Photo">📷</div>
                    </div>
                    <div style="text-align: right; margin-top: 10px;">
                        <button class="btn-outline" style="font-size: 0.9rem;" onclick="openEditBioModal()">Edit Profile</button>
                    </div>
                    <div style="margin-top: 60px;">
                        <h1 id="profile-name" style="font-size: 1.8rem;"><?= $user['name'] ?? 'New User' ?></h1>
                        <p id="profile-headline" style="font-size: 1.1rem; color: var(--text-main);"><?= $user['headline'] ?? 'Student at University' ?></p>
                        <p id="profile-bio" style="margin-top: 10px; line-height: 1.4;"><?= $user['bio'] ?? 'Add a short bio.' ?></p>
                    </div>
                </div>
            </div>
            <!-- EXP/EDU/SKILL -->
            <div class="card" style="padding: 20px;">
                <div style="display:flex; justify-content:space-between;"><h3>Experience</h3><button class="btn-outline" style="font-size:0.8rem;" onclick="openAddExpModal()">+ Add</button></div>
                <div id="experience-container" style="margin-top:10px;">
                    <?php if (count($experience) == 0): ?>
                    <div style="text-align:center; color:#999;">No experience.</div>
                    <?php else: ?>
                        <?php foreach ($experience as $exp): ?>
                        <div class="list-item"><div class="item-icon">💼</div><div><h4><?= htmlspecialchars($exp['title']) ?></h4><p><?= htmlspecialchars($exp['company']) ?> • <?= htmlspecialchars($exp['duration']) ?></p></div></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card" style="padding: 20px;">
                <div style="display:flex; justify-content:space-between;"><h3>Education</h3><button class="btn-outline" style="font-size:0.8rem;" onclick="openAddEduModal()">+ Add</button></div>
                <div id="education-container" style="margin-top:10px;">
                    <?php if (count($education) == 0): ?>
                    <div style="text-align:center; color:#999;">No education.</div>
                    <?php else: ?>
                        <?php foreach ($education as $edu): ?>
                        <div class="list-item"><div class="item-icon">🎓</div><div><h4><?= htmlspecialchars($edu['school']) ?></h4><p><?= htmlspecialchars($edu['degree']) ?></p></div></div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card" style="padding: 20px;">
                <div style="display:flex; justify-content:space-between;"><h3>Skills</h3><button class="btn-outline" style="font-size:0.8rem;" onclick="openAddSkillModal()">+ Add</button></div>
                <div id="skills-container" style="margin-top:10px;">
                    <?php if (count($skills) == 0): ?>
                    <div style="text-align:center; color:#999;">No skills.</div>
                    <?php else: ?>
                        <?php foreach ($skills as $skill): ?>
                        <span class="skill-tag"><?= htmlspecialchars($skill['name']) ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
 
    <!-- RIGHT SIDEBAR -->
    <aside class="right-sidebar" id="right-sidebar">
        <div class="card" style="padding: 16px;">
            <div style="font-size: 1rem; font-weight: 600; margin-bottom: 15px;">Trending Now</div>
            <div class="trending-item">
                <img src="https://picsum.photos/seed/trend1/40/40" class="trending-thumb">
                <div><div style="font-weight: 600; font-size: 0.85rem;">Remote Work</div><div style="font-size: 0.75rem; color: var(--text-sub);">Trending in Tech</div></div>
            </div>
        </div>
    </aside>
</main>
 
<script>
let currentUser = "<?= $user['name'] ?? 'Guest' ?>";
let currentPostType = "update";
 
function openModal(id) { document.getElementById(id).style.display = 'flex'; }
function closeModal(id) { document.getElementById(id).style.display = 'none'; }
 
function switchView(viewName, navElement) {
    document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
    if(navElement) navElement.classList.add('active');
    document.querySelectorAll('.view-section').forEach(el => el.classList.remove('active'));
    document.getElementById('view-' + viewName).classList.add('active');
 
    const leftSidebar = document.getElementById('left-sidebar');
    const rightSidebar = document.getElementById('right-sidebar');
    if (viewName === 'profile' || viewName === 'network') {
        if(window.innerWidth > 768) { leftSidebar.style.display = 'none'; rightSidebar.style.display = 'none'; }
    } else {
        if(window.innerWidth > 768) { leftSidebar.style.display = 'block'; rightSidebar.style.display = 'block'; }
    }
    history.pushState({}, '', '?view=' + viewName);
}
 
function handleAuth(e) {
    // Form submits normally to index.php
}
 
function openPostModal() {
    document.getElementById('post-text').value = ''; setPostType('update'); openModal('post-modal');
}
 
function setPostType(type) {
    currentPostType = type;
    document.getElementById('post-type-input').value = type;
    document.querySelectorAll('.type-btn').forEach(btn => btn.classList.remove('active'));
    document.getElementById('btn-' + type).classList.add('active');
    document.getElementById('fields-update').style.display = (type === 'update') ? 'block' : 'none';
    document.getElementById('fields-job').style.display = (type === 'job') ? 'block' : 'none';
    document.getElementById('fields-article').style.display = (type === 'article') ? 'block' : 'none';
}
 
function toggleLike(btn, statsId) {
    btn.classList.toggle('liked');
    const statsEl = document.getElementById(statsId); 
    let text = statsEl.innerText; 
    let likeCount = parseInt(text.split(' ')[1]);
    if(btn.classList.contains('liked')) { likeCount++; } else { likeCount--; }
    let parts = text.split(' '); parts[1] = likeCount; statsEl.innerText = parts.join(' ');
 
    const formData = new FormData();
    formData.append('action', 'like');
    fetch('index.php', { method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: formData });
}
 
function toggleCommentInput(boxId) {
    const box = document.getElementById(boxId);
    box.style.display = (box.style.display === 'block') ? 'none' : 'block';
}
 
function postComment(btn, listId, statsId, boxId, postId) {
    const inputArea = btn.closest('.comment-input-area'); 
    const textarea = inputArea.querySelector('textarea');
    const text = textarea.value.trim(); 
    if(!text) return;
 
    const formData = new FormData();
    formData.append('action', 'comment');
    formData.append('post_id', postId);
    formData.append('content', text);
 
    fetch('index.php', { method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: formData })
    .then(() => {
        const commentsList = document.getElementById(listId);
        const newComment = document.createElement('div'); 
        newComment.className = 'comment-item';
        newComment.innerHTML = `<img src="<?= $user['avatar'] ?? '' ?>" class="comment-avatar"><div class="comment-bubble"><strong><?= $user['name'] ?? 'User' ?></strong>${text}</div>`;
        commentsList.appendChild(newComment); 
        commentsList.style.display = 'block';
 
        const statsEl = document.getElementById(statsId); 
        let textParts = statsEl.innerText.split('•'); 
        let commentPart = textParts[1] || " 0 comments"; 
        let count = parseInt(commentPart) || 0; 
        count++;
        statsEl.innerHTML = `${textParts[0]} • <span>${count} comments</span>`;
        textarea.value = ''; 
        document.getElementById(boxId).style.display = 'none';
    });
}
 
function openShareModal(author) { document.getElementById('share-input').value = ''; openModal('share-modal'); }
function confirmShare() { showToast("Post shared!"); closeModal('share-modal'); }
function copyLink() { showToast("Link copied"); closeModal('share-modal'); }
 
function showToast(msg) { 
    const t = document.getElementById("toast"); 
    t.innerText = msg; 
    t.className = "toast show"; 
    setTimeout(()=>{t.className = t.className.replace("show", "");}, 3000); 
}
 
function openEditBioModal() { 
    document.getElementById('edit-headline').value = document.getElementById('profile-headline').innerText; 
    document.getElementById('edit-about').value = document.getElementById('profile-bio').innerText;
    openModal('edit-bio-modal'); 
}
 
function openAddExpModal() { document.getElementById('exp-title').value=''; openModal('add-exp-modal'); }
function openAddEduModal() { document.getElementById('edu-school').value=''; openModal('add-edu-modal'); }
function openAddSkillModal() { document.getElementById('skill-name').value=''; openModal('add-skill-modal'); }
 
function handlePhotoUpload(input) {
    openEditBioModal();
}
 
function sendRequest(userId) {
    const btn = document.getElementById(`connect-btn-${userId}`);
    btn.innerText = "Pending";
    btn.style.border = "1px solid #ccc";
    btn.style.color = "#666";
    btn.style.background = "#f3f2ef";
    btn.onclick = null;
    showToast("Request sent!");
 
    const formData = new FormData();
    formData.append('action', 'connect');
    formData.append('target_id', userId);
    fetch('index.php', { method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: formData });
}
 
function acceptRequest(reqId, requesterId) {
    const el = document.getElementById(`req-${reqId}`);
    if(el) el.remove();
    showToast("Connection added!");
 
    const formData = new FormData();
    formData.append('action', 'accept_request');
    formData.append('requester_id', requesterId);
    fetch('index.php', { method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: formData });
}
 
function ignoreRequest(reqId) {
    const el = document.getElementById(`req-${reqId}`);
    if(el) el.remove();
 
    const formData = new FormData();
    formData.append('action', 'ignore_request');
    fetch('index.php', { method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: formData });
}
</script>
</body>
</html>
