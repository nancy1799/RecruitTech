# RecruitTech - Project Structure Reference

## Project Description
RecruitTech is a custom WordPress plugin that turns a WordPress site into a 
multi-company recruitment platform. Companies post jobs and review candidates 
with AI assistance. Job seekers create profiles, upload CVs, and apply to jobs.

## Folder Structure
recruittech/
├── recruittech.php          (main plugin file)
├── admin/                   (company dashboard pages and logic)
├── public/                  (front-facing pages: job listing, job seeker views)
├── includes/                (shared core files: DB setup, registration, helpers)
├── ai/                      (AI integration: prompt building, API calls)
├── assets/                  (CSS, JS, images)
└── templates/                (page templates for jobs, profiles, dashboards)

## Database Tables

### companies
- id (PK)
- user_id (FK to wp_users)
- company_name
- description
- logo_url
- created_at

### company_documents
- id (PK)
- company_id (FK to companies)
- file_path
- doc_type
- extracted_text
- uploaded_at

### jobs
- id (PK)
- company_id (FK to companies)
- title
- description
- requirements
- skills_required
- experience_level
- status
- created_at

### job_seekers
- id (PK)
- user_id (FK to wp_users)
- full_name
- phone
- summary
- skills
- experience
- created_at

### cvs
- id (PK)
- job_seeker_id (FK to job_seekers)
- file_path
- extracted_text
- uploaded_at

### applications
- id (PK)
- job_seeker_id (FK to job_seekers)
- job_id (FK to jobs)
- status
- match_score
- ai_feedback
- created_at

### ai_interview_questions
- id (PK)
- application_id (FK to applications)
- question_text

### ai_analysis_log
- id (PK)
- application_id (FK to applications)
- prompt_sent
- response_received
- created_at

## Notes for AI assistant (Copilot)
- This is a beginner-level graduation project. Keep code simple, readable, 
  and avoid over-engineering or unnecessary abstraction layers.
- Use WordPress's $wpdb for all custom table operations.
- Use WordPress's built-in user system with custom roles (company, job_seeker) 
  for authentication, not a custom auth system.
- Always check this file before creating new files or tables to stay consistent 
  with existing naming and structure.

## AI Module (added)
- `includes/ai/class-ai-client.php` — talks to the ITI student AI gateway
  (`apiaccess.iti.net.eg`, NOT OpenAI or raw Bedrock). Reads the API key,
  model ID, and max_tokens from the options set on the Settings > RecruitTech AI
  admin page.
- `includes/ai/class-text-extractor.php` — dependency-free PDF/DOCX/TXT text
  extraction used for both CVs and company documents.
- `includes/ai/class-agent.php` — the Agent workflow. `recruittech_ai_analyze_application()`
  pulls the CV, job, and company documents (lightweight keyword-based RAG,
  see `recruittech_ai_get_company_documents_context()`), builds the prompt,
  calls the LLM, and saves match_score / ai_feedback / interview questions.
- `includes/ai-settings.php` — Settings > RecruitTech AI admin page for the
  gateway API key / model ID / max tokens.
- `includes/ai-ajax.php` — `wp_ajax_recruittech_analyze_candidate`, wired to
  the "Analyze with AI" button on the Company Applications page
  (`includes/dashboards.php`, `recruittech_company_applications_shortcode()`).
- `includes/company-documents.php` + `templates/company-documents.php` — lets
  a company upload/delete the hiring policy documents (`company_documents`
  table) that the Agent retrieves from.

### Caching (added)
- `applications.ai_input_hash` and the `job_fit_checks` table store a sha256
  hash of everything that went into the last AI call (job fields, CV text,
  company documents text). `recruittech_ai_analyze_application()` and
  `recruittech_ai_check_job_fit()` both recompute this hash on every request
  and only call the AI gateway again if it changed — otherwise they return
  the last saved result (`from_cache: true` in the response). Re-uploading a
  CV clears its cached `extracted_text` (see `recruittech_save_cv_file()`),
  which is what makes a new CV correctly bust the cache.

### Top 10 candidates (added)
- `recruittech_ai_rank_top_candidates()` in `class-agent.php` runs (or reuses
  the cached) analysis for every applicant on a job and returns the top 10 by
  match score. Triggered by the "Rank Top 10 with AI" button that appears on
  Company Applications once a specific job is selected in the new Job filter
  dropdown. AJAX action: `recruittech_rank_top_candidates`.

### Check My Fit (added)
- `recruittech_ai_check_job_fit()` in `class-agent.php` lets a job seeker see
  their match % and CV improvement tips for a job before applying. It never
  sends the company's internal hiring documents to the candidate. Button
  appears next to "Apply Now" on the job details page. AJAX action:
  `recruittech_check_job_fit`.

## Subscriptions Module (added)

Adds optional paid subscription plans for `company` and `job_seeker`
accounts. Everything defaults to fully free/unlimited: as long as
Settings > RecruitTech Subscriptions has "Enable Subscriptions" turned off,
`recruittech_subscription_is_enabled()` returns `false` and every
enforcement check below short-circuits to "allowed" — no other plugin
behavior changes.

### Database Tables (added)

**subscription_plans**
- id (PK), account_type (`company`/`job_seeker`), plan_name, duration_days,
  price, usage_limit (jobs per period for company, applications per period
  for job_seeker), ai_features (comma-separated feature keys, e.g.
  `analyze,rank_top10`), status (`active`/`inactive`), created_at, updated_at

**user_subscriptions**
- id (PK), user_id, account_type, plan_id, plan_name_snapshot,
  usage_limit_snapshot, ai_features_snapshot, usage_count, status
  (`pending`/`active`/`expired`/`cancelled`), starts_at, expires_at,
  created_at. Snapshots are taken at purchase time so editing/deleting a
  plan later never changes an existing subscriber's terms. Every renewal
  inserts a new row (never updates an old one), so this table also serves
  as each user's subscription history.

**subscription_transactions**
- id (PK), user_id, subscription_id, plan_id, gateway, gateway_order_id,
  gateway_transaction_id, amount, currency, status
  (`pending`/`success`/`failed`), raw_response, created_at, updated_at

### Files (added)
- `includes/subscriptions/class-subscription-manager.php` — plan lookup,
  `recruittech_subscription_get_current()` (the subscription currently in
  effect for a user), `recruittech_subscription_can_post_job()`,
  `recruittech_subscription_can_apply()`,
  `recruittech_subscription_can_use_ai_feature()`, and
  `recruittech_subscription_activate()` (renewal logic: if the user still
  has time left on their current/queued subscription, the new period is
  stacked to start right when it ends so no paid days are lost; otherwise
  it starts immediately).
- `includes/subscriptions/class-payment-gateway.php` — the
  `RecruitTech_Payment_Gateway` interface (`create_payment()`,
  `verify_webhook()`) any gateway must implement.
- `includes/subscriptions/class-subscription-cron.php` — the daily
  WP-Cron job that marks truly-expired subscriptions as `expired` (see
  "Notes / refinements" below).
- `includes/subscriptions/class-paymob-gateway.php` — `RecruitTech_Paymob_Gateway`,
  the PayMob Intention API implementation, plus the documented
  SHA-512 HMAC verification for its transaction callback.
- `includes/subscriptions-settings.php` — Settings > RecruitTech
  Subscriptions admin page: the master enable/disable toggle, PayMob
  credentials, and add/edit/delete forms for Company and Job Seeker plans
  (checkboxes for which AI features each plan grants).
- `includes/subscriptions-ajax.php` — `recruittech_handle_subscription_purchase()`
  (handles the "Subscribe Now" form, opens a `subscription_transactions`
  row, and redirects to PayMob), and `recruittech_ajax_paymob_webhook()`
  (`wp_ajax`/`wp_ajax_nopriv` action `recruittech_paymob_webhook` —
  verifies the HMAC, marks the transaction, and calls
  `recruittech_subscription_activate()` on success).
- `includes/subscriptions-page.php` + `templates/subscription-page.php` —
  the `recruittech_my_subscription` shortcode/page: current plan status and
  remaining usage, or a free/unlimited notice while subscriptions are
  disabled, plus the list of plans available to subscribe to.

### Enforcement (added)
- `includes/dashboards.php` — `recruittech_company_create_job_shortcode()`
  checks `recruittech_subscription_can_post_job()` before inserting a *new*
  job (editing an existing job is never blocked) and redirects to My
  Subscription if the limit is reached; on success it calls
  `recruittech_subscription_increment_usage()`. `recruittech_handle_job_application()`
  does the same with `recruittech_subscription_can_apply()` before inserting
  a new application.
- `includes/ai-ajax.php` — each of the three AJAX handlers
  (`recruittech_analyze_candidate`, `recruittech_rank_top_candidates`,
  `recruittech_check_job_fit`) calls
  `recruittech_subscription_can_use_ai_feature()` for its feature key
  (`analyze`, `rank_top10`, `check_fit`) and returns a clear "upgrade your
  plan" error instead of running the AI call when it's not included in the
  user's current plan.

### Notes / refinements
- **Job creation is handled on `init`, not inside the shortcode.**
  `recruittech_handle_job_creation_submission()` in `dashboards.php` (hooked
  to `init`, same pattern as `recruittech_handle_job_application()`) does
  all the validation/DB work and is the only place that redirects.
  `recruittech_company_create_job_shortcode()` now only loads a job for
  editing (GET) and displays whatever that handler left behind in
  short-lived, per-user transients (`recruittech_create_job_errors_{id}`,
  `..._error_message_{id}`, `..._success_{id}`, `..._form_data_{id}`) -
  calling `wp_safe_redirect()` from inside a shortcode fails with "headers
  already sent" because shortcodes run mid-page-render, after headers are
  sent.
- **No plans = free usage.** `recruittech_subscription_account_type_has_plans()`
  checks whether an account type has any `active` plan at all.
  `can_post_job()`, `can_apply()`, and `can_use_ai_feature()` treat "system
  enabled but zero plans for this account type" the same as "system
  disabled" (fully free), so turning subscriptions on never locks an
  account type out before the admin has actually created a plan for it. The
  My Subscription page shows the same free/unlimited message in that case.
- **Free plans (price = 0) activate instantly**, no PayMob call at all:
  `recruittech_handle_subscription_purchase()` calls
  `recruittech_subscription_activate()` directly and logs a `gateway =
  'free'`, `status = 'success'`, `amount = 0` row in
  `subscription_transactions` so the payment history stays complete.
- **PayMob failures keep the full response for debugging.**
  `RecruitTech_Paymob_Gateway::create_payment()` returns the entire PayMob
  response body as `WP_Error` data (not just a message), and
  `recruittech_handle_subscription_purchase()` stores it as JSON in
  `subscription_transactions.raw_response` on failure.
- **Daily cron keeps `status` truthful.** `includes/subscriptions/class-subscription-cron.php`
  registers a `daily` WP-Cron event (`recruittech_subscription_daily_cron`,
  scheduled on activation via `wp_schedule_event()`/`wp_next_scheduled()`,
  cleared on uninstall via `wp_clear_scheduled_hook()`) that flips any
  `user_subscriptions` row still marked `active` whose `expires_at` has
  passed to `expired`. `recruittech_subscription_get_current()` also does
  the same update lazily for the user/account_type it just looked up, so
  the column doesn't have to wait for cron on a low-traffic site. This is
  purely cosmetic/reporting - every enforcement check already compares
  `expires_at` to `NOW()` directly in SQL, so it never depended on the
  stored status word being fresh.