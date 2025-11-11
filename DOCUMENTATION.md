# KES-SMART: Student Monitoring Application Documentation

## Table of Contents
1. [System Overview](#system-overview)
2. [System Architecture](#system-architecture)
3. [Features](#features)
4. [Database Schema](#database-schema)
5. [User Roles & Permissions](#user-roles--permissions)
6. [Installation Guide](#installation-guide)
7. [User Manual](#user-manual)
8. [API Documentation](#api-documentation)
9. [Technical Specifications](#technical-specifications)
10. [Security Features](#security-features)
11. [Troubleshooting](#troubleshooting)
12. [Contributing](#contributing)

---

## System Overview

**KES-SMART** (KES Student Monitoring Application with Real-time QR and SMS) is a comprehensive web-based Progressive Web Application (PWA) designed for efficient student attendance monitoring and management. The system integrates QR code technology with automated SMS notifications to provide real-time attendance tracking for schools.

### Key Highlights
- **Multi-role support**: Admin, Teachers, Students, and Parents
- **QR-based attendance**: Real-time scanning and processing
- **SMS notifications**: Automated parent alerts
- **Progressive Web App**: Offline functionality and mobile optimization
- **Auto-evaluation**: Intelligent attendance analysis
- **Real-time reports**: Comprehensive analytics and insights

---

## System Architecture

### Technology Stack
- **Backend**: PHP 8.x with MySQL/MariaDB
- **Frontend**: HTML5, CSS3, JavaScript (ES6+), Bootstrap 5
- **QR Generation**: Endroid QR Code Library
- **PDF Generation**: TCPDF Library
- **SMS Integration**: IPROG SMS API
- **PWA**: Service Worker, Web App Manifest
- **Real-time Features**: AJAX, WebSockets-ready architecture

### Directory Structure
```
smart/
├── api/                    # API endpoints
├── assets/                 # Static resources
│   ├── css/               # Stylesheets
│   ├── js/                # JavaScript files
│   ├── icons/             # Application icons
│   └── sounds/            # Audio notifications
├── cron/                  # Scheduled tasks
├── logs/                  # System logs
├── uploads/               # User uploads
│   └── student_photos/    # Profile pictures
├── vendor/                # Composer dependencies
├── config.php             # Database & system configuration
├── manifest.json          # PWA manifest
└── sw.js                  # Service worker
```

---

## Features

### Core Features

#### 1. QR Code Attendance System
- **Teacher QR Generation**: Generate separate QR codes for Time In/Time Out
- **Student QR Scanning**: Mobile-optimized camera scanning
- **Real-time Processing**: Instant attendance recording
- **Time-based Rules**: Automatic status determination (Present/Late/Absent)
- **Multi-subject Support**: Subject-specific attendance tracking

#### 2. User Management
- **Multi-role System**: Admin, Teachers, Students, Parents
- **Profile Management**: Photo uploads, personal information
- **Section Assignment**: Grade level and classroom organization
- **Parent-Child Linking**: Family relationship tracking

#### 3. SMS Notification System
- **Real-time Alerts**: Instant notifications to parents
- **Automated Messages**: Time In/Time Out notifications
- **Custom Templates**: Configurable message formats
- **Delivery Tracking**: SMS status monitoring

#### 4. Reporting & Analytics
- **Attendance Reports**: Daily, weekly, monthly summaries
- **Student Analytics**: Individual performance tracking
- **Section Reports**: Class-wide attendance analysis
- **Export Functions**: PDF and CSV downloads

#### 5. Progressive Web App (PWA)
- **Offline Support**: Cached functionality without internet
- **Mobile Optimization**: Native app-like experience
- **Push Notifications**: System alerts and reminders
- **Home Screen Installation**: Add to device home screen

### Advanced Features

#### 6. Auto-Evaluation Engine
- **Attendance Rate Calculation**: Percentage-based performance
- **Status Classification**: Excellent/Good/Fair/Poor ratings
- **Monthly Summaries**: Period-based evaluations
- **Trend Analysis**: Performance tracking over time

#### 7. Real-time Dashboard
- **Role-based Views**: Customized for each user type
- **Live Statistics**: Real-time data updates
- **Quick Actions**: Fast access to common tasks
- **Activity Feeds**: Recent system activities

#### 8. Security & Privacy
- **Session Management**: Secure login/logout handling
- **Role-based Access**: Permission-controlled features
- **Data Encryption**: Secure data transmission
- **Audit Logging**: User activity tracking

---

## Database Schema

### Core Tables

#### 1. Users Table
```sql
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    role ENUM('admin', 'teacher', 'student', 'parent') NOT NULL,
    lrn VARCHAR(12) UNIQUE NULL, -- Learner Reference Number
    section_id INT,
    parent_id INT,
    profile_image VARCHAR(255),
    profile_image_path VARCHAR(255),
    qr_code TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### 2. Sections Table
```sql
CREATE TABLE sections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    section_name VARCHAR(50) NOT NULL,
    grade_level VARCHAR(20) NOT NULL,
    teacher_id INT,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id)
);
```

#### 3. Attendance Table
```sql
CREATE TABLE attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    teacher_id INT NOT NULL,
    section_id INT NOT NULL,
    subject_id INT DEFAULT NULL,
    attendance_date DATE NOT NULL,
    time_in TIME,
    time_out TIME,
    status ENUM('present', 'absent', 'late', 'out') NOT NULL,
    remarks TEXT,
    qr_scanned BOOLEAN DEFAULT FALSE,
    attendance_source ENUM('manual', 'qr_scan', 'auto') DEFAULT 'manual',
    scan_location VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (teacher_id) REFERENCES users(id),
    FOREIGN KEY (section_id) REFERENCES sections(id),
    UNIQUE KEY unique_attendance (student_id, attendance_date)
);
```

#### 4. QR Scans Table
```sql
CREATE TABLE qr_scans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    teacher_id INT NOT NULL,
    scan_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    location VARCHAR(100),
    device_info TEXT,
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (teacher_id) REFERENCES users(id)
);
```

#### 5. SMS Configuration Table
```sql
CREATE TABLE sms_config (
    id INT PRIMARY KEY AUTO_INCREMENT,
    provider_name VARCHAR(50) NOT NULL,
    api_url VARCHAR(255) NOT NULL,
    api_key VARCHAR(255) NOT NULL,
    sender_name VARCHAR(20) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

#### 6. Student-Parent Relationship
```sql
CREATE TABLE student_parents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    parent_id INT NOT NULL,
    relationship ENUM('father', 'mother', 'guardian') NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id),
    FOREIGN KEY (parent_id) REFERENCES users(id)
);
```

### Supporting Tables

#### 7. Subjects Table
```sql
CREATE TABLE subjects (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subject_name VARCHAR(100) NOT NULL,
    subject_code VARCHAR(20) NOT NULL,
    grade_level VARCHAR(20) NOT NULL,
    teacher_id INT,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id)
);
```

#### 8. SMS Logs Table
```sql
CREATE TABLE sms_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    phone_number VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    response TEXT,
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    notification_type VARCHAR(50) DEFAULT 'general',
    reference_id VARCHAR(100),
    scheduled_at TIMESTAMP NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### 9. System Settings
```sql
CREATE TABLE settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_name VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

---

## User Roles & Permissions

### 1. Admin Role
**Full system access and management capabilities**

#### Permissions:
- ✅ User Management (Create, Read, Update, Delete all users)
- ✅ Section Management (Create, manage all sections)
- ✅ System Configuration (SMS settings, school settings)
- ✅ QR Scanner Access (Scan any student QR codes)
- ✅ Reports Access (View all attendance reports)
- ✅ Dashboard (System-wide statistics)
- ✅ Profile Management (Manage own profile)

#### Accessible Pages:
- `dashboard.php` - Admin dashboard with system statistics
- `users.php` - User management interface
- `students.php` - Student management
- `teachers.php` - Teacher management
- `sections.php` - Section/class management
- `qr-scanner.php` - QR code scanning interface
- `attendance.php` - Attendance records management
- `reports.php` - Comprehensive reporting
- `settings.php` - System settings
- `sms-config.php` - SMS configuration
- `profile.php` - Personal profile management

### 2. Teacher Role
**Classroom and student management within assigned sections**

#### Permissions:
- ✅ QR Code Generation (For their subjects and sections)
- ✅ QR Scanner Access (Scan student QR codes)
- ✅ Attendance Management (View/edit attendance for their students)
- ✅ Student Management (View assigned students)
- ✅ Section Access (Manage assigned sections only)
- ✅ Reports Access (For their students and sections)
- ✅ Profile Management (Manage own profile)
- ❌ User Creation/Deletion
- ❌ System Configuration
- ❌ Other teachers' data access

#### Accessible Pages:
- `dashboard.php` - Teacher dashboard with classroom statistics
- `qr-scanner.php` - QR scanning for attendance
- `qr-code.php` - QR code generation for subjects
- `attendance.php` - Attendance management for assigned students
- `students.php` - View assigned students
- `sections.php` - View assigned sections
- `reports.php` - Generate reports for assigned classes
- `profile.php` - Personal profile management

### 3. Student Role
**Personal attendance monitoring and QR code access**

#### Permissions:
- ✅ QR Scanner Access (Scan teacher-generated QR codes)
- ✅ Personal Attendance View (Own attendance records only)
- ✅ QR Code Access (Personal QR code for identification)
- ✅ Profile Management (Manage own profile and photo)
- ✅ Student ID Card (View/download personal ID card)
- ❌ Other students' data access
- ❌ Teacher features
- ❌ Administrative functions

#### Accessible Pages:
- `dashboard.php` - Student dashboard with personal statistics
- `attendance.php` - Personal attendance records
- `profile.php` - Personal profile and photo management

#### Special Features:
- **QR Code Scanning**: Scan teacher QR codes for attendance
- **Student ID Card**: Digital ID card with QR code
- **Attendance Summary**: Personal performance analytics

### 4. Parent Role
**Monitor children's attendance and receive notifications**

#### Permissions:
- ✅ Children's Attendance View (Linked children only)
- ✅ Profile Management (Manage own profile)
- ✅ SMS Notifications (Receive attendance alerts)
- ❌ School-wide data access
- ❌ Other students' data access
- ❌ Administrative functions

#### Accessible Pages:
- `dashboard.php` - Parent dashboard showing children's summary
- `attendance.php` - Children's attendance records
- `profile.php` - Personal profile management

#### Special Features:
- **Child Linking**: View multiple children's attendance
- **SMS Notifications**: Automatic attendance alerts
- **Performance Overview**: Summary of all children's attendance

---

## Installation Guide

### Prerequisites
- **Web Server**: Apache/Nginx with PHP 8.0+
- **Database**: MySQL 5.7+ or MariaDB 10.3+
- **PHP Extensions**: PDO, GD, Curl, OpenSSL, Zip
- **Composer**: For dependency management

### Step 1: Download and Extract
```bash
# Clone or download the project files
git clone <repository-url> kes-smart
cd kes-smart
```

### Step 2: Install Dependencies
```bash
# Install PHP dependencies via Composer
composer install
```

### Step 3: Database Setup
```sql
-- Create database
CREATE DATABASE kes_smart;

-- Import database schema
mysql -u username -p kes_smart < database.sql
```

### Step 4: Configuration
Edit `config.php` with your database credentials:
```php
// Database Configuration
$db_host = 'localhost';
$db_name = 'kes_smart';
$db_user = 'your_username';
$db_pass = 'your_password';
```

### Step 5: File Permissions
```bash
# Set proper permissions for upload directories
chmod 755 uploads/
chmod 755 uploads/student_photos/
chmod 755 uploads/student_photos/thumbnails/
chmod 755 logs/
```

### Step 6: SMS Configuration
1. Navigate to `sms-config.php` as admin
2. Configure your SMS provider settings
3. Test SMS functionality

### Step 7: PWA Setup
Update `manifest.json` with your domain:
```json
{
  "start_url": "https://yourdomain.com/smart/",
  "scope": "https://yourdomain.com/smart/"
}
```

### Step 8: Default Admin Account
Default login credentials:
- **Username**: `admin`
- **Role**: `admin`
- **Password**: Set during first login

---

## User Manual

### Getting Started

#### For Administrators

1. **First Login**
   - Use admin credentials to access the system
   - Complete SMS configuration
   - Set up school information

2. **User Management**
   - Create teacher accounts
   - Set up sections/classes
   - Import or create student records
   - Link parent accounts to students

3. **System Configuration**
   - Configure SMS settings
   - Set attendance time rules
   - Customize school information

#### For Teachers

1. **Daily Workflow**
   - Log into the system
   - Navigate to QR Code generator
   - Select subject and section
   - Generate Time In QR code
   - Display QR code for students to scan
   - Generate Time Out QR code when class ends

2. **Attendance Management**
   - Monitor real-time attendance
   - Review daily attendance reports
   - Make manual corrections if needed

3. **Report Generation**
   - Access Reports section
   - Filter by date, section, or student
   - Export attendance data

#### For Students

1. **Daily Attendance**
   - Open the application
   - Access QR Scanner
   - Scan teacher's Time In QR code upon arrival
   - Scan Time Out QR code when leaving

2. **Monitor Progress**
   - View personal attendance summary
   - Check attendance history
   - Download student ID card

#### For Parents

1. **Monitor Children**
   - View children's attendance dashboard
   - Check recent attendance records
   - Receive SMS notifications

2. **Communication**
   - Receive real-time attendance alerts
   - Monitor academic performance trends

### Time-based Attendance Rules

The system automatically determines attendance status based on scan time:

- **Before 7:15 AM**: Present ✅
- **7:15 AM - 4:31 PM**: Late ⚠️
- **After 4:31 PM**: Auto-Absent (Scanner disabled) ❌

---

## API Documentation

### Authentication Endpoints

#### POST `/api/auth.php`
Authenticate user and create session
```json
{
  "username": "student_username",
  "role": "student"
}
```

Response:
```json
{
  "success": true,
  "user": {
    "id": 1,
    "username": "student",
    "full_name": "Student Name",
    "role": "student"
  }
}
```

### Attendance Endpoints

#### POST `/api/process-attendance-qr.php`
Process QR code scan for attendance
```json
{
  "qr_data": "encoded_qr_string",
  "student_id": 123
}
```

#### GET `/api/get-student-subjects.php`
Get subjects for a specific student
```json
{
  "student_id": 123
}
```

### Dashboard Endpoints

#### GET `/api/dashboard.php`
Get dashboard data based on user role
```json
{
  "user_role": "student",
  "user_id": 123
}
```

### Sync Endpoints

#### POST `/api/sync-attendance.php`
Sync offline attendance data
```json
{
  "attendance_records": [
    {
      "student_id": 123,
      "date": "2025-11-11",
      "status": "present",
      "time_in": "07:30:00"
    }
  ]
}
```

---

## Technical Specifications

### Performance Requirements
- **Page Load Time**: < 3 seconds
- **QR Scan Processing**: < 1 second
- **Database Queries**: Optimized with indexes
- **Concurrent Users**: 500+ simultaneous users

### Browser Compatibility
- **Chrome**: 90+
- **Firefox**: 88+
- **Safari**: 14+
- **Edge**: 90+
- **Mobile Browsers**: iOS Safari 14+, Chrome Mobile 90+

### Mobile Optimization
- **Responsive Design**: Bootstrap 5 framework
- **Touch-Friendly**: Large buttons and touch targets
- **Offline Support**: Service worker implementation
- **Camera Access**: Native camera API integration

### Security Measures
- **Session Management**: Secure PHP sessions with timeout
- **SQL Injection Protection**: Prepared statements
- **XSS Prevention**: Input sanitization and output encoding
- **CSRF Protection**: Token-based validation
- **File Upload Security**: Type validation and secure storage

### Backup and Recovery
- **Database Backup**: Daily automated backups
- **File Backup**: Regular file system backups
- **Recovery Procedures**: Documented restore processes
- **Data Export**: CSV/PDF export functionality

---

## Security Features

### Authentication & Authorization
- **Role-based Access Control**: Granular permission system
- **Session Security**: Secure session handling with timeout
- **Password Security**: Hashed password storage (future enhancement)
- **Account Lockout**: Protection against brute force attacks

### Data Protection
- **Input Validation**: All user inputs sanitized
- **Output Encoding**: XSS prevention measures
- **SQL Injection Prevention**: Prepared statements only
- **File Upload Security**: Type and size validation

### Privacy Measures
- **Data Minimization**: Only necessary data collected
- **Access Logging**: User activity audit trails
- **Data Retention**: Configurable data retention policies
- **GDPR Compliance**: Privacy-focused design

### Network Security
- **HTTPS Support**: SSL/TLS encryption ready
- **API Security**: Token-based authentication
- **Rate Limiting**: DoS protection measures
- **Cross-Origin Controls**: CORS policy implementation

---

## Troubleshooting

### Common Issues

#### 1. Database Connection Errors
**Symptoms**: "Database connection failed" messages
**Solutions**:
- Verify database credentials in `config.php`
- Ensure MySQL service is running
- Check database server accessibility
- Verify database user permissions

#### 2. QR Code Generation Failures
**Symptoms**: QR codes not displaying
**Solutions**:
- Check Composer dependencies installation
- Verify GD extension is enabled
- Check file permissions in uploads directory
- Test with external QR API fallback

#### 3. SMS Notifications Not Working
**Symptoms**: Parents not receiving SMS alerts
**Solutions**:
- Verify SMS configuration in admin panel
- Check SMS provider API credentials
- Review SMS logs for error messages
- Test with different phone numbers

#### 4. Camera Access Issues
**Symptoms**: QR scanner camera not working
**Solutions**:
- Ensure HTTPS is enabled (required for camera access)
- Check browser permissions for camera
- Test on different devices/browsers
- Verify camera hardware functionality

#### 5. Offline Mode Problems
**Symptoms**: Application not working offline
**Solutions**:
- Check service worker registration
- Clear browser cache and data
- Verify manifest.json configuration
- Test PWA installation

### Error Logging
Check the following log files:
- `logs/error.log` - General application errors
- `logs/sms.log` - SMS delivery logs
- `logs/qr_scan.log` - QR scanning activities
- Server error logs (Apache/Nginx)

### Performance Issues

#### Slow Database Queries
- Review and optimize database indexes
- Analyze query execution plans
- Consider database query caching
- Monitor concurrent connection limits

#### Memory Usage
- Monitor PHP memory limits
- Optimize image processing
- Clear temporary files regularly
- Review session storage usage

---

## Contributing

### Development Setup
1. **Fork the repository**
2. **Set up development environment**
3. **Create feature branch**: `git checkout -b feature-name`
4. **Make changes and test thoroughly**
5. **Submit pull request with detailed description**

### Code Standards
- **PHP**: Follow PSR-12 coding standards
- **JavaScript**: ES6+ with consistent formatting
- **CSS**: BEM methodology for class naming
- **SQL**: Use prepared statements only

### Testing Guidelines
- **Unit Testing**: Test individual functions
- **Integration Testing**: Test component interactions
- **User Testing**: Test complete user workflows
- **Security Testing**: Verify security measures

### Documentation Updates
- Update this documentation for new features
- Include code comments for complex logic
- Provide API documentation for new endpoints
- Update installation instructions as needed

---

## System Maintenance

### Regular Tasks

#### Daily
- Monitor system logs for errors
- Check SMS delivery status
- Verify backup completion
- Review attendance statistics

#### Weekly
- Clean temporary files
- Archive old log files
- Review user account status
- Update system statistics

#### Monthly
- Database optimization and maintenance
- Security patch reviews
- Performance monitoring
- User feedback review

### Upgrade Procedures
1. **Backup current system**
2. **Test upgrade in staging environment**
3. **Apply updates during low-usage periods**
4. **Verify functionality post-upgrade**
5. **Monitor for issues**

---

## Support and Contact

### Technical Support
- **Documentation**: This comprehensive guide
- **Log Files**: Check error logs for diagnostics
- **Community**: GitHub Issues for bug reports
- **Updates**: Regular security and feature updates

### Version Information
- **Current Version**: 1.0.0
- **Last Updated**: November 2025
- **Compatibility**: PHP 8.0+, MySQL 5.7+
- **License**: Educational Use License

---

**© 2025 KES-SMART: Student Monitoring Application**
*Developed for educational institutions to streamline attendance monitoring and parent communication.*