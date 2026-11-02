<?php
// cash_bank/transfer.php - Internal Fund Transfer & Account Creation Handler
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/accounting.php';

requireLogin();

$action = $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validatePostCsrf();

    if ($action === 'create_account') {
        $branch_id = getActiveBranchId();
        $name = sanitize($_POST['name'] ?? '');
        $type = sanitize($_POST['type'] ?? 'bank');
        $bank_name = sanitize($_POST['bank_name'] ?? '');
        $acct_num = sanitize($_POST['account_number'] ?? '');
        $init_bal = (float)($_POST['initial_balance'] ?? 0);

        if (empty($name)) {
            flash('danger', 'Account name is required.');
            redirect(BASE_URL . 'cash_bank/index.php');
        }

        try {
            $ledger_acct_id = ($type === 'cash') ? getAccountIdByCode($pdo, '1001') : getAccountIdByCode($pdo, '1002');

            $stmt = $pdo->prepare("INSERT INTO financial_accounts (branch_id, account_id, name, type, account_number, bank_name, current_balance) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$branch_id, $ledger_acct_id, $name, $type, $acct_num, $bank_name, $init_bal]);

            logAudit($pdo, 'create', 'financial_accounts', $pdo->lastInsertId(), "Created financial account {$name}");
            flash('success', "Account '{$name}' created successfully.");
            redirect(BASE_URL . 'cash_bank/index.php');
        } catch (Exception $e) {
            flash('danger', 'Error creating account: ' . $e->getMessage());
            redirect(BASE_URL . 'cash_bank/index.php');
        }
    }

    // Default: Internal Transfer
    $from_id = (int)$_POST['from_account_id'];
    $to_id = (int)$_POST['to_account_id'];
    $amount = (float)($_POST['amount'] ?? 0);
    $date = sanitize($_POST['transfer_date'] ?? date('Y-m-d'));
    $notes = sanitize($_POST['notes'] ?? 'Internal Transfer');

    if ($from_id === $to_id) {
        flash('danger', 'Source and destination accounts cannot be the same.');
        redirect(BASE_URL . 'cash_bank/index.php');
    }
    if ($amount <= 0) {
        flash('danger', 'Please enter a valid transfer amount.');
        redirect(BASE_URL . 'cash_bank/index.php');
    }

    try {
        $pdo->beginTransaction();

        $fromStmt = $pdo->prepare("SELECT * FROM financial_accounts WHERE id = ? FOR UPDATE");
        $fromStmt->execute([$from_id]);
        $fromAcct = $fromStmt->fetch();

        $toStmt = $pdo->prepare("SELECT * FROM financial_accounts WHERE id = ? FOR UPDATE");
        $toStmt->execute([$to_id]);
        $toAcct = $toStmt->fetch();

        if (!$fromAcct || !$toAcct) {
            throw new Exception("One of the selected accounts was not found.");
        }

        // Deduct from source & add to destination
        $pdo->prepare("UPDATE financial_accounts SET current_balance = current_balance - ? WHERE id = ?")->execute([$amount, $from_id]);
        $pdo->prepare("UPDATE financial_accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$amount, $to_id]);

        $transfer_no = generateDocNo($pdo, 'TRF-F', 'account_transfers', 'transfer_no');

        $stmt = $pdo->prepare("INSERT INTO account_transfers (transfer_no, from_account_id, to_account_id, amount, transfer_date, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$transfer_no, $from_id, $to_id, $amount, $date, $notes, $_SESSION['user_id']]);
        $trans_id = $pdo->lastInsertId();

        // DOUBLE-ENTRY POSTING: Debit Destination Ledger Account, Credit Source Ledger Account
        $from_ledger = getFinancialLedgerAccountId($pdo, $from_id);
        $to_ledger = getFinancialLedgerAccountId($pdo, $to_id);

        $j_lines = [
            [
                'account_id' => $to_ledger,
                'debit' => $amount,
                'credit' => 0.00,
                'description' => "Internal transfer deposit into {$toAcct['name']} - {$notes}"
            ],
            [
                'account_id' => $from_ledger,
                'debit' => 0.00,
                'credit' => $amount,
                'description' => "Internal transfer out from {$fromAcct['name']}"
            ]
        ];

        postJournalEntry($pdo, $fromAcct['branch_id'], 'fund_transfer', $trans_id, $j_lines, $date, "Fund Transfer {$transfer_no}");

        logAudit($pdo, 'transfer', 'financial_accounts', $trans_id, "Transferred {$amount} from {$fromAcct['name']} to {$toAcct['name']}");

        $pdo->commit();
        flash('success', "Transferred " . formatCurrency($amount) . " from {$fromAcct['name']} to {$toAcct['name']} successfully.");
        redirect(BASE_URL . 'cash_bank/index.php');

    } catch (Exception $e) {
        $pdo->rollBack();
        flash('danger', 'Error transferring funds: ' . $e->getMessage());
        redirect(BASE_URL . 'cash_bank/index.php');
    }
}
