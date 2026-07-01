# Website Prof

Modern PHP website with:
- Docker
- Nginx
- PHP-FPM
- MariaDB

## Setup

```bash
docker-compose build
docker-compose up -d

## 🚀 Latest Updates: Authentication & User Management System

Implemented a secure, server-side authentication and student management system with the following enterprise-grade security features:

* **Role-Based Access Control (RBAC):** Distinct session handling to separate access between `admin` dashboards and student learning modules (`index.php`), completely preventing unauthorized authorization bypass.
* **Secure Student Self-Registration:** Integrated dynamic user creation with high-level password encryption utilizing PHP's `password_hash()` (Bcrypt).
* **Anti-Spam Registration Token:** Implemented a secure access-token verification mechanism on the signup form to block automated bot registrations and prevent resource overhead on the server.
* **Administrative User Management:** Added a lightweight, secure User Directory Dashboard for administrators to review and manage student directories (Read & Delete functionality) with built-in Cross-Site Scripting (XSS) mitigation via `htmlspecialchars()`.
* **Mobile-Responsive Admin Interface:** Refactored administrative data tables with overflow-x safety layers for seamless maintenance and server monitoring via mobile devices.