<?php
// expenses/create.php - Expense Handler & Accounting Posting
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/accounting.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validatePostCsrf();
    $branch_id = getActiveBranchId();
    $category_id = (int)$_POST['category_id'];
    $account_id = (int)$_POST['account_id'];
    $amount = (float)($_POST['amount'] ?? 0);
    $expense_date = sanitize($_POST['expense_date'] ?? date('Y-m-d'));
    $description = sanitize($_POST['description'] ?? '');

    if ($amount <= 0 || $category_id <= 0 || $account_id <= 0) {
        flash('danger', 'Please enter a valid expense amount and select required accounts.');
        redirect(BASE_URL . 'expenses/index.php');
    }

    try {
        $pdo->beginTransaction();

        $expense_no = generateDocNo($pdo, 'EXP', 'expenses', 'expense_no');

        // Insert Expense
        $stmt = $pdo->prepare("INSERT INTO expenses 
            (expense_no, branch_id, category_id, account_id, amount, expense_date, description, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$expense_no, $branch_id, $category_id, $account_id, $amount, $expense_date, $description, $_SESSION['user_id']]);
        $expense_id = $pdo->lastInsertId();

        // Deduct from financial account
        $pdo->prepare("UPDATE financial_accounts SET current_balance = current_balance - ? WHERE id = ?")
            ->execute([$amount, $account_id]);

        // Get Category name to match ledger account
        $cName = $pdo->prepare("SELECT name FROM expense_categories WHERE id = ?");
        $cName->execute([$category_id]);
        $cat_title = $cName->fetchColumn() ?: 'General Expense';

        $exp_ledger_code = '6004'; // Default General & Admin
        if (stripos($cat_title, 'rent') !== false) $exp_ledger_code = '6001';
        elseif (stripos($cat_title, 'elect') !== false || stripos($cat_title, 'util') !== false) $exp_ledger_code = '6002';
        elseif (stripos($cat_title, 'salar') !== false) $exp_ledger_code = '6003';

        $exp_acct = getAccountIdByCode($pdo, $exp_ledger_code);
        $cash_acct = getFinancialLedgerAccountId($pdo, $account_id);

        $j_lines = [
            [
                'account_id' => $exp_acct,
                'debit' => $amount,
                'credit' => 0.00,
                'description' => "Expense: {$cat_title} - {$description}"
            ],
            [
                'account_id' => $cash_acct,
                'debit' => 0.00,
                'credit' => $amount,
                'description' => "Paid for expense {$expense_no}"
            ]
        ];

        postJournalEntry($pdo, $branch_id, 'expense', $expense_id, $j_lines, $expense_date, "Expense {$expense_no}: {$cat_title}");

        logAudit($pdo, 'create', 'expenses', $expense_id, "Recorded expense {$expense_no} of {$amount}");

        $pdo->commit();
        flash('success', "Expense {$expense_no} of " . formatCurrency($amount) . " recorded successfully.");
        redirect(BASE_URL . 'expenses/index.php');

    } catch (Exception $e) {
        $pdo->rollBack();
        flash('danger', 'Error recording expense: ' . $e->getMessage());
        redirect(BASE_URL . 'expenses/index.php');
    }
}
