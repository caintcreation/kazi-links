<?php
// api/complete-level.php - Grades answers server-side, issues rewards, updates task progress & referral eligibility
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    jsonResponse(['success' => false, 'message' => 'Please log in to continue.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Invalid request method.'], 405);
}

$csrf = $_POST['csrf_token'] ?? '';
if (!verifyCsrfToken($csrf)) {
    jsonResponse(['success' => false, 'message' => 'Security token expired. Please refresh.'], 403);
}

$user = getLoggedInUser();
$levelId = (int)($_POST['level_id'] ?? 0);
$submittedAnswers = $_POST['answers'] ?? []; // e.g. [question_id => selected_option]

if ($levelId <= 0 || empty($submittedAnswers) || !is_array($submittedAnswers)) {
    jsonResponse(['success' => false, 'message' => 'Please answer all questions before submitting.'], 422);
}

$db = getDB();

// Fetch Level
$stmt = $db->prepare("SELECT l.*, t.id as task_id, t.name as task_name, t.slug as task_slug 
                      FROM levels l 
                      JOIN tasks t ON l.task_id = t.id 
                      WHERE l.id = ? AND l.status = 'active'");
$stmt->execute([$levelId]);
$level = $stmt->fetch();

if (!$level) {
    jsonResponse(['success' => false, 'message' => 'Level not found.'], 404);
}

// Check Level Progress state
$progStmt = $db->prepare("SELECT * FROM user_level_progress WHERE user_id = ? AND level_id = ?");
$progStmt->execute([$user['id'], $levelId]);
$progress = $progStmt->fetch();

// Check if already completed to prevent duplicate rewards
if ($progress && $progress['status'] === 'completed') {
    jsonResponse([
        'success' => false,
        'message' => 'You have already completed this level and claimed its reward.'
    ], 400);
}

// Fetch all active questions for this level
$qStmt = $db->prepare("SELECT id, question_text, correct_option, points, explanation FROM questions WHERE level_id = ? AND status = 'active'");
$qStmt->execute([$levelId]);
$questions = $qStmt->fetchAll();

if (empty($questions)) {
    jsonResponse(['success' => false, 'message' => 'No questions found for this level.'], 404);
}

$totalQuestions = count($questions);
$correctCount = 0;
$totalScore = 0;
$reviewDetails = [];

foreach ($questions as $q) {
    $qId = (string)$q['id'];
    $userChoice = strtoupper(trim($submittedAnswers[$qId] ?? ''));
    $isCorrect = ($userChoice === $q['correct_option']);

    if ($isCorrect) {
        $correctCount++;
        $totalScore += (int)$q['points'];
    }

    $reviewDetails[] = [
        'question_id' => $q['id'],
        'question' => $q['question_text'],
        'user_choice' => $userChoice,
        'correct' => $isCorrect,
        'explanation' => $q['explanation']
    ];
}

$scorePercent = (int)round(($correctCount / $totalQuestions) * 100);
$passingScore = (int)$level['passing_score'];
$passed = ($scorePercent >= $passingScore);

// Record submission log
$insSub = $db->prepare("INSERT INTO answers (user_id, level_id, score, total_questions, passed, details) VALUES (?, ?, ?, ?, ?, ?)");
$insSub->execute([
    $user['id'],
    $levelId,
    $scorePercent,
    $totalQuestions,
    $passed ? 1 : 0,
    json_encode($reviewDetails)
]);

if (!$passed) {
    // Increment attempts
    if ($progress) {
        $db->prepare("UPDATE user_level_progress SET attempts = attempts + 1, score = ? WHERE id = ?")
           ->execute([$scorePercent, $progress['id']]);
    } else {
        $db->prepare("INSERT INTO user_level_progress (user_id, level_id, status, score, attempts) VALUES (?, ?, 'unlocked', ?, 1)")
           ->execute([$user['id'], $levelId, $scorePercent]);
    }

    jsonResponse([
        'success' => false,
        'passed' => false,
        'score' => $scorePercent,
        'passing_score' => $passingScore,
        'message' => "You scored {$scorePercent}%. The passing threshold is {$passingScore}%. You can review the concepts and try again!",
        'review' => $reviewDetails
    ]);
}

// User passed! Every completed level earns a fixed KSh 100 reward.
$rewardAmount = 100.00;
$desc = "Reward for completing " . $level['task_name'] . " - Level " . $level['level_number'];
$txRef = recordTransaction(
    $user['id'],
    'task_reward',
    $rewardAmount,
    'credit',
    $desc,
    'completed',
    json_encode(['level_id' => $levelId, 'task_id' => $level['task_id'], 'score' => $scorePercent])
);

if ($txRef === false) {
    error_log("Failed to credit level reward for user {$user['id']} and level {$levelId}.");
    jsonResponse([
        'success' => false,
        'passed' => false,
        'message' => 'Your answers were passed, but we could not credit KSh 100 to your account. Please try again or contact support.'
    ], 500);
}

// Update level progress to 'completed'
if ($progress) {
    $db->prepare("UPDATE user_level_progress SET status = 'completed', score = ?, attempts = attempts + 1, completed_at = NOW() WHERE id = ?")
       ->execute([$scorePercent, $progress['id']]);
} else {
    $db->prepare("INSERT INTO user_level_progress (user_id, level_id, status, score, attempts, completed_at) VALUES (?, ?, 'completed', ?, 1, NOW())")
       ->execute([$user['id'], $levelId, $scorePercent]);
}

// Update Task Overall Progress
$countDoneStmt = $db->prepare("SELECT COUNT(*) FROM user_level_progress ulp 
                              JOIN levels l ON ulp.level_id = l.id 
                              WHERE ulp.user_id = ? AND l.task_id = ? AND ulp.status = 'completed'");
$countDoneStmt->execute([$user['id'], $level['task_id']]);
$totalCompletedForTask = (int)$countDoneStmt->fetchColumn();

$taskProgStmt = $db->prepare("INSERT INTO user_task_progress (user_id, task_id, completed_levels, last_active_at) 
                             VALUES (?, ?, ?, NOW()) 
                             ON DUPLICATE KEY UPDATE completed_levels = ?, last_active_at = NOW()");
$taskProgStmt->execute([$user['id'], $level['task_id'], $totalCompletedForTask, $totalCompletedForTask]);

jsonResponse([
    'success' => true,
    'passed' => true,
    'score' => $scorePercent,
    'reward' => formatCurrency($rewardAmount),
    'message' => "Congratulations! You scored {$scorePercent}% and passed Level {$level['level_number']}! KSh 100 has been credited to your account.",
    'redirect' => BASE_URL . "/job.php?slug=" . urlencode($level['task_slug']),
    'review' => $reviewDetails
]);
