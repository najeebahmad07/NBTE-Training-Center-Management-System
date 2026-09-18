# NBTE Training Center Management System

<p align="center">
  <strong>A Complete Training Center, Student, Result & Certificate Management System</strong>
</p>

<p align="center">
  <strong>Developed by Najeeb Ahmad 07</strong>
</p>

<p align="center">

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![TCPDF](https://img.shields.io/badge/TCPDF-PDF%20Generation-red?style=for-the-badge)

</p>

---

## 📌 About The Project

The **NBTE Training Center Management System** is a web-based management application developed using **PHP and MySQL** for managing training centers, students, programs, courses, marks, results, certificates, ID cards and verification records.

The system provides a centralized platform for administrators and training centers to manage academic and administrative activities digitally.

It includes modules for:

- Training Center Management
- Admin Management
- Student Management
- Program Management
- Course Management
- Marks Entry
- Result Management
- Marksheet Generation
- Student ID Card Generation
- Authorized Certificate Generation
- Student Verification
- Result Search
- QR Code Verification
- PDF Document Generation

---

# 🎯 Project Objective

The main objective of this project is to provide a centralized digital platform for managing training center and student-related operations.

### Objectives

- 🏫 Digitally manage training centers
- 👨‍🎓 Manage student records
- 📚 Manage programs and courses
- 📝 Manage student marks
- 📊 Generate and display results
- 📄 Generate marksheets
- 🪪 Generate student ID cards
- 🏆 Generate authorized certificates
- 🔎 Verify student information
- 🔐 Secure administrative access
- 📱 Provide a responsive user interface
- 🗃️ Store information in a centralized MySQL database

---

# ✨ Features

## 🏫 Training Center Management

The system provides functionality to manage authorized training centers.

### Features

- Add training center
- Manage training center information
- Assign administrator to center
- View center information
- Manage center status
- View all registered centers
- Generate authorized center certificate

---

## 👤 Admin Management

Administrators can manage system operations through a secure administrative interface.

### Features

- Admin login
- Admin dashboard
- Admin profile
- Admin management
- Center assignment
- Role-based access
- Administrative controls

### Supported Roles

```text
Admin
Super Admin
````

---

# 👨‍🎓 Student Management

The student management module allows administrators to manage complete student records.

### Student Information

* Full Name
* Enrollment Number
* Roll Number
* Father Name
* Gender
* Date of Birth
* Student Photo
* Session
* Batch
* Program
* Course
* Training Center
* Student Status

### Features

* Add student
* View student
* Update student
* Manage student status
* Assign program
* Assign course
* Manage student photo
* Search students
* Generate student documents

---

# 📚 Program Management

Programs can be managed from the administrative panel.

### Features

* Add programs
* Edit programs
* View programs
* Program duration
* Assign courses
* Assign students
* Program-based academic management

---

# 📖 Course Management

The course module manages courses associated with programs and students.

### Features

* Add course
* Edit course
* View course
* Course assignment
* Student-course mapping
* Subject association

---

# 📝 Marks Entry

The marks management module allows administrators to enter student academic marks.

### Features

* Student-wise marks entry
* Subject-wise marks
* Theory marks
* Practical marks
* Total marks
* Maximum marks
* Obtained marks
* Marks validation
* Duplicate entry prevention
* Secure marks saving

---

# 📊 Result Management

The system provides a result search and result display module.

Students can search their results using their **Roll Number**.

### Result Includes

* Student Name
* Roll Number
* Enrollment Number
* Program
* Course
* Session
* Batch
* Subject-wise marks
* Theory marks
* Practical marks
* Total marks
* Maximum marks
* Percentage
* Result Status

---

# 🔎 Result Search

The result search page provides a simple interface for searching student results.

### Search Method

```text
Roll Number
```

The user enters the Roll Number and selects:

```text
Search Result
```

The system retrieves the corresponding student result from the database.

---

# 📄 Marksheet Generation

The system supports dynamic PDF marksheet generation using **TCPDF**.

### Marksheet Features

* Student details
* Enrollment number
* Roll number
* Program
* Course
* Session
* Batch
* Subject-wise marks
* Theory marks
* Practical marks
* Total marks
* Percentage
* Grade
* Result status
* QR code
* Authorized signature
* Instructions
* PDF generation

---

# 🪪 Student ID Card Generation

The system can dynamically generate student ID cards.

### ID Card Includes

* Student photograph
* Student name
* Enrollment number
* Roll number
* Program
* Course
* Session
* Training center
* Student signature
* QR code
* Authorized information

### Output

```text
PDF ID Card
```

---

# 🏆 Authorized Certificate Generation

The system provides authorized certificate generation for training centers.

### Certificate Includes

* Training center name
* Admin information
* Program
* Certificate number
* Issue date
* Official logo
* Center logo
* Authorized signature
* Official seal
* QR code
* Verification information

### Output

```text
PDF Authorized Certificate
```

---

# 🔐 Certificate Verification

Generated certificates can contain QR-based verification information.

The QR code can be used to access the verification system and validate certificate-related information.

---

# 🔎 Student Verification

The student verification module allows student records to be checked digitally.

### Search Options

* Enrollment Number
* Certificate ID

### Verification Information

* Student Name
* Enrollment Number
* Roll Number
* Father Name
* Gender
* Date of Birth
* Program
* Course
* Duration
* Session
* Batch
* Certificate ID
* Issue Date
* Student Status

---

# 📱 Responsive Design

The application is designed to work across different screen sizes.

### Supported Devices

* 💻 Desktop
* 🖥️ Laptop
* 📱 Mobile
* 📲 Tablet

The interface uses responsive Bootstrap components and custom CSS.

---

# 🎨 UI Design

The application uses an NBTE-inspired professional color scheme.

| Color            | Hex Code  |
| ---------------- | --------- |
| Navy             | `#062A5A` |
| Gold             | `#D49729` |
| Light Background | `#F8F9FC` |
| Dark Text        | `#2C3E50` |

---

# 🛠️ Technology Stack

## Frontend

* HTML5
* CSS3
* Bootstrap 5
* JavaScript
* Bootstrap Icons
* AJAX

## Backend

* PHP 8.x
* PDO
* MySQL

## PDF & Document Processing

* TCPDF
* QR Code Generation

## Server

* Apache
* XAMPP
* WAMP
* Linux Hosting

## Development Tools

* Visual Studio Code
* Git
* GitHub
* phpMyAdmin

---

# 🏗️ System Architecture

```text
                    ┌──────────────────────┐
                    │        User          │
                    └──────────┬───────────┘
                               │
                               ▼
                    ┌──────────────────────┐
                    │    Web Interface     │
                    │ HTML / CSS / Bootstrap│
                    └──────────┬───────────┘
                               │
                               ▼
                    ┌──────────────────────┐
                    │    PHP Application    │
                    └──────────┬───────────┘
                               │
          ┌────────────────────┼────────────────────┐
          │                    │                    │
          ▼                    ▼                    ▼
   Authentication       Student Management     Admin Management
          │                    │                    │
          └────────────────────┼────────────────────┘
                               │
          ┌────────────────────┼────────────────────┐
          │                    │                    │
          ▼                    ▼                    ▼
   Marks Management     Result Management      Verification
          │                    │                    │
          └────────────────────┼────────────────────┘
                               │
          ┌────────────────────┼────────────────────┐
          │                    │                    │
          ▼                    ▼                    ▼
     Marksheet             ID Card             Certificate
    Generation           Generation            Generation
          │                    │                    │
          └────────────────────┼────────────────────┘
                               │
                               ▼
                    ┌──────────────────────┐
                    │    MySQL Database    │
                    └──────────────────────┘
```

---

# 📁 Project Structure

```text
NBTE-Training-Center-Management-System/
│
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
│
├── includes/
│   ├── auth.php
│   ├── db.php
│   └── csrf.php
│
├── uploads/
│   ├── students/
│   ├── signatures/
│   ├── certificates/
│   └── id_cards/
│
├── lib/
│   └── TCPDF/
│
├── admin/
│
├── index.php
├── dashboard.php
├── students.php
├── programs.php
├── courses.php
├── marks_entry.php
├── verify_marksheet.php
├── Student-Verification.php
├── Results.php
├── generate_marksheet.php
├── generate_id_card.php
├── generate_admin_certificate.php
├── admin_authorized_certificates.php
├── AdminLogin.php
├── CenterLogin.php
├── README.md
└── ...
```

> The exact structure can vary depending on the deployed version of the application.

---

# 📸 Screenshots

Create a folder named:

```text
screenshots/
```

Add your project screenshots to this folder.

## 🏠 Dashboard

![Dashboard](screenshots/dashboard.png)

---

## 👤 Admin Management

![Admin Management](screenshots/admin-management.png)

---

## 🏫 Training Center Management

![Training Center Management](screenshots/training-center-management.png)

---

## 👨‍🎓 Student Management

![Student Management](screenshots/student-management.png)

---

## 📝 Student Admission

![Student Admission](screenshots/student-admission.png)

---

## 📚 Programs

![Programs](screenshots/programs.png)

---

## 📖 Courses

![Courses](screenshots/courses.png)

---

## 📝 Marks Entry

![Marks Entry](screenshots/marks-entry.png)

---

## 📊 Result Search

![Result Search](screenshots/result-search.png)

---

## 📄 Student Result

![Student Result](screenshots/result.png)

---

## 🏆 Marksheet

![Marksheet](screenshots/marksheet.png)

---

## 🪪 Student ID Card

![Student ID Card](screenshots/id-card.png)

---

## 🏅 Authorized Certificate

![Authorized Certificate](screenshots/authorized-certificate.png)

---

## 🔎 Student Verification

![Student Verification](screenshots/student-verification.png)

---

# ⚙️ Installation

## 1. Clone the Repository

```bash
git clone https://github.com/najeebahmad07/NBTE-Training-Center-Management-System.git
```

Move into the project directory:

```bash
cd NBTE-Training-Center-Management-System
```

---

# 2. Install Local Server

You can use any PHP-compatible server environment.

Recommended:

```text
XAMPP
```

For XAMPP, place the project inside:

```text
C:\xampp\htdocs\
```

Example:

```text
C:\xampp\htdocs\NBTE-Training-Center-Management-System\
```

---

# 3. Start Apache and MySQL

Open XAMPP Control Panel and start:

```text
Apache
MySQL
```

---

# 4. Create MySQL Database

Open:

```text
http://localhost/phpmyadmin
```

Create a database.

Example:

```text
nbte_management
```

Import the SQL database file if it is included in the project.

---

# 5. Configure Database

Open:

```text
includes/db.php
```

Configure your database credentials.

Example:

```php
$host = "localhost";
$dbname = "nbte_management";
$username = "root";
$password = "";
```

Use your own production credentials when deploying to a live server.

---

# 6. Run the Application

Open:

```text
http://localhost/NBTE-Training-Center-Management-System/
```

---

# 🔐 Security

The application includes several security mechanisms.

### Security Features

* Session-based authentication
* Role-based access control
* CSRF protection
* PDO prepared statements
* Input validation
* Output escaping
* Protected administrative pages
* Authentication checks
* Restricted document generation

---

# ⚠️ Security Warning

Do **not** upload sensitive information to GitHub.

Never commit:

```text
.env
database passwords
API keys
Razorpay credentials
server credentials
hosting credentials
private student documents
private certificates
production configuration files
```

Use `.gitignore` where required.

Example:

```gitignore
.env
.env.*
vendor/
uploads/
*.log
.DS_Store
Thumbs.db
```

---

# 🚀 Future Enhancements

Possible future improvements include:

* [ ] Attendance Management
* [ ] Advanced Dashboard Analytics
* [ ] Email Notifications
* [ ] SMS Notifications
* [ ] WhatsApp Notifications
* [ ] Online Student Registration
* [ ] Online Admission Form
* [ ] Advanced Reports
* [ ] Excel Export
* [ ] PDF Reports
* [ ] Automated Database Backup
* [ ] Activity Logs
* [ ] Advanced Permission Management
* [ ] REST API
* [ ] Mobile Application
* [ ] Cloud Deployment
* [ ] Advanced Certificate Verification
* [ ] Digital Document Management
* [ ] Automated Notifications
* [ ] Multi-center Reporting

---

# 💡 Use Cases

The system can be adapted for:

* Training Institutes
* Skill Development Centers
* Educational Institutions
* Vocational Training Centers
* Certification Organizations
* Academic Administration
* Student Management
* Result Management
* Certificate Management
* Training Center Management

---

# 🔄 Application Workflow

```text
Training Center
       │
       ▼
Student Admission
       │
       ▼
Program & Course Assignment
       │
       ▼
Student Academic Management
       │
       ▼
Marks Entry
       │
       ▼
Result Calculation
       │
       ├──────────────► Result Search
       │
       ▼
Marksheet Generation
       │
       ▼
ID Card Generation
       │
       ▼
Student / Certificate Verification
```

---

# 📊 Academic Workflow

```text
Student
   │
   ▼
Admission
   │
   ▼
Program
   │
   ▼
Course
   │
   ▼
Subjects
   │
   ▼
Theory + Practical Marks
   │
   ▼
Total Marks
   │
   ▼
Percentage
   │
   ▼
Result
   │
   ▼
Marksheet
```

---

# 📄 Document Generation

The system supports dynamic document generation using database information.

```text
Database
   │
   ├── Student Information
   ├── Center Information
   ├── Program Information
   ├── Course Information
   ├── Marks
   └── Certificate Information
          │
          ▼
       TCPDF
          │
          ├── Marksheet PDF
          ├── ID Card PDF
          └── Authorized Certificate PDF
```

---

# 🔎 Verification Workflow

```text
User
 │
 ▼
Enter Roll Number / Enrollment Number / Certificate ID
 │
 ▼
Search Database
 │
 ▼
Match Record
 │
 ▼
Display Verification Information
```

---

# 👨‍💻 Developer

## Najeeb Ahmad 07

**Developer:** Najeeb Ahmad 07

### Profile

```text
Software Engineering Student
Web Developer
Backend Developer
Full Stack Web Developer
Tech Enthusiast
Problem Solver
```

### Technical Skills

```text
C
C++
Java
JavaScript
Python
PHP
HTML
CSS
Bootstrap
MySQL
WordPress
WooCommerce
Elementor
TCPDF
```

### Development Areas

```text
Web Development
Backend Development
Full Stack Development
PHP & MySQL Applications
WordPress Development
E-Commerce Development
Database Management
Web Application Development
PDF Document Generation
```

---

# 🌐 GitHub Repository

## Source Code

```text
https://github.com/najeebahmad07/NBTE-Training-Center-Management-System
```

---

# 🤝 Contributing

Contributions and improvements are welcome.

## Create a Feature Branch

```bash
git checkout -b feature/new-feature
```

## Add Changes

```bash
git add .
```

## Commit Changes

```bash
git commit -m "Add new feature"
```

## Push Changes

```bash
git push origin feature/new-feature
```

Then create a Pull Request.

---

# 🐛 Bug Reports

If you find a bug, please create a GitHub Issue and provide:

* Bug description
* Steps to reproduce
* Expected result
* Actual result
* Screenshot
* PHP version
* MySQL version
* Browser information

---

# ⭐ Project Highlights

```text
✓ Training Center Management
✓ Admin Management
✓ Student Management
✓ Program Management
✓ Course Management
✓ Student Admission
✓ Marks Entry
✓ Theory Marks
✓ Practical Marks
✓ Result Calculation
✓ Result Search
✓ Student Verification
✓ Marksheet Generation
✓ Student ID Card Generation
✓ Authorized Certificate Generation
✓ QR Code Verification
✓ PDF Generation
✓ Role-Based Access
✓ CSRF Protection
✓ PDO Database Security
✓ Responsive Design
✓ MySQL Database
✓ TCPDF Integration
```

---

# 📜 License

This project is developed by **Najeeb Ahmad 07**.

Unless a separate license is provided in this repository, the source code should not be assumed to be freely reusable, redistributed, or commercially used without permission from the developer.

---

# ❤️ Credits

### Developed By

**Najeeb Ahmad 07**

Web Developer | Backend Developer | Full Stack Developer

---

<p align="center">

# 💻 NBTE Training Center Management System

### Developed with ❤️ by Najeeb Ahmad 07

⭐ If you find this project useful, please consider giving it a star.

</p>
 
