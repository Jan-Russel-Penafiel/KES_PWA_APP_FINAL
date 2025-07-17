// Table generation functions for reports.php

// Generate Users table
function generateUsersTable(tabContent) {
    let html = '<table>';
    html += '<thead><tr>';
    html += '<th>Name</th>';
    html += '<th>Username</th>';
    html += '<th>Role</th>';
    html += '<th>Email</th>';
    html += '<th>Phone</th>';
    html += '<th>Section</th>';
    html += '<th>Status</th>';
    html += '</tr></thead><tbody>';
    
    // Extract data from each user card
    const cards = tabContent.querySelectorAll('.card');
    if (cards.length === 0) {
        return '<p>No user data available</p>';
    }
    
    cards.forEach(function(card) {
        const name = card.querySelector('.card-title') ? 
            card.querySelector('.card-title').textContent.trim() : 
            (card.querySelector('h5') ? card.querySelector('h5').textContent.trim() : '');
        
        const username = card.querySelector('.text-muted.small') ? 
            card.querySelector('.text-muted.small').textContent.trim() : '';
        
        const roleElement = card.querySelector('.badge');
        const role = roleElement ? roleElement.textContent.trim() : '';
        
        // Extract email, phone, section from list items
        let email = '', phone = '', section = '';
        const listItems = card.querySelectorAll('.list-group-item');
        
        listItems.forEach(function(item) {
            const icon = item.querySelector('i');
            if (icon) {
                if (icon.classList.contains('fa-envelope')) {
                    email = item.textContent.trim();
                } else if (icon.classList.contains('fa-phone')) {
                    phone = item.textContent.trim();
                } else if (icon.classList.contains('fa-users')) {
                    section = item.textContent.trim();
                }
            }
        });
        
        const statusElement = card.querySelector('.card-header .badge:last-child');
        const status = statusElement ? statusElement.textContent.trim() : '';
        
        html += '<tr>';
        html += '<td>' + name + '</td>';
        html += '<td>' + username + '</td>';
        html += '<td>' + role + '</td>';
        html += '<td>' + email + '</td>';
        html += '<td>' + phone + '</td>';
        html += '<td>' + section + '</td>';
        html += '<td>' + status + '</td>';
        html += '</tr>';
    });
    
    html += '</tbody></table>';
    return html;
}

// Generate Sections table
function generateSectionsTable(tabContent) {
    let html = '<table>';
    html += '<thead><tr>';
    html += '<th>Section Name</th>';
    html += '<th>Grade Level</th>';
    html += '<th>Teacher</th>';
    html += '<th>Students</th>';
    html += '<th>Description</th>';
    html += '<th>Status</th>';
    html += '</tr></thead><tbody>';
    
    const cards = tabContent.querySelectorAll('.card');
    if (cards.length === 0) {
        return '<p>No section data available</p>';
    }
    
    cards.forEach(function(card) {
        const sectionName = card.querySelector('.card-title') ? 
            card.querySelector('.card-title').textContent.trim() : '';
        
        const gradeLevel = card.querySelector('.card-header .fw-bold') ? 
            card.querySelector('.card-header .fw-bold').textContent.trim() : '';
        
        // Extract teacher, students, description from list items
        let teacher = '', students = '', description = '';
        const listItems = card.querySelectorAll('.list-group-item');
        
        listItems.forEach(function(item) {
            const icon = item.querySelector('i');
            if (icon) {
                if (icon.classList.contains('fa-chalkboard-teacher')) {
                    teacher = item.textContent.replace('Teacher:', '').trim();
                } else if (icon.classList.contains('fa-user-graduate')) {
                    const badge = item.querySelector('.badge');
                    students = badge ? badge.textContent.trim() : '';
                } else if (icon.classList.contains('fa-info-circle')) {
                    description = item.textContent.trim();
                }
            }
        });
        
        const statusElement = card.querySelector('.card-header .badge');
        const status = statusElement ? statusElement.textContent.trim() : '';
        
        html += '<tr>';
        html += '<td>' + sectionName + '</td>';
        html += '<td>' + gradeLevel + '</td>';
        html += '<td>' + teacher + '</td>';
        html += '<td>' + students + '</td>';
        html += '<td>' + description + '</td>';
        html += '<td>' + status + '</td>';
        html += '</tr>';
    });
    
    html += '</tbody></table>';
    return html;
}

