<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$customer_id = $_GET['id'] ?? 0;

/* ---------- helpers ---------- */

function normalize_type($t) {
    $t = strtolower(trim((string) $t));
    if (in_array($t, ['give', 'debit', 'paid', 'pay'], true))
        return 'give';
    if (in_array($t, ['take', 'credit', 'receive', 'got', 'get'], true))
        return 'take';
    return $t; // fallback (shown raw)
}

/* ---------- fetch customer ---------- */
$stmt = $conn->prepare("SELECT * FROM customers WHERE id = :cid AND user_id = :uid");
$stmt->execute([':cid' => $customer_id, ':uid' => $user_id]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$customer) {
    die("Customer not found!");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['customer_id'])) {
    $stmt = $conn->prepare("UPDATE customers SET name = :name, phone = :phone, email = :email WHERE id = :cid AND user_id = :uid");
    $stmt->execute([
        ':name' => trim($_POST['name']),
        ':phone' => trim($_POST['phone']),
        ':email' => trim($_POST['email']),
        ':cid' => (int) $_POST['customer_id'],
        ':uid' => $user_id
    ]);
    header("Location: customer.php?id=" . (int) $_POST['customer_id']);
    exit;
}


/* ---------- delete transaction (same-page) ---------- */
if (isset($_GET['delete'])) {
    $delete_id = (int) $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM ledger WHERE id = :id AND user_id = :uid AND customer_id = :cid");
    $stmt->execute([':id' => $delete_id, ':uid' => $user_id, ':cid' => $customer_id]);
    header("Location: customer.php?id=$customer_id");
    exit;
}

