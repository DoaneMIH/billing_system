// Payments Page - Enhanced Customer Search with Dropdown

let searchTimeout;

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search_customer');
    const resultsDiv = document.getElementById('customer_results');
    const customerIdInput = document.getElementById('customer_id');
    const billingPeriodSelect = document.getElementById('billing_period');
    const selectedCustomerDiv = document.getElementById('selected_customer');
    const selectedCustomerName = document.getElementById('selected_customer_name');
    const billingInfo = document.getElementById('billing_info');
    
    // Load all customers on page load for dropdown
    loadAllCustomers();
    
    if (searchInput) {
        // Show dropdown on focus
        searchInput.addEventListener('focus', function() {
            if (this.value.trim().length === 0) {
                loadAllCustomers();
            }
        });
        
        // Search as user types
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length === 0) {
                loadAllCustomers(); // Show all if empty
                customerIdInput.value = '';
                billingPeriodSelect.innerHTML = '<option value="">Select customer first</option>';
                billingPeriodSelect.disabled = true;
                selectedCustomerDiv.style.display = 'none';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                searchCustomers(query);
            }, 300);
        });
    }
    
    function loadAllCustomers() {
        const xhr = new XMLHttpRequest();
        xhr.open('GET', 'ajax/search_customers.php?q=', true);
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const customers = JSON.parse(xhr.responseText);
                    displayCustomerResults(customers, true);
                } catch (e) {
                    console.error('Error parsing JSON:', e);
                }
            }
        };
        
        xhr.send();
    }
    
    function searchCustomers(query) {
        const xhr = new XMLHttpRequest();
        xhr.open('GET', `ajax/search_customers.php?q=${encodeURIComponent(query)}`, true);
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const customers = JSON.parse(xhr.responseText);
                    displayCustomerResults(customers, false);
                } catch (e) {
                    console.error('Error parsing JSON:', e);
                }
            }
        };
        
        xhr.send();
    }
    
    function displayCustomerResults(customers, showAll = false) {
        if (customers.length === 0) {
            resultsDiv.innerHTML = '<div style="padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; margin-top: 5px; border-radius: 5px;">No customers found</div>';
            return;
        }
        
        // Limit to first 10 if showing all
        const displayCustomers = showAll ? customers.slice(0, 10) : customers;
        
        let html = '<div style="position: absolute; width: 100%; background: white; border: 2px solid var(--primary-color); max-height: 300px; overflow-y: auto; z-index: 1000; margin-top: 5px; box-shadow: 0 4px 8px rgba(0,0,0,0.15); border-radius: 5px;">';
        
        displayCustomers.forEach((customer, index) => {
            html += `
                <div class="customer-result-item" 
                     data-id="${customer.customer_id}" 
                     data-name="${customer.subscriber_name}"
                     data-account="${customer.account_number}"
                     style="padding: 12px 15px; cursor: pointer; border-bottom: 1px solid #e9ecef; transition: background 0.2s;"
                     onmouseover="this.style.background='#e7f3ff'" 
                     onmouseout="this.style.background='white'">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="color: var(--primary-color); font-size: 14px;">${customer.subscriber_name}</strong><br>
                            <small style="color: #6c757d;">
                                <strong>Account:</strong> ${customer.account_number} | 
                                <strong>Area:</strong> ${customer.area_name || 'N/A'} |
                                <strong>Monthly:</strong> ₱${parseFloat(customer.monthly_fee).toFixed(2)}
                            </small>
                        </div>
                        <div style="background: var(--success-color); color: white; padding: 4px 8px; border-radius: 12px; font-size: 11px;">
                            Select
                        </div>
                    </div>
                </div>
            `;
        });
        
        if (showAll && customers.length > 10) {
            html += `<div style="padding: 10px; text-align: center; background: #f8f9fa; font-size: 12px; color: #666;">
                Showing 10 of ${customers.length} customers. Type to search...
            </div>`;
        }
        
        html += '</div>';
        resultsDiv.innerHTML = html;
        
        // Add click handlers
        document.querySelectorAll('.customer-result-item').forEach(item => {
            item.addEventListener('click', function() {
                selectCustomer(this);
            });
        });
    }
    
    function selectCustomer(item) {
        const customerId = item.getAttribute('data-id');
        const customerName = item.getAttribute('data-name');
        const accountNumber = item.getAttribute('data-account');
        
        searchInput.value = `${customerName} (${accountNumber})`;
        customerIdInput.value = customerId;
        resultsDiv.innerHTML = '';
        
        // Show selected customer
        selectedCustomerName.textContent = `${customerName} - ${accountNumber}`;
        selectedCustomerDiv.style.display = 'block';
        
        // Load billing periods for this customer
        loadBillingPeriods(customerId);
    }
    
    function loadBillingPeriods(customerId) {
        billingPeriodSelect.innerHTML = '<option value="">Loading billing periods...</option>';
        billingPeriodSelect.disabled = true;
        billingInfo.innerHTML = '<em>Loading...</em>';
        
        const xhr = new XMLHttpRequest();
        xhr.open('GET', `ajax/get_customer_billings.php?customer_id=${customerId}`, true);
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const billings = JSON.parse(xhr.responseText);
                    displayBillingPeriods(billings);
                } catch (e) {
                    console.error('Error parsing JSON:', e);
                    billingPeriodSelect.innerHTML = '<option value="">Error loading billing periods</option>';
                    billingInfo.innerHTML = '<span style="color: var(--danger-color);">Error loading billings</span>';
                }
            }
        };
        
        xhr.onerror = function() {
            billingPeriodSelect.innerHTML = '<option value="">Connection error</option>';
            billingInfo.innerHTML = '<span style="color: var(--danger-color);">Connection error</span>';
        };
        
        xhr.send();
    }
    
    function displayBillingPeriods(billings) {
        if (billings.length === 0) {
            billingPeriodSelect.innerHTML = '<option value="">No unpaid bills found</option>';
            billingPeriodSelect.disabled = true;
            billingInfo.innerHTML = '<span style="color: var(--success-color);">✓ All bills are paid!</span>';
            return;
        }
        
        let html = '<option value="">Select billing period to pay</option>';
        let totalUnpaid = 0;
        
        const monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 
                          'July', 'August', 'September', 'October', 'November', 'December'];
        
        billings.forEach(billing => {
            const monthName = monthNames[billing.billing_month];
            const netAmount = parseFloat(billing.net_amount);
            const totalPaid = parseFloat(billing.total_paid || 0);
            const balance = netAmount - totalPaid;
            totalUnpaid += balance;
            
            const statusBadge = billing.status === 'unpaid' ? '🔴 Unpaid' : 
                               billing.status === 'partial' ? '🟡 Partial' : '✅ Paid';
            
            html += `<option value="${billing.billing_id}" data-amount="${balance.toFixed(2)}">
                ${monthName} ${billing.billing_year} - Balance: ₱${balance.toFixed(2)} (${statusBadge})
            </option>`;
        });
        
        billingPeriodSelect.innerHTML = html;
        billingPeriodSelect.disabled = false;
        
        billingInfo.innerHTML = `
            <strong style="color: var(--primary-color);">Total Unpaid:</strong> 
            <span style="color: var(--danger-color); font-size: 14px; font-weight: bold;">₱${totalUnpaid.toFixed(2)}</span> 
            across ${billings.length} billing period(s)
        `;
        
        // Auto-fill amount when billing period is selected
        billingPeriodSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const amount = selectedOption.getAttribute('data-amount');
            if (amount && parseFloat(amount) > 0) {
                document.getElementById('amount_paid').value = amount;
                
                // Show info about selected billing
                const optionText = selectedOption.textContent;
                billingInfo.innerHTML = `
                    <strong>Selected:</strong> ${optionText}<br>
                    <strong>Amount to pay:</strong> <span style="color: var(--success-color); font-weight: bold;">₱${amount}</span>
                `;
            }
        });
    }
    
    // Close search results when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
            setTimeout(() => {
                resultsDiv.innerHTML = '';
            }, 200);
        }
    });
    
    // Prevent form submission if customer not selected
    const paymentForm = document.querySelector('form');
    if (paymentForm) {
        paymentForm.addEventListener('submit', function(e) {
            if (!customerIdInput.value) {
                e.preventDefault();
                alert('Please select a customer first');
                searchInput.focus();
                return false;
            }
            
            if (!billingPeriodSelect.value) {
                e.preventDefault();
                alert('Please select a billing period');
                billingPeriodSelect.focus();
                return false;
            }
        });
    }
});