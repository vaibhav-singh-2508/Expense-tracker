<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require 'config.php';

$user_id = $_SESSION['user_id'];
$msg = "";

// ✅ Handle expense submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = trim($_POST['amount'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $expense_date = trim($_POST['expense_date'] ?? '');
    $note = trim($_POST['note'] ?? '');

    if ($category === 'custom' && !empty($_POST['custom_category'])) {
        $category = trim($_POST['custom_category']);
    }

    try {
        // 1️⃣ Insert main expense
        $stmt = $conn->prepare("INSERT INTO expenses (user_id, amount, category, expense_date, note)
                                VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $amount, $category, $expense_date, $note]);
        $expense_id = $conn->lastInsertId();

        // 2️⃣ Handle shared expense
        if (isset($_POST['is_shared'])) {
            $names = json_decode($_POST['shared_name_json'] ?? '[]', true);
            $emails = json_decode($_POST['shared_email_json'] ?? '[]', true);

            $contacts = [];
            $count = min(count($names), count($emails));

            for ($i = 0; $i < $count; $i++) {
                $n = trim((string)($names[$i] ?? ''));
                $e = trim((string)($emails[$i] ?? ''));
                if ($n !== '' && $e !== '') {
                    $contacts[] = ['name' => $n, 'email' => $e];
                }
            }

            if (!empty($contacts)) {
                $registeredContacts = [];

                foreach ($contacts as $contact) {
                    $checkUser = $conn->prepare("SELECT id FROM users WHERE email = ?");
                    $checkUser->execute([$contact['email']]);
                    $existingUserId = $checkUser->fetchColumn();

                    if ($existingUserId) {
                        $registeredContacts[] = [
                            'id' => $existingUserId,
                            'name' => $contact['name']
                        ];

                        // Save to user_contacts if not exists
                        $saveContact = $conn->prepare("INSERT IGNORE INTO user_contacts (owner_id, contact_name, contact_email)
                                                       VALUES (?, ?, ?)");
                        $saveContact->execute([$user_id, $contact['name'], $contact['email']]);
                    }
                }

                if (!empty($registeredContacts)) {
                    $total_people = count($registeredContacts) + 1;
                    $share_amount = $amount / $total_people;

                    foreach ($registeredContacts as $rc) {
                        $stmt = $conn->prepare("INSERT INTO shared_expenses 
                            (expense_id, user_id, share_amount, is_settled, paid_request, created_at, shared_with_name)
                            VALUES (?, ?, ?, 0, 0, NOW(), ?)");
                        $stmt->execute([$expense_id, $rc['id'], $share_amount, $rc['name']]);
                    }

                    // Insert your settled share
                    $stmt = $conn->prepare("INSERT INTO shared_expenses 
                        (expense_id, user_id, share_amount, is_settled, paid_request, created_at, shared_with_name)
                        VALUES (?, ?, ?, 1, 1, NOW(), 'You')");
                    $stmt->execute([$expense_id, $user_id, $share_amount]);

                    $msg = "✅ Expense shared successfully with registered users.";
                } else {
                    $msg = "⚠️ No registered users found. Expense saved only for you.";
                }
            } else {
                $msg = "⚠️ No valid contacts entered.";
            }
        } else {
            $msg = "✅ Expense added successfully.";
        }
    } catch (PDOException $e) {
        $msg = "❌ Error: " . $e->getMessage();
    }
}

// ✅ Fetch saved contacts (only user’s own)
$contactStmt = $conn->prepare("SELECT contact_name, contact_email FROM user_contacts WHERE owner_id = ?");
$contactStmt->execute([$user_id]);
$contacts = $contactStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Add Expense</title>
<style>
    body {
        font-family: 'Poppins', sans-serif;
        background: linear-gradient(135deg, #1f1c2c, #928dab);
        color: #fff;
        margin: 0;
        padding: 0;
    }
    header {
        background: rgba(0, 0, 0, 0.9);
        padding: 15px 40px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    header h1 {
        font-size: 26px;
        margin: 0;
    }
    nav ul {
        list-style: none;
        display: flex;
        gap: 20px;
        margin: 0;
        padding: 0;
    }
    nav ul li a {
        color: #fff;
        text-decoration: none;
        font-weight: 500;
    }
    nav ul li a:hover {
        color: #f1c40f;
    }
    form {
        background: #fff;
        color: #333;
        width: 90%;
        max-width: 550px;
        margin: 50px auto;
        padding: 35px 40px;
        border-radius: 15px;
        box-shadow: 0px 5px 20px rgba(0,0,0,0.2);
    }
    h2 {
        text-align: center;
        margin-top: 30px;
        font-size: 28px;
    }
    input, select, textarea {
        width: 100%;
        padding: 12px;
        margin: 8px 0 15px;
        border: 1px solid #ccc;
        border-radius: 8px;
        font-size: 15px;
    }
    input[type=submit] {
        background: #f39c12;
        color: white;
        font-weight: bold;
        border: none;
        cursor: pointer;
        border-radius: 8px;
        padding: 12px;
        transition: 0.3s;
    }
    input[type=submit]:hover {
        background: #d35400;
    }
    label {
        font-weight: 500;
    }
    .msg {
        text-align: center;
        font-weight: 600;
        margin-top: 10px;
    }
    .contact-list label {
        display: block;
        margin: 8px 0;
    }
    .add-contact {
        margin-top: 10px;
        background: #203a43;
        color: #fff;
        border: none;
        padding: 8px 12px;
        border-radius: 6px;
        cursor: pointer;
        transition: 0.3s;
    }
    .add-contact:hover {
        background: #2c5364;
    }
    .shared-section {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 10px 0 20px;
    }
    .shared-section input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: #f39c12;
        cursor: pointer;
    }
    .shared-section label {
        font-size: 16px;
        color: #333;
        cursor: pointer;
        user-select: none;
    }
</style>
</head>
<body>
<header>
    <h1>Expense Manager</h1>
    <nav>
        <ul>
            <li><a href="dashboard.php">Dashboard</a></li>
            <li><a href="report.php">Report</a></li>
            <li><a href="profile.php">Profile</a></li>
            <li><a href="logout.php">Logout</a></li>
        </ul>
    </nav>
</header>

<h2>Add Expense</h2>

<form method="POST">
    <input type="number" step="0.01" name="amount" placeholder="Enter Amount (₹)" required>
    <select name="category" id="category" required>
        <option value="">Select Category</option>
        <option value="Food">Food</option>
        <option value="Travel">Travel</option>
        <option value="Shopping">Shopping</option>
        <option value="Bills">Bills</option>
        <option value="custom">Other</option>
    </select>
    <input type="text" name="custom_category" id="custom_category" placeholder="Enter custom category" style="display:none;">
    <input type="date" name="expense_date" required>
    <textarea name="note" placeholder="Note (optional)" rows="3"></textarea>

    <div class="shared-section">
        <input type="checkbox" id="is_shared" name="is_shared">
        <label for="is_shared">Shared Expense</label>
    </div>

    <div id="shared_div" style="display:none;">
        <h3>Saved Contacts</h3>
        <div class="contact-list">
            <?php foreach ($contacts as $c): ?>
                <label>
                    <input type="checkbox" class="shared-contact"
                        data-name="<?= htmlspecialchars($c['contact_name']) ?>"
                        data-email="<?= htmlspecialchars($c['contact_email']) ?>">
                    <?= htmlspecialchars($c['contact_name']) ?> (<?= htmlspecialchars($c['contact_email']) ?>)
                </label>
            <?php endforeach; ?>
        </div>

        <h4>Add New Contact:</h4>
        <div id="new_contacts"></div>
        <button type="button" class="add-contact" onclick="addContact()">+ Add Contact</button>
    </div>

    <!-- Hidden JSON fields -->
    <input type="hidden" name="shared_name_json" id="shared_name_json">
    <input type="hidden" name="shared_email_json" id="shared_email_json">

    <input type="submit" value="Add Expense">

    <?php if (!empty($msg)): ?>
        <p class="msg" style="color: <?= strpos($msg, 'Error') !== false ? 'red' : (strpos($msg, '⚠️') !== false ? 'orange' : 'green') ?>;">
            <?= htmlspecialchars($msg) ?>
        </p>
    <?php endif; ?>
</form>

<script>
const category = document.getElementById("category");
const custom = document.getElementById("custom_category");
const sharedDiv = document.getElementById("shared_div");

category.addEventListener("change", () => {
    custom.style.display = category.value === "custom" ? "block" : "none";
});

document.getElementById("is_shared").addEventListener("change", function() {
    sharedDiv.style.display = this.checked ? "block" : "none";
});

function addContact() {
    const div = document.createElement("div");
    div.innerHTML = `
        <input type="text" name="shared_name[]" placeholder="Name" required>
        <input type="email" name="shared_email[]" placeholder="Email" required>
    `;
    document.getElementById("new_contacts").appendChild(div);
}

// ✅ Collect checked contacts before submit
document.querySelector("form").addEventListener("submit", function(e) {
    const selectedContacts = document.querySelectorAll(".shared-contact:checked");
    const names = [];
    const emails = [];

    selectedContacts.forEach(c => {
        names.push(c.dataset.name);
        emails.push(c.dataset.email);
    });

    // Include new manually added contacts
    document.querySelectorAll("#new_contacts div").forEach(div => {
        const n = div.querySelector('input[name="shared_name[]"]')?.value.trim();
        const e = div.querySelector('input[name="shared_email[]"]')?.value.trim();
        if (n && e) {
            names.push(n);
            emails.push(e);
        }
    });

    document.getElementById("shared_name_json").value = JSON.stringify(names);
    document.getElementById("shared_email_json").value = JSON.stringify(emails);
});
</script>
</body>
</html>