/* ---------- add or update transaction ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = normalize_type($_POST['type'] ?? '');
    if (!in_array($type, ['give', 'take'], true))
        $type = 'give';

    $amount = (float) ($_POST['amount'] ?? 0);
    $remark = trim($_POST['remark'] ?? '');

    if (isset($_POST['transaction_id']) && $_POST['transaction_id'] !== '') {
        // update
        $stmt = $conn->prepare("
            UPDATE ledger 
               SET type = :type, amount = :amount, remark = :remark 
             WHERE id = :id AND user_id = :uid AND customer_id = :cid
        ");
        $stmt->execute([
            ':type' => $type,
            ':amount' => $amount,
            ':remark' => $remark,
            ':id' => (int) $_POST['transaction_id'],
            ':uid' => $user_id,
            ':cid' => $customer_id
        ]);
    } else {
        // insert
        $stmt = $conn->prepare("
            INSERT INTO ledger (user_id, customer_id, type, amount, remark, created_at) 
            VALUES (:uid, :cid, :type, :amount, :remark, NOW())
        ");
        $stmt->execute([
            ':uid' => $user_id,
            ':cid' => $customer_id,
            ':type' => $type,
            ':amount' => $amount,
            ':remark' => $remark
        ]);
    }

    header("Location: customer.php?id=$customer_id");
    exit;
}

/* ---------- fetch transactions ---------- */
$stmt = $conn->prepare("
    SELECT id, type, amount, remark, created_at 
      FROM ledger 
     WHERE customer_id = :cid AND user_id = :uid 
  ORDER BY created_at DESC
");
$stmt->execute([':cid' => $customer_id, ':uid' => $user_id]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ---------- totals (PHP-based, supports all legacy types) ---------- */
$totalGive = 0;
$totalTake = 0;
foreach ($transactions as $t) {
    $norm = normalize_type($t['type']);
    if ($norm === 'give')
        $totalGive += (float) $t['amount'];
    else if ($norm === 'take')
        $totalTake += (float) $t['amount'];
}
?>
<!DOCTYPE html>
<html>
    <head>
        <title>Customer - <?= htmlspecialchars($customer['name']) ?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link href="css/ledger_style.css" rel="stylesheet" type="text/css"/>
        <style>
            /* ============ page styles ============ */
            body {
                font-family: Arial, sans-serif;
                margin:0;
                background:linear-gradient(180deg,#0b1022, #0f172a 40%, #0b1022);
            }
            .container {
                max-width: 1000px;
                margin: 20px auto;
                padding: 15px;
            }

            .customer-card {
                background:#fff;
                padding:16px;
                border-radius:12px;
                box-shadow:0 2px 6px rgba(0,0,0,.08);
                margin-bottom:16px;
            }
            .customer-card h2{
                margin:0 0 6px;
                font-size:22px;
            }

            .summary {
                display:flex;
                gap:14px;
                flex-wrap:wrap;
                margin-bottom:18px;
            }
            .box {
                flex:1;
                min-width:240px;
                padding:18px;
                border-radius:14px;
                text-align:center;
                color:#fff;
                font-weight:700;
            }
            .box .label {
                opacity:.95;
                font-size:14px;
                display:block;
                margin-bottom:6px;
                letter-spacing:.2px;
            }
            .box .amt {
                font-size:22px;
            }
            .give {
                background:#e74c3c;
            }
            .take {
                background:#27ae60;
            }

            .form-card {
                background:#fff;
                padding:16px;
                border-radius:12px;
                box-shadow:0 2px 6px rgba(0,0,0,.08);
                margin-bottom:18px;
            }
            .form-card h3 {
                margin:0 0 10px;
            }
            form label {
                font-weight:600;
                margin-top:8px;
                display:block;
            }
            form input, form select, form button {
                padding:10px;
                margin:8px 0;
                width:100%;
                border-radius:8px;
                border:1px solid #ccd3db;
                font-size:14px;
            }
            form button {
                background:#1f7ae0;
                color:#fff;
                border:none;
                cursor:pointer;
            }
            form button:hover {
                background:#1766bd;
            }

            .table-wrap {
                overflow-x:auto;
            }
            .transactions-table {
                width:100%;
                border-collapse:collapse;
                background:#fff;
                border-radius:12px;
                overflow:hidden;
                box-shadow:0 2px 6px rgba(0,0,0,.08);
                margin-top:8px;
            }
            .transactions-table th, .transactions-table td {
                padding:12px 14px;
                border-bottom:1px solid #eef1f5;
                text-align:left;
            }
            .transactions-table th {
                background:#f7f9fc;
                font-weight:700;
            }
            .transactions-table tr:nth-child(even){
                background:#fafbfe;
            }

            .badge {
                padding:5px 10px;
                border-radius:12px;
                font-size:13px;
                font-weight:700;
                color:#fff;
            }
            .badge-give {
                background:#e74c3c;
            }
            .badge-take {
                background:#27ae60;
            }

            .btn {
                display:inline-block;
                padding:7px 12px;
                border-radius:8px;
                font-weight:700;
                text-decoration:none;
                font-size:13px;
            }
            .btn-edit {
                background:#f39c12;
                color:#fff;
                margin-right:6px;
            }
            .btn-edit:hover {
                background:#d68910;
            }
            .btn-delete {
                background:#e74c3c;
                color:#fff;
            }
            .btn-delete:hover {
                background:#c0392b;
            }

            @media (max-width: 640px){
                .box .amt {
                    font-size:20px;
                }
            }
            .customer-card {
                position: relative;
            }

            .edit-customer-btn {
                position: absolute;
                top: 16px;
                right: 16px;
                font-size: 18px;
                text-decoration: none;
                color: #1f7ae0;
                cursor: pointer;
                transition: transform 0.2s ease, color 0.2s ease;
            }
            .edit-customer-btn:hover {
                color: #1766bd;
                transform: scale(1.2);
            }

        </style>
    </head>
    <body style="background:linear-gradient(180deg,#0b1022, #0f172a 40%, #0b1022); ">
        <?php include 'ledger_header.php'; ?>

        <div class="container">
            <!-- Customer -->
            <div class="customer-card">
                <h2>
                    <?= htmlspecialchars($customer['name']) ?>
                    <!-- Pencil icon -->
                    <a href="#"
                       class="edit-customer-btn"
                       onclick="return fillCustomerFormForEdit('<?= htmlspecialchars($customer['name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($customer['phone'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($customer['email'] ?? '', ENT_QUOTES) ?>');">
                        ✏
                    </a>
                </h2>
                <div><strong>📞 Phone:</strong> <?= htmlspecialchars($customer['phone'] ?? 'N/A') ?></div>
                <div><strong>✉ Email:</strong> <?= htmlspecialchars($customer['email'] ?? 'N/A') ?></div>
            </div>
            <div class="form-card" id="editCustomerForm" style="display:none;">
                <h3>Update Customer Info</h3>
                <form method="post" id="customerEditForm">
                    <input type="hidden" name="customer_id" value="<?= (int) $customer_id ?>">
                    <label>Name</label>
                    <input type="text" name="name" id="cust_name" required>
                    <label>Phone</label>
                    <input type="text" name="phone" id="cust_phone">
                    <label>Email</label>
                    <input type="email" name="email" id="cust_email">
                    <button type="submit">Update Customer</button>
                </form>
            </div>

            <!-- Summary -->
            <div class="summary">
                <div class="box give">
                    <span class="label">You Will Give</span>
                    <span class="amt">₹<?= number_format($totalGive, 2) ?></span>
                </div>
                <div class="box take">
                    <span class="label">You Will Get</span>
                    <span class="amt">₹<?= number_format($totalTake, 2) ?></span>
                </div>
            </div>

            <!-- Add / Update -->
            <div class="form-card">
                <h3>Add / Update Transaction</h3>
                <form method="post">
                    <input type="hidden" name="transaction_id" id="edit_transaction_id" value="">
                    <label>Transaction Type</label>
                    <select name="type" id="edit_type" required>
                        <option value="give">Give Money</option>
                        <option value="take">Take Money</option>
                    </select>

                    <label>Amount</label>
                    <input type="number" step="0.01" name="amount" id="edit_amount" required>

                    <label>Remark</label>
                    <input type="text" name="remark" id="edit_remark">

                    <button type="submit" id="submit_btn">Add Transaction</button>

                </form>
            </div>

            <!-- Table -->
            <h3 style="color: #fff;">Transactions</h3>
            <div class="table-wrap">
                <table class="transactions-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Remark</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $row): ?>
                            <?php $norm = normalize_type($row['type']); ?>
                            <tr>
                                <td><?= htmlspecialchars($row['created_at']); ?></td>
                                <td>
                                    <?php if ($norm === 'give'): ?>
                                        <span class="badge badge-give">Give</span>
                                    <?php elseif ($norm === 'take'): ?>
                                        <span class="badge badge-take">Take</span>
                                    <?php else: ?>
                                        <?= htmlspecialchars($row['type']); ?>
                                    <?php endif; ?>
                                </td>
                                <td>₹<?= number_format((float) $row['amount'], 2); ?></td>
                                <td><?= htmlspecialchars($row['remark']); ?></td>
                                <td>
                                    <a href="#"
                                       class="btn btn-edit"
                                       onclick="return fillFormForEdit(<?= (int) $row['id']; ?>, '<?= htmlspecialchars($norm, ENT_QUOTES) ?>',<?= (float) $row['amount']; ?>, '<?= htmlspecialchars($row['remark'], ENT_QUOTES) ?>');">
                                        ✏ Edit
                                    </a>
                                    <a class="btn btn-delete"
                                       href="?id=<?= (int) $customer_id; ?>&delete=<?= (int) $row['id']; ?> "
                                       onclick="return confirm('Delete this transaction?');">
                                        🗑 Delete
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>

        <?php include 'ledger_footer.php'; ?>

        <script>
            function fillFormForEdit(id, type, amount, remark) {
                document.getElementById('edit_transaction_id').value = id;
                document.getElementById('edit_type').value = type;
                document.getElementById('edit_amount').value = amount;
                document.getElementById('edit_remark').value = remark;
                document.getElementById('submit_btn').textContent = 'Update Transaction';
                window.scrollTo({top: document.querySelector('.form-card').offsetTop - 12, behavior: 'smooth'});
                return false;
            }
            function fillCustomerFormForEdit(name, phone, email) {
                document.getElementById('cust_name').value = name;
                document.getElementById('cust_phone').value = phone;
                document.getElementById('cust_email').value = email;
                document.getElementById('editCustomerForm').style.display = 'block';
                window.scrollTo({top: document.getElementById('editCustomerForm').offsetTop - 12, behavior: 'smooth'});
                return false;
            }
        </script>
    </body>
</html>