// Generate Attendance table
function generateAttendanceTable(tabContent) {
    let html = '<table>';
    html += '<thead><tr>';
    html += '<th>Date</th>';
    html += '<th>Student</th>';
    html += '<th>Section</th>';
    html += '<th>Time In/Out</th>';
    html += '<th>Status</th>';
    html += '<th>Teacher</th>';
    html += '<th>Remarks</th>';
    html += '</tr></thead><tbody>';
    
    const cards = tabContent.querySelectorAll('.card');
    if (cards.length === 0) {
        return '<p>No attendance data available</p>';
    }
    
    cards.forEach(function(card) {
        const dateElement = card.querySelector('.card-header .small');
        const date = dateElement ? dateElement.textContent.trim() : '';
        
        const studentElement = card.querySelector('h6');
        const student = studentElement ? studentElement.textContent.trim() : '';
        
        const usernameElement = card.querySelector('.text-muted.small');
        const username = usernameElement ? usernameElement.textContent.trim() : '';
        
        // Extract section, time, teacher, remarks from list items
        let section = '', time = '', teacher = '', remarks = '';
        const listItems = card.querySelectorAll('.list-group-item');
        
        listItems.forEach(function(item) {
            const icon = item.querySelector('i');
            if (icon) {
                if (icon.classList.contains('fa-users')) {
                    section = item.textContent.replace('Section:', '').trim();
                } else if (icon.classList.contains('fa-clock')) {
                    time = item.textContent.replace('Time:', '').trim();
                } else if (icon.classList.contains('fa-chalkboard-teacher')) {
                    teacher = item.textContent.replace('Teacher:', '').trim();
                } else if (icon.classList.contains('fa-comment')) {
                    remarks = item.textContent.trim();
                }
            }
        });
        
        const statusElement = card.querySelector('.card-header .badge');
        const status = statusElement ? statusElement.textContent.trim() : '';
        
        html += '<tr>';
        html += '<td>' + date + '</td>';
        html += '<td>' + student + ' (' + username + ')</td>';
        html += '<td>' + section + '</td>';
        html += '<td>' + time + '</td>';
        html += '<td>' + status + '</td>';
        html += '<td>' + teacher + '</td>';
        html += '<td>' + remarks + '</td>';
        html += '</tr>';
    });
    
    html += '</tbody></table>';
    return html;
}

