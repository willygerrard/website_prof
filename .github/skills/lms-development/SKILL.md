---
name: lms-development
description: 'Guidance, standards, and workflow for LMS project development, including PHP/JS code style, prepared SQL statements, role-based access control (Admin/Student), grade calculations, query optimization, and XSS/SQLi security.'
argument-hint: 'Task description or feature to implement in the LMS project'
user-invocable: true
disable-model-invocation: false
---

# LMS Project Rules & Development Workflow

This skill provides guidelines, standards, and step-by-step procedures for developing and maintaining the Learning Management System (LMS) codebase.

## 1. Tech Stack & Coding Style

- **Primary Languages**: PHP and JavaScript (with Tailwind CSS for UI components).
- **Database Access**: **ALWAYS** use prepared statements (`mysqli_prepare` / `PDO::prepare`) for all database queries to prevent SQL injection and enable query plan caching.
- **Concise Outputs**: Keep code modifications precise and edit only necessary code blocks.

## 2. Business Logic & Architecture

- **Multi‑Tenant / Role‑Based Access Control**:
  - Roles: `Admin` and `Student` (`Siswa`).
  - Verify user authorization and session permissions on every endpoint before performing actions or returning data.
  - Admin users have full management capabilities; Student users have read‑only access to their own data and limited write actions (e.g., submitting assignments, taking quizzes).
- **Grade Calculations**:
  - All calculated scores, GPA, or quiz grades must be rounded to exactly **2 decimal places** (`round($score, 2)` in PHP or `Number(score.toFixed(2))` in JS).

## 3. Security & Performance Guidelines

- **Input Validation & Sanitization**:
  - Validate all inbound requests (`$_GET`, `$_POST`, `$_REQUEST`).
  - Sanitize string inputs using prepared statements for database operations and HTML entity encoding (`htmlspecialchars($str, ENT_QUOTES, 'UTF-8')`) when rendering output to prevent XSS.
  - Enforce CSRF protection tokens on state‑changing forms and endpoints.
- **Database Query Optimization**:
  - Ensure proper indexing on foreign keys, user IDs, and filter columns (e.g., status, module_id, student_id).
  - Avoid `SELECT *` on large tables; select only required columns.
  - Monitor and optimize queries to prevent high CPU utilization on Netdata system monitoring.

## 4. Development Workflow

1. **Understand Requirements & Impact**:
   - Determine which user roles (`Admin` or `Student`) are affected.
   - Identify affected database tables and endpoints.
2. **Implement Backend & Database Queries**:
   - Write PHP code using prepared statements.
   - Apply role checks and input sanitization.
   - Ensure grade calculations use 2‑decimal rounding.
3. **Implement Frontend / UI**:
   - Use Tailwind CSS and clean JavaScript.
   - Ensure responsive design and clear user feedback.
4. **Verification & Quality Checks**:
   - Verify SQL injection / XSS prevention.
   - Confirm proper execution without high database CPU overhead.

## 5. Pre‑Commit Quality Checklist

- [ ] Are all database queries using prepared statements with bound parameters?
- [ ] Is input sanitized and HTML‑encoded where appropriate?
- [ ] Are role checks enforced for the specific action/endpoint (Admin vs. Student)?
- [ ] Are grade and score calculations rounded to 2 decimal places?
- [ ] Are SQL queries optimized (using indexed columns and specific field selections)?