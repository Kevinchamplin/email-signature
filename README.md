# Ironcrest Email Signature Platform

> Built solo to prove I can own a full-stack product — from product strategy to polished UI, operations, and analytics.

**Live Demo:** https://apps.ironcrestsoftware.com/email-signature/ • **Stack:** PHP 8 · MySQL 8 · Tailwind · Vanilla JS · Mailgun • **Role:** Product designer, full-stack engineer, DevOps

---

## Why This Project Matters to Recruiters

1. **Product thinking:** This isn’t a toy app. It’s a free lead-gen tool that converts traffic into qualified leads via an email-capture/export flow, drip-ready automations, and analytics hooks.
2. **Shipping discipline:** Production deployed, error monitoring via Mailgun + logs, migration scripts, rollback plan, and docs (`SETUP.md`, `QUICKSTART.md`, `CHANGELOG.md`).
3. **Enterprise-ready architecture:** Modular PHP stack with PDO, layered services, 15-table schema (auth, sessions, analytics, org-level expansion), and future SaaS monetization baked in.
4. **UX craft:** 11 premium templates, micro-interactions, WCAG AA compliance, inline guides, reduced-motion support, responsive layout, and zero-framework JS with state management.
5. **Security mindset:** Rate limiting, CSRF tokens, prepared statements, IP hashing, EXIF stripping, Mailgun credential isolation, and environment-driven config.

Use this repo to evaluate my ability to take ambiguous goals (“create a useful free tool”) and deliver a production-ready product end-to-end.

---

## Highlights

| Area | Impact |
| --- | --- |
| **Acquisition** | Magic-link auth, grandfathered pricing flags, analytics events, and optional drip sequences for nurturing leads. |
| **Templating Engine** | 11 polished layouts driven by JSON config + Renderer class, enabling instant switching and future theme marketplace. |
| **Persistence Layer** | Clean PDO wrapper, transaction-safe CRUD, and tier-based feature gating (`sig_feature_access`) for future paid plans. |
| **User Experience** | Real-time preview, auto-save, profile auto-fill, CTA/disclaimer blocks, mobile-first design, install guides for Gmail/Outlook/Apple Mail. |
| **DevOps/Quality** | Composer autoloading, documented migrations, watchdog endpoint (`api/log-error.php`) with Mailgun alerts, and Git-friendly config strategy. |

---

## Architecture At a Glance

```text
email-signature/
├── api/                 # Stateless JSON endpoints (signatures, auth, render, export, uploads)
├── src/                 # Domain classes (Auth, Signature, Renderer, EmailService, ImageProcessor)
├── public/              # Tailwind-powered UI (wizard, dashboard, auth)
├── database/            # schema.sql + seeds + auth migrations
├── config/              # Environment-specific config (gitignored)
├── docs/                # SETUP/QUICKSTART/CHANGELOG for recruiters
└── tests/               # Manual sanity tools (test.php, Postman collections)
```

Key technical decisions:
1. **Passwordless-first auth** – magic links reduce friction, while still supporting upgrades to tiered accounts.
2. **State machine via Vanilla JS** – custom store manages templates, preferences, autosave timers, and gatekeeping for premium layouts.
3. **Mailgun integration** – centralized `EmailService` handles exports, notifications, and error alerts with cURL + signature logging.
4. **Feature gating** – database-driven, so pricing experiments don’t require code changes.
5. **Analytics-ready** – `sig_events`, `sig_activity_log`, and tracking pixels for signature usage, including abuse throttling.

---

## Feature Deep Dive

1. **Creation flow** – 3-step wizard (Template → Details → Export) with instant preview, CTA blocks, social icons, and accessibility guidance.
2. **Profile auto-fill** – users store their info once; every new signature auto-populates via `preferences.php` API.
3. **Export toolkit** – Copy HTML, Copy Visual (rich clipboard), Download `.html`, and Email delivery (Mailgun) with install guides.
4. **Signature management** – Full CRUD with autosave, duplicate, delete, and live previews inside dashboard.
5. **Analytics + error handling** – Events API, Mailgun alert endpoint, logs fallback, and Slack-ready payload structure.

---

## Technical Wins

1. **Security-first**
   - PDO prepared statements, strict validation, CSRF tokens, rate limiting, IP hashing.
   - Upload hardening: MIME/type checks, EXIF stripping, size enforcement, dedicated uploads directory.
   - Environment isolation: `.env` + `config.php` template pattern keeps secrets out of git.

2. **Performance & accessibility**
   - Zero build pipeline (pure ES modules + Tailwind CDN) keeps load time <1.5s on 3G.
   - IntersectionObserver-driven animations respect `prefers-reduced-motion`.
   - Componentized templates to avoid reflow thrash; lazy image loading.

3. **Scalability roadmap**
   - Database already models orgs, tiers, feature flags, analytics, and Stripe-ready subscriptions.
   - Auth service exposes hooks for OAuth/SAML if future enterprise customers require it.

---

## Setup (Recruiter-Friendly TL;DR)

```bash
git clone git@github.com:kevinchamplin/email-signature.git
cd email-signature
composer install
cp config/config.example.php config/config.php   # fill DB + Mailgun creds
mysql -u root -p < database/schema.sql
mysql -u root -p < database/seed_templates.sql
php -S localhost:8080 -t public
```

Credentials you’ll need:
- MySQL connection
- Mailgun (optional for local testing; fallbacks exist)

---

## Showcasing My Skills

1. **System Design:** Designed a SaaS-ready architecture with auth, feature flags, analytics, queue-ready exports, and separation of concerns.
2. **Frontend Craftsmanship:** Built a delightful UI without React—custom state management, responsive layouts, animations, and accessibility best practices.
3. **Backend Engineering:** Built reusable data layer, service classes, API endpoints, and error monitoring.
4. **DevOps/Process:** Produced documentation, migrations, logging, environment configs, and a changelog to mirror real-world workflow.
5. **Growth Mindset:** Includes marketing hooks (comparison table, pricing toggles), conversion tactics (capture modal, grandfathered tier), and upgrade path.

If you’re evaluating me for a full-stack/product role, this codebase demonstrates I can take a fuzzy idea, design a strategy, execute, iterate, and ship.

---

## Contact

- Email: kevin@kevinchamplin.com
- LinkedIn: https://www.linkedin.com/in/kevinchamplin/
- Portfolio: https://kevinchamplin.com/portfolio.php

Thanks for reviewing! I’m excited to bring this level of ownership and polish to your team.
