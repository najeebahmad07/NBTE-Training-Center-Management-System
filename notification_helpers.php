<?php
/**
 * ================================================================
 * RISE - Notification Helper Functions
 * Add these functions to your includes/functions.php (or auth.php)
 * ================================================================
 */

/**
 * Get unread notification count for current user
 */
function getUnreadNotificationCount() {
    $userId = getCurrentUserId();
    if (!$userId) return 0;

    $db   = getDB();
    $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE to_admin = :id AND is_read = 0");
    $stmt->execute([':id' => $userId]);
    return (int) $stmt->fetchColumn();
}

/**
 * Get latest unread notifications for current user (for dropdown)
 */
function getLatestNotifications($limit = 5) {
    $userId = getCurrentUserId();
    if (!$userId) return [];

    $db   = getDB();
    $stmt = $db->prepare("
        SELECT n.*, s.full_name, s.enrollment_no
        FROM notifications n
        JOIN students s ON n.student_id = s.id
        WHERE n.to_admin = :id
        ORDER BY n.is_read ASC, n.created_at DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':id',    $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit,  PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Mark all notifications as read for current user
 */
function markAllNotificationsRead() {
    $userId = getCurrentUserId();
    if (!$userId) return;

    $db   = getDB();
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE to_admin = :id");
    $stmt->execute([':id' => $userId]);
}