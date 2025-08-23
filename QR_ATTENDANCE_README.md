# QR Code Attendance System - Implementation Guide

This document describes the new QR code attendance functionality added to the KES-SMART system.

## Overview

The QR code attendance system allows:
- **Teachers** to generate QR codes for specific subjects and sections
- **Students** to scan teacher QR codes to automatically mark their attendance
- **Real-time attendance tracking** with SMS notifications to parents
- **Enhanced security** with QR code validation and cooldown periods

## Features

### For Teachers
1. **QR Code Generation**: Generate unique QR codes for each subject/section combination
2. **Session Management**: QR codes are valid for 24 hours and tracked by session
3. **Print/Download**: Teachers can print or download QR codes for classroom display
4. **Real-time Monitoring**: View scan statistics on the dashboard

### For Students
1. **QR Scanner**: Built-in camera-based QR code scanner in the dashboard
2. **Automatic Attendance**: Scanning teacher QR codes automatically marks attendance as "present"
3. **Auto-enrollment**: Students are automatically enrolled in subjects when they first scan
4. **Feedback**: Immediate feedback on successful scans or errors

### System Features
1. **Duplicate Prevention**: Students cannot mark attendance twice for the same subject on the same day
2. **Cooldown Protection**: 5-minute cooldown between scans for the same student-subject combination
3. **SMS Notifications**: Parents receive automatic SMS when students mark attendance
4. **Comprehensive Logging**: All QR scans are logged with device info and location
5. **Enhanced Reporting**: Attendance source tracking (manual, QR scan, auto)

## Database Changes

### New Tables
- `teacher_qr_sessions`: Tracks active QR code sessions for teachers
- Enhanced `attendance` table with source tracking and scan location
- Enhanced `qr_scans` table with result tracking and session linking

### New Views
- `attendance_summary`: Comprehensive view combining attendance with user and subject data

### New System Settings
- `qr_session_duration`: QR code validity period (default: 24 hours)
- `auto_mark_absent`: Auto-mark absent students (default: enabled)
- `attendance_grace_period`: Grace period for late marking (default: 30 minutes)
- `qr_scan_cooldown`: Cooldown between scans (default: 5 minutes)

## File Structure

```
/api/
  process-attendance-qr.php     # API endpoint for processing QR attendance
dashboard.php                   # Updated with teacher QR generation and student scanner
qr_helpers.php                  # QR code utility functions
config.php                      # Updated to include QR helpers
migration_qr_attendance_system.sql  # Database migration script
install-qr-system.php          # Installation script
```

## Installation

1. **Run the installation script**:
   ```
   http://your-domain/smart/install-qr-system.php
   ```

2. **Or manually execute the migration**:
   ```sql
   -- Import the migration file
   mysql -u username -p database_name < migration_qr_attendance_system.sql
   ```

3. **Verify installation** by checking that all new tables and columns exist

## Usage Instructions

### For Teachers

1. **Navigate to Dashboard**: Log in as a teacher and go to the dashboard
2. **Select Subject and Section**: Choose from the dropdowns in the "Generate Attendance QR Code" section
3. **Generate QR Code**: Click "Generate QR Code" to create a unique QR code
4. **Display QR Code**: Show the QR code to students (on screen, printed, or projected)
5. **Monitor Scans**: Watch the "Today's QR Scans" counter for real-time feedback

#### QR Code Management
- QR codes are valid for 24 hours
- Each teacher-section-subject combination gets a unique QR code
- QR codes can be regenerated at any time
- Print or download QR codes for offline use

### For Students

1. **Navigate to Dashboard**: Log in as a student and go to the dashboard
2. **Open QR Scanner**: Click "Open QR Scanner" in the attendance section
3. **Scan Teacher QR Code**: Point camera at teacher's QR code
4. **Confirm Attendance**: Wait for confirmation message
5. **Check Status**: Attendance is immediately marked as "present"

#### Scanner Features
- Automatic QR code detection
- Real-time feedback on scan results
- Error handling for invalid QR codes
- Auto-close after successful scan

