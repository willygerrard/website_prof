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

## 🛠️ Recent Updates: Quiz Management Improvements

A few practical upgrades to make managing the quiz bank less tedious, plus some basic hardening
I picked up while working on this as a learning project:

* **Bulk Question Actions:** Added checkboxes to the quiz dashboard so I don't have to delete or
  edit questions one by one anymore. Bulk delete and bulk edit now share the same selection,
  which helps a lot now that the question bank has grown past a hundred items.
* **Bulk Edit Reuses the Existing AI Import Parser:** Instead of writing new parsing logic,
  bulk edit reuses the same text-parsing function from the AI Quiz Import feature. Selected
  questions get pre-filled into an editable text block, with a simple before/after preview so I
  can double-check what's actually changing before it hits the database.
* **Basic Transaction Safety:** Bulk updates run inside a PDO transaction, so if one question
  fails validation partway through, nothing gets half-saved.
* **CSRF Tokens on POST Forms:** Added session-based CSRF tokens to the forms that change data
  (question CRUD, bulk actions, notification toggle). Still learning the nuances here — for
  instance I initially over-applied it to the login form by mistake before realizing login
  endpoints don't really need it the same way state-changing actions do.
* **Nginx Rate Limiting on Login:** Added request rate limiting at the Nginx level to slow down
  brute-force login attempts. Stress-tested it with a bot hitting login and module pages
  aggressively — legitimate-looking single-attempt traffic never got blocked, while the
  aggressive bot traffic reliably tripped 429s. Ended up around a 90% success rate under
  intentionally hostile bot behavior, which I'm reading as "the limiter is doing its job," not
  as a bug to chase down further.

### Known Gaps (being upfront about this)

This is a solo school project (SIJA/TKJ subject), not a production system, so a few things are
intentionally left as-is for now:

* Single-item delete (`?hapus=<id>`) still uses a GET request and isn't CSRF-protected yet —
  only the bulk actions are covered so far.
* Access control currently only distinguishes `admin` vs everyone else. If this ever needs to
  support multiple teachers/subjects, the schema will need a proper `guru`/`mapel` relation —
  not something I've built out since there's no real need for it yet.

I'd rather list these honestly than pretend the project is more airtight than it is — happy to
revisit any of them if the scope actually calls for it.
