<?php
// includes/accounting.php
// Double-Entry Accounting Engine

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/functions.php';

/**
 * Creates and posts a balanced journal entry.
 * $lines is an array of items: [
 *   'account_id' => int,
 *   'debit' => float,
 *   'credit' => float,
 *   'description' => string
 * ]
 */
function postJournalEntry($pdo, $branch_id, $ref_type, $ref_id, array $lines, $entry_date = null, $narration = '', $user_id = null) {
    if (empty($entry_date)) {
        $entry_date = date('Y-m-d');
    }
    if ($user_id === null && !empty($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
    }

    $total_debit = 0.00;
    $total_credit = 0.00;

    foreach ($lines as $line) {
        $total_debit += (float)($line['debit'] ?? 0);
        $total_credit += (float)($line['credit'] ?? 0);
    }

    // Double-entry validation: Debits must balance Credits within 2 decimals
    if (abs($total_debit - $total_credit) > 0.01) {
        throw new Exception("Unbalanced journal entry! Debits: {$total_debit}, Credits: {$total_credit}");
    }

    if ($total_debit <= 0) {
        // Nothing to post
        return null;
    }

    // Generate entry number
    $entry_no = generateDocNo($pdo, 'JV', 'journal_entries', 'entry_no');

    $stmt = $pdo->prepare("INSERT INTO journal_entries 
        (branch_id, entry_no, entry_date, reference_type, reference_id, narration, total_debit, total_credit, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $branch_id,
        $entry_no,
        $entry_date,
        $ref_type,
        $ref_id,
        $narration,
        $total_debit,
        $total_credit,
        $user_id
    ]);
    $entry_id = $pdo->lastInsertId();

    $lineStmt = $pdo->prepare("INSERT INTO journal_entry_lines 
        (entry_id, account_id, debit, credit, description) 
        VALUES (?, ?, ?, ?, ?)");

    $updateAcctStmt = $pdo->prepare("UPDATE accounts a
        JOIN account_groups g ON a.group_id = g.id
        SET a.current_balance = a.current_balance + (
            CASE 
                WHEN g.group_type IN ('asset', 'expense', 'cogs') THEN (? - ?)
                ELSE (? - ?)
            END
        )
        WHERE a.id = ?");

    foreach ($lines as $line) {
        $acct_id = (int)$line['account_id'];
        $debit = (float)($line['debit'] ?? 0);
        $credit = (float)($line['credit'] ?? 0);
        $desc = $line['description'] ?? $narration;

        if ($debit == 0 && $credit == 0) continue;

        $lineStmt->execute([$entry_id, $acct_id, $debit, $credit, $desc]);

        // Update account ledger balance
        $updateAcctStmt->execute([$debit, $credit, $credit, $debit, $acct_id]);
    }

    return $entry_id;
}

/**
 * Helper to get account ID by code
 */
function getAccountIdByCode($pdo, $code) {
    static $accts = [];
    if (!isset($accts[$code])) {
        $stmt = $pdo->prepare("SELECT id FROM accounts WHERE code = ? LIMIT 1");
        $stmt->execute([$code]);
        $accts[$code] = $stmt->fetchColumn();
    }
    return $accts[$code];
}

/**
 * Helper to get Financial Account ledger account_id
 */
function getFinancialLedgerAccountId($pdo, $financial_account_id) {
    $stmt = $pdo->prepare("SELECT account_id FROM financial_accounts WHERE id = ?");
    $stmt->execute([$financial_account_id]);
    return $stmt->fetchColumn() ?: getAccountIdByCode($pdo, '1001'); // fallback Cash in Hand
}
