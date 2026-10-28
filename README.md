# 🎫 Ticketflow: Complaint Management System

A web-based complaint management system where complaints are submitted, assigned, tracked, and resolved as **tickets** through one organized platform.

Developed as a BCA project at **Hetauda School of Management and Social Sciences**, Tribhuvan University.

---

## 📌 About the Project

Complaints handled through phone calls, verbal messages, or scattered e-mails are easily lost, and nobody knows who is responsible. Ticketflow solves this by giving every complaint a **ticket number, an owner, a status, and a history**.

The system has three roles, and each role gets its own dashboard.

---

## ✨ Features

### 👤 User
- Register and log in
- Create tickets with subject, category, priority, description, and optional attachment
- View, edit, and delete own tickets
- Track ticket status and reply to tickets
- Notifications and profile management

### 🛠️ Support Staff
- View tickets assigned to them
- Search and filter by ticket number, subject, client, status, priority, and category
- Update ticket status and reply to users

### 🔑 Administrator
- System-wide statistics dashboard
- View and manage all tickets and assign them to staff
- Add and edit users and assign roles
- Manage ticket categories
- Audit trail of recent activity
- Reports page with filters and **CSV export**

### 🌐 General
- Role-based access control
- Light / dark display mode
- Notification indicator in the header
- File attachments (maximum 5 MB per file)
- Ticket numbers such as `TKT-2026-00001`
- Priority levels: Low, Medium, High, Urgent

---

## 🧰 Tech Stack

| Layer | Technology |
|---|---|
| Front end | HTML, CSS, JavaScript |
| Back end | PHP |
| Database | MySQL (accessed through PDO) |
| Server | Apache (XAMPP) |
| Version control | Git and GitHub |

---

## 🗄️ Database

Database name: `ticket_management_system`

| Table | Purpose |
|---|---|
| `users` | Registered users and their roles |
| `categories` | Ticket categories |
| `tickets` | Complaints |
| `ticket_messages` | Replies on tickets |
| `ticket_history` | Audit trail |
| `ticket_attachments` | Uploaded file details |
| `notifications` | User notifications |

---

## ⚙️ Installation (XAMPP)

### 1. Install XAMPP
Download and install [XAMPP](https://www.apachefriends.org/), then start **Apache** and **MySQL** from the XAMPP Control Panel.

### 2. Clone the repository
Open Git Bash inside `C:\xampp\htdocs\` and run:

```bash
git clone https://github.com/prajwal-ui50/4th-sem-project.git
```

### 3. Create the database
1. Open `http://localhost/phpmyadmin`
2. Click **New** and create a database named `ticket_management_system`
3. Select it, click **Import**, and choose `ticket_management_system.sql` from the project folder
4. If a file named `phase11_migration.sql` exists, import it too (it adds the attachments table)

### 4. Check the database connection
Open the database config file (for example `config.php` or `db.php`) and confirm:

```php
$host = "localhost";
$dbname = "ticket_management_system";
$username = "root";
$password = "";
```

### 5. Run the project
Open in your browser:

```
http://localhost/4th-sem-project/
```

Use the folder name you cloned into.

---

## 🔐 Default Login

Add your demo accounts here after importing the database:

| Role | Email | Password |
|---|---|---|
| Admin | your-admin-email | your-password |
| Staff | your-staff-email | your-password |
| User | your-user-email | your-password |

Or register a new account from the registration page.

---

## 📸 Screenshots

Add your screenshots to a `screenshots/` folder and link them like this:

```markdown
![Login](screenshots/login.png)
![User Dashboard](screenshots/user-dashboard.png)
![Admin Dashboard](screenshots/admin-dashboard.png)
![Reports](screenshots/reports.png)
```

---

## ⚠️ Limitations

- Web only, no mobile app
- In-app notifications only (no e-mail or SMS)
- Reports show counts and tables, not graphical charts
- Tested with a small amount of sample data

## 🚀 Future Enhancements

- E-mail and SMS notifications
- Graphical charts in reports
- Mobile-friendly app
- Automatic priority and category suggestion

---

## 👨‍💻 Author

**Prajwal Lamichhane**
BCA, Hetauda School of Management and Social Sciences, Tribhuvan University
Supervisor: Gunjan Gupta

GitHub: [@prajwal-ui50](https://github.com/prajwal-ui50)

---

## 📄 License

This project was developed for academic purposes.