// Generate Students Per Section table
function generateStudentsSectionTable(tabContent) {
    let html = '';
    
    // Process each section
    const sectionCards = tabContent.querySelectorAll('.card.mb-4');
    if (sectionCards.length === 0) {
        return '<p>No section data available</p>';
    }
    
    sectionCards.forEach(function(sectionCard, index) {
        const sectionNameElement = sectionCard.querySelector('.card-header h5');
        const sectionName = sectionNameElement ? sectionNameElement.textContent.trim() : 'Section ' + (index + 1);
        
        const teacherElement = sectionCard.querySelector('.card-header p');
        const teacherName = teacherElement ? teacherElement.textContent.replace('Teacher:', '').trim() : '';
        
        html += '<h3>' + sectionName + '</h3>';
        html += '<p><strong>Teacher:</strong> ' + teacherName + '</p>';
        
        html += '<table>';
        html += '<thead><tr>';
        html += '<th>Name</th>';
        html += '<th>Username</th>';
        html += '<th>LRN</th>';
        html += '<th>Email</th>';
        html += '<th>Phone</th>';
        html += '</tr></thead><tbody>';
        
        // Extract student data from each student card in this section
        const studentCards = sectionCard.querySelectorAll('.card');
        
        if (studentCards.length === 0) {
            html += '<tr><td colspan="5">No students in this section</td></tr>';
        } else {
            studentCards.forEach(function(studentCard) {
                const nameElement = studentCard.querySelector('h6');
                const name = nameElement ? nameElement.textContent.trim() : '';
                
                const usernameElement = studentCard.querySelector('.text-muted');
                const username = usernameElement ? usernameElement.textContent.trim() : '';
                
                // Extract LRN, email, phone from list items
                let lrn = '', email = '', phone = '';
                const listItems = studentCard.querySelectorAll('.list-group-item');
                
                listItems.forEach(function(item) {
                    const icon = item.querySelector('i');
                    if (icon) {
                        if (icon.classList.contains('fa-id-card')) {
                            lrn = item.textContent.replace('LRN:', '').trim();
                        } else if (icon.classList.contains('fa-envelope')) {
                            email = item.textContent.trim();
                        } else if (icon.classList.contains('fa-phone')) {
                            phone = item.textContent.trim();
                        }
                    }
                });
                
                html += '<tr>';
                html += '<td>' + name + '</td>';
                html += '<td>' + username + '</td>';
                html += '<td>' + lrn + '</td>';
                html += '<td>' + email + '</td>';
                html += '<td>' + phone + '</td>';
                html += '</tr>';
            });
        }
        
        html += '</tbody></table>';
        html += '<hr>';
    });
    
    return html;
}

// Generate QR Codes table
function generateQRCodesTable(tabContent) {
    let html = '<table>';
    html += '<thead><tr>';
    html += '<th>Student Name</th>';
    html += '<th>Username</th>';
    html += '<th>LRN</th>';
    html += '<th>Section</th>';
    html += '<th>QR Code</th>';
    html += '</tr></thead><tbody>';
    
    const cards = tabContent.querySelectorAll('.qr-card');
    if (cards.length === 0) {
        return '<p>No QR code data available</p>';
    }
    
    cards.forEach(function(card) {
        const nameElement = card.querySelector('.card-header .fw-bold');
        const name = nameElement ? nameElement.textContent.trim() : '';
        
        const usernameElement = card.querySelector('.card-body .text-muted');
        const username = usernameElement ? usernameElement.textContent.trim() : '';
        
        // Extract LRN and section
        let lrn = '', section = '';
        const strongElements = card.querySelectorAll('.card-body strong');
        
        strongElements.forEach(function(el) {
            if (el.textContent === 'LRN:') {
                lrn = el.parentNode.textContent.replace('LRN:', '').trim();
            } else if (el.textContent === 'Section:') {
                section = el.parentNode.textContent.replace('Section:', '').trim();
            }
        });
        
        // Get QR code
        let qrHtml = '';
        const qrContainer = card.querySelector('.qr-code');
        if (qrContainer) {
            const canvas = qrContainer.querySelector('canvas');
            const img = qrContainer.querySelector('img');
            
            if (canvas) {
                try {
                    qrHtml = '<img src="' + canvas.toDataURL() + '" style="width: 100px; height: 100px;">';
                } catch (e) {
                    qrHtml = '[QR Code]';
                }
            } else if (img) {
                qrHtml = '<img src="' + img.src + '" style="width: 100px; height: 100px;">';
            }
        }
        
        html += '<tr>';
        html += '<td>' + name + '</td>';
        html += '<td>' + username + '</td>';
        html += '<td>' + lrn + '</td>';
        html += '<td>' + section + '</td>';
        html += '<td class="text-center">' + qrHtml + '</td>';
        html += '</tr>';
    });
    
    html += '</tbody></table>';
    return html;
}

