<?php
session_start();
require 'config.php';
include 'ledger_header.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Filters
$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$customer_id = $_GET['customer_id'] ?? '';

$where = " WHERE l.user_id = :uid ";
$params = [':uid' => $user_id];

if ($from && $to) {
    $where .= " AND DATE(l.created_at) BETWEEN :from AND :to ";
    $params[':from'] = $from;
    $params[':to'] = $to;
}
if ($customer_id) {
    $where .= " AND l.customer_id = :cid ";
    $params[':cid'] = $customer_id;
}

// Customers for dropdown
$custStmt = $conn->prepare("SELECT id, name FROM customers WHERE user_id = :uid ORDER BY name");
$custStmt->execute([':uid' => $user_id]);
$customers = $custStmt->fetchAll(PDO::FETCH_ASSOC);

// Transactions
$sql = "SELECT l.*, c.name AS customer_name 
        FROM ledger l
        JOIN customers c ON l.customer_id = c.id
        $where
        ORDER BY l.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Totals
$totalGive = 0;
$totalTake = 0;
foreach ($transactions as $t) {
    $amt = (float) ($t['amount'] ?? 0);
    if (($t['type'] ?? '') === 'give')
        $totalGive += $amt;
    if (($t['type'] ?? '') === 'take')
        $totalTake += $amt;
}
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Ledger Report</title>
        <link href="css/ledger_style.css" rel="stylesheet" type="text/css"/>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <style>
            :root{
                --bg:#0f172a;
                --card:#111827;
                --muted:#94a3b8;
                --text:#e5e7eb;
                --accent:#22c55e;
                --danger:#ef4444;
                --ring:#334155;
                --border:#1f2937;
            }
            *{
                box-sizing:border-box
            }
            body{
                margin:0;
                font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Cantarell,Inter,Arial,sans-serif;
                background:linear-gradient(180deg,#0b1022, #0f172a 40%, #0b1022);
                color:var(--text);
            }
            .container{
                max-width:1100px;
                margin:24px auto;
                padding:0 16px;
            }
            .h2{
                font-size:28px;
                font-weight:700;
                text-align:center;
                letter-spacing:.3px;
                margin:8px 0 18px;
            }
            .card{
                background:rgba(255,255,255,0.04);
                border:1px solid var(--border);
                border-radius:16px;
                box-shadow:0 8px 25px rgba(0,0,0,.25);
                backdrop-filter: blur(6px);
            }
            .section{
                padding:16px
            }
            .grid{
                display:grid;
                gap:16px
            }
            .grid-2{
                grid-template-columns:repeat(2,minmax(0,1fr))
            }
            .grid-3{
                grid-template-columns:repeat(3,minmax(0,1fr))
            }
            @media (max-width:900px){
                .grid-2,.grid-3{
                    grid-template-columns:1fr
                }
            }

            .label{
                display:block;
                font-size:12px;
                color:var(--muted);
                margin:0 0 6px 2px
            }
            .input, .select, .btn{
                width:100%;
                padding:10px 12px;
                border-radius:12px;
                border:1px solid var(--ring);
                background:#0b1224;
                color:var(--text);
            }
            .input:focus, .select:focus{
                outline:2px solid #3b82f6;
                border-color:#3b82f6
            }
            .btn{
                background:#3b82f6;
                border-color:#3b82f6;
                cursor:pointer;
                font-weight:600;
            }
            .btn:hover{
                filter:brightness(1.05)
            }
            .btn-row{
                display:flex;
                gap:10px;
                flex-wrap:wrap
            }
            .btn.secondary{
                background:#0b1224;
                border-color:#3b82f6
            }
            .kpi{
                text-align:center;
                padding:18px;
                border-radius:16px;
                border:1px solid var(--border);
                background:rgba(255,255,255,0.03);
            }
            .kpi h5{
                margin:4px 0 8px;
                font-weight:600;
                color:var(--muted)
            }
            .kpi .val{
                font-size:26px;
                font-weight:800
            }
            .kpi .val.danger{
                color:var(--danger)
            }
            .kpi .val.ok{
                color:var(--accent)
            }
            .table-wrap{
                overflow:auto;
                border-radius:12px;
                border:1px solid var(--border)
            }
            table{
                width:100%;
                border-collapse:collapse;
                min-width:720px
            }
            th, td{
                padding:12px 10px;
                border-bottom:1px solid var(--border)
            }
            th{
                font-size:12px;
                text-transform:uppercase;
                letter-spacing:.08em;
                color:var(--muted);
                text-align:left;
                background:rgba(255,255,255,0.03)
            }
            tr:hover{
                background:rgba(255,255,255,0.025)
            }
            .badge{
                display:inline-block;
                padding:4px 10px;
                border-radius:999px;
                font-size:12px;
                border:1px solid var(--ring);
            }
            .badge.give{
                color:var(--danger);
                border-color:#7f1d1d
            }
            .badge.take{
                color:var(--accent);
                border-color:#14532d
            }
            .actions{
                display:flex;
                gap:10px;
                justify-content:flex-end
            }
            .chart-card{
                padding:20px
            }
            .small{
                font-size:12px;
                color:var(--muted)
            }
            hr.sep{
                border:0;
                border-top:1px solid var(--border);
                margin:12px 0
            }
            .header-area{
                display:flex;
                align-items:center;
                justify-content:space-between;
                gap:12px;
                margin-bottom:14px
            }
            #ledgerChart {
    max-width: 250px;   /* reduce width */
    max-height: 250px;  /* reduce height */
    margin: 0 auto;     /* center it */
    display: block;
}
        </style>
    </head>
    <body>
        <div class="container">
            <h2 class="h2">Ledger Report</h2>

            <!-- Filters -->
            <div class="card section" id="filterCard">
                <form method="GET" class="grid grid-3">
                    <div>
                        <label class="label">From date</label>
                        <input class="input" type="date" name="from" value="<?= htmlspecialchars($from ?? '') ?>">
                    </div>
                    <div>
                        <label class="label">To date</label>
                        <input class="input" type="date" name="to" value="<?= htmlspecialchars($to ?? '') ?>">
                    </div>
                    <div>
                        <label class="label">Customer</label>
                        <select class="select" name="customer_id">
                            <option value="">All</option>
                                <?php foreach ($customers as $c): ?>
                                <option value="<?= (int) $c['id'] ?>" <?= ($customer_id == $c['id'] ? 'selected' : '') ?>>
                                <?= htmlspecialchars($c['name'] ?? 'Unknown') ?>
                                </option>
