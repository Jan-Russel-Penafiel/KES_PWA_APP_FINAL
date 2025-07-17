# KES-SMART Attendance System Documentation

## Attendance Rules and Time Restrictions

### Time-Based Attendance Status

1. **Present (Before 7:15 AM)**
   - Students who scan their QR code before 7:15 AM are marked as "Present"
   - SMS notification sent to parents: "Your child [Name] has arrived at school at [Time]"

2. **Late (After 7:15 AM)**
   - Students who scan their QR code after 7:15 AM are marked as "Late"
   - SMS notification sent to parents: "Your child [Name] has arrived late at school at [Time]"

3. **Absent (Auto-marked)**
   - Students who don't check in by 4:15 PM are automatically marked as "Absent"
   - This is handled by the automated script in `cron/mark_absent.php`

4. **Out (Early Departure)**
   - Students who check out before 4:15 PM are marked as "Out"
   - SMS notification sent to parents: "Your child [Name] has left school early at [Time]"

5. **Normal Checkout (After 4:15 PM)**
   - Students who check out after 4:15 PM maintain their original status (Present/Late)
   - SMS notification sent to parents: "Your child [Name] has left school at [Time]"

### Dual Attendance Recording

The system records attendance twice per day:

1. **Check-in (Morning)**
   - Time window: Start of day until 4:15 PM
   - Status: Present (before 7:15 AM) or Late (after 7:15 AM)
   - Records `time_in` field

2. **Check-out (Afternoon)**
   - Time window: After first check-in until end of day
   - Status: Out (before 4:15 PM) or maintains original status (after 4:15 PM)
   - Records `time_out` field

### SMS Notification Rules

1. **Check-in SMS** - Sent only once per day on first scan
2. **Check-out SMS** - Always sent regardless of previous SMS
3. **No duplicate check-in SMS** - If already sent, shows "SMS already sent today"

### Database Status Values

- `present` - Student arrived on time (before 7:15 AM)
- `late` - Student arrived late (after 7:15 AM)
- `absent` - Student didn't check in by 4:15 PM (auto-marked)
- `out` - Student left early (before 4:15 PM)
- `excused` - Manual status for special circumstances

### Error Handling

- **After 4:15 PM first scan**: "Attendance recording period has ended"
- **Double checkout**: "Student has already checked in and out today"
- **Teacher permissions**: Teachers can only scan students from their assigned sections

### Automated Absent Marking

The system includes an automated script that should run daily at 4:16 PM:

#### For Linux/Unix (Crontab)
```bash
16 16 * * 1-5 /usr/bin/php /path/to/smart/cron/mark_absent.php
```

#### For Windows (Task Scheduler)
1. Open Task Scheduler
2. Create Basic Task
3. Set trigger: Daily at 4:16 PM, weekdays only
4. Set action: Start program `C:\xampp\htdocs\smart\cron\run_auto_absent.bat`

### File Structure

- `qr-scanner.php` - Main QR scanner interface with attendance logic
- `cron/mark_absent.php` - Automated absent marking script
- `cron/run_auto_absent.bat` - Windows batch file for scheduled task
- `logs/` - Directory for system logs

### Configuration

Time thresholds can be modified in `qr-scanner.php`:
```php
$late_threshold = '07:15:00';  // 7:15 AM
$absent_cutoff = '16:15:00';   // 4:15 PM
```

### Features

- **Real-time scanning** with camera or manual input
- **Time-based status determination**
- **Duplicate prevention** for SMS notifications
- **Checkout tracking** with early departure detection
- **Automatic absent marking** for no-shows
- **Permission-based access** (teachers see only their sections)
- **Visual feedback** with status-colored cards and badges
- **SMS integration** with parent notifications
- **Attendance history** display in card format

This system ensures comprehensive attendance tracking with appropriate notifications and automated processes to maintain accurate records.