// Generate Attendance Per Section table
function generateAttendanceSectionTable(tabContent) {
    let html = '';
    
    // Process each section
    const sectionCards = tabContent.querySelectorAll('.card.mb-4');
    if (sectionCards.length === 0) {
        return '<p>No section attendance data available</p>';
    }
    
    sectionCards.forEach(function(sectionCard, index) {
        const sectionNameElement = sectionCard.querySelector('.card-header h5');
        const sectionName = sectionNameElement ? sectionNameElement.textContent.trim() : 'Section ' + (index + 1);
        
        const teacherElement = sectionCard.querySelector('.card-header p');
        const teacherName = teacherElement ? teacherElement.textContent.replace('Teacher:', '').trim() : '';
        
        html += '<h3>' + sectionName + '</h3>';
        html += '<p><strong>Teacher:</strong> ' + teacherName + '</p>';
        
        // Extract attendance summary
        const totalRecordsElement = sectionCard.querySelector('.display-4');
        const totalRecords = totalRecordsElement ? totalRecordsElement.textContent.trim() : '0';
        
        const presentElement = sectionCard.querySelectorAll('.text-success.mb-0')[0];
        const presentCount = presentElement ? presentElement.textContent.trim() : '0';
        
        const absentElement = sectionCard.querySelectorAll('.text-danger.mb-0')[0];
        const absentCount = absentElement ? absentElement.textContent.trim() : '0';
        
        const lateElement = sectionCard.querySelectorAll('.text-warning.mb-0')[0];
        const lateCount = lateElement ? lateElement.textContent.trim() : '0';
        
        const outElement = sectionCard.querySelectorAll('.text-info.mb-0')[0];
        const outCount = outElement ? outElement.textContent.trim() : '0';
        
        // Summary table
        html += '<table class="mb-4">';
        html += '<tr>';
        html += '<th>Total Records</th>';
        html += '<th>Present</th>';
        html += '<th>Absent</th>';
        html += '<th>Late</th>';
        html += '<th>Out</th>';
        html += '</tr>';
        html += '<tr>';
        html += '<td>' + totalRecords + '</td>';
        html += '<td class="text-success">' + presentCount + '</td>';
        html += '<td class="text-danger">' + absentCount + '</td>';
        html += '<td class="text-warning">' + lateCount + '</td>';
        html += '<td class="text-info">' + outCount + '</td>';
        html += '</tr>';
        html += '</table>';
        
        // Recent records table
        const tableElement = sectionCard.querySelector('table');
        if (tableElement) {
            html += '<h4>Recent Records</h4>';
            html += '<table>';
            html += '<thead><tr>';
            html += '<th>Date</th>';
            html += '<th>Student</th>';
            html += '<th>Status</th>';
            html += '</tr></thead><tbody>';
            
            const rows = tableElement.querySelectorAll('tbody tr');
            if (rows.length === 0) {
                html += '<tr><td colspan="3">No recent records</td></tr>';
            } else {
                rows.forEach(function(row) {
                    const cells = row.querySelectorAll('td');
                    if (cells.length >= 3) {
                        html += '<tr>';
                        html += '<td>' + cells[0].textContent.trim() + '</td>';
                        html += '<td>' + cells[1].textContent.trim() + '</td>';
                        html += '<td>' + cells[2].textContent.trim() + '</td>';
                        html += '</tr>';
                    }
                });
            }
            
            html += '</tbody></table>';
        }
        
        html += '<hr>';
    });
    
    return html;
}

