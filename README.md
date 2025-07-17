# KES-SMART: Student Monitoring Application

![KES-SMART Logo](https://via.placeholder.com/300x100/007bff/ffffff?text=KES-SMART)

## 🏫 About KES-SMART

**KES-SMART** (Kalaw Elementary School - Student Monitoring Application with Real-Time QR and SMS Notifications) is a comprehensive Progressive Web Application (PWA) designed to modernize student attendance tracking and parent communication in educational institutions.

### ✨ Key Features

- 📱 **Progressive Web App** - Install and use like a native mobile app
- 🔐 **Multi-Role Access** - Admin, Teacher, Student, and Parent interfaces
- 📷 **QR Code Attendance** - Real-time scanning for instant attendance tracking
- 📨 **SMS Notifications** - Automatic parent notifications for attendance
- 📊 **Comprehensive Reports** - Detailed analytics and exportable reports
- 👥 **User Management** - Complete user and section administration
- ⚙️ **Configuration Panel** - Easy system settings and SMS setup
- 📱 **Mobile-First Design** - Optimized for smartphones and tablets

## 🚀 Technology Stack

- **Backend**: PHP 7.4+ (Non-OOP procedural style)
- **Database**: MySQL 5.7+ with PDO
- **Frontend**: HTML5, CSS3, JavaScript ES6+
- **UI Framework**: Bootstrap 5.3.0
- **Icons**: Font Awesome 6.0.0
- **QR Code**: Endroid QR Code Library
- **Charts**: Chart.js for analytics
- **PWA**: Service Worker + Web App Manifest

## 👥 User Roles & Features

### 🛡️ Admin
- Complete system administration
- User and section management
- SMS configuration and testing
- System settings and maintenance
- Comprehensive reporting and analytics
- Database backup and maintenance

### 👩‍🏫 Teacher
- Class attendance monitoring
- Student management for assigned sections
- Parent communication via SMS
- Attendance reports and analytics
- QR code generation for students

### 🎓 Student
- Personal QR code access
- Attendance history viewing
- Profile information management
- Mobile-optimized interface

### 👨‍👩‍👧‍👦 Parent
- Child attendance monitoring
- Real-time SMS notifications
- Attendance history and reports
- Multiple children support

## 📋 Core Functionality

### 🔍 QR Code System
- **Unique QR Codes**: Each student gets a unique QR code
- **Real-Time Scanning**: Instant attendance recording with camera
- **Mobile Optimized**: Works seamlessly on smartphones
- **Print & Share**: Download or print QR codes for physical use

### 📱 SMS Notifications
- **Real-Time Alerts**: Instant parent notifications upon attendance
- **Configurable**: Customizable message templates
- **Philippine SMS**: Integrated with local SMS providers
- **Delivery Tracking**: Complete SMS log and status tracking

### 📊 Reporting System
- **Attendance Reports**: Daily, weekly, monthly summaries
- **Export Options**: HTML, CSV, PDF formats
- **Role-Based Access**: Different reports for different user types
- **Visual Analytics**: Charts and graphs for data visualization

## 🛠️ Installation

### Quick Start
1. **Download** the application files
2. **Import** `database.sql` into MySQL
3. **Configure** database connection in `config.php`
4. **Install** dependencies with `composer install`
5. **Access** via web browser

### Detailed Installation
See [INSTALLATION.md](INSTALLATION.md) for complete setup instructions.

### Default Login Credentials
```
Admin:    Username: admin    (No password)
Teacher:  Username: teacher  (No password)
Student:  Username: student  (No password)
Parent:   Username: parent   (No password)
```

## 📸 Screenshots

### 🏠 Dashboard
![Dashboard](https://via.placeholder.com/800x600/f8f9fa/6c757d?text=Role-Based+Dashboard)

### 📷 QR Scanner
![QR Scanner](https://via.placeholder.com/800x600/28a745/ffffff?text=Real-Time+QR+Scanner)

### 📊 Reports
![Reports](https://via.placeholder.com/800x600/17a2b8/ffffff?text=Comprehensive+Reports)

### ⚙️ Settings
![Settings](https://via.placeholder.com/800x600/6f42c1/ffffff?text=System+Configuration)

## 🔧 Configuration

### SMS Setup
1. Sign up for Philippines SMS service (e.g., PhilSMS)
2. Navigate to **Settings > SMS Configuration**
3. Enter your API credentials:
   - API URL: `https://api.philsms.com/v1/send`
   - API Key: Your service API key
   - Sender Name: Your school name (max 11 characters)

### School Information
Update school details in **Settings > General Settings**:
- School name and contact information
- Timezone and date/time formats
- System preferences

## 📱 Progressive Web App Features

### Installation
- **Add to Home Screen**: Install like a native app
- **Offline Support**: Basic functionality works offline
- **Push Notifications**: Real-time updates (if supported)
- **App-like Experience**: Full-screen mode without browser UI

### Service Worker Features
- **Caching Strategy**: Critical files cached for offline use
- **Background Sync**: Sync data when connection restored
- **Update Notifications**: Automatic app updates

## 🎯 Use Cases

### Educational Institutions
- **Elementary Schools**: Primary target for student monitoring
- **High Schools**: Scalable for larger student populations
- **Private Schools**: Enhanced parent communication
- **Tutorial Centers**: Small-scale attendance tracking

### Implementation Scenarios
- **Daily Attendance**: Morning and afternoon check-ins
- **Event Tracking**: Field trips, assemblies, activities
- **Parent Engagement**: Real-time communication system
- **Administrative Efficiency**: Automated reporting and analytics

## 🔒 Security Features

### Data Protection
- **Input Sanitization**: All user inputs properly sanitized
- **SQL Injection Prevention**: PDO prepared statements
- **XSS Protection**: Output escaping and security headers
- **Session Management**: Secure session handling

### Access Control
- **Role-Based Access**: Different permissions per user type
- **Session Validation**: Automatic logout on inactivity
- **Secure Headers**: HTTPS and security headers recommended

## 📈 System Requirements

### Server Requirements
- **PHP**: 7.4 or higher
- **MySQL**: 5.7 or higher
- **Web Server**: Apache 2.4+ or Nginx
- **Storage**: Minimum 100MB

### Client Requirements
- **Modern Browser**: Chrome 67+, Firefox 63+, Safari 11.1+
- **JavaScript**: Enabled for full functionality
- **Camera**: For QR code scanning (mobile devices)

## 🚀 Performance Features

### Optimization
- **Lightweight Design**: Fast loading on mobile networks
- **Efficient Queries**: Optimized database operations
- **Lazy Loading**: On-demand content loading
- **Compressed Assets**: Minified CSS and JavaScript

### Scalability
- **Database Indexing**: Optimized for large datasets
- **Modular Design**: Easy to extend and modify
- **API Ready**: Built for future API integration

## 📋 Development Roadmap

### Phase 1 ✅ (Completed)
- Core attendance system
- QR code generation and scanning
- Basic SMS notifications
- Role-based user management
- PWA implementation

### Phase 2 📋 (Future)
- Advanced analytics and AI insights
- Mobile app (React Native/Flutter)
- Parent mobile app
- Integration with school management systems
- Multi-language support

### Phase 3 🔮 (Planned)
- Facial recognition attendance
- IoT device integration
- Advanced reporting with machine learning
- Real-time dashboards
- API for third-party integrations

## 🤝 Contributing

### Development Setup
1. Fork the repository
2. Set up local development environment
3. Follow coding standards (PSR-12 for PHP)
4. Submit pull requests for review

### Reporting Issues
- Use GitHub Issues for bug reports
- Provide detailed reproduction steps
- Include system information and screenshots

## 📞 Support

### Documentation
- [Installation Guide](INSTALLATION.md)
- [User Manual](docs/USER_MANUAL.md)
- [API Documentation](docs/API.md)

### Community
- GitHub Issues for bug reports
- Feature requests welcome
- Community contributions encouraged

## 📄 License

This project is open source and available under the [MIT License](LICENSE).

### Attribution
Built with ❤️ for educational institutions in the Philippines.

### Third-Party Libraries
- **Bootstrap**: MIT License
- **Font Awesome**: Font Awesome Free License
- **Chart.js**: MIT License
- **Endroid QR Code**: MIT License

## 🎓 Educational Purpose

**Note**: This application is developed for educational and demonstration purposes. For production deployment in critical environments, additional security measures, proper authentication systems, and comprehensive testing are recommended.

---

**KES-SMART** - Modernizing education through technology, one scan at a time! 📱✨

![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3.0-purple)
![PWA](https://img.shields.io/badge/PWA-Ready-green)
![License](https://img.shields.io/badge/License-MIT-yellow)
