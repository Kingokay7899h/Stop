$(document).ready(function() {
    let userRole = '';
    let selectedItems = [];

    // Fetch user role
    $.ajax({
        url: 'get_user_role.php',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            userRole = data.role;
            loadMaintenanceTable();
            loadAlertTable();
        },
        error: function() {
            showNotification('Failed to fetch user role', 'error');
            loadMaintenanceTable();
            loadAlertTable();
        }
    });

    // Load maintenance table
    function loadMaintenanceTable() {
        $('#maintenanceLoading').show();
        $.ajax({
            url: 'display_maintenance_table.php',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                const tableBody = $('#maintenanceTable tbody');
                tableBody.empty();
                data.forEach(item => {
                    const row = `
                        <tr>
                            <td><input type="checkbox" class="select-item" value="${item.sr_no}"></td>
                            <td>${item.sr_no}</td>
                            <td>${item.name_of_the_item}</td>
                            <td>${item.item_specification}</td>
                            <td>${item.procurement_date || 'N/A'}</td>
                            <td>${item.cost || 'N/A'}</td>
                            <td>${item.last_maintenance || 'N/A'}</td>
                            <td>${item.maintainence_due || 'N/A'}</td>
                            <td>${item.service_provider || 'N/A'}</td>
                            <td>${item.lab_name}</td>
                            <td>${item.category_name}</td>
                            <td>${item.disposal_status || 'N/A'}</td>
                            <td><button onclick="openMaintenanceModal(${item.sr_no}, '${item.last_maintenance || ''}', '${item.maintainence_due || ''}', '${item.service_provider || ''}')">Update</button></td>
                        </tr>`;
                    tableBody.append(row);
                });
                $('#maintenanceLoading').hide();
            },
            error: function() {
                showNotification('Failed to load maintenance data', 'error');
                $('#maintenanceLoading').hide();
            }
        });
    }

    // Load alert table
    function loadAlertTable() {
        $('#alertLoading').show();
        $.ajax({
            url: 'display_maintenance_alerts.php',
            type: 'GET',
            dataType: 'json',
            success: function(data) {
                const tableBody = $('#alertTable tbody');
                tableBody.empty();
                data.forEach(item => {
                    const row = `
                        <tr>
                            <td>${item.sr_no}</td>
                            <td>${item.name_of_the_item}</td>
                            <td>${item.item_specification}</td>
                            <td>${item.last_maintenance || 'N/A'}</td>
                            <td>${item.maintainence_due || 'N/A'}</td>
                            <td>${item.service_provider || 'N/A'}</td>
                            <td>${item.lab_name}</td>
                            <td>${item.category_name}</td>
                            <td>${item.status}</td>
                        </tr>`;
                    tableBody.append(row);
                });
                $('#alertLoading').hide();
            },
            error: function() {
                showNotification('Failed to load alerts', 'error');
                $('#alertLoading').hide();
            }
        });
    }

    // Select all checkboxes
    $('#selectAll').click(function() {
        $('.select-item').prop('checked', this.checked);
        selectedItems = [];
        $('.select-item:checked').each(function() {
            selectedItems.push($(this).val());
        });
    });

    // Update selected items on checkbox change
    $(document).on('change', '.select-item', function() {
        selectedItems = [];
        $('.select-item:checked').each(function() {
            selectedItems.push($(this).val());
        });
    });

    // Open maintenance update modal
    function openMaintenanceModal(sr_no, last_maintenance, maintainence_due, service_provider) {
        $('#sr_no').val(sr_no);
        $('#last_maintenance').val(last_maintenance || '');
        $('#maintainence_due').val(maintainence_due || '');
        $('#service_provider').val(service_provider || '');
        $('#maintenanceModal').show();
    }

    // Close modals
    $('.close').click(function() {
        $(this).closest('.modal').hide();
    });

    // Update maintenance form submission
    $('#maintenanceForm').submit(function(e) {
        e.preventDefault();
        const last_maintenance = $('#last_maintenance').val();
        const maintainence_due = $('#maintainence_due').val();
        if (!last_maintenance || !maintainence_due) {
            showNotification('Please fill all required fields', 'error');
            return;
        }
        const data = {
            sr_no: $('#sr_no').val(),
            last_maintenance: last_maintenance,
            maintainence_due: maintainence_due,
            service_provider: $('#service_provider').val()
        };
        $.ajax({
            url: 'update_maintenance.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
            success: function(response) {
                if (response.status === 'success') {
                    $('#maintenanceModal').hide();
                    loadMaintenanceTable();
                    loadAlertTable();
                    showNotification('Maintenance updated successfully', 'success');
                } else {
                    showNotification(response.message, 'error');
                }
            },
            error: function() {
                showNotification('Failed to update maintenance', 'error');
            }
        });
    });

    // Send disposal request
    $('#sendDisposalRequest').click(function() {
        if (selectedItems.length === 0) {
            showNotification('Please select at least one item', 'error');
            return;
        }
        $.ajax({
            url: 'check_disposal_status.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ sr_nos: selectedItems }),
            success: function(response) {
                if (response.status === 'success') {
                    if (selectedItems.length === 1) {
                        $('#disposalModal').show();
                    } else {
                        window.location.href = `condemnation_form.html?sr_nos=${selectedItems.join(',')}`;
                    }
                } else {
                    const invalidItems = response.invalid_items.map(item => 
                        `SR No ${item.sr_no}: ${item.name} (${item.status} - ${item.reason})`).join('<br>');
                    showNotification(`Cannot proceed: <br>${invalidItems}`, 'error');
                }
            },
            error: function() {
                showNotification('Failed to check disposal status', 'error');
            }
        });
    });

    // Disposal form submission
    $('#disposalForm').submit(function(e) {
        e.preventDefault();
        const reason = $('#disposal_reason').val();
        if (!reason) {
            showNotification('Please provide a reason for disposal', 'error');
            return;
        }
        window.location.href = `condemnation_form.html?sr_nos=${selectedItems[0]}&reason=${encodeURIComponent(reason)}`;
    });

    // Notification function
    function showNotification(message, type) {
        const notification = $('#notification');
        notification.html(message).removeClass('success error').addClass(type).show();
        setTimeout(() => notification.hide(), 10000);
    }
});