// Generate Attendance Per Student table
function generateAttendanceStudentTable(tabContent) {
    let html = '<table>';
    html += '<thead><tr>';
    html += '<th>Student</th>';
    html += '<th>Section</th>';
    html += '<th>Present</th>';
    html += '<th>Absent</th>';
    html += '<th>Late</th>';
    html += '<th>Out</th>';
    html += '<th>Attendance %</th>';
    html += '</tr></thead><tbody>';
    
    const cards = tabContent.querySelectorAll('.card');
    if (cards.length === 0) {
        return '<p>No student attendance data available</p>';
    }
    
    cards.forEach(function(card) {
        const nameElement = card.querySelector('h6');
        const name = nameElement ? nameElement.textContent.trim() : '';
        
        const usernameElement = card.querySelector('.text-muted.small');
        const username = usernameElement ? usernameElement.textContent.trim() : '';
        
        const sectionElement = card.querySelector('.small.mb-0');
        const section = sectionElement ? sectionElement.textContent.trim() : '';
        
        // Get attendance counts
        const presentElement = card.querySelectorAll('.text-success.mb-0')[0];
        const presentCount = presentElement ? presentElement.textContent.trim() : '0';
        
        const absentElement = card.querySelectorAll('.text-danger.mb-0')[0];
        const absentCount = absentElement ? absentElement.textContent.trim() : '0';
        
        const lateElement = card.querySelectorAll('.text-warning.mb-0')[0];
        const lateCount = lateElement ? lateElement.textContent.trim() : '0';
        
        const outElement = card.querySelectorAll('.text-info.mb-0')[0];
        const outCount = outElement ? outElement.textContent.trim() : '0';
        
        // Calculate attendance percentage
        const present = parseInt(presentCount) || 0;
        const absent = parseInt(absentCount) || 0;
        const late = parseInt(lateCount) || 0;
        const out = parseInt(outCount) || 0;
        const total = present + absent + late + out;
        const percentage = total > 0 ? Math.round((present / total) * 100) : 0;
        
        html += '<tr>';
        html += '<td>' + name + ' (' + username + ')</td>';
        html += '<td>' + section + '</td>';
        html += '<td class="text-success">' + presentCount + '</td>';
        html += '<td class="text-danger">' + absentCount + '</td>';
        html += '<td class="text-warning">' + lateCount + '</td>';
        html += '<td class="text-info">' + outCount + '</td>';
        html += '<td>' + percentage + '%</td>';
        html += '</tr>';
    });
    
    html += '</tbody></table>';
    return html;
}

// Generate SMS Logs table
function generateSMSTable(tabContent) {
    let html = '<table>';
    html += '<thead><tr>';
    html += '<th>Date/Time</th>';
    html += '<th>Phone Number</th>';
    html += '<th>Message</th>';
    html += '<th>Type</th>';
    html += '<th>Status</th>';
    html += '</tr></thead><tbody>';
    
    const cards = tabContent.querySelectorAll('.card');
    if (cards.length === 0) {
        return '<p>No SMS log data available</p>';
    }
    
    cards.forEach(function(card) {
        const dateTimeElement = card.querySelector('.card-header .small');
        const dateTime = dateTimeElement ? dateTimeElement.textContent.trim() : '';
        
        const statusElement = card.querySelector('.card-header .badge');
        const status = statusElement ? statusElement.textContent.trim() : '';
        
        const typeElement = card.querySelector('h6');
        const type = typeElement ? typeElement.textContent.trim() : '';
        
        const phoneElement = card.querySelector('.small.text-muted');
        const phone = phoneElement ? phoneElement.textContent.replace('To:', '').trim() : '';
        
        const messageElement = card.querySelector('.bg-light.p-2 .small');
        const message = messageElement ? messageElement.textContent.trim() : '';
        
        html += '<tr>';
        html += '<td>' + dateTime + '</td>';
        html += '<td>' + phone + '</td>';
        html += '<td>' + message + '</td>';
        html += '<td>' + type + '</td>';
        html += '<td>' + status + '</td>';
        html += '</tr>';
    });
    
    html += '</tbody></table>';
    return html;
}

