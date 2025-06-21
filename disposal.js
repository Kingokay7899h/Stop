$(document).ready(function() {
    let userRole = 'guest'; // Default fallback

    // Fetch user role
    $.ajax({
        url: 'get_user_role.php',
        type: 'GET',
        dataType: 'json',
        success: function(data) {
            userRole = data.role || 'guest';
            loadDisposals('pending');
            $('.tab-button[data-tab="pending"]').addClass('active');
            $('#pending').addClass('active');
        },
        error: function() {
            showNotification('Failed to fetch user role', 'error');
            loadDisposals('pending');
            $('.tab-button[data-tab="pending"]').addClass('active');
            $('#pending').addClass('active');
        }
    });

    // Tab switching
    $('.tab-button').click(function() {
        $('.tab-button').removeClass('active');
        $(this).addClass('active');
        $('.tab-content').removeClass('active').hide();
        const tabId = $(this).data('tab');
        $(`#${tabId}`).addClass('active').show();
        loadDisposals(tabId);
    });

    // Load disposals
    function loadDisposals(type = 'pending') {
        $(`#${type}Loading`).show();
        $.ajax({
            url: 'fetch_disposals.php',
            type: 'GET',
            data: { type: type },
            dataType: 'json',
            success: function(data) {
                const tableBody = $(`#${type}_disposal_table tbody`);
                tableBody.empty();
                data.forEach(item => {
                    const row = `
                        <tr>
                            <td>${item.id}</td>
                            <td>${item.name_of_item} (${item.item_specification || 'N/A'})</td>
                            <td>${item.status}</td>
                            <td>${item.condemnation_reason || 'N/A'}</td>
                            <td>${item.created_at}</td>
                            <td>
                                <button onclick="viewDisposal(${item.id}, '${type}')">View</button>
                                ${type === 'pending' && (userRole === 'store' || userRole === 'principal') ? 
                                    `<button onclick="reviewDisposal(${item.id})">Review</button>` : 
                                    type === 'pending' && userRole === 'store' && item.status === 'Approved by Committee' ? 
                                    `<button onclick="disposeItem(${item.id})">Dispose</button>` : ''}
                            </td>
                        </tr>`;
                    tableBody.append(row);
                });
                $(`#${type}Loading`).hide();
            },
            error: function() {
                showNotification('Failed to load disposals', 'error');
                $(`#${type}Loading`).hide();
            }
        });
    }

    // View disposal form
    window.viewDisposal = function(id, type) {
        $.ajax({
            url: 'fetch_disposal_form.php',
            type: 'GET',
            data: { id: id, type: type },
            dataType: 'json',
            success: function(data) {
                if (data.error) {
                    showNotification(data.error, 'error');
                } else {
                    // Open a new window or modal with form data (implement as needed)
                    let formHtml = '<h2>Disposal Form</h2><ul>';
                    data.items.forEach(item => {
                        formHtml += `<li>${item.description} - Status: ${data.status}</li>`;
                    });
                    formHtml += '</ul>';
                    const win = window.open('', '_blank');
                    win.document.write('<html><body>' + formHtml + '</body></html>');
                }
            },
            error: function() {
                showNotification('Failed to load disposal form', 'error');
            }
        });
    };

    // Open review modal
    window.reviewDisposal = function(id) {
        $('#requestId').val(id);
        $('#rejection_reason').val('');
        $('#reviewModal').show();
    };

    // Dispose item
    window.disposeItem = function(id) {
        if (confirm('Are you sure you want to mark this item as disposed?')) {
            $.ajax({
                url: 'dispose_approved.php',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ requestId: id }),
                success: function(response) {
                    if (response.status === 'success') {
                        loadDisposals('pending');
                        loadDisposals('past');
                        showNotification('Item disposed successfully', 'success');
                    } else {
                        showNotification(response.message, 'error');
                    }
                },
                error: function() {
                    showNotification('Failed to dispose item', 'error');
                }
            });
        }
    };

    // Review form submission
    $('#reviewForm').submit(function(e) {
        e.preventDefault();
        const data = {
            requestId: $('#requestId').val(),
            action: $('input[name="action"]:checked').val(),
            rejection_reason: $('#rejection_reason').val()
        };
        const url = userRole === 'store' ? 'stores_review.php' : 'principal_review.php';
        $.ajax({
            url: url,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
            success: function(response) {
                if (response.status === 'success') {
                    $('#reviewModal').hide();
                    loadDisposals('pending');
                    loadDisposals('rejected');
                    showNotification('Review submitted successfully', 'success');
                } else {
                    showNotification(response.message, 'error');
                }
            },
            error: function() {
                showNotification('Failed to submit review', 'error');
            }
        });
    });

    // Close modals
    $('.close').click(function() {
        $(this).closest('.modal').hide();
    });

    // Notification function
    function showNotification(message, type) {
        const notification = $('#notification');
        notification.html(message).removeClass('success error').addClass(type).show();
        setTimeout(() => notification.hide(), 10000);
    }
});