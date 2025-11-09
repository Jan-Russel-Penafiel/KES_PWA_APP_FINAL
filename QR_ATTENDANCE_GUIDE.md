# QR Code Attendance System

## Overview
The QR Code Attendance System allows teachers to generate QR codes for their subjects and sections, which students can scan to automatically record their attendance.

## How It Works

### For Teachers:
1. **Login** to the dashboard as a teacher
2. **Navigate** to the Teacher Dashboard
3. **Find** the "Attendance QR Code Generator" section
4. **Select** a subject and section from the dropdown menus
5. **Click** "Generate QR Code" to create a unique QR code
6. **Display** the QR code to students (on screen, printed, or projected)
7. **Students scan** the QR code to record their attendance

### For Students:
1. **Login** to the dashboard as a student
2. **Navigate** to the Student Dashboard
3. **Find** the "QR Attendance Scanner" section
4. **Click** "Start Scanner" to activate the camera
5. **Point** the camera at the teacher's QR code
6. **Wait** for the scan confirmation
7. **Attendance** is automatically recorded

## Features

### Teacher Features:
- **Subject/Section Selection**: Choose specific subject and section for attendance
- **QR Code Generation**: Create unique QR codes for each attendance session
- **Download/Print Options**: Save or print QR codes for classroom display
- **Session Management**: Track active attendance sessions
- **Attendance Analytics**: View real-time attendance data

### Student Features:
- **Camera Scanner**: Use device camera to scan teacher QR codes
- **Automatic Recording**: Attendance is recorded instantly upon scanning
- **Recent Scans**: View history of recent attendance scans
- **SMS Notifications**: Parents receive automatic SMS when attendance is recorded
- **Time-based Status**: Automatically marked as "present" or "late" based on scan time

## Technical Details

### QR Code Data Structure:
```json
{
    "teacher_id": 123,
    "teacher_name": "John Doe",
    "subject_id": 456,
    "subject_name": "Mathematics",
    "section_id": 789,
    "section_name": "Grade 7-A",
    "session_id": "session_1234567890",
    "created_at": "2024-01-01T10:00:00Z",
    "expires_at": "2024-01-02T10:00:00Z"
}
```

### Attendance Rules:
- **Present**: Scan before 7:15 AM
- **Late**: Scan between 7:15 AM and 4:15 PM
- **Checkout**: Second scan for students who already checked in
- **Cutoff**: No attendance recording after 4:15 PM

### Security Features:
- **Student Enrollment Verification**: Only enrolled students can record attendance
- **Section Validation**: Students must belong to the correct section
- **Teacher Authorization**: Only assigned teachers can create QR codes
- **Session Tracking**: Each QR code has a unique session ID
- **Duplicate Prevention**: Students cannot record multiple attendances for the same subject/day

## Database Structure

### Key Tables:
- `attendance`: Stores attendance records with subject_id
- `student_subjects`: Manages student enrollment in subjects
- `subjects`: Contains subject information and teacher assignments
- `sections`: Manages class sections and grade levels
- `qr_scans`: Logs all QR code scans for auditing

## Installation & Setup

1. **Run the database migration**:
   ```sql
   SOURCE database_qr_attendance_update.sql;
   ```

2. **Ensure students are enrolled in subjects**:
   - Check the `student_subjects` table
   - Students must be enrolled to record attendance

3. **Configure SMS notifications** (optional):
   - Set up SMS configuration in admin panel
   - Parents will receive automatic notifications

## Usage Tips

### For Teachers:
- Generate new QR codes for each class session
- Display QR codes prominently for easy scanning
- Keep QR codes active only during class time
- Monitor attendance in real-time through the dashboard

### For Students:
- Ensure good lighting when scanning
- Hold the device steady during scanning
- Scan as soon as you arrive to avoid being marked late
- Check recent scans to confirm attendance was recorded

### For Administrators:
- Monitor attendance patterns through reports
- Set up parent SMS notifications for better communication
- Regular database backups to preserve attendance data
- Train teachers and students on proper QR code usage

## Troubleshooting

### Common Issues:
1. **Camera not working**: Check browser permissions for camera access
2. **QR not scanning**: Ensure good lighting and stable positioning
3. **Attendance not recorded**: Verify student enrollment in the subject
4. **Late status**: Check if scan time is after 7:15 AM threshold

### Error Messages:
- "Student not enrolled": Contact admin to enroll in subject
- "Invalid QR code": Use only teacher-generated QR codes
- "Attendance already recorded": Student has already checked in today
- "Recording period ended": Cannot scan after 4:15 PM

## Support
For technical support or questions about the QR attendance system, contact the school administrator or IT support team.