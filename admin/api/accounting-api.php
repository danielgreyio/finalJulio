<?php
// API endpoints for accounting module
require_once '../../config/database.php';
require_once '../../config/db-connection-pool.php';
require_once '../../config/redis-cache.php';
require_once '../../config/queue-system.php';

header('Content-Type: application/json');

// Check if user is authenticated
if (!isLoggedIn() || getUserRole() !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    // Use connection pooling for better performance
    $pdo = getOptimizedDBConnection();
    
    // Create indexes for better query performance
    createAccountingIndexes($pdo);
} catch(Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get_chart_of_accounts':
        getChartOfAccounts($pdo);
        break;
        
    case 'get_account_balance':
        getAccountBalance($pdo);
        break;
        
    case 'add_journal_entry':
        addJournalEntry($pdo);
        break;
        
    case 'get_general_ledger':
        getGeneralLedger($pdo);
        break;
        
    case 'get_accounts_payable':
        getAccountsPayable($pdo);
        break;
        
    case 'get_accounts_receivable':
        getAccountsReceivable($pdo);
        break;
        
    case 'add_account_payable':
        addAccountPayable($pdo);
        break;
        
    case 'add_account_receivable':
        addAccountReceivable($pdo);
        break;
        
    case 'pay_account_payable':
        payAccountPayable($pdo);
        break;
        
    case 'receive_account_receivable':
        receiveAccountReceivable($pdo);
        break;
        
    case 'generate_financial_report':
        generateFinancialReport($pdo);
        break;
        
    // New endpoints for enhanced functionality
    case 'get_sales_commissions':
        getSalesCommissions($pdo);
        break;
        
    case 'add_sales_commission':
        addSalesCommission($pdo);
        break;
        
    case 'get_commission_tiers':
        getCommissionTiers($pdo);
        break;
        
    case 'calculate_commission':
        calculateCommission($pdo);
        break;
        
    case 'get_marketing_campaigns':
        getMarketingCampaigns($pdo);
        break;
        
    case 'add_marketing_campaign':
        addMarketingCampaign($pdo);
        break;
        
    case 'get_marketing_expenses':
        getMarketingExpenses($pdo);
        break;
        
    case 'add_marketing_expense':
        addMarketingExpense($pdo);
        break;
        
    case 'get_operations_costs':
        getOperationsCosts($pdo);
        break;
        
    case 'add_operations_cost':
        addOperationsCost($pdo);
        break;
        
    case 'get_product_costing':
        getProductCosting($pdo);
        break;
        
    case 'update_product_costing':
        updateProductCosting($pdo);
        break;
        
    case 'get_payroll':
        getPayroll($pdo);
        break;
        
    case 'add_payroll':
        addPayroll($pdo);
        break;
        
    case 'get_financial_ratios':
        getFinancialRatios($pdo);
        break;
        
    case 'get_monetary_attribution':
        getMonetaryAttribution($pdo);
        break;
        
    case 'get_dashboard_summary':
        getDashboardSummary($pdo);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        break;
}

// ... existing functions ...

function getChartOfAccounts($pdo) {
    try {
        // Try to get from cache first
        $cacheManager = getAccountingCacheManager();
        $accounts = $cacheManager->getChartOfAccounts();
        
        if ($accounts === null) {
            // Not in cache, fetch from database
            $stmt = $pdo->query("SELECT * FROM chart_of_accounts WHERE is_active = 1 ORDER BY account_code");
            $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Cache the result
            $cacheManager->cacheChartOfAccounts($accounts);
        }
        
        echo json_encode(['success' => true, 'data' => $accounts]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve chart of accounts']);
    }
}

function getAccountBalance($pdo) {
    $accountId = $_GET['account_id'] ?? 0;
    
    if (!$accountId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Account ID is required']);
        return;
    }
    
    try {
        // Try to get from cache first
        $cacheManager = getAccountingCacheManager();
        $balance = $cacheManager->getAccountBalance($accountId);
        
        if ($balance === null) {
            // Not in cache, fetch from database
            $stmt = $pdo->prepare("SELECT balance FROM chart_of_accounts WHERE id = ?");
            $stmt->execute([$accountId]);
            $account = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($account) {
                $balance = $account['balance'];
                // Cache the result
                $cacheManager->cacheAccountBalance($accountId, $balance);
                echo json_encode(['success' => true, 'balance' => $balance]);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'Account not found']);
            }
        } else {
            echo json_encode(['success' => true, 'balance' => $balance]);
        }
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve account balance']);
    }
}

function addJournalEntry($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
        return;
    }
    
    $accountId = $data['account_id'] ?? 0;
    $transactionDate = $data['transaction_date'] ?? date('Y-m-d');
    $description = $data['description'] ?? '';
    $debitAmount = $data['debit_amount'] ?? 0;
    $creditAmount = $data['credit_amount'] ?? 0;
    $referenceType = $data['reference_type'] ?? 'manual';
    $referenceId = $data['reference_id'] ?? null;
    
    if (!$accountId || (!$debitAmount && !$creditAmount)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Account ID and amount are required']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Insert journal entry
        $stmt = $pdo->prepare("INSERT INTO general_ledger (account_id, transaction_date, description, debit_amount, credit_amount, reference_type, reference_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$accountId, $transactionDate, $description, $debitAmount, $creditAmount, $referenceType, $referenceId]);
        $transactionId = $pdo->lastInsertId();
        
        // Update account balance
        if ($debitAmount > 0) {
            $stmt = $pdo->prepare("UPDATE chart_of_accounts SET balance = balance + ? WHERE id = ?");
            $stmt->execute([$debitAmount, $accountId]);
        } else {
            $stmt = $pdo->prepare("UPDATE chart_of_accounts SET balance = balance - ? WHERE id = ?");
            $stmt->execute([$creditAmount, $accountId]);
        }
        
        $pdo->commit();
        
        // Clear relevant caches
        $cacheManager = getAccountingCacheManager();
        $cacheManager->clearAllCache();
        
        echo json_encode(['success' => true, 'transaction_id' => $transactionId, 'message' => 'Journal entry added successfully']);
    } catch(Exception $e) {
        $pdo->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to add journal entry: ' . $e->getMessage()]);
    }
}

