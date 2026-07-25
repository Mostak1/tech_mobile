# Cash Register Functionality Specification

This document provides a detailed technical blueprint of the Cash Register functionality to enable replication in another Point of Sale (POS) system.

---

## 1. Database Schema

The cash register system relies on two primary tables: `cash_registers` and `cash_register_transactions`.

### `cash_registers` Table
This table stores the session details of a user's register from the moment they open it until it is closed.

| Column | Type | Nullable | Description |
| :--- | :--- | :--- | :--- |
| `id` | `INT AUTO_INCREMENT` | No | Primary key. |
| `business_id` | `INT` | No | ID of the business entity. |
| `user_id` | `INT` | Yes | ID of the user (cashier) who opened the register. |
| `location_id` | `INT` | Yes | Location of the store/POS. |
| `department_id` | `INT` | Yes | Division or department code (e.g., Retail, Clinic). |
| `status` | `ENUM('open', 'close')` | No | Status of the register session. Default: `'open'`. |
| `closed_at` | `DATETIME` | Yes | Timestamp when the register session was closed. |
| `closing_amount` | `DECIMAL(22, 4)` | No | Cashier's counted physical cash at closing. Default: `0`. |
| `total_card_slips` | `INT` | No | Count of card transactions/slips at closing. Default: `0`. |
| `total_cheques` | `INT` | No | Count of cheques collected at closing. Default: `0`. |
| `closing_note` | `TEXT` | Yes | Remarks entered by the cashier at closing. |
| `denominations` | `JSON` | Yes | Breakdown of currency counts (e.g., `{"100": 5, "50": 10}`). |
| `created_at` | `TIMESTAMP` | Yes | Time when register was opened. |
| `updated_at` | `TIMESTAMP` | Yes | Last update timestamp. |

### `cash_register_transactions` Table
This table tracks individual flow elements of cash/payments within a register session.

| Column | Type | Nullable | Description |
| :--- | :--- | :--- | :--- |
| `id` | `INT AUTO_INCREMENT` | No | Primary key. |
| `cash_register_id`| `INT` | No | Foreign key linking to `cash_registers.id`. |
| `amount` | `DECIMAL(22, 4)` | No | Amount of the transaction. |
| `pay_method` | `VARCHAR(191)` | Yes | Payment method used (e.g., `'cash'`, `'card'`, `'cheque'`, `'bank_transfer'`, `'custom_pay_1'`). |
| `type` | `ENUM('debit', 'credit')` | No | `'credit'` adds to the register (inflows), `'debit'` subtracts (outflows/refunds). |
| `transaction_type` | `VARCHAR(191)` | Yes | Type of operation: `'initial'` (opening balance), `'sell'`, `'refund'`, `'expense'`. |
| `transaction_id` | `INT` | Yes | ID linking to the main `transactions` invoice table (if applicable). |
| `department_id` | `INT` | Yes | Department associated with the payment. |
| `created_at` | `TIMESTAMP` | Yes | Creation timestamp. |
| `updated_at` | `TIMESTAMP` | Yes | Last update timestamp. |

---

## 2. Core Workflows & Life Cycle

### A. Opening the Register
When a cashier navigates to the POS screen, the system checks if they have an active register:
1. **Check Existing Session**: Query `cash_registers` where `user_id = CURRENT_USER`, `status = 'open'`, and optionally match by `department_id`.
2. **Redirect or Open Dialog**:
   - If an open session exists, bypass and open the POS window.
   - If no open session exists, present the **Open Cash Register** dialog.
3. **Capture Opening Cash**: Prompt the cashier to input the starting cash amount (Cash in Hand).
4. **Create Records**:
   - Insert a row into `cash_registers` with `status = 'open'`.
   - If the opening cash > 0, insert a transaction in `cash_register_transactions`:
     - `amount` = Opening cash value
     - `pay_method` = `'cash'`
     - `type` = `'credit'`
     - `transaction_type` = `'initial'`

### B. Recording POS Transactions (Inflows & Outflows)
Whenever a sale, return, or refund is processed:
* **Sells (Credit)**: Insert a record into `cash_register_transactions` with `type = 'credit'` and `transaction_type = 'sell'`.
* **Draft to Final Sale Transition**: If an invoice is converted from draft to finalized, add the payment amounts to the current register.
* **Refunds/Returns (Debit)**: Insert a record with `type = 'debit'` and `transaction_type = 'refund'`.
* **Payments Updates**: If the user updates invoice payments, calculate the difference between the old and new payments. Add a `'debit'` entry to deduct if refunding, or a `'credit'` entry to add extra funds.

### C. Closing the Register
When the cashier completes their shift:
1. **Fetch Summaries**: Query the active register details (aggregate all credits and debits to calculate expected totals).
2. **Closing Modal Inputs**: Present a screen showing:
   - Expected Cash, Card Slips count, and Cheque count.
   - Inputs for **Closing Amount** (actual counted physical cash), **Total Card Slips**, **Total Cheques**, **Denominations**, and **Closing Note**.
3. **Update Status**: Set `status = 'close'`, update closing fields, set `closed_at = NOW()`.

---

## 3. SQL Calculations & Aggregations

To compute totals for the active register session, use the following SQL structure:

```sql
SELECT 
    cr.id,
    cr.created_at AS open_time,
    
    -- 1. Cash In Hand (Opening Balance)
    COALESCE(SUM(CASE WHEN ct.transaction_type = 'initial' THEN ct.amount ELSE 0 END), 0) AS cash_in_hand,
    
    -- 2. Total Inflows (Sales minus Refunds)
    COALESCE(SUM(CASE WHEN ct.transaction_type = 'sell' THEN ct.amount 
                      WHEN ct.transaction_type = 'refund' THEN -1 * ct.amount 
                      ELSE 0 END), 0) AS total_sale,
                      
    -- 3. Inflows By Payment Methods
    COALESCE(SUM(CASE WHEN ct.transaction_type = 'sell' AND ct.pay_method = 'cash' THEN ct.amount ELSE 0 END), 0) AS total_cash_sales,
    COALESCE(SUM(CASE WHEN ct.transaction_type = 'sell' AND ct.pay_method = 'card' THEN ct.amount ELSE 0 END), 0) AS total_card_sales,
    COALESCE(SUM(CASE WHEN ct.transaction_type = 'sell' AND ct.pay_method = 'cheque' THEN ct.amount ELSE 0 END), 0) AS total_cheque_sales,
    COALESCE(SUM(CASE WHEN ct.transaction_type = 'sell' AND ct.pay_method = 'bank_transfer' THEN ct.amount ELSE 0 END), 0) AS total_bank_transfer_sales,
    
    -- 4. Inflows By Expenses
    COALESCE(SUM(CASE WHEN ct.transaction_type = 'expense' THEN ct.amount ELSE 0 END), 0) AS total_expense,
    
    -- 5. Outflow Refunds By Payment Methods
    COALESCE(SUM(CASE WHEN ct.transaction_type = 'refund' AND ct.pay_method = 'cash' THEN ct.amount ELSE 0 END), 0) AS total_cash_refunds
    
FROM cash_registers cr
LEFT JOIN cash_register_transactions ct ON ct.cash_register_id = cr.id
WHERE cr.user_id = :user_id AND cr.status = 'open'
GROUP BY cr.id;
```

---

## 4. Architectural Implementation Tips

1. **Transaction Wrapping**: Wrap register inserts/updates in DB transactions (`DB::beginTransaction()`) alongside main POS invoices to guarantee data integrity.
2. **Multi-location & Departments**: If your POS supports multiple locations or departments, ensure that you filter both `cash_registers` and `cash_register_transactions` by `location_id` and `department_id` to prevent cross-register contamination.
3. **Closing Discrepancy Audits**: Store both **Expected Cash** (calculated from transactions) and **Actual Cash** (entered by user) to generate register discrepancy reports for store administrators.