<?php endforeach; ?>
                        </select>
                    </div>
                    <div class="actions" style="grid-column:1/-1">
                        <button class="btn" type="submit">Apply Filters</button>
                        <a class="btn secondary" href="ledger_report.php">Reset</a>
                        <button class="btn secondary" type="button" id="btnCSV">Export CSV</button>
                        <button class="btn" type="button" id="btnPDF">Export PDF</button>
                    </div>
                </form>
            </div>

            <!-- KPIs -->
            <div class="grid grid-2" style="margin:16px 0;">
                <div class="kpi">
                    <h5>Total Give</h5>
                    <div class="val danger">₹<?= number_format($totalGive, 2) ?></div>
                </div>
                <div class="kpi">
                    <h5>Total Take</h5>
                    <div class="val ok">₹<?= number_format($totalTake, 2) ?></div>
                </div>
            </div>

            <!-- Transactions -->
            <div class="card section" id="tableCard">
                <div class="header-area">
                    <div><strong>Transactions</strong> <span class="small">(<?= count($transactions) ?> rows)</span></div>
                </div>
                <div class="table-wrap">
                    <table id="txTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Note</th>
                            </tr>
                        </thead>
                        <tbody>
<?php if ($transactions): ?>
    <?php foreach ($transactions as $t): ?>
                                    <tr>
                                        <td><?= htmlspecialchars(($t['created_at'] ?? '')) ?></td>
                                        <td><?= htmlspecialchars(($t['customer_name'] ?? '')) ?></td>
                                        <td><span class="badge <?= ($t['type'] === 'give' ? 'give' : 'take') ?>"><?= htmlspecialchars(ucfirst($t['type'] ?? '')) ?></span></td>
                                        <td>₹<?= number_format((float) ($t['amount'] ?? 0), 2) ?></td>
                                        <td><?= htmlspecialchars(($t['note'] ?? '')) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="small" style="text-align:center">No transactions found</td></tr>
<?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Chart -->
            <div class="card chart-card" id="chartCard">
                <canvas id="ledgerChart"></canvas>
                <div class="small" style="margin-top:8px">Give vs Take</div>
            </div>
        </div>

        <script src="js/chart.min.js"></script>
        <script src="js/jspdf.umd.min.js"></script>
        <script src="js/html2canvas.min.js"></script>


        <script>
        // Chart
            (function () {
                const ctx = document.getElementById('ledgerChart');
                if (!ctx || typeof Chart === 'undefined')
                    return;
                new Chart(ctx, {
                    type: 'pie',
                    data: {
                        labels: ['Give', 'Take'],
                        datasets: [{
                                data: [<?= (float) $totalGive ?>, <?= (float) $totalTake ?>]
                            }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            legend: {position: 'bottom'},
                            tooltip: {callbacks: {label: (c) => c.label + ': ₹' + Number(c.raw).toLocaleString()}}
                        }
                    }
                });
            })();

        // CSV Export
            document.getElementById('btnCSV')?.addEventListener('click', () => {
                const rows = [['Date', 'Customer', 'Type', 'Amount', 'Note']];
                const trs = document.querySelectorAll('#txTable tbody tr');
                trs.forEach(tr => {
                    const tds = tr.querySelectorAll('td');
                    if (tds.length) {
                        rows.push([
                            tds[0].innerText.trim(),
                            tds[1].innerText.trim(),
                            tds[2].innerText.trim(),
                            tds[3].innerText.replace('₹', '').trim(),
                            tds[4].innerText.trim()
                        ]);
                    }
                });
                const csv = rows.map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
                const blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
                const a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = 'ledger_report.csv';
                a.click();
            });

        // PDF Export
            document.getElementById('btnPDF')?.addEventListener('click', async () => {
                try {
                    const {jsPDF} = window.jspdf || {};
                    if (!jsPDF || typeof html2canvas === 'undefined')
                        return alert('jsPDF/html2canvas not loaded');

                    const pdf = new jsPDF('p', 'pt', 'a4');
                    const target = document.querySelector('.container');
                    const scale = 2;
                    const canvas = await html2canvas(target, {scale});
                    const imgData = canvas.toDataURL('image/png');

                    const pageWidth = pdf.internal.pageSize.getWidth();
                    const pageHeight = pdf.internal.pageSize.getHeight();

                    const ratio = Math.min(pageWidth / canvas.width, pageHeight / canvas.height);
                    const w = canvas.width * ratio;
                    const h = canvas.height * ratio;

                    pdf.addImage(imgData, 'PNG', (pageWidth - w) / 2, 20, w, h);
                    pdf.save('ledger_report.pdf');
                } catch (e) {
                    alert('PDF export failed');
                    console.error(e);
                }
            });
        </script>
    </body>
</html>
<?php include 'ledger_footer.php'; ?>