### For Administrators

1. **Monitor QR Usage**: View QR scan statistics in reports
2. **Configure Settings**: Adjust QR system settings in the settings page
3. **Manage Sessions**: View active QR sessions in the database
4. **Attendance Reports**: Enhanced reports with QR source tracking

## API Endpoints

### POST /api/process-attendance-qr.php

Processes QR code attendance marking.

**Request Body**:
```json
{
  "teacher_qr_data": "base64-encoded-teacher-qr",
  "student_qr_data": "base64-encoded-student-qr"
}
```

**Response**:
```json
{
  "success": true,
  "message": "Attendance marked successfully",
  "action": "created|updated",
  "attendance_data": {
    "student_id": 123,
    "student_name": "John Doe",
    "teacher_name": "Jane Smith",
    "section_name": "Grade 7A",
    "subject_name": "Mathematics",
    "time_in": "09:30:00",
    "date": "2025-08-19",
    "status": "present"
  }
}
```

## QR Code Format

### Teacher QR Codes
```
KES-SMART-TEACHER-{teacher_id}-{section_id}-{subject_id}-{timestamp}
```
Example: `KES-SMART-TEACHER-2-1-5-1692434400`

### Student QR Codes
```
KES-SMART-STUDENT-{student_id}-{year}
```
Example: `KES-SMART-STUDENT-3-2025`

## Security Features

1. **QR Code Validation**: All QR codes are validated for format and authenticity
2. **User Permission Checks**: Only authenticated users can mark attendance
3. **Data Integrity**: Cross-references student enrollment and teacher assignments
4. **Cooldown Protection**: Prevents spam scanning with time-based cooldowns
5. **Session Tracking**: All scans are logged with timestamps and device information

## Error Handling

Common error scenarios and their handling:

- **Invalid QR Code**: Clear error message with format requirements
- **Expired QR Code**: Prompts to request new QR code from teacher
- **Duplicate Attendance**: Prevents double-marking with informative message
- **Network Issues**: Graceful degradation with offline capability
- **Permission Denied**: Clear access control messages

## SMS Integration

When students mark attendance via QR scan:
1. System checks if SMS notifications are enabled
2. Locates primary parent contact information
3. Sends formatted SMS with attendance details
4. Logs SMS delivery status

**SMS Message Format**:
```
Hi! Your child [Student Name] has been marked present for [Subject] at [Time] on [Date]. Section: [Section Name]. - KES-SMART
```

## Performance Considerations

1. **Database Indexing**: Optimized indexes for attendance queries
2. **QR Code Caching**: Efficient QR code generation and storage
3. **Session Management**: Automatic cleanup of expired sessions
4. **Mobile Optimization**: Responsive design for mobile scanning

## Troubleshooting

### Common Issues

1. **Camera Not Working**: Check browser permissions and HTTPS requirement
2. **QR Code Not Scanning**: Ensure good lighting and steady camera
3. **Attendance Not Marking**: Check network connection and QR code validity
4. **SMS Not Sending**: Verify SMS configuration in system settings

### Developer Debug

Enable debug mode by adding to config.php:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

Check logs in:
- Browser console for JavaScript errors
- PHP error logs for server-side issues
- Database logs for query performance

## Future Enhancements

Potential improvements for future versions:

1. **Geolocation Verification**: Ensure students are on campus when scanning
2. **Batch QR Generation**: Generate multiple QR codes for different time slots
3. **Advanced Analytics**: Detailed reports on QR usage patterns
4. **Integration with LMS**: Connect with learning management systems
5. **Offline Sync**: Store scans offline and sync when online

## Support

For technical support or questions:
1. Check the installation logs in `install-qr-system.php`
2. Review the database migration results
3. Test functionality with sample QR codes
4. Contact system administrator for configuration issues

---

**Last Updated**: August 19, 2025
**Version**: 2.0
**Compatibility**: PHP 7.4+, MySQL 5.7+, Modern browsers with camera support
