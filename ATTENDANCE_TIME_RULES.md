# Attendance Time Rules and Logic

## Overview
The KES-SMART Attendance System implements time-based attendance tracking with automatic status assignment and restrictions.

## Time Boundaries

### 1. Early Hours (Before 6:00 AM)
- **Status**: Scanning/Input DISABLED
- **Message**: "Attendance recording not yet available (starts at 6:00 AM)"
- **Action**: Students cannot check in

### 2. On-Time Period (6:00 AM - 7:15 AM)
- **Status**: PRESENT
- **Message**: "Students will be marked as PRESENT"
- **Action**: Students checking in during this period are marked as present

### 3. Late Period (7:16 AM - 4:15 PM)
- **Status**: LATE
- **Message**: "Students will be marked as LATE"
- **Action**: Students checking in during this period are marked as late

### 4. After Hours (After 4:15 PM)
- **Status**: Scanning/Input DISABLED
- **Message**: "Attendance recording closed. Auto-absent marking will run soon."
- **Action**: Students cannot check in, system prepares for auto-absent marking

## Automatic Absent Marking

### Process
1. **Trigger**: Daily at 4:16 PM (Monday-Friday only)
2. **Target**: Students who haven't checked in for the day
3. **Action**: Automatically marked as "absent"
4. **Notification**: SMS sent to parents about absence
5. **Logging**: All actions logged for audit trail

### Technical Implementation
- **Script**: `/cron/mark_absent.php`
- **Scheduler**: Windows Task Scheduler (via `run_auto_absent.bat`)
- **Frequency**: Daily at 4:16 PM, weekdays only
- **Safety**: Prevents duplicate runs on the same day

## User Interface Features

### Real-Time Status Display
- Shows current time and attendance status
- Updates every minute automatically
- Color-coded alerts:
  - **Gray**: Before 6:00 AM (disabled)
  - **Green**: 6:00 AM - 7:15 AM (present)
  - **Yellow**: 7:16 AM - 4:15 PM (late)
  - **Red**: After 4:15 PM (disabled)

### Button State Management
- Scanner and manual input buttons disabled during restricted hours
- Visual feedback with appropriate icons and text
- Automatic re-enabling when time restrictions lift

## Error Handling

### Client-Side Validation
- JavaScript checks time before allowing submissions
- Immediate feedback to users
- Prevents unnecessary server requests

### Server-Side Validation
- PHP validates time on every attendance request
- Throws appropriate exceptions for invalid times
- Consistent error messages

## SMS Notifications

### Check-In Notifications
- Sent only once per day per student
- Different messages for present vs. late status
- Includes student name, time, date, and section

### Absence Notifications
- Sent automatically at 4:16 PM for absent students
- Explains reason (no check-in by 4:15 PM)
- Includes contact information for queries

## Configuration

### Time Settings (in PHP)
```php
$checkin_start = '06:00:00';   // 6:00 AM - earliest check-in
$late_threshold = '07:15:00';  // 7:15 AM - late cutoff
$absent_cutoff = '16:15:00';   // 4:15 PM - end of day
```

### Cron Schedule
- **Linux**: `16 16 * * 1-5 /usr/bin/php /path/to/smart/cron/mark_absent.php`
- **Windows**: Task Scheduler set to run daily at 4:16 PM, weekdays only

## Troubleshooting

### Common Issues
1. **Auto-absent not running**: Check Task Scheduler/cron configuration
2. **Time display incorrect**: Verify server timezone settings
3. **Students marked incorrectly**: Check system clock synchronization
4. **SMS not sending**: Verify SMS configuration in settings

### Logs
- **Auto-absent**: `/logs/auto_absent.log`
- **Batch execution**: `/logs/batch_execution.log`
- **Errors**: `/logs/auto_absent_errors.log`

## Business Rules Summary

1. **6:00 AM or earlier**: No attendance allowed
2. **6:01 AM - 7:15 AM**: Present status
3. **7:16 AM - 4:15 PM**: Late status
4. **4:16 PM or later**: No attendance allowed, auto-absent runs
5. **All day no check-in**: Automatically marked absent at 4:16 PM

This system ensures accurate attendance tracking while providing flexibility for different arrival times and maintaining data integrity through automated processes.