// Print tab content functionality
document.addEventListener('DOMContentLoaded', function() {
    // Add event listeners to print buttons
    document.querySelectorAll('.print-tab-content').forEach(function(button) {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const tabContent = document.getElementById(targetId);
            
            if (!tabContent) return;
            
            // Create a new window for printing
            const printWindow = window.open('', '_blank');
            const tabTitle = this.closest('.d-flex').querySelector('h5').textContent;
            
            // Create the HTML content with table-based layout
            let html = '<!DOCTYPE html><html><head>';
            html += '<title>' + tabTitle + '</title>';
            html += '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">';
            html += '<style>';
            html += 'body { font-family: Arial, sans-serif; padding: 20px; }';
            html += 'h1 { color: #007bff; margin-bottom: 20px; }';
            html += 'table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }';
            html += 'th { background-color: #f8f9fa; font-weight: bold; text-align: left; padding: 10px; border: 1px solid #dee2e6; }';
            html += 'td { padding: 10px; border: 1px solid #dee2e6; vertical-align: top; }';
            html += 'tr:nth-child(even) { background-color: #f2f2f2; }';
            html += '.text-center { text-align: center; }';
            html += '.text-success { color: #28a745; }';
            html += '.text-danger { color: #dc3545; }';
            html += '.text-warning { color: #ffc107; }';
            html += '.badge { display: inline-block; padding: 0.25em 0.4em; font-size: 75%; font-weight: 700; line-height: 1; text-align: center; white-space: nowrap; vertical-align: baseline; border-radius: 0.25rem; }';
            html += '.badge-success { background-color: #28a745; color: white; }';
            html += '.badge-danger { background-color: #dc3545; color: white; }';
            html += '.badge-warning { background-color: #ffc107; color: black; }';
            html += '.badge-info { background-color: #17a2b8; color: white; }';
            html += '.badge-primary { background-color: #007bff; color: white; }';
            html += '.badge-secondary { background-color: #6c757d; color: white; }';
            html += '@media print {';
            html += '  @page { margin: 0.5cm; }';
            html += '  body { font-size: 12pt; }';
            html += '  h1 { font-size: 18pt; }';
            html += '  th { background-color: #f8f9fa !important; -webkit-print-color-adjust: exact; }';
            html += '  tr:nth-child(even) { background-color: #f2f2f2 !important; -webkit-print-color-adjust: exact; }';
            html += '  .badge-success { background-color: #28a745 !important; color: white !important; -webkit-print-color-adjust: exact; }';
            html += '  .badge-danger { background-color: #dc3545 !important; color: white !important; -webkit-print-color-adjust: exact; }';
            html += '  .badge-warning { background-color: #ffc107 !important; color: black !important; -webkit-print-color-adjust: exact; }';
            html += '  .badge-info { background-color: #17a2b8 !important; color: white !important; -webkit-print-color-adjust: exact; }';
            html += '  .badge-primary { background-color: #007bff !important; color: white !important; -webkit-print-color-adjust: exact; }';
            html += '  .badge-secondary { background-color: #6c757d !important; color: white !important; -webkit-print-color-adjust: exact; }';
            html += '}';
            html += '</style>';
            html += '</head><body>';
            html += '<h1>' + tabTitle + '</h1>';
            html += '<p>Generated on: ' + new Date().toLocaleString() + '</p>';
            
            // Generate table content based on tab type
            switch(targetId) {
                case 'users':
                    html += generateUsersTable(tabContent);
                    break;
                case 'sections':
                    html += generateSectionsTable(tabContent);
                    break;
                case 'attendance':
                    html += generateAttendanceTable(tabContent);
                    break;
                case 'students-section':
                    html += generateStudentsSectionTable(tabContent);
                    break;
                case 'qr-codes':
                    html += generateQRCodesTable(tabContent);
                    break;
                case 'attendance-section':
                    html += generateAttendanceSectionTable(tabContent);
                    break;
                case 'attendance-student':
                    html += generateAttendanceStudentTable(tabContent);
                    break;
                case 'sms':
                    html += generateSMSTable(tabContent);
                    break;
                default:
                    html += '<div class="alert alert-warning">No table format available for this tab.</div>';
            }
            
            html += '<script>';
            html += 'window.onload = function() { setTimeout(function() { window.print(); }, 500); };';
            html += '<\/script>';
            html += '</body></html>';
            
            // Write the HTML to the new window and close the document
            printWindow.document.write(html);
            printWindow.document.close();
        });
    });
}); 