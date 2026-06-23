<?php
require_once 'includes/db.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}

$user_id = $_SESSION['user_id'];
$with_id = isset($_GET['with']) ? (int)$_GET['with'] : 0;
$with_user = null;

$search_results = [];
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
if ($search_query) {
    $s = $conn->prepare(
        'SELECT id, username, full_name FROM users
         WHERE (username LIKE ? OR full_name LIKE ?) AND id != ?
         LIMIT 10'
    );
    $like = '%' . $search_query . '%';
    $s->bind_param('ssi', $like, $like, $user_id);
    $s->execute();
    $search_results = $s->get_result()->fetch_all(MYSQLI_ASSOC);
}

$convos = $conn->prepare(
    'SELECT DISTINCT
        CASE WHEN sender_id=? THEN receiver_id ELSE sender_id END AS contact_id,
        u.username, u.full_name,
        MAX(m.sent_at) AS last_message_time,
        SUM(CASE WHEN m.receiver_id=? AND m.is_read=0 THEN 1 ELSE 0 END) AS unread
     FROM messages m
     JOIN users u ON u.id = CASE WHEN sender_id=? THEN receiver_id ELSE sender_id END
     WHERE m.sender_id=? OR m.receiver_id=?
     GROUP BY contact_id, u.username, u.full_name
     ORDER BY last_message_time DESC'
);
$convos->bind_param('iiiii', $user_id, $user_id, $user_id, $user_id, $user_id);
$convos->execute();
$conversations = $convos->get_result();

$thread = null;
if ($with_id) {
    $us = $conn->prepare('SELECT id, username, full_name FROM users WHERE id=?');
    $us->bind_param('i', $with_id);
    $us->execute();
    $with_user = $us->get_result()->fetch_assoc();

    $markRead = $conn->prepare('UPDATE messages SET is_read=1 WHERE receiver_id=? AND sender_id=?');
    $markRead->bind_param('ii', $user_id, $with_id);
    $markRead->execute();

    $msgs = $conn->prepare(
        'SELECT m.*, u.username AS sender_name
         FROM messages m
         JOIN users u ON m.sender_id = u.id
         WHERE (m.sender_id=? AND m.receiver_id=?)
            OR (m.sender_id=? AND m.receiver_id=?)
         ORDER BY m.sent_at ASC'
    );
    $msgs->bind_param('iiii', $user_id, $with_id, $with_id, $user_id);
    $msgs->execute();
    $thread = $msgs->get_result();
}
?>

<?php include 'includes/header.php'; ?>