function getGeneralLedger($pdo) {
    $accountId = $_GET['account_id'] ?? 0;
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    
    try {
        // Try to get from cache first
        $cacheManager = getAccountingCacheManager();
        $transactions = $cacheManager->getGeneralLedger($accountId, $startDate, $endDate);
        
        if ($transactions === null) {
            // Not in cache, fetch from database
            $sql = "SELECT gl.*, ca.account_code, ca.account_name FROM general_ledger gl 
                    JOIN chart_of_accounts ca ON gl.account_id = ca.id";
            $params = [];
            
            if ($accountId) {
                $sql .= " WHERE gl.account_id = ?";
                $params[] = $accountId;
                
                if ($startDate) {
                    $sql .= " AND gl.transaction_date >= ?";
                    $params[] = $startDate;
                }
                
                if ($endDate) {
                    $sql .= " AND gl.transaction_date <= ?";
                    $params[] = $endDate;
                }
            } elseif ($startDate || $endDate) {
                $sql .= " WHERE 1=1";
                
                if ($startDate) {
                    $sql .= " AND gl.transaction_date >= ?";
                    $params[] = $startDate;
                }
                
                if ($endDate) {
                    $sql .= " AND gl.transaction_date <= ?";
                    $params[] = $endDate;
                }
            }
            
            $sql .= " ORDER BY gl.transaction_date DESC, gl.id DESC LIMIT 1000"; // Limit for performance
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Cache the result
            $cacheManager->cacheGeneralLedger($accountId, $transactions, $startDate, $endDate);
        }
        
        echo json_encode(['success' => true, 'data' => $transactions]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve general ledger']);
    }
}

function getAccountsPayable($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM accounts_payable ORDER BY due_date");
        $payables = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $payables]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve accounts payable']);
    }
}

function getAccountsReceivable($pdo) {
    try {
        $stmt = $pdo->query("SELECT * FROM accounts_receivable ORDER BY due_date");
        $receivables = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $receivables]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve accounts receivable']);
    }
}

function addAccountPayable($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
        return;
    }
    
    $vendorName = $data['vendor_name'] ?? '';
    $invoiceNumber = $data['invoice_number'] ?? '';
    $invoiceDate = $data['invoice_date'] ?? '';
    $dueDate = $data['due_date'] ?? '';
    $amount = $data['amount'] ?? 0;
    $description = $data['description'] ?? '';
    
    if (!$vendorName || !$invoiceNumber || !$invoiceDate || !$dueDate || !$amount) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO accounts_payable (vendor_name, invoice_number, invoice_date, due_date, amount, description) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$vendorName, $invoiceNumber, $invoiceDate, $dueDate, $amount, $description]);
        $payableId = $pdo->lastInsertId();
        
        // Clear relevant caches
        $cacheManager = getAccountingCacheManager();
        $cacheManager->clearAllCache();
        
        echo json_encode(['success' => true, 'payable_id' => $payableId, 'message' => 'Account payable added successfully']);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to add account payable']);
    }
}

function addAccountReceivable($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
        return;
    }
    
    $customerName = $data['customer_name'] ?? '';
    $invoiceNumber = $data['invoice_number'] ?? '';
    $invoiceDate = $data['invoice_date'] ?? '';
    $dueDate = $data['due_date'] ?? '';
    $amount = $data['amount'] ?? 0;
    $description = $data['description'] ?? '';
    
    if (!$customerName || !$invoiceNumber || !$invoiceDate || !$dueDate || !$amount) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO accounts_receivable (customer_name, invoice_number, invoice_date, due_date, amount, description) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$customerName, $invoiceNumber, $invoiceDate, $dueDate, $amount, $description]);
        $receivableId = $pdo->lastInsertId();
        
        // Clear relevant caches
        $cacheManager = getAccountingCacheManager();
        $cacheManager->clearAllCache();
        
        echo json_encode(['success' => true, 'receivable_id' => $receivableId, 'message' => 'Account receivable added successfully']);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to add account receivable']);
    }
}

function payAccountPayable($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
        return;
    }
    
    $payableId = $data['payable_id'] ?? 0;
    $amount = $data['amount'] ?? 0;
    
    if (!$payableId || !$amount) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payable ID and amount are required']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Get payable details
        $stmt = $pdo->prepare("SELECT * FROM accounts_payable WHERE id = ?");
        $stmt->execute([$payableId]);
        $payable = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$payable) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Account payable not found']);
            $pdo->rollback();
            return;
        }
        
        // Update payable
        $newPaidAmount = $payable['paid_amount'] + $amount;
        $status = ($newPaidAmount >= $payable['amount']) ? 'paid' : 'pending';
        
        $stmt = $pdo->prepare("UPDATE accounts_payable SET paid_amount = ?, status = ? WHERE id = ?");
        $stmt->execute([$newPaidAmount, $status, $payableId]);
        
        // Add journal entry for payment
        $stmt = $pdo->prepare("INSERT INTO general_ledger (account_id, transaction_date, description, debit_amount, credit_amount, reference_type, reference_id) VALUES ((SELECT id FROM chart_of_accounts WHERE account_code = '2000'), NOW(), ?, ?, 0, 'payment', ?)");
        $stmt->execute(["Payment to " . $payable['vendor_name'] . " for invoice " . $payable['invoice_number'], $amount, $payableId]);
        
        $stmt = $pdo->prepare("INSERT INTO general_ledger (account_id, transaction_date, description, debit_amount, credit_amount, reference_type, reference_id) VALUES ((SELECT id FROM chart_of_accounts WHERE account_code = '1000'), NOW(), ?, 0, ?, 'payment', ?)");
        $stmt->execute(["Payment to " . $payable['vendor_name'] . " for invoice " . $payable['invoice_number'], $amount, $payableId]);
        
        $pdo->commit();
        
        // Clear relevant caches
        $cacheManager = getAccountingCacheManager();
        $cacheManager->clearAllCache();
        
        echo json_encode(['success' => true, 'message' => 'Account payable paid successfully']);
    } catch(Exception $e) {
        $pdo->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to pay account payable']);
    }
}

