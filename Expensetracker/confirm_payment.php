<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
require 'config.php';

$user_id = $_SESSION['user_id'];

// Expenses YOU owe (pending)
$stmt = $conn->prepare("SELECT se.id as share_id, e.note as description, e.amount as total_amount, se.share_amount, u.name as paid_by, se.paid_request
                        FROM shared_expenses se
                        JOIN expenses e ON se.expense_id = e.id
                        JOIN users u ON e.user_id = u.id
                        WHERE se.user_id = ? AND se.is_settled = 0 AND e.user_id != ?");
$stmt->execute([$user_id, $user_id]);
$owe_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Expenses OTHERS owe YOU (pending)
$stmt = $conn->prepare("SELECT se.id as share_id, e.note as description, e.amount as total_amount, se.share_amount, u.name as owes_user, se.paid_request
                        FROM shared_expenses se
                        JOIN expenses e ON se.expense_id = e.id
                        JOIN users u ON se.user_id = u.id
                        WHERE e.user_id = ? AND se.user_id != ? AND se.is_settled = 0");
$stmt->execute([$user_id, $user_id]);
$owed_records = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Settled history (either you paid or you received)
$stmt = $conn->prepare("SELECT se.id as share_id, e.note as description, e.amount as total_amount, se.share_amount, se.settled_date,
                              se.user_id AS owes_user_id, (SELECT name FROM users WHERE id = se.user_id) as owes_name,
                              e.user_id AS paid_by_id, (SELECT name FROM users WHERE id = e.user_id) as paid_by_name
                       FROM shared_expenses se
                       JOIN expenses e ON se.expense_id = e.id
                       WHERE (se.user_id = ? OR e.user_id = ?) AND se.is_settled = 1
                       ORDER BY se.settled_date DESC LIMIT 50");
$stmt->execute([$user_id, $user_id]);
$settled_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <title>Activity - Shared Expenses</title>
        <meta name="viewport" content="width=device-width,initial-scale=1" />
        <style>
            body {
                font-family:'Segoe UI',sans-serif;
                background: linear-gradient(135deg,#0f2027,#203a43,#2c5364);
                color:#fff;
                margin:0;
            }
            header {
                padding:16px 28px;
                background: rgba(0,0,0,0.3);
                display:flex;
                justify-content:space-between;
                align-items:center;
            }
            .container {
                max-width:1000px;
                margin:28px auto;
                padding:0 16px;
            }
            h1,h2 {
                margin:6px 0 12px;
            }
            .box {
                background: rgba(255,255,255,0.06);
                padding:16px;
                border-radius:12px;
                margin-bottom:16px;
                color:#fff;
            }
            table {
                width:100%;
                border-collapse:collapse;
                margin-top:8px;
            }
            th, td {
                padding:10px 12px;
                border-bottom:1px solid rgba(255,255,255,0.06);
                text-align:left;
            }
            th {
                color:#fff;
                opacity:0.95;
                font-weight:700;
            }
            .muted {
                color:rgba(255,255,255,0.7);
                font-size:0.95rem;
            }
            .btn {
                padding:8px 12px;
                border-radius:8px;
                font-weight:700;
                border:none;
                cursor:pointer;
                color:#fff;
            }
            .btn.pay {
                background:#007bff;
            }
            .btn.confirm {
                background:#28a745;
            }
            .label.pending {
                background:#f39c12;
                color:#000;
                padding:6px 8px;
                border-radius:8px;
                font-weight:700;
            }
            .label.request {
                background:#6c757d;
                color:#fff;
                padding:6px 8px;
                border-radius:8px;
                font-weight:700;
            }
            .small {
                font-size:0.9rem;
                color:rgba(255,255,255,0.85);
            }
            .back {
                margin-top:12px;
                display:inline-block;
                color:#f39c12;
                text-decoration:none;
                font-weight:700;
            }
            form.inline {
                display:inline-block;
                margin:0;
            }
        </style>
    </head>
    <body>
        <header>
            <div><strong>Expense Manager</strong></div>
            <div><a href="dashboard.php" style="color:#f39c12; text-decoration:none;">← Back to Dashboard</a></div>
        </header>

        <div class="container">
            <h2>Activity</h2>

            <div class="box">
                <h3>Expenses you owe</h3>
                <?php if (count($owe_records) === 0): ?>
                    <p class="muted">No pending amounts you owe.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr><th>For</th><th>Total</th><th>Your share</th><th>Paid By</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($owe_records as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['description'] ?: 'Expense') ?></td>
                                    <td>₹<?= number_format($r['total_amount'], 2) ?></td>
                                    <td>₹<?= number_format($r['share_amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($r['paid_by']) ?></td>
                                    <td>
                                        <?php if ($r['paid_request']): ?>
                                            <span class="label.request">Payment requested — waiting confirmation</span>
                                        <?php else: ?>
                                            <form class="inline" method="POST" action="mark_paid.php">
                                                <input type="hidden" name="share_id" value="<?= $r['share_id'] ?>">
                                                <button class="btn pay" type="submit">I Paid</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="box">
                <h3>Expenses others owe you</h3>
                <?php if (count($owed_records) === 0): ?>
                    <p class="muted">No pending amounts others owe you.</p>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr><th>For</th><th>Total</th><th>Share</th><th>Owes</th><th>Action</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($owed_records as $r): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['description'] ?: 'Expense') ?></td>
                                    <td>₹<?= number_format($r['total_amount'], 2) ?></td>
                                    <td>₹<?= number_format($r['share_amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($r['owes_user']) ?></td>
                                    <td>
                                        <?php if ($r['paid_request']): ?>
                                            <form class="inline" method="POST" action="confirm_payment.php">
                                                <input type="hidden" name="share_id" value="<?= $r['share_id'] ?>">
                                                <button class="btn confirm" type="submit">Confirm Payment</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="label.pending">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <div class="box">
                <h3>Settled history (recent)</h3>
                <?php if (count($settled_records) === 0): ?>
                    <p class="muted">No settled records yet.</p>
                <?php else: ?>
                    <table>
                        <thead><tr><th>For</th><th>Share</th><th>Who paid</th><th>Who owed</th><th>When</th></tr></thead>
                        <tbody>
                            <?php foreach ($settled_records as $s): ?>
                                <tr>
                                    <td><?= htmlspecialchars($s['description'] ?? 'Expense') ?></td>
                                    <td>₹<?= number_format($s['share_amount'], 2) ?></td>
                                    <td><?= htmlspecialchars($s['paid_by_name'] ?? '') ?></td>
                                    <td><?= htmlspecialchars($s['owes_name'] ?? '') ?></td>
                                    <td class="small"><?= htmlspecialchars($s['settled_date'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>

                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

        </div>
    </body>
</html>