<div style="max-width:1000px;margin:30px auto;padding:0 20px">
    <h1 style="color:#003366;margin-bottom:20px;font-size:26px">💬 Messages</h1>

    <!-- SEARCH BAR -->
    <form method="GET" style="margin-bottom:20px;display:flex;gap:10px">
        <input
            type="text"
            name="search"
            value="<?= htmlspecialchars($search_query) ?>"
            placeholder="🔍 Search for a user to message..."
            style="flex:1;padding:11px 16px;border:2px solid #ddd;border-radius:8px;
                   font-size:15px;margin:0;outline:none"
            onfocus="this.style.borderColor='#003366'"
            onblur="this.style.borderColor='#ddd'">
        <button type="submit"
                style="padding:11px 24px;background:#003366;color:white;border:none;
                       border-radius:8px;font-weight:bold;cursor:pointer;font-size:15px;
                       width:auto;margin:0">
            Search
        </button>
        <?php if ($search_query): ?>
        <a href="messages.php"
           style="padding:11px 16px;background:#f5f5f5;border:1px solid #ddd;
                  border-radius:8px;font-size:14px;text-decoration:none;color:#666;
                  display:flex;align-items:center">
            Clear
        </a>
        <?php endif; ?>
    </form>

    <!-- SEARCH RESULTS -->
    <?php if ($search_query): ?>
    <div style="background:white;border:1px solid #ddd;border-radius:12px;
                overflow:hidden;margin-bottom:20px">
        <div style="background:#003366;color:white;padding:12px 16px;
                    font-weight:bold;font-size:14px">
            Search Results for "<?= htmlspecialchars($search_query) ?>"
        </div>
        <?php if (empty($search_results)): ?>
            <div style="padding:24px;text-align:center;color:#666;font-size:14px">
                No users found matching "<?= htmlspecialchars($search_query) ?>"
            </div>
        <?php else: ?>
            <?php foreach ($search_results as $u): ?>
            <a href="messages.php?with=<?= $u['id'] ?>"
               style="display:flex;align-items:center;gap:12px;padding:14px 16px;
                      text-decoration:none;color:#1a1a18;border-bottom:1px solid #f0f0f0;
                      transition:background 0.2s"
               onmouseover="this.style.background='#f5f8ff'"
               onmouseout="this.style.background='white'">
                <div style="width:42px;height:42px;border-radius:50%;background:#003366;
                            color:white;display:flex;align-items:center;justify-content:center;
                            font-weight:bold;font-size:16px;flex-shrink:0">
                    <?= strtoupper(substr($u['username'], 0, 1)) ?>
                </div>
                <div>
                    <div style="font-weight:bold;font-size:14px;color:#003366">
                        <?= htmlspecialchars($u['full_name'] ?: $u['username']) ?>
                    </div>
                    <div style="font-size:12px;color:#999">@<?= htmlspecialchars($u['username']) ?></div>
                </div>
                <div style="margin-left:auto;font-size:13px;color:#003366;font-weight:bold">
                    Message →
                </div>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:280px 1fr;gap:20px;min-height:500px">

        <!-- LEFT: CONVERSATIONS LIST -->
        <div style="background:white;border:1px solid #ddd;border-radius:12px;overflow:hidden">
            <div style="background:#003366;color:white;padding:14px 16px;font-weight:bold;font-size:15px">
                Conversations
            </div>
            <?php if ($conversations->num_rows === 0): ?>
                <div style="padding:30px 16px;text-align:center;color:#666;font-size:14px">
                    <div style="font-size:40px;margin-bottom:10px">💬</div>
                    No conversations yet
                </div>
            <?php else: ?>
                <?php while ($convo = $conversations->fetch_assoc()): ?>
                <a href="messages.php?with=<?= $convo['contact_id'] ?>"
                   style="display:flex;align-items:center;gap:12px;padding:14px 16px;
                          text-decoration:none;color:#1a1a18;border-bottom:1px solid #f0f0f0;
                          background:<?= $with_id === $convo['contact_id'] ? '#e8f0fb' : 'white' ?>;
                          transition:background 0.2s">
                    <div style="width:42px;height:42px;border-radius:50%;background:#003366;
                                color:white;display:flex;align-items:center;justify-content:center;
                                font-weight:bold;font-size:16px;flex-shrink:0">
                        <?= strtoupper(substr($convo['username'], 0, 1)) ?>
                    </div>
                    <div style="flex:1;min-width:0">
                        <div style="font-weight:bold;font-size:14px;color:#003366">
                            <?= htmlspecialchars($convo['full_name'] ?: $convo['username']) ?>
                        </div>
                        <div style="font-size:12px;color:#999">
                            <?= date('d M, H:i', strtotime($convo['last_message_time'])) ?>
                        </div>
                    </div>
                    <?php if ($convo['unread'] > 0): ?>
                    <span style="background:#cc0000;color:white;border-radius:50%;
                                 width:20px;height:20px;font-size:11px;font-weight:bold;
                                 display:flex;align-items:center;justify-content:center">
                        <?= $convo['unread'] ?>
                    </span>
                    <?php endif; ?>
                </a>
                <?php endwhile; ?>
            <?php endif; ?>
        </div>

        <!-- RIGHT: MESSAGE THREAD -->
        <div style="background:white;border:1px solid #ddd;border-radius:12px;overflow:hidden;display:flex;flex-direction:column">

            <?php if (!$with_id): ?>
                <div style="flex:1;display:flex;align-items:center;justify-content:center;
                            flex-direction:column;color:#666;padding:40px">
                    <div style="font-size:60px;margin-bottom:16px">💬</div>
                    <h3 style="color:#003366;margin-bottom:8px">Select a conversation</h3>
                    <p style="font-size:14px;text-align:center">Search for a user above or choose a conversation from the left.</p>
                </div>

            <?php else: ?>
                <div style="background:#003366;color:white;padding:14px 20px;display:flex;align-items:center;gap:12px">
                    <div style="width:38px;height:38px;border-radius:50%;background:#cc0000;
                                display:flex;align-items:center;justify-content:center;
                                font-weight:bold;font-size:15px">
                        <?= strtoupper(substr($with_user['username'], 0, 1)) ?>
                    </div>
                    <div>
                        <div style="font-weight:bold;font-size:15px">
                            <?= htmlspecialchars($with_user['full_name'] ?: $with_user['username']) ?>
                        </div>
                        <div style="font-size:12px;opacity:0.8">@<?= htmlspecialchars($with_user['username']) ?></div>
                    </div>
                </div>

                <div style="flex:1;overflow-y:auto;padding:20px;display:flex;flex-direction:column;
                            gap:10px;min-height:350px;max-height:400px" id="messageThread">
                    <?php if ($thread->num_rows === 0): ?>
                        <div style="text-align:center;color:#999;font-size:14px;margin:auto">
                            No messages yet. Say hello! 👋
                        </div>
                    <?php else: ?>
                        <?php while ($msg = $thread->fetch_assoc()): ?>
                        <div style="display:flex;justify-content:<?= $msg['sender_id'] == $user_id ? 'flex-end' : 'flex-start' ?>">
                            <div style="max-width:70%;padding:10px 14px;border-radius:16px;font-size:14px;line-height:1.5;
                                        background:<?= $msg['sender_id'] == $user_id ? '#003366' : '#f0f0f0' ?>;
                                        color:<?= $msg['sender_id'] == $user_id ? 'white' : '#1a1a18' ?>;
                                        border-bottom-<?= $msg['sender_id'] == $user_id ? 'right' : 'left' ?>-radius:4px">
                                <?= htmlspecialchars($msg['body']) ?>
                                <div style="font-size:11px;opacity:0.6;margin-top:4px;text-align:right">
                                    <?= date('H:i', strtotime($msg['sent_at'])) ?>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>

                <div style="border-top:1px solid #ddd;padding:16px">
                    <form method="POST" action="send_message.php" style="display:flex;gap:10px">
                        <input type="hidden" name="receiver_id" value="<?= $with_id ?>">
                        <input type="text" name="body" placeholder="Type your message..." required
                               style="flex:1;padding:10px 14px;border:1px solid #ddd;
                                      border-radius:8px;font-size:14px;margin:0">
                        <button type="submit"
                                style="padding:10px 20px;background:#003366;color:white;border:none;
                                       border-radius:8px;cursor:pointer;font-weight:bold;
                                       font-size:14px;width:auto;margin:0">
                            Send
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const thread = document.getElementById('messageThread');
if (thread) thread.scrollTop = thread.scrollHeight;
</script>

<?php include 'includes/footer.php'; ?>