function receiveAccountReceivable($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
        return;
    }
    
    $receivableId = $data['receivable_id'] ?? 0;
    $amount = $data['amount'] ?? 0;
    
    if (!$receivableId || !$amount) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Receivable ID and amount are required']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Get receivable details
        $stmt = $pdo->prepare("SELECT * FROM accounts_receivable WHERE id = ?");
        $stmt->execute([$receivableId]);
        $receivable = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$receivable) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Account receivable not found']);
            $pdo->rollback();
            return;
        }
        
        // Update receivable
        $newReceivedAmount = $receivable['received_amount'] + $amount;
        $status = ($newReceivedAmount >= $receivable['amount']) ? 'paid' : 'pending';
        
        // Update collection status if this receivable was in collections
        updateCollectionStatusForPayment($pdo, $receivableId, $amount);
        
        $stmt = $pdo->prepare("UPDATE accounts_receivable SET received_amount = ?, status = ? WHERE id = ?");
        $stmt->execute([$newReceivedAmount, $status, $receivableId]);
        
        // Add journal entry for receipt
        $stmt = $pdo->prepare("INSERT INTO general_ledger (account_id, transaction_date, description, debit_amount, credit_amount, reference_type, reference_id) VALUES ((SELECT id FROM chart_of_accounts WHERE account_code = '1000'), NOW(), ?, ?, 0, 'receipt', ?)");
        $stmt->execute(["Receipt from " . $receivable['customer_name'] . " for invoice " . $receivable['invoice_number'], $amount, $receivableId]);
        
        $stmt = $pdo->prepare("INSERT INTO general_ledger (account_id, transaction_date, description, debit_amount, credit_amount, reference_type, reference_id) VALUES ((SELECT id FROM chart_of_accounts WHERE account_code = '1100'), NOW(), ?, 0, ?, 'receipt', ?)");
        $stmt->execute(["Receipt from " . $receivable['customer_name'] . " for invoice " . $receivable['invoice_number'], $amount, $receivableId]);
        
        $pdo->commit();
        
        // Clear relevant caches
        $cacheManager = getAccountingCacheManager();
        $cacheManager->clearAllCache();
        
        echo json_encode(['success' => true, 'message' => 'Account receivable received successfully']);
    } catch(Exception $e) {
        $pdo->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to receive account receivable']);
    }
}

function generateFinancialReport($pdo) {
    $reportType = $_GET['report_type'] ?? '';
    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    
    if (!$reportType || !$startDate || !$endDate) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Report type and date range are required']);
        return;
    }
    
    try {
        // Try to get from cache first
        $cacheManager = getAccountingCacheManager();
        $reportData = $cacheManager->getFinancialReport($reportType, $startDate, $endDate);
        
        if ($reportData === null) {
            // Not in cache, enqueue for background processing
            $jobId = enqueueFinancialReport($reportType, $startDate, $endDate, $_SESSION['user_id'] ?? null);
            
            if ($jobId) {
                // Return job ID for tracking
                echo json_encode([
                    'success' => true, 
                    'job_id' => $jobId, 
                    'message' => 'Financial report generation queued successfully',
                    'status' => 'processing'
                ]);
                return;
            } else {
                // Fallback to immediate processing if queue is not available
                switch ($reportType) {
                    case 'income_statement':
                        $reportData = generateIncomeStatement($pdo, $startDate, $endDate);
                        break;
                    case 'balance_sheet':
                        $reportData = generateBalanceSheet($pdo, $endDate);
                        break;
                    case 'cash_flow':
                        $reportData = generateCashFlowStatement($pdo, $startDate, $endDate);
                        break;
                    default:
                        http_response_code(400);
                        echo json_encode(['success' => false, 'message' => 'Invalid report type']);
                        return;
                }
                
                // Cache the result
                $cacheManager->cacheFinancialReport($reportType, $startDate, $endDate, $reportData);
            }
        }
        
        // Save report to database
        $stmt = $pdo->prepare("INSERT INTO financial_reports (report_name, report_type, period_start, period_end, report_data, generated_by) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            ucfirst(str_replace('_', ' ', $reportType)) . " Report",
            $reportType,
            $startDate,
            $endDate,
            json_encode($reportData),
            $_SESSION['user_id'] ?? null
        ]);
        $reportId = $pdo->lastInsertId();
        
        echo json_encode(['success' => true, 'report_id' => $reportId, 'data' => $reportData]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to generate financial report']);
    }
}

function generateIncomeStatement($pdo, $startDate, $endDate) {
    // Revenue accounts (4000-4999)
    $stmt = $pdo->prepare("SELECT SUM(gl.credit_amount - gl.debit_amount) as total FROM general_ledger gl JOIN chart_of_accounts ca ON gl.account_id = ca.id WHERE ca.account_code LIKE '4%' AND gl.transaction_date BETWEEN ? AND ?");
    $stmt->execute([$startDate, $endDate]);
    $revenue = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Expense accounts (5000-5999)
    $stmt = $pdo->prepare("SELECT SUM(gl.debit_amount - gl.credit_amount) as total FROM general_ledger gl JOIN chart_of_accounts ca ON gl.account_id = ca.id WHERE ca.account_code LIKE '5%' AND gl.transaction_date BETWEEN ? AND ?");
    $stmt->execute([$startDate, $endDate]);
    $expenses = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    return [
        'period' => "$startDate to $endDate",
        'revenue' => floatval($revenue),
        'expenses' => floatval($expenses),
        'net_income' => floatval($revenue - $expenses)
    ];
}

function generateBalanceSheet($pdo, $endDate) {
    // Assets (1000-1999)
    $stmt = $pdo->prepare("SELECT SUM(ca.balance) as total FROM chart_of_accounts ca WHERE ca.account_code LIKE '1%' AND ca.is_active = 1");
    $stmt->execute();
    $assets = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Liabilities (2000-2999)
    $stmt = $pdo->prepare("SELECT SUM(ca.balance) as total FROM chart_of_accounts ca WHERE ca.account_code LIKE '2%' AND ca.is_active = 1");
    $stmt->execute();
    $liabilities = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Equity (3000-3999)
    $stmt = $pdo->prepare("SELECT SUM(ca.balance) as total FROM chart_of_accounts ca WHERE ca.account_code LIKE '3%' AND ca.is_active = 1");
    $stmt->execute();
    $equity = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    return [
        'as_of_date' => $endDate,
        'assets' => floatval($assets),
        'liabilities' => floatval($liabilities),
        'equity' => floatval($equity),
        'balanced' => (abs(($assets - $liabilities - $equity)) < 0.01)
    ];
}

function generateCashFlowStatement($pdo, $startDate, $endDate) {
    // Operating activities - Cash receipts
    $stmt = $pdo->prepare("SELECT SUM(gl.debit_amount) as total FROM general_ledger gl JOIN chart_of_accounts ca ON gl.account_id = ca.id WHERE ca.account_code = '1000' AND gl.transaction_date BETWEEN ? AND ? AND gl.debit_amount > 0");
    $stmt->execute([$startDate, $endDate]);
    $cashReceipts = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Operating activities - Cash payments
    $stmt = $pdo->prepare("SELECT SUM(gl.credit_amount) as total FROM general_ledger gl JOIN chart_of_accounts ca ON gl.account_id = ca.id WHERE ca.account_code = '1000' AND gl.transaction_date BETWEEN ? AND ? AND gl.credit_amount > 0");
    $stmt->execute([$startDate, $endDate]);
    $cashPayments = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $netCashFlow = $cashReceipts - $cashPayments;
    
    return [
        'period' => "$startDate to $endDate",
        'cash_receipts' => floatval($cashReceipts),
        'cash_payments' => floatval($cashPayments),
        'net_cash_flow' => floatval($netCashFlow)
    ];
}

// New functions for enhanced functionality

function getSalesCommissions($pdo) {
    try {
        // Try to get from cache first
        $cacheManager = getAccountingCacheManager();
        $commissions = $cacheManager->getSalesCommissions();
        
        if ($commissions === null) {
            // Not in cache, fetch from database
            $stmt = $pdo->query("SELECT * FROM sales_commissions ORDER BY period_start DESC LIMIT 100");
            $commissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Cache the result
            $cacheManager->cacheSalesCommissions($commissions);
        }
        
        echo json_encode(['success' => true, 'data' => $commissions]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve sales commissions']);
    }
}

function addSalesCommission($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
        return;
    }
    
    $salespersonId = $data['salesperson_id'] ?? 0;
    $salespersonName = $data['salesperson_name'] ?? '';
    $periodStart = $data['period_start'] ?? '';
    $periodEnd = $data['period_end'] ?? '';
    $totalSales = $data['total_sales'] ?? 0;
    $commissionRate = $data['commission_rate'] ?? 0.05;
    $commissionAmount = $data['commission_amount'] ?? 0;
    $tierLevel = $data['tier_level'] ?? 'bronze';
    
    if (!$salespersonId || !$salespersonName || !$periodStart || !$periodEnd || !$totalSales) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Insert commission record
        $stmt = $pdo->prepare("INSERT INTO sales_commissions (salesperson_id, salesperson_name, period_start, period_end, total_sales, commission_rate, commission_amount, tier_level) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$salespersonId, $salespersonName, $periodStart, $periodEnd, $totalSales, $commissionRate, $commissionAmount, $tierLevel]);
        $commissionId = $pdo->lastInsertId();
        
        // Add journal entry for accrued commission
        $stmt = $pdo->prepare("INSERT INTO general_ledger (account_id, transaction_date, description, debit_amount, credit_amount, reference_type, reference_id) VALUES ((SELECT id FROM chart_of_accounts WHERE account_code = '5910'), NOW(), ?, ?, 0, 'commission', ?)");
        $stmt->execute(["Commission expense for " . $salespersonName, $commissionAmount, $commissionId]);
        
        $stmt = $pdo->prepare("INSERT INTO general_ledger (account_id, transaction_date, description, debit_amount, credit_amount, reference_type, reference_id) VALUES ((SELECT id FROM chart_of_accounts WHERE account_code = '2400'), NOW(), ?, 0, ?, 'commission', ?)");
        $stmt->execute(["Accrued commission payable to " . $salespersonName, $commissionAmount, $commissionId]);
        
        $pdo->commit();
        
        // For large commissions, enqueue for background processing
        if ($totalSales > 50000) {
            $jobId = enqueueCommissionCalculation($salespersonId, $periodStart, $periodEnd);
        }
        
        // Clear relevant caches
        $cacheManager = getAccountingCacheManager();
        $cacheManager->clearAllCache();
        
        $response = ['success' => true, 'commission_id' => $commissionId, 'message' => 'Sales commission added successfully'];
        if (isset($jobId)) {
            $response['job_id'] = $jobId;
            $response['message'] .= ' Commission calculation queued for background processing.';
        }
        
        echo json_encode($response);
    } catch(Exception $e) {
        $pdo->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to add sales commission: ' . $e->getMessage()]);
    }
}

function getCommissionTiers($pdo) {
    try {
        // Try to get from cache first
        $cacheManager = getAccountingCacheManager();
        $tiers = $cacheManager->getCommissionTiers();
        
        if ($tiers === null) {
            // Not in cache, fetch from database
            $stmt = $pdo->query("SELECT * FROM commission_tiers ORDER BY min_sales_threshold");
            $tiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Cache the result
            $cacheManager->cacheCommissionTiers($tiers);
        }
        
        echo json_encode(['success' => true, 'data' => $tiers]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve commission tiers']);
    }
}

function calculateCommission($pdo) {
    $salespersonId = $_GET['salesperson_id'] ?? 0;
    $salesAmount = $_GET['sales_amount'] ?? 0;
    
    if (!$salespersonId || !$salesAmount) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Salesperson ID and sales amount are required']);
        return;
    }
    
    try {
        // For immediate calculation, get commission tiers
        $stmt = $pdo->query("SELECT * FROM commission_tiers ORDER BY min_sales_threshold DESC");
        $tiers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Determine applicable tier
        $applicableTier = null;
        foreach ($tiers as $tier) {
            if ($salesAmount >= $tier['min_sales_threshold']) {
                $applicableTier = $tier;
                break;
            }
        }
        
        if (!$applicableTier) {
            $applicableTier = $tiers[count($tiers) - 1]; // Default to lowest tier
        }
        
        // Calculate commission
        $commissionAmount = $salesAmount * $applicableTier['commission_rate'];
        
        // For complex calculations with historical data, enqueue for background processing
        if ($salesAmount > 10000) { // Only for large amounts
            $jobId = enqueueCommissionCalculation($salespersonId, date('Y-m-01'), date('Y-m-t'));
            
            if ($jobId) {
                echo json_encode([
                    'success' => true,
                    'sales_amount' => $salesAmount,
                    'tier' => $applicableTier['tier_name'],
                    'commission_rate' => $applicableTier['commission_rate'],
                    'commission_amount' => $commissionAmount,
                    'job_id' => $jobId,
                    'message' => 'Detailed commission calculation queued for background processing'
                ]);
                return;
            }
        }
        
        echo json_encode([
            'success' => true,
            'sales_amount' => $salesAmount,
            'tier' => $applicableTier['tier_name'],
            'commission_rate' => $applicableTier['commission_rate'],
            'commission_amount' => $commissionAmount
        ]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to calculate commission']);
    }
}

function getMarketingCampaigns($pdo) {
    try {
        // Try to get from cache first
        $cacheManager = getAccountingCacheManager();
        $campaigns = $cacheManager->getMarketingCampaigns();
        
        if ($campaigns === null) {
            // Not in cache, fetch from database
            $stmt = $pdo->query("SELECT * FROM marketing_campaigns ORDER BY start_date DESC LIMIT 100");
            $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Cache the result
            $cacheManager->cacheMarketingCampaigns($campaigns);
        }
        
        echo json_encode(['success' => true, 'data' => $campaigns]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve marketing campaigns']);
    }
}

function addMarketingCampaign($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
        return;
    }
    
    $campaignName = $data['campaign_name'] ?? '';
    $campaignType = $data['campaign_type'] ?? '';
    $startDate = $data['start_date'] ?? '';
    $endDate = $data['end_date'] ?? '';
    $budget = $data['budget'] ?? 0;
    
    if (!$campaignName || !$campaignType || !$startDate || !$endDate) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO marketing_campaigns (campaign_name, campaign_type, start_date, end_date, budget) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$campaignName, $campaignType, $startDate, $endDate, $budget]);
        $campaignId = $pdo->lastInsertId();
        
        // Enqueue ROI calculation for background processing
        $jobId = enqueueMarketingROICalculation($campaignId);
        
        // Clear relevant caches
        $cacheManager = getAccountingCacheManager();
        $cacheManager->clearAllCache();
        
        echo json_encode([
            'success' => true, 
            'campaign_id' => $campaignId, 
            'message' => 'Marketing campaign added successfully',
            'job_id' => $jobId
        ]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to add marketing campaign']);
    }
}

function getMarketingExpenses($pdo) {
    try {
        // Try to get from cache first
        $cacheManager = getAccountingCacheManager();
        $expenses = $cacheManager->getMarketingExpenses();
        
        if ($expenses === null) {
            // Not in cache, fetch from database
            $stmt = $pdo->query("SELECT me.*, mc.campaign_name FROM marketing_expenses me LEFT JOIN marketing_campaigns mc ON me.campaign_id = mc.id ORDER BY me.expense_date DESC LIMIT 100");
            $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Cache the result
            $cacheManager->cacheMarketingExpenses($expenses);
        }
        
        echo json_encode(['success' => true, 'data' => $expenses]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve marketing expenses']);
    }
}

function addMarketingExpense($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
        return;
    }
    
    $campaignId = $data['campaign_id'] ?? null;
    $expenseDate = $data['expense_date'] ?? date('Y-m-d');
    $expenseType = $data['expense_type'] ?? '';
    $description = $data['description'] ?? '';
    $amount = $data['amount'] ?? 0;
    
    if (!$expenseType || !$amount) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Expense type and amount are required']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Insert expense record
        $stmt = $pdo->prepare("INSERT INTO marketing_expenses (campaign_id, expense_date, expense_type, description, amount) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$campaignId, $expenseDate, $expenseType, $description, $amount]);
        $expenseId = $pdo->lastInsertId();
        
        // Add journal entry for marketing expense
        $accountCode = '5400'; // Default to Marketing Expense
        switch ($expenseType) {
            case 'ads':
                $accountCode = '5920'; // Social Media Ads
                break;
            case 'processing_fees':
                $accountCode = '5930'; // Processing Fees
                break;
            case 'promotional_costs':
                $accountCode = '5940'; // Promotional Costs
                break;
        }
        
        $stmt = $pdo->prepare("INSERT INTO general_ledger (account_id, transaction_date, description, debit_amount, credit_amount, reference_type, reference_id) VALUES ((SELECT id FROM chart_of_accounts WHERE account_code = ?), ?, ?, ?, 0, 'marketing', ?)");
        $stmt->execute([$accountCode, $expenseDate, $description, $amount, $expenseId]);
        
        $stmt = $pdo->prepare("INSERT INTO general_ledger (account_id, transaction_date, description, debit_amount, credit_amount, reference_type, reference_id) VALUES ((SELECT id FROM chart_of_accounts WHERE account_code = '1000'), ?, ?, 0, ?, 'marketing', ?)");
        $stmt->execute([$expenseDate, "Payment for " . $description, $amount, $expenseId]);
        
        // Update campaign spent amount
        if ($campaignId) {
            $stmt = $pdo->prepare("UPDATE marketing_campaigns SET spent_amount = spent_amount + ? WHERE id = ?");
            $stmt->execute([$amount, $campaignId]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'expense_id' => $expenseId, 'message' => 'Marketing expense added successfully']);
    } catch(Exception $e) {
        $pdo->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to add marketing expense: ' . $e->getMessage()]);
    }
}

function getOperationsCosts($pdo) {
    try {
        // Try to get from cache first
        $cacheManager = getAccountingCacheManager();
        $costs = $cacheManager->getOperationsCosts();
        
        if ($costs === null) {
            // Not in cache, fetch from database
            $stmt = $pdo->query("SELECT * FROM operations_costs ORDER BY expense_date DESC LIMIT 100");
            $costs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Cache the result
            $cacheManager->cacheOperationsCosts($costs);
        }
        
        echo json_encode(['success' => true, 'data' => $costs]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve operations costs']);
    }
}

function addOperationsCost($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
        return;
    }
    
    $costCenter = $data['cost_center'] ?? '';
    $costType = $data['cost_type'] ?? '';
    $expenseDate = $data['expense_date'] ?? date('Y-m-d');
    $description = $data['description'] ?? '';
    $amount = $data['amount'] ?? 0;
    
    if (!$costCenter || !$costType || !$amount) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Insert cost record
        $stmt = $pdo->prepare("INSERT INTO operations_costs (cost_center, cost_type, expense_date, description, amount) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$costCenter, $costType, $expenseDate, $description, $amount]);
        $costId = $pdo->lastInsertId();
        
        // Add journal entry for operations cost
        $stmt = $pdo->prepare("INSERT INTO general_ledger (account_id, transaction_date, description, debit_amount, credit_amount, reference_type, reference_id) VALUES ((SELECT id FROM chart_of_accounts WHERE account_code = '5000'), ?, ?, ?, 0, 'operations', ?)");
        $stmt->execute([$expenseDate, $description, $amount, $costId]);
        
        $stmt = $pdo->prepare("INSERT INTO general_ledger (account_id, transaction_date, description, debit_amount, credit_amount, reference_type, reference_id) VALUES ((SELECT id FROM chart_of_accounts WHERE account_code = '1000'), ?, ?, 0, ?, 'operations', ?)");
        $stmt->execute([$expenseDate, "Payment for " . $description, $amount, $costId]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'cost_id' => $costId, 'message' => 'Operations cost added successfully']);
    } catch(Exception $e) {
        $pdo->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to add operations cost: ' . $e->getMessage()]);
    }
}

function getProductCosting($pdo) {
    $productId = $_GET['product_id'] ?? 0;
    
    try {
        if ($productId) {
            $stmt = $pdo->prepare("SELECT * FROM product_costing WHERE product_id = ?");
            $stmt->execute([$productId]);
            $costing = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $stmt = $pdo->query("SELECT * FROM product_costing");
            $costing = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        echo json_encode(['success' => true, 'data' => $costing]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve product costing']);
    }
}

function updateProductCosting($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
        return;
    }
    
    $productId = $data['product_id'] ?? 0;
    $costMethod = $data['cost_method'] ?? 'weighted_average';
    $unitCost = $data['unit_cost'] ?? 0;
    $totalUnits = $data['total_units'] ?? 0;
    $totalCost = $data['total_cost'] ?? 0;
    
    if (!$productId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Product ID is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO product_costing (product_id, cost_method, unit_cost, total_units, total_cost) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE cost_method = VALUES(cost_method), unit_cost = VALUES(unit_cost), total_units = VALUES(total_units), total_cost = VALUES(total_cost)");
        $stmt->execute([$productId, $costMethod, $unitCost, $totalUnits, $totalCost]);
        
        echo json_encode(['success' => true, 'message' => 'Product costing updated successfully']);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update product costing']);
    }
}

function getPayroll($pdo) {
    try {
        // Try to get from cache first
        $cacheManager = getAccountingCacheManager();
        $payroll = $cacheManager->getPayrollRecords();
        
        if ($payroll === null) {
            // Not in cache, fetch from database
            $stmt = $pdo->query("SELECT * FROM payroll ORDER BY payroll_period_start DESC LIMIT 100");
            $payroll = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Cache the result
            $cacheManager->cachePayrollRecords($payroll);
        }
        
        echo json_encode(['success' => true, 'data' => $payroll]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to retrieve payroll records']);
    }
}

function addPayroll($pdo) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data provided']);
        return;
    }
    
    $employeeId = $data['employee_id'] ?? 0;
    $employeeName = $data['employee_name'] ?? '';
    $periodStart = $data['period_start'] ?? '';
    $periodEnd = $data['period_end'] ?? '';
    $salaryAmount = $data['salary_amount'] ?? 0;
    $commissionAmount = $data['commission_amount'] ?? 0;
    $bonusAmount = $data['bonus_amount'] ?? 0;
    $overtimeAmount = $data['overtime_amount'] ?? 0;
    $benefitsAmount = $data['benefits_amount'] ?? 0;
    $taxDeductions = $data['tax_deductions'] ?? 0;
    $otherDeductions = $data['other_deductions'] ?? 0;
    $netPay = $data['net_pay'] ?? 0;
    
    if (!$employeeId || !$employeeName || !$periodStart || !$periodEnd) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Required fields are missing']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Insert payroll record
        $stmt = $pdo->prepare("INSERT INTO payroll (employee_id, employee_name, payroll_period_start, payroll_period_end, salary_amount, commission_amount, bonus_amount, overtime_amount, benefits_amount, tax_deductions, other_deductions, net_pay) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$employeeId, $employeeName, $periodStart, $periodEnd, $salaryAmount, $commissionAmount, $bonusAmount, $overtimeAmount, $benefitsAmount, $taxDeductions, $otherDeductions, $netPay]);
        $payrollId = $pdo->lastInsertId();
        
        // Add journal entries for payroll
        $totalGross = $salaryAmount + $commissionAmount + $bonusAmount + $overtimeAmount + $benefitsAmount;
        
        // Debit expenses
        if ($salaryAmount > 0) {
            $stmt = $pdo->prepare("INSERT INTO general_ledger (account_id, transaction_date, description, debit_amount, credit_amount, reference_type, reference_id) VALUES ((SELECT id FROM chart_of_accounts WHERE account_code = '5100'), ?, ?, ?, 0, 'payroll', ?)");
            $stmt->execute([$periodEnd, "Salary for " . $employeeName, $salaryAmount, $payrollId]);
        }
        
        if ($commissionAmount > 0) {
            $stmt = $pdo->prepare("INSERT INTO general_ledger (account_id, transaction_date, description, debit_amount, credit_amount, reference_type, reference_id) VALUES ((SELECT id FROM chart_of_accounts WHERE account_code = '5910'), ?, ?, ?, 0, 'payroll', ?)");
            $stmt->execute([$periodEnd, "Commission for " . $employeeName, $commissionAmount, $payrollId]);
        }
        
        // Credit liabilities
        $stmt = $pdo->prepare("INSERT INTO general_ledger (account_id, transaction_date, description, debit_amount, credit_amount, reference_type, reference_id) VALUES ((SELECT id FROM chart_of_accounts WHERE account_code = '2500'), ?, ?, 0, ?, 'payroll', ?)");
        $stmt->execute([$periodEnd, "Accrued payroll payable to " . $employeeName, $netPay, $payrollId]);
        
        // Credit tax liabilities
        if ($taxDeductions > 0) {
            $stmt = $pdo->prepare("INSERT INTO general_ledger (account_id, transaction_date, description, debit_amount, credit_amount, reference_type, reference_id) VALUES ((SELECT id FROM chart_of_accounts WHERE account_code = '2200'), ?, ?, 0, ?, 'payroll', ?)");
            $stmt->execute([$periodEnd, "Payroll taxes payable for " . $employeeName, $taxDeductions, $payrollId]);
        }
        
        $pdo->commit();
        echo json_encode(['success' => true, 'payroll_id' => $payrollId, 'message' => 'Payroll record added successfully']);
    } catch(Exception $e) {
        $pdo->rollback();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to add payroll record: ' . $e->getMessage()]);
    }
}

function getFinancialRatios($pdo) {
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    
    try {
        // Try to get from cache first
        $cacheManager = getAccountingCacheManager();
        $cachedRatios = $cacheManager->getFinancialRatios($endDate);
        
        if ($cachedRatios === null) {
            // Not in cache, calculate ratios
            // Get balance sheet data
            $balanceSheet = generateBalanceSheet($pdo, $endDate);
            
            // Calculate ratios
            $currentAssets = 0;
            $inventory = 0;
            $currentLiabilities = 0;
            $totalDebt = 0;
            $totalEquity = $balanceSheet['equity'];
            $cogs = 0;
            $averageInventory = 0;
            
            // Get current assets (accounts 1000-1999)
            $stmt = $pdo->prepare("SELECT SUM(ca.balance) as total FROM chart_of_accounts ca WHERE ca.account_code LIKE '1%' AND ca.account_code NOT LIKE '1____' AND ca.is_active = 1");
            $stmt->execute();
            $currentAssets = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Get inventory
            $stmt = $pdo->prepare("SELECT balance FROM chart_of_accounts WHERE account_code = '1200'");
            $stmt->execute();
            $inventory = $stmt->fetch(PDO::FETCH_ASSOC)['balance'] ?? 0;
            
            // Get current liabilities (accounts 2000-2999)
            $stmt = $pdo->prepare("SELECT SUM(ca.balance) as total FROM chart_of_accounts ca WHERE ca.account_code LIKE '2%' AND ca.account_code NOT LIKE '2____' AND ca.is_active = 1");
            $stmt->execute();
            $currentLiabilities = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Get total debt (accounts 2000-2999)
            $stmt = $pdo->prepare("SELECT SUM(ca.balance) as total FROM chart_of_accounts ca WHERE ca.account_code LIKE '2%' AND ca.is_active = 1");
            $stmt->execute();
            $totalDebt = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Calculate ratios
            $currentRatio = $currentLiabilities > 0 ? $currentAssets / $currentLiabilities : 0;
            $quickRatio = $currentLiabilities > 0 ? ($currentAssets - $inventory) / $currentLiabilities : 0;
            $debtToEquity = $totalEquity > 0 ? $totalDebt / $totalEquity : 0;
            $inventoryTurnover = $averageInventory > 0 ? $cogs / $averageInventory : 0;
            
            $ratios = [
                'current_ratio' => round($currentRatio, 2),
                'quick_ratio' => round($quickRatio, 2),
                'debt_to_equity' => round($debtToEquity, 2),
                'inventory_turnover' => round($inventoryTurnover, 2)
            ];
            
            // Cache the result
            $cacheManager->cacheFinancialRatios($ratios, $endDate);
            
            echo json_encode([
                'success' => true,
                'data' => $ratios
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'data' => $cachedRatios
            ]);
        }
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to calculate financial ratios']);
    }
}

function getMonetaryAttribution($pdo) {
    try {
        // Customer Acquisition Cost (CAC)
        // This would typically be calculated based on marketing spend and new customers
        $cac = 0;
        
        // Customer Lifetime Value (CLV)
        // This would typically be calculated based on average order value, purchase frequency, and customer lifespan
        $clv = 0;
        
        // Marketing ROI
        // This would typically be calculated based on revenue generated vs marketing costs
        $stmt = $pdo->query("SELECT SUM(budget) as total_budget, SUM(revenue_generated) as total_revenue FROM marketing_campaigns");
        $marketingData = $stmt->fetch(PDO::FETCH_ASSOC);
        $marketingCost = $marketingData['total_budget'] ?? 0;
        $marketingRevenue = $marketingData['total_revenue'] ?? 0;
        $marketingROI = $marketingCost > 0 ? ($marketingRevenue - $marketingCost) / $marketingCost : 0;
        
        // Commission ROI
        // This would typically be calculated based on sales generated vs commissions paid
        $stmt = $pdo->query("SELECT SUM(total_sales) as total_sales, SUM(commission_amount) as total_commission FROM sales_commissions");
        $commissionData = $stmt->fetch(PDO::FETCH_ASSOC);
        $salesGenerated = $commissionData['total_sales'] ?? 0;
        $commissionPaid = $commissionData['total_commission'] ?? 0;
        $commissionROI = $commissionPaid > 0 ? ($salesGenerated - $commissionPaid) / $commissionPaid : 0;
        
        echo json_encode([
            'success' => true,
            'data' => [
                'cac' => $cac,
                'clv' => $clv,
                'marketing_roi' => round($marketingROI, 4),
                'commission_roi' => round($commissionROI, 4)
            ]
        ]);
    } catch(Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to calculate monetary attribution']);
    }
}

/**
 * Update collection status when a payment is received
 */
function updateCollectionStatusForPayment($pdo, $receivableId, $amount) {
    try {
        // Check if this receivable is in collections
        $stmt = $pdo->prepare("SELECT id, outstanding_amount FROM collections WHERE invoice_id = ? AND collection_status != 'resolved' AND collection_status != 'written_off'");
        $stmt->execute([$receivableId]);
        $collection = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($collection) {
            // Update the collection record
            $newOutstanding = max(0, $collection['outstanding_amount'] - $amount);
            $status = ($newOutstanding <= 0) ? 'resolved' : 'in_progress';
            $resolutionType = ($newOutstanding <= 0) ? 'paid' : 'partial_payment';
            
            $stmt = $pdo->prepare("UPDATE collections SET outstanding_amount = ?, collection_status = ?, resolution_date = CURDATE(), resolution_amount = resolution_amount + ?, resolution_type = ? WHERE id = ?");
            $stmt->execute([$newOutstanding, $status, $amount, $resolutionType, $collection['id']]);
        }
    } catch (Exception $e) {
        // Log error but don't fail the main transaction
        error_log("Failed to update collection status: " . $e->getMessage());
    }
}
?> 
 f u n c t i o n   g e t D a s h b o a r d S u m m a r y ( $ p d o )   {  
         t r y   {  
                 / /   1 .   T o t a l   R e v e n u e   ( F r o m   O r d e r s )  
                 $ s t m t   =   $ p d o - > q u e r y ( " S E L E C T   C O A L E S C E ( S U M ( t o t a l ) ,   0 )   F R O M   o r d e r s   W H E R E   s t a t u s   N O T   I N   ( ' c a n c e l l e d ' ,   ' f a i l e d ' ) " ) ;  
                 $ r e v e n u e   =   $ s t m t - > f e t c h C o l u m n ( ) ;  
  
                 / /   2 .   A c c o u n t s   R e c e i v a b l e   ( P e n d i n g   O r d e r s   +   s p e c i f i c   A R   e n t r i e s )  
                 $ s t m t   =   $ p d o - > q u e r y ( " S E L E C T   C O A L E S C E ( S U M ( t o t a l ) ,   0 )   F R O M   o r d e r s   W H E R E   p a y m e n t _ s t a t u s   I N   ( ' p e n d i n g ' ,   ' p e n d i n g _ p a y m e n t ' )   A N D   s t a t u s   ! =   ' c a n c e l l e d ' " ) ;  
                 $ p e n d i n g O r d e r s   =   $ s t m t - > f e t c h C o l u m n ( ) ;  
                  
                 $ s t m t   =   $ p d o - > q u e r y ( " S E L E C T   C O A L E S C E ( S U M ( a m o u n t   -   r e c e i v e d _ a m o u n t ) ,   0 )   F R O M   a c c o u n t s _ r e c e i v a b l e   W H E R E   s t a t u s   ! =   ' p a i d ' " ) ;  
                 $ a r T a b l e   =   $ s t m t - > f e t c h C o l u m n ( ) ;  
                  
                 $ a c c o u n t s R e c e i v a b l e   =   $ p e n d i n g O r d e r s   +   $ a r T a b l e ;  
  
                 / /   3 .   A c c o u n t s   P a y a b l e   ( P e n d i n g   M e r c h a n t   P a y o u t s   +   s p e c i f i c   A P   e n t r i e s )  
                 / /   C h e c k   i f   m e r c h a n t _ c o m m i s s i o n s   t a b l e   e x i s t s   a n d   h a s   d a t a   ( i t   w a s   j u s t   c r e a t e d )  
                 t r y   {  
                         $ s t m t   =   $ p d o - > q u e r y ( " S E L E C T   C O A L E S C E ( S U M ( n e t _ a m o u n t ) ,   0 )   F R O M   m e r c h a n t _ c o m m i s s i o n s   W H E R E   s t a t u s   =   ' p e n d i n g _ p a y o u t ' " ) ;  
                         $ p e n d i n g P a y o u t s   =   $ s t m t - > f e t c h C o l u m n ( ) ;  
                 }   c a t c h   ( E x c e p t i o n   $ e )   {  
                         $ p e n d i n g P a y o u t s   =   0 ;  
                 }  
  
                 $ s t m t   =   $ p d o - > q u e r y ( " S E L E C T   C O A L E S C E ( S U M ( a m o u n t   -   p a i d _ a m o u n t ) ,   0 )   F R O M   a c c o u n t s _ p a y a b l e   W H E R E   s t a t u s   ! =   ' p a i d ' " ) ;  
                 $ a p T a b l e   =   $ s t m t - > f e t c h C o l u m n ( ) ;  
  
                 $ a c c o u n t s P a y a b l e   =   $ p e n d i n g P a y o u t s   +   $ a p T a b l e ;  
  
                 / /   4 .   T o t a l   E x p e n s e s   ( F r o m   G e n e r a l   L e d g e r   C l a s s   5 x x x )  
                 $ s t m t   =   $ p d o - > q u e r y ( " S E L E C T   C O A L E S C E ( S U M ( d e b i t _ a m o u n t   -   c r e d i t _ a m o u n t ) ,   0 )   F R O M   g e n e r a l _ l e d g e r   g l    
                                                         J O I N   c h a r t _ o f _ a c c o u n t s   c a   O N   g l . a c c o u n t _ i d   =   c a . i d    
                                                         W H E R E   c a . a c c o u n t _ t y p e   =   ' e x p e n s e ' " ) ;  
                 $ e x p e n s e s   =   $ s t m t - > f e t c h C o l u m n ( ) ;  
  
                 e c h o   j s o n _ e n c o d e ( [  
                         ' s u c c e s s '   = >   t r u e ,  
                         ' d a t a '   = >   [  
                                 ' t o t a l R e v e n u e '   = >   f l o a t v a l ( $ r e v e n u e ) ,  
                                 ' t o t a l E x p e n s e s '   = >   f l o a t v a l ( $ e x p e n s e s ) ,  
                                 ' a c c o u n t s R e c e i v a b l e '   = >   f l o a t v a l ( $ a c c o u n t s R e c e i v a b l e ) ,  
                                 ' a c c o u n t s P a y a b l e '   = >   f l o a t v a l ( $ a c c o u n t s P a y a b l e )  
                         ]  
                 ] ) ;  
         }   c a t c h   ( E x c e p t i o n   $ e )   {  
                 h t t p _ r e s p o n s e _ c o d e ( 5 0 0 ) ;  
                 e c h o   j s o n _ e n c o d e ( [ ' s u c c e s s '   = >   f a l s e ,   ' m e s s a g e '   = >   ' F a i l e d   t o   r e t r i e v e   d a s h b o a r d   s u m m a r y :   '   .   $ e - > g e t M e s s a g e ( ) ] ) ;  
         }  
 }  
 