# 🎓 NBTE Training Center Management System

<p align="center">
  <img src="assets/images/logo.jpg" alt="NBTE Logo" width="120" style="border-radius: 50%; box-shadow: 0 4px 12px rgba(0,0,0,0.15);" />
</p>

<p align="center">
  <strong>A Complete Web-Based Training Center, Student Admission, Examination, Marksheet, and QR-Verified Certificate Management Platform</strong>
</p>

<p align="center">
  <em>Developed with ❤️ by <strong><a href="https://github.com/najeebahmad07">Najeeb Ahmad 07</a></strong></em>
</p>

<p align="center">
  <a href="https://github.com/najeebahmad07/NBTE-Training-Center-Management-System"><img src="https://img.shields.io/badge/PHP-8.1%20%7C%208.2-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.x" /></a>
  <a href="https://github.com/najeebahmad07/NBTE-Training-Center-Management-System"><img src="https://img.shields.io/badge/MySQL-8.0%2B-4479A1?style=for-the-badge&logo=mysql&logoColor=white" alt="MySQL" /></a>
  <a href="https://github.com/najeebahmad07/NBTE-Training-Center-Management-System"><img src="https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=for-the-badge&logo=bootstrap&logoColor=white" alt="Bootstrap 5" /></a>
  <a href="https://github.com/najeebahmad07/NBTE-Training-Center-Management-System"><img src="https://img.shields.io/badge/TCPDF-Vector%20Engine-E03C11?style=for-the-badge&logo=adobeacrobatreader&logoColor=white" alt="TCPDF" /></a>
  <a href="https://github.com/najeebahmad07/NBTE-Training-Center-Management-System"><img src="https://img.shields.io/badge/Razorpay-Payment%20Gateway-0C2340?style=for-the-badge&logo=razorpay&logoColor=white" alt="Razorpay" /></a>
  <a href="https://github.com/najeebahmad07/NBTE-Training-Center-Management-System/blob/main/LICENSE"><img src="https://img.shields.io/badge/License-MIT-green.svg?style=for-the-badge" alt="License MIT" /></a>
</p>

---

## 📑 Table of Contents

- [📌 Overview](#-overview)
- [✨ Key Features](#-key-features)
  - [🏢 Super Admin Portal](#-super-admin-portal)
  - [🏫 Training Center (Admin) Portal](#-training-center-admin-portal)
  - [🖨️ High-Definition Document Generation (TCPDF Engine)](#️-high-definition-document-generation-tcpdf-engine)
  - [🔍 Public Instant Verification System](#-public-instant-verification-system)
  - [💳 Integrated Wallet & Online Recharge](#-integrated-wallet--online-recharge)
- [🖼️ Screenshot Gallery](#️-screenshot-gallery)
- [🛠️ Technology Stack & Architecture](#️-technology-stack--architecture)
- [🗄️ Database Architecture](#️-database-architecture)
- [🚀 Quick Start & Installation](#-quick-start--installation)
  - [Prerequisites](#prerequisites)
  - [Step-by-Step Setup](#step-by-step-setup)
- [⚙️ Configuration](#️-configuration)
- [📁 Folder Structure](#-folder-structure)
- [🔒 Security Implementations](#-security-implementations)
- [🤝 Contributing](#-contributing)
- [📄 License](#-license)
- [👨‍💻 Author & Contact](#-author--contact)

---

## 📌 Overview

The **NBTE Training Center Management System** is an enterprise-grade academic administration suite built for technical boards, vocational councils, and multi-franchise education networks. Developed natively in **PHP 8** and **MySQL PDO**, it digitizes the entire academic workflow from center accreditation and student admissions to examination grading, automated marksheet computation, and tamper-proof certificate issuance.

Every issued certificate, marksheet, and student ID card features dynamic **QR Code Instant Verification**, enabling employers, universities, and regulatory authorities to authenticate credentials instantly without manual paperwork.

---

## ✨ Key Features

### 🏢 Super Admin Portal
- **Centralized Control Hub**: Live dashboard with AJAX dynamic statistical widgets displaying active centers, enrolled students, pending approvals, revenue, and system throughput.
- **Training Center Accreditation**: Review, approve, suspend, or configure affiliated training institutions (`admins` table). Inspect center infrastructure, labs, faculty counts, and registration documents.
- **Academic Program & Course Mapping**: Manage multi-tier educational programs (Engineering, IT, Fire Safety, Nursery Teacher Training, etc.), associated diploma/certificate courses, and semester subjects.
- **Subject & Syllabus Engine**: Configure individual subjects with maximum theory marks, practical marks, and pass marks thresholds.
- **Student Verification & Approval**: Review student admissions submitted by training centers with instant status toggles (`Pending` -> `Approved`).
- **Global Financial Oversight**: Monitor center wallet balances, set admission fee tariffs, review Razorpay transaction logs, and process balance top-ups.
- **Broadcast Notifications**: Dispatch global administrative notices and updates across the entire training center network.

### 🏫 Training Center (Admin) Portal
- **Center Profile & Infrastructure Records**: Maintain institutional profiles, address records, contact information, head of center passport photos, and facility photos (computer labs, theory rooms, office).
- **Direct Student Admissions**: Multi-step student registration form capturing personal details, father's name, enrolled course, academic session, batch, and uploads for student photo, signatures, Aadhaar card, and qualification credentials.
- **Prepaid Wallet Billing**: Admissions automatically deduct credit from the center's prepaid balance, ensuring transparent accounting.
- **Live Razorpay Wallet Recharge**: Integrated payment gateway allowing center owners to recharge their admission balance via UPI, Credit/Debit cards, and Net Banking.
- **Examination & Marks Entry**: Enter theory and practical marks per student with real-time total, percentage, result (Passed/Failed), and letter grade calculation (`A`, `B`, `C`, `D`).
- **Attendance Sheets**: Generate printable, formatted student batch attendance rosters ready for examination centers.

### 🖨️ High-Definition Document Generation (TCPDF Engine)
The application includes a custom-engineered vector graphics PDF generator designed to institutional millimeter accuracy:

1. **Student Passing Certificate (`generate_certificate.php`)**:
   - Professional **A4 Landscape** format.
   - Dual navy and gold border framing with non-overlapping 24mm corner polygons.
   - Faded 0.08 alpha NBTE seal watermark centered on the background.
   - Official institutional header: Board name in Sky Blue (`#00AEEF`), Trust line, Ministry of Corporate Affairs incorporation details, and bold red `Original Certificate`.
   - Gold-framed student passport photo.
   - Direct encrypted QR code linking to the live public verification URL.
   - Accreditation badges (JAS-ANZ & AB-CAB), official embossed gold hologram seal, and authorized Director signature.
   - Automatic disk caching (`uploads/certificates/`) and database link synchronization.

2. **Official Marksheet (`generate_marksheet.php`)**:
   - Standard **A4 Portrait** format matching regulatory board standards.
   - Full student profile header: Enrollment No., Candidate Name, Father's Name, Course, Session, Batch, and Study Center.
   - Continuous vertical tabular column dividers spanning down to the Grand Total without row-break clutter.
   - Automatic computation of Theory Marks, Practical Marks, Grand Total, Percentage, Result Status, and Letter Grade with an official Grade Explanation Legend.
   - Live Verification QR code and dual Controller/Director signatures.

3. **Student ID Card (`generate_id_card.php`)**:
   - Double-sided **CR80 standard** badge dimensions (90mm × 68mm).
   - **Front Side**: Clean white background header container with responsive font sizing (`nbte_fit_font_size`), institutional logo, full legal tagline, student photo, and core academic details.
   - **Back Side**: Student residential address, Study Center Address fetched from `admins.institute_address`, student signature, and authorized center signature.
   - Embedded "Scan to Verify" micro QR code.

4. **Training Center Affiliation Certificate (`generate_admin_certificate.php`)**:
   - Formal institutional accreditation certificate recognizing the center's authority to conduct training programs.
   - Center Name framed in a dual-line gold-accented display box.
   - Center Head's passport photo from `admins.passport_pic`.
   - Public affiliation verification QR code.

### 🔍 Public Instant Verification System
- **`verify_student.php`**: Instant web-based verification by scanning the QR code on a student's certificate, marksheet, or ID card. Displays student bio, photo, enrolled course, center affiliation, and examination results.
- **`verify_admin_certificate.php`**: Validates training center affiliation credentials and validity status.
- **`verify_marksheet.php` & `verify_certificate.php`**: Direct document validation engines.

### 💳 Integrated Wallet & Online Recharge
- Automated credit management for training centers.
- Seamless Razorpay checkout integration with server-side signature verification (`razorpay_handler.php`, `create_order.php`).
- Complete ledger of balance top-ups, transaction IDs, payment statuses, and admission deductions (`wallet_transactions`).

---

## 🖼️ Screenshot Gallery

| Module | Description | Preview |
|:---|:---|:---:|
| **Official Student Certificate** | High-definition A4 landscape passing certificate with gold corner polygons, accreditation seals & QR code. | `assets/screenshots/certificate_preview.png` |
| **Academic Marksheet** | Official semester/annual marksheet with continuous vertical dividers and auto-computed grades. | `assets/screenshots/marksheet_preview.png` |
| **Student ID Card (Front & Back)** | CR80 standard badge with dynamic responsive header and center address from `admins.institute_address`. | `assets/screenshots/id_card_preview.png` |
| **Admin Affiliation Certificate** | Official recognition certificate for approved training centers with center head photo. | `assets/screenshots/admin_certificate_preview.png` |
| **Super Admin Dashboard** | Real-time analytics, center overview, student admissions, and financial statistics. | `assets/screenshots/dashboard_preview.png` |
| **Student Verification Portal** | Public verification view displaying student credentials upon scanning the QR code. | `assets/screenshots/verification_preview.png` |

*(Place screenshots in your repository under `assets/screenshots/` to display them directly)*

---

## 🛠️ Technology Stack & Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                       PRESENTATION LAYER                        │
│   HTML5  •  CSS3  •  Bootstrap 5.3  •  FontAwesome 6  •  ES6 JS │
└────────────────────────────────┬────────────────────────────────┘
                                 │ AJAX / HTTP Requests
┌────────────────────────────────▼────────────────────────────────┐
│                       APPLICATION LAYER                         │
│   PHP 8.x Native MVC-Style Architecture                         │
│   ├── Authentication & Role-Based Access Control (RBAC)         │
│   ├── PDF Vector Engine (TCPDF 6.x with custom canvas overrides)│
│   ├── Payment Gateway Integration (Razorpay REST APIs)          │
│   └── Input Sanitization, CSRF Token Security & Error Handling │
└────────────────────────────────┬────────────────────────────────┘
                                 │ PDO Prepared Statements
┌────────────────────────────────▼────────────────────────────────┐
│                          DATA LAYER                             │
│   MySQL Database (InnoDB Engine, UTF-8mb4 Character Set)        │
│   13 Interconnected Relational Tables                           │
└─────────────────────────────────────────────────────────────────┘
```

- **Backend**: PHP 8.0 / 8.1 / 8.2 (Vanilla, high performance, zero framework overhead)
- **Database**: MySQL 5.7+ / 8.0+ / MariaDB (InnoDB, Foreign Key Constraints)
- **PDF Generation**: [TCPDF](https://tcpdf.org/) (Custom geometry, alpha channels, 300 DPI image flattening)
- **Frontend**: Bootstrap 5.3, Vanilla JavaScript (ES6+), FontAwesome Icons
- **Payment Processing**: Razorpay Checkout API
- **QR Code Engine**: Built-in 2D Barcode Generator (ECC Level H / M)

---

## 🗄️ Database Architecture

The system utilizes an optimized MySQL schema consisting of 13 primary relational tables:

| Table Name | Description | Key Relationships |
|:---|:---|:---|
| `admins` | Super admins & affiliated training center records, center credentials, infrastructure & address details. | Master entity for center users. |
| `admin_programs` | Many-to-many mapping between training centers and permitted educational programs. | `admin_id` ⟷ `admins.id`, `program_id` ⟷ `programs.id` |
| `programs` | Academic programs (e.g., Computer Engineering, Vocational Arts, Fire Safety). | Parent to courses. |
| `courses` | Specific certificate & diploma courses linked to programs. | `program_id` ⟷ `programs.id` |
| `subjects` | Individual curriculum subjects categorized by theory & practical marks. | `course_id` ⟷ `courses.id` |
| `students` | Registered student profiles, photos, identity documents, enrollment & roll numbers. | `admin_id` ⟷ `admins.id`, `course_id` ⟷ `courses.id` |
| `marks` | Student subject-wise marks entries (theory marks, practical marks, total). | `student_id` ⟷ `students.id`, `subject_id` ⟷ `subjects.id` |
| `certificates` | Generated student passing certificate records and cached PDF paths. | `student_id` ⟷ `students.id` |
| `admin_certificates` | Generated training center affiliation certificate records and cached PDF paths. | `admin_id` ⟷ `admins.id` |
| `wallet_transactions`| Ledger of center wallet balance recharges, payments, and student fee deductions. | `admin_id` ⟷ `admins.id` |
| `notifications` | Internal system alerts and announcements. | System-wide broadcasting. |
| `messages` / `public_messages` | Inquiries and communications between centers and super admin. | Center-to-Admin messaging. |

---

## 🚀 Quick Start & Installation

### Prerequisites
- **Web Server**: Apache 2.4+ (XAMPP, WampServer, LAMP, or Nginx)
- **PHP**: Version 8.0 or higher
  - Required Extensions: `pdo_mysql`, `gd`, `fileinfo`, `mbstring`, `curl`, `openssl`
- **Database**: MySQL 5.7+ or MariaDB 10.3+

### Step-by-Step Setup

1. **Clone the Repository**:
   ```bash
   git clone https://github.com/najeebahmad07/NBTE-Training-Center-Management-System.git
   cd NBTE-Training-Center-Management-System
   ```

2. **Move to Web Directory**:
   - If using **XAMPP on Windows**, move or symlink the folder into `C:\xampp\htdocs\nbteapp`.
   - If using **Linux (Apache)**, place it in `/var/www/html/nbteapp`.

3. **Import Database**:
   - Open **phpMyAdmin** (`http://localhost/phpmyadmin`) or MySQL CLI.
   - Create a new database named `nbte`:
     ```sql
     CREATE DATABASE nbte CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
     ```
   - Import the database dump provided in the project repository (`nbte.sql` or your initial schema backup).

4. **Configure Database Credentials**:
   - Open [`includes/db.php`](includes/db.php) and verify your connection parameters:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_NAME', 'nbte');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     ```

5. **Configure Application URLs & Credentials**:
   - Check [`config.php`](config.php) to set your domain name, organization title, and Razorpay API credentials:
     ```php
     define('RAZORPAY_KEY_ID', 'rzp_test_YOUR_KEY_HERE');
     define('RAZORPAY_KEY_SECRET', 'YOUR_SECRET_KEY_HERE');
     ```

6. **Directory Permissions**:
   Ensure the following storage directories are writable by the web server process:
   ```bash
   chmod -R 775 uploads/
   chmod -R 775 uploads/certificates/
   chmod -R 775 uploads/marksheets/
   chmod -R 775 uploads/id_cards/
   chmod -R 775 uploads/photos/
   chmod -R 775 uploads/admin/
   ```

7. **Launch the Application**:
   Navigate to `http://localhost/nbteapp/` in your web browser.

---

## ⚙️ Configuration

Key configuration files:
- **`includes/db.php`**: Database connection settings using PDO.
- **`includes/auth.php`**: Session controls, password hashing, user authentication, and RBAC middleware.
- **`config.php`**: General app configuration, Razorpay keys, and constants.
- **`includes/header.php` & `includes/sidebar.php`**: Dynamic navigation menus and dark/light theme switching.

---

## 📁 Folder Structure

```
nbteapp/
├── assets/
│   ├── css/
│   │   └── style.css                 # Master theme styling & responsive layout
│   ├── images/
│   │   ├── logo.jpg                  # Official board logo
│   │   ├── certificate_seal.png      # Gold embossed hologram seal
│   │   ├── seal.jpg                  # Official rubber stamp seal
│   │   ├── authorized_signature.jpg  # Digital director signature
│   │   ├── controller_signature.jpg  # Controller of examination signature
│   │   └── accreditation_logos.png   # JAS-ANZ & AB-CAB accreditation logos
│   └── js/
│       ├── app.js                    # Core frontend interactive logic
│       └── theme-toggle.js           # Light / Dark mode switcher
├── includes/
│   ├── auth.php                      # RBAC, user session & password security
│   ├── csrf.php                      # Cross-Site Request Forgery tokens
│   ├── db.php                        # PDO database singleton connection
│   ├── header.php                    # Standard HTML header & metadata
│   ├── sidebar.php                   # Role-filtered dashboard navigation
│   └── footer.php                    # Dashboard footer & JS scripts
├── lib/
│   └── TCPDF/                        # TCPDF library engine
├── uploads/
│   ├── admin/                        # Center head photos & registration docs
│   ├── certificates/                 # Cached passing & center affiliation PDFs
│   ├── documents/                    # Student admission credentials & Aadhaar
│   ├── id_cards/                     # Cached student ID card PDFs
│   ├── marksheets/                   # Cached student marksheet PDFs
│   ├── photos/                       # Student passport photos
│   └── signatures/                   # Uploaded student & center signatures
├── add_student.php                   # Student admission portal
├── admin_management.php              # Training center onboarding & oversight
├── approve_student.php               # Student approval workflow
├── attendance_sheet.php              # Printable examination attendance roster
├── course_management.php             # Course catalog manager
├── dashboard.php                     # Role-tailored control dashboard
├── generate_admin_certificate.php    # Training Center Affiliation Certificate PDF
├── generate_certificate.php          # Official Student Passing Certificate PDF
├── generate_id_card.php              # Dual-sided CR80 Student ID Card PDF
├── generate_marksheet.php            # Official Student Marksheet PDF
├── marks_entry.php                   # Examination grading & result entry
├── program_management.php            # Program hierarchy manager
├── razorpay_handler.php              # Razorpay webhook & verification
├── students.php                      # Student registry & search filters
├── subject_management.php            # Course subject & marks threshold manager
├── verify_admin_certificate.php      # Public Center Affiliation Verification
├── verify_certificate.php            # Public Passing Certificate Verification
├── verify_marksheet.php              # Public Marksheet Verification
├── verify_student.php                # Universal Student QR Verification Landing
├── wallet.php                        # Center prepaid wallet & payment history
└── README.md                         # Documentation
```

---

## 🔒 Security Implementations

- **Parameterized Queries**: 100% of database queries use PDO prepared statements to eliminate SQL Injection risks.
- **CSRF Token Protection**: Forms are guarded against Cross-Site Request Forgery via dynamic token validation (`includes/csrf.php`).
- **Role-Based Access Control (RBAC)**: Strict role demarcation between `super_admin` and `admin` (Center Heads), preventing horizontal privilege escalation.
- **Strict File Upload Validation**: Uploads are restricted by MIME type, file extension whitelist (`jpg`, `png`, `pdf`, `webp`), size limits, and randomized filename storage to prevent arbitrary script execution.
- **Secure Password Hashing**: Passwords stored using industry-standard `PASSWORD_DEFAULT` (Bcrypt) hashing algorithms.
- **Anti-Tampering PDF Seals**: Official documents render embedded vector QR codes, transparent security watermarks, and verification endpoints that pull live server data.

---

## 🤝 Contributing

Contributions, issues, and feature suggestions are welcome!

1. Fork the Project
2. Create your Feature Branch (`git checkout -b feature/AmazingFeature`)
3. Commit your Changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the Branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

---

## 📄 License

Distributed under the **MIT License**. See [`LICENSE`](LICENSE) for more information.

---

## 👨‍💻 Author & Contact

**Developed with expertise by Najeeb Ahmad 07**

- **GitHub**: [@najeebahmad07](https://github.com/najeebahmad07)
- **Repository**: [NBTE-Training-Center-Management-System](https://github.com/najeebahmad07/NBTE-Training-Center-Management-System)
- **Email**: [njbdev07@gmail.com](mailto:njbdev07@gmail.com)

⭐ **If you find this project useful, please consider giving it a Star on GitHub!**
