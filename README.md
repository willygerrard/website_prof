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

## 🎯 Recent Updates: Class Targeting, Topic Tagging & Short-Answer Questions

A larger round of changes aimed at making quiz deployment more precise (right questions,
right class, right time in the term) and adding a second question type beyond multiple choice.

* **Class-Targeted Deployment:** Quizzes can now be deployed to one or more specific classes
  (e.g. "X TKJ 1" and "X TKJ 2" but not "XI SIJA") instead of being visible to every student in
  the same category/level. Students only see quizzes meant for their class, both in the listing
  page and when accessing a quiz directly by URL — the class check happens server-side on both
  ends, not just by hiding the card.
* **Topic Tagging (`materi`):** Questions can optionally be tagged with a specific topic (e.g.
  "Subnetting", "IP Addressing") independent of category/level. Deployment can then be scoped to
  only the topics already covered in class, so students don't get quizzed on material taught two
  months from now. Leaving this untagged still works — the quiz just pulls from the whole
  category/level pool like before.
* **Per-Deployment Attempt Tracking:** Previously, redeploying the same category/level (e.g.
  running "Network — Beginner" three times across a semester) pooled all attempts and best
  scores together, which quietly ate into students' 4-attempt limit across unrelated deployments.
  Results are now tied to the specific deployment (`sesi_id`), so each redeploy gets its own
  attempt pool and shows up as a distinct row in the gradebook.
* **Short-Answer Questions (v1):** Added a second question type alongside multiple choice.
  Teachers list accepted answer variants per question (e.g. "IP address", "alamat IP", "Internet
  Protocol"); grading normalizes both the student's answer and each accepted variant (lowercase,
  strip punctuation, collapse whitespace) before comparing, so formatting differences don't
  count as wrong. Every short-answer response is logged with a match/no-match flag so I can spot
  questions where students keep giving reasonable answers that weren't anticipated, and add them
  as accepted variants.
* **Anti-Tampering & Session Safety:** The set of question IDs served to a student is now pinned
  server-side in their session and re-validated on submit, so answers can no longer be forged via
  DevTools by injecting arbitrary question IDs. Quiz sessions also auto-expire after 2x their
  configured duration as a safety net in case a session is left open by mistake, and students get
  a visible warning if they switch tabs mid-quiz.
* **Fixed a WhatsApp Delivery Bug:** Found and fixed a double-prefixing bug in the Fonnte
  integration where phone numbers starting with "0" were manually converted to "62..." *and* the
  API's own auto-prefix parameter was left pointed at "62", producing a malformed number for the
  majority of parent contacts. The same bug existed independently in a second, unrelated project
  (RekapMapel) that shares the same WhatsApp provider.
* **Closed an Access Control Gap:** During testing, found that students could reach several
  admin-only pages (quiz management, bulk actions) by guessing the URL, because those pages only
  checked "is logged in" and not "is admin" — role checks were missing on a few endpoints added
  after the original admin pages were built. All of them now consistently check role, not just
  login status.
* **Real Names, Separate from Login Username:** Added a `nama_asli` (full name) field, collected
  at signup and editable later, used in report cards and WhatsApp notifications instead of the
  student's login username — usernames aren't always something a parent would recognize.
* **Reduced Duplication Across Question-Entry Points:** Manual entry, AI-assisted text import,
  and bulk edit all used to have their own copies of the question-parsing logic and their own
  INSERT/UPDATE queries. Consolidated the parser into one shared file and the database write
  logic into one shared helper, after a bug (a metadata field silently not saving) showed up in
  one entry point but not the others — same root cause, just duplicated three ways.

### Known Gaps (being upfront about this)

This is a solo school project (SIJA/TKJ subject), not a production system, so a few things are
intentionally left as-is for now:

* Single-item delete (`?hapus=<id>`) still uses a GET request and isn't CSRF-protected yet —
  only the bulk actions are covered so far.
* Short-answer questions aren't supported yet in AI-assisted import or bulk edit — for now they
  can only be added and edited one at a time through the manual form.
* No dedicated review screen yet for short-answer responses flagged as "no automatic match" —
  the data is logged, but I'm currently just eyeballing it via a database query rather than a
  proper UI.
* Topic tags (`materi`) are free text with autocomplete-style suggestions, not a controlled
  vocabulary — nothing stops slightly inconsistent naming over time (e.g. "Subnetting" vs
  "subnetting dasar") beyond my own discipline when tagging.
* If this ever needs to support multiple teachers across different subjects, the schema will
  need a proper `guru`/`mapel` relation — not something I've built out since there's no real
  need for it yet at this scale.

I'd rather list these honestly than pretend the project is more airtight than it is — happy to
revisit any of them if the scope actually calls for it.