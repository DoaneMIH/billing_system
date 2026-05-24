  NOVALINK BILLING SYSTEM - COMPLETE USER GUIDE
Explanation of Each Menu Item \& What They Do

1. DASHBOARD
Purpose: 
Central hub showing overview of your entire billing system at a glance.

What You See:
Statistics Cards:
    Total Customers (how many customers you have)
    Active Customers (customers currently receiving service)
    Unpaid Bills (number of outstanding bills)
    Monthly Revenue (total money collected this month)

Quick Actions: Buttons to quickly access common tasks
    Add New Customer
    Record Payment
    Generate Billing
    View Reports


(Still Working...)
Recent Activity: Latest actions in the system
    New payments recorded
    Billings generated
    Customer status changes


What You Do Here:
    Check daily statistics
    Get quick overview of business health
    Access frequently used features quickly
    Monitor recent activities


2. CUSTOMERS
Purpose:
Manage all customer information and records.

What You See:
Customer List Table showing:
    Account Number (e.g., ACC-001)
    Customer Name
    Address
    Area/Barangay
    Package (internet plan)
    Monthly Fee
    Status (Active/Disconnected/Hold)
    Action buttons


What You Can Do:
As Admin:
    View all customers
    Add new customer (Click "Add Customer" button)
    Edit customer details (Click "Edit" button)
        Enter name, address, area, package, contact info
        System auto-generates account number
        Update address, phone, package, etc.
    View Ledger (Click "View Ledger")
        See complete billing history
        All payments made
        Current balance
    Disconnect customer (Click "Disconnect")
        Stops service
        Stops future billing
    Reconnect customer (Click "Reconnect")
        Restores service
        Resumes billing
    Filter by area/barangay
        See only customers from specific area


As Accounting:
* View customers (read-only)
* View Ledger
❌ Cannot add, edit, or disconnect


As Cashier:
* View customers (read-only)
* Search customers
* View Ledger
❌ Cannot add, edit, or disconnect



Real-World Use Cases:
New customer signs up → Add Customer
Customer moves → Edit address
Customer doesn't pay → View ledger, then disconnect
Customer pays debt → Reconnect
Field visit to Barangay 2 → Filter by Barangay 2



3. PAYMENTS
Purpose:
Record customer payments and issue official receipts.

What You See:
    Payment Form (top section):
    Search Customer field (with dropdown)
    Billing Period dropdown
    OR Number field
    Payment Date
    Amount Paid
    Payment Method
    Remarks
    Recent Payments Table (bottom section):
    All payments recorded today/recently
    Print Invoice button
    Print Receipt button

Who Can Access: Cashier and Admin
Real-World Use Cases:
Customer comes to pay → Record payment, print invoice
Partial payment → Enter less than full amount
Multiple months → Pay each month separately
Different payment methods → Select check/online/cash

4. BILLINGS
Purpose:
Generate monthly bills and manage billing records.

What You See:
* Generate Billing Section (top):
    Month selector
    Year selector
    Generate button

* View Billings Section (bottom):
    Filter by month/year/status
    Table showing all billings
    Previous Balance column
    Current Charges column
    Total Amount column
    Payment status

What You Can Do:
As Admin:
    Generate monthly bills (Click "Generate")
    Add Additional Fee
    View all billing reports
    Filter by month/year/status
    See customer details for each billing
    See payment status for each billing

As Accounting:
    View billing records (read-only)
    Filter by month/year/status

Billing Logic Explained:
Active Customer:
✅ Gets billed every month
✅ Unpaid balance carries forward
✅ Due date = last day of month

Hold Disconnection:
✅ Still gets billed (grace period)
✅ Balance carries forward
⚠️ Warning to pay soon

Disconnected:
❌ NO new bills generated
✅ Old unpaid bills remain
✅ When reconnected, billing resumes

Real-World Use Cases:
Start of month → Generate billing for all customers
Customer needs router repair → Add service fee to their bill
Check who hasn't paid → Filter by "Unpaid" status
Monthly reconciliation → View all February billings

5. UNPAID BILLS
Purpose:
Quick view of all customers with outstanding balances.

What You See:

* Filter Section:
    Area filter
    Month filter
    Year filter


* Unpaid Bills Table:
    Customer name
    Account number
    Billing period
    Amount owed
    Days overdue
    Status
    Contact information

What You Can Do:
Who Can Access: Admin and Accounting
    Find Customers Who Owe Money
    Print Report

Color Coding:
🟢 Green: Less than 30 days overdue
🟡 Yellow: 30-60 days overdue
🔴 Red: 60+ days overdue (urgent!)

Real-World Use Cases:
Month-end → Check who didn't pay
Field collection → Print list for specific barangay
Follow-up calls → See all overdue customers
Management report → Show total unpaid amounts


6. REPORTS
Purpose:
Generate comprehensive business reports and analytics.

What You See:
    Report Type Selector: Dropdown with 5 report types
    Filters: Month, Year, Area
    Print/PDF Button: Save or print reports
    Report Display Area: Shows selected report

Available Reports:
Who Can Access: Admin and Accounting
6.1. Monthly Billing Report
Shows:
    All billings for selected month
    Previous balance
    Current charges
    Total billed
    Payments received
    Remaining balance
    Summary statistics

Use Case:
    Month-end reconciliation
    Verify all customers billed correctly
    Check collection rate

6.2. Monthly Sales Report
Shows:
    Total sales for the month
    Payment method breakdown (Cash/Check/Online)
    Number of transactions
    Unique customers who paid
    Average transaction amount
    Detailed transaction list

Use Case:
    Track revenue
    Verify cash vs check vs online
    Reconcile with bank deposits
    Monthly financial reporting

6.3. Unpaid Accounts Report
Shows:
    All customers with unpaid bills
    Total owed per customer
    Days overdue
    Contact information
    Color-coded urgency

Use Case:
    Collection planning
    Identify problem accounts
    Track bad debt
    Prioritize collection efforts

6.4. Customers for Disconnection
Shows:
    Customers 60+ days overdue
    Hold disconnection customers 45+ days
    Total balance at risk
    Recommended actions
    Disconnection criteria

Use Case:
    Identify who to disconnect
    Service management
    Reduce bad debt
    Collection escalation

6.5. Last Payment Dates Report
Shows:
    Every customer's last payment date
    Days since payment
    Lifetime total paid
    Current balance
    Payment behavior category

Use Case:
    Monitor payment patterns
    Identify irregular payers
    Track customer relationship
    Predict future issues

7. SEARCH
Who Can Access: Everyone
Purpose:
Quick customer lookup and ledger access.

What You See:
    Search Box: Large search field
    Search Results: Customer cards with details
    Quick Actions: View Ledger button


Real-World Use Cases:
Customer calls asking about balance → Quick search, view ledger
Customer at counter → Search by name, check status
Verify account number → Search by name
Fast lookup → Don't need to scroll through customer list


8. USER MANAGEMENT
What You Can Do (Admin only):
Purpose:
Manage system users and their access permissions.

What You See:
    User List Table:
        Username
        Full name
        Role (Admin/Accounting/Cashier)
        Status (Active/Inactive)
        Last login
        Action buttons

Role Permissions:
Admin (Full Access):

✅ Everything
✅ Create users
✅ Disconnect customers
✅ Generate billings
✅ Edit all records

Accounting (View Only):

✅ View customers
✅ View billings
✅ Generate reports
❌ Cannot edit
❌ Cannot process payments
❌ Cannot disconnect

Cashier (Payment Processing):

✅ Record payments
✅ Print invoices
✅ View ledgers
❌ Cannot edit customers
❌ Cannot generate billings
❌ Cannot view reports

Real-World Use Cases:
New cashier hired → Add cashier user
Accounting promotion → Change role from cashier to accounting
Employee left → Deactivate user
Password forgotten → Reset password
Audit trail → See who logged in when

9. SETTINGS
Who Can Access: Admin only
Purpose:
Configure system settings and preferences.

What You See (varies by what's implemented):
    Company Information:
        Company name
        Address
        Contact details
        TIN number
        Logo upload
    Areas/Barangays:
        Add new service areas
        Edit existing areas
        Deactivate areas
    Packages/Plans:
        Add new internet packages
        Set monthly fees
        Define bandwidth
        Edit existing packages
    System Preferences:
        Date format
        Currency symbol
        Timezone
        Receipt numbering


Manage Service Areas:
    Add: "New Subdivision"
    Edit: Change "Barangay 1" to "Brgy. 1 Poblacion"
    Delete: Remove unused areas
Manage Packages:
    Add new package:
    Name: Premium Plus
    Speed: 200 Mbps
    Monthly Fee: ₱1,999
    Description: For heavy users

    Edit existing:
    Change "Basic Plan" from ₱599 to ₱699
Company Details:
    Update company address
    Change contact number
    Update TIN for invoices


Real-World Use Cases:
Expand to new area → Add new barangay
Price increase → Update package fees
New product → Add faster speed package
Rebranding → Update logo
Company move → Update address