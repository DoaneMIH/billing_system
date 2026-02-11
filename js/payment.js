// Payments Page - Customer Search and Billing Period Loading

let searchTimeout;

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('search_customer');
    const resultsDiv = document.getElementById('customer_results');
    const customerIdInput = document.getElementById('customer_id');
    const billingPeriodSelect = document.getElementById('billing_period');
    
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                resultsDiv.innerHTML = '';
                customerIdInput.value = '';
                billingPeriodSelect.innerHTML = '<option value="">Select billing period</option>';
                return;
            }
            
            searchTimeout = setTimeout(() => {
                searchCustomers(query);
            }, 300);
        });
    }
    
    function searchCustomers(query) {
        // Create AJAX request
        const xhr = new XMLHttpRequest();
        xhr.open('GET', `ajax/search_customers.php?q=${encodeURIComponent(query)}`, true);
        
        xhr.onload = function() {
            if (xhr.status === 200) {
                try {
                    const customers = JSON.parse(xhr.responseText);
                    displayCustomerResults(customers);
                } catch (e) {
                    console.error('Error parsing JSON:', e);
                }
            }
        };
        
        xhr.send();
    }
    
    function displayCustomerResults(customers) {
        if (customers.length === 0) {
            resultsDiv.innerHTML = '<div style="padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; margin-top: 5px;">No customers found</div>';
            return;
        }
        
        let html = '<div style="position: absolute; width: 100%; background: white; border: 1px solid #dee2e6; max-height: 200px; overflow-y: auto; z-index: 1000; margin-top: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
        
        customers.forEach(customer => {
            html += `
                <div class="customer-result-item" 
                     data-id="${customer.customer_id}" 
                     data-name="${customer.subscriber_name}"
                     data-account="${customer.account_number}"
                     style="padding: 10px; cursor: pointer; border-bottom: 1px solid #dee2e6;">
                    <strong>${customer.subscriber_name}</strong><br>
                    <small>Account: ${customer.account_number} | ${customer.address}</small>
                </div>
            `;
        });
        
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
        
        // Load billing periods for this customer
        loadBillingPeriods(customerId);
    }
    
    function loadBillingPeriods(customerId) {
        billingPeriodSelect.innerHTML = '<option value="">Loading...</option>';
        
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
                }
            }
        };
        
        xhr.send();
    }
    
    function displayBillingPeriods(billings) {
        if (billings.length === 0) {
            billingPeriodSelect.innerHTML = '<option value="">No unpaid bills found</option>';
            return;
        }
        
        let html = '<option value="">Select billing period</option>';
        
        billings.forEach(billing => {
            const monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 
                              'July', 'August', 'September', 'October', 'November', 'December'];
            const monthName = monthNames[billing.billing_month];
            const amount = parseFloat(billing.net_amount).toFixed(2);
            const paid = parseFloat(billing.total_paid || 0).toFixed(2);
            const balance = (parseFloat(billing.net_amount) - parseFloat(billing.total_paid || 0)).toFixed(2);
            
            html += `<option value="${billing.billing_id}" data-amount="${balance}">
                ${monthName} ${billing.billing_year} - Balance: ₱${balance} (Status: ${billing.status})
            </option>`;
        });
        
        billingPeriodSelect.innerHTML = html;
        
        // Auto-fill amount when billing period is selected
        billingPeriodSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const amount = selectedOption.getAttribute('data-amount');
            if (amount) {
                document.getElementById('amount_paid').value = amount;
            }
        });
    }
    
    // Close search results when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target !== searchInput && e.target !== resultsDiv) {
            resultsDiv.innerHTML = '';
        }
    });
});