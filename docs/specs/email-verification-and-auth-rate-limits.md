# Tambahkan verifikasi email Pelanggan dan rate limit autentikasi

## Problem Statement

Pelanggan yang mendaftar saat ini langsung dibuat aktif dan dapat login tanpa membuktikan bahwa alamat emailnya benar-benar dimiliki. Endpoint pendaftaran, login, dan pengiriman ulang email juga belum memiliki perlindungan yang memadai terhadap spam dan percobaan berulang. Akibatnya, toko berisiko menerima akun dengan email palsu, mengirim email secara berlebihan, dan menghadapi percobaan kredensial otomatis.

## Solution

Tambahkan alur verifikasi email bawaan Laravel 12 yang disesuaikan untuk Toko Sinar Manis. Pelanggan baru otomatis login dalam keadaan terbatas, menerima email verifikasi berbahasa Indonesia melalui queue dan SMTP generik, lalu menjadi Pelanggan Terverifikasi setelah membuka signed URL yang sah dalam 60 menit.

Pelanggan yang belum terverifikasi tetap dapat memakai fitur publik, termasuk katalog, keranjang berbasis sesi, dan Rekomendasi Publik. Checkout, riwayat transaksi, ulasan, verifikasi pembayaran, serta Rekomendasi Personal hanya tersedia bagi Pelanggan Terverifikasi. Seluruh pelanggan lama diakui sebagai terverifikasi saat migrasi agar tidak kehilangan akses.

Tambahkan rate limit berlapis pada pendaftaran, login gagal, dan pengiriman ulang email verifikasi. Gunakan cache database yang sudah tersedia agar limit bertahan antar-request dan dapat digunakan pada deployment saat ini.

## User Stories

1. As a calon Pelanggan, I want to register with my email address, so that I can create an account for shopping.
2. As a newly registered Pelanggan, I want to be logged in automatically in a limited state, so that I can immediately see what must be done next.
3. As a newly registered Pelanggan, I want to land on a clear email verification notice, so that I understand that my account is not yet fully available.
4. As a newly registered Pelanggan, I want a verification email sent to my inbox, so that I can prove ownership of my email address.
5. As a newly registered Pelanggan, I want the email to use Toko Sinar Manis branding and Bahasa Indonesia, so that the message is recognizable and understandable.
6. As a newly registered Pelanggan, I want a clear “Verifikasi Email” action in the email, so that completing verification is straightforward.
7. As a newly registered Pelanggan, I want the verification email to be queued, so that registration remains responsive when SMTP is slow.
8. As a newly registered Pelanggan, I want registration to succeed independently of temporary SMTP latency, so that an email transport delay does not discard my account.
9. As a Pelanggan, I want a verification link to remain valid for 60 minutes, so that I have a reasonable period to use it without leaving it valid indefinitely.
10. As a Pelanggan opening a verification link while logged out, I want to be sent to login and returned to the verification flow afterward, so that I can complete verification safely.
11. As a Pelanggan, I want a verification link to work only for the matching account, so that another account cannot use my link.
12. As a Pelanggan, I want expired or invalid verification links to show an actionable Bahasa Indonesia message, so that I know to request a new link.
13. As a Pelanggan, I want to request another verification email, so that a deleted, delayed, or expired email does not permanently block me.
14. As a Pelanggan, I want resend confirmation in Bahasa Indonesia, so that I know the request was accepted.
15. As a Pelanggan, I want repeated resend clicks to be limited, so that accidental clicks do not generate excessive email.
16. As a Pelanggan, I want successful verification to redirect me to the Products page with a success message, so that I can continue shopping immediately.
17. As a Pelanggan Terverifikasi, I want checkout access, so that I can place an order.
18. As a Pelanggan Terverifikasi, I want transaction history access, so that I can review my purchases.
19. As a Pelanggan Terverifikasi, I want to submit product reviews, so that I can share feedback.
20. As a Pelanggan Terverifikasi, I want to verify payment status, so that my transaction can be synchronized safely.
21. As a Pelanggan Terverifikasi, I want Rekomendasi Personal to use my identity and history, so that suggestions are relevant to me.
22. As an unverified Pelanggan, I want to browse public home, catalog, product detail, and cart experiences, so that verification does not prevent product discovery.
23. As an unverified Pelanggan, I want to see only Rekomendasi Publik, so that unverified account data is not used for personalization.
24. As an unverified Pelanggan attempting a protected feature, I want to be redirected to the verification notice, so that the required next step is clear.
25. As an existing Pelanggan, I want my account recognized as verified during migration, so that this release does not unexpectedly remove my access.
26. As an Admin, I want admin login and admin features to remain unaffected by customer email verification, so that store operations continue normally.
27. As a store operator, I want excessive registration requests from one IP limited, so that automated account creation and email spam are reduced.
28. As a legitimate household or shared-network visitor, I want registration limits to allow reasonable retries, so that a shared IP is not blocked too aggressively.
29. As a store operator, I want repeated failed login attempts limited per normalized email and IP combination, so that credential attacks are slowed without globally blocking an email address.
30. As a legitimate Pelanggan, I want a successful login to clear my failed-login counter, so that prior mistakes do not reduce future access.
31. As a visitor who exceeds a limit, I want a clear Bahasa Indonesia rate-limit response with retry timing where available, so that I know when to retry.
32. As a deployer, I want mail transport configured entirely through environment variables, so that SMTP providers can be changed without code changes.
33. As a deployer, I want setup documentation for SMTP and the queue worker, so that real inbox delivery can be enabled safely in each environment.
34. As a developer, I want automated behavior-level tests for verification and throttling, so that future authentication changes do not silently weaken these protections.
35. As a developer, I want the established domain terms Pelanggan, Pelanggan Terverifikasi, Rekomendasi Publik, and Rekomendasi Personal used consistently, so that product behavior remains unambiguous.

## Implementation Decisions

- Use Laravel 12’s email-verification contract, registered event, signed verification request, standard verification route names, and verified middleware rather than building a parallel token system.
- Add a nullable email verification timestamp to customer records. New registrations leave it empty until verification succeeds.
- The migration must backfill the verification timestamp for every Pelanggan that exists when the migration runs. This preserves access for the 800 existing customers observed during planning. Fresh registrations after the migration remain unverified.
- Admin accounts are outside the customer verification requirement. Admin authentication and admin routes must not be gated by customer email verification.
- After registration, authenticate the new Pelanggan, regenerate the session, dispatch the registration event, and redirect to the verification notice.
- The verification notice requires authentication and clearly explains the pending state, email validity window, resend action, logout option, and access restrictions.
- The verification handler requires authentication and a valid signed URL. It must validate that the authenticated account matches the URL, mark the email verified idempotently, emit the framework verification event, and redirect to Products with a Bahasa Indonesia success message.
- Preserve the originally intended verification URL across login. Customer login should honor a safe intended destination before applying its normal Products redirect.
- Verification links expire after 60 minutes.
- Invalid or expired verification attempts must produce a branded, actionable Bahasa Indonesia response rather than an unexplained generic error. The response must not weaken signed-link or account-matching validation.
- Override the customer verification notification with a queued notification. Its subject, explanatory copy, expiry wording, and action label use Bahasa Indonesia and Toko Sinar Manis branding.
- Use the existing database-backed queue and existing worker topology. Delivery follows the worker’s existing maximum of three attempts.
- Use generic SMTP settings from environment configuration. Document the required mailer, host, port, scheme/encryption, username, password, sender address, sender name, and application URL. Never commit SMTP credentials.
- Keep local testing compatible with the array mailer and synchronous queue configured by the test environment.
- Apply verified access control to every customer checkout entry point, transaction history, review submission, and customer-facing payment verification. Existing authentication and role checks remain in place.
- Public home, catalog, product detail, cart, and recommendation pages remain available. Cart behavior remains session-based and is not newly gated.
- When the current session belongs to an unverified Pelanggan, recommendation behavior must act as anonymous for personalization: show Rekomendasi Publik, do not use customer history, and do not create identity-linked personal recommendation logs.
- The personal recommendation API must also fall back to Rekomendasi Publik for an unverified Pelanggan rather than expose personalized results.
- Configure registration throttling with two independent IP-based limits: 5 attempts per 10 minutes and 20 attempts per day. Both successful and invalid registration submissions count as attempts at the HTTP boundary.
- Configure failed-login throttling as 5 failures in 5 minutes, keyed by normalized lowercase email plus IP address. Invalid credentials, role mismatch, and inactive-account attempts count as failures. Validation failures that do not attempt authentication need not consume this counter.
- Clear the matching failed-login counter after successful authentication.
- Configure verification resend throttling with two independent customer-based limits: 1 request per minute and 5 requests per hour.
- A verified customer who reaches the resend endpoint must not receive another verification email and should be redirected to Products with an appropriate status message.
- Rate-limited responses use HTTP 429 and Bahasa Indonesia copy. Include a retry interval when Laravel exposes one.
- Normalize limiter keys and prefix each window so independent limits cannot collide.
- Continue using the existing cache database store for rate limiting; adding Redis is not required.
- Update customer-facing registration and login success/error states to explain verification where relevant without redesigning the overall authentication UI.
- Update the customer factory with explicit verified and unverified states so existing unrelated tests can intentionally choose their account status. Preserve compatibility for tests whose behavior assumes an account can use protected features.
- Use the project glossary’s canonical domain vocabulary. No ADR is required because this follows Laravel’s standard, reversible mechanism and introduces no surprising architectural lock-in.

## Testing Decisions

- Use Laravel HTTP feature tests as the primary and highest test seam. Tests issue real framework requests and assert observable responses, authentication state, database state, queued notifications, and rate-limit behavior.
- Prefer external behavior assertions over controller method calls, private method assertions, notification implementation details, or cache-key internals.
- Follow the project’s existing feature-test prior art: the shared application test case, database refresh isolation, model factories, route-name requests, response assertions, and database assertions.
- Registration tests cover successful account creation, automatic limited login, null verification timestamp, queued verification notification, verification-notice redirect, and duplicate/invalid input behavior.
- Verification tests cover a valid signed link, matching-account enforcement, guest login-and-return flow, idempotent reuse, expiration/invalid signature behavior, framework verification state, and Products success redirect.
- Resend tests cover successful queued delivery, verified-account behavior, one-per-minute enforcement, five-per-hour enforcement, and isolation between customers.
- Access-control tests cover checkout entry points, history, review submission, and payment verification for guests, unverified customers, verified customers, and admins where relevant.
- Recommendation tests cover public results for guests and unverified customers, personal results only for Pelanggan Terverifikasi, and absence of identity-linked personal logging before verification.
- Registration throttling tests cover both the 10-minute and daily windows, IP isolation, HTTP 429 behavior, and normal recovery after time advances.
- Login throttling tests cover only failed authentication attempts, normalized email-plus-IP isolation, role mismatch/inactive accounts, successful-counter clearing, HTTP 429 behavior, and recovery after five minutes.
- Email content tests assert recipient-visible behavior: Bahasa Indonesia subject/body/action, Toko Sinar Manis identity, and a verification URL with the expected expiry. They do not assert private class structure.
- Add one focused migration test that begins with a pre-feature customer schema/data state, runs the additive migration, and proves existing Pelanggan records are backfilled while the resulting column still permits unverified future registrations.
- Tests use time control where expiration or limiter windows matter and reset limiter state between cases.
- The full existing PHP test suite must continue to pass after factories and protected routes change.

## Out of Scope

- Selecting, purchasing, or creating an account with a specific SMTP provider.
- Committing production SMTP credentials or changing secrets in the tracked repository.
- DNS deliverability configuration such as SPF, DKIM, or DMARC.
- CAPTCHA, bot scoring, IP reputation services, or third-party abuse-prevention products.
- Password reset, passwordless login, multi-factor authentication, or social login.
- Verification of phone numbers.
- A workflow for changing an already verified email address.
- Automatic deletion or archival of accounts that remain unverified.
- Requiring email verification for Admin accounts.
- Replacing the database cache/queue with Redis or another infrastructure service.
- A general redesign of the authentication, catalog, or recommendation interfaces.
- Changes to recommendation algorithms beyond withholding personalization from unverified customers.

## Further Notes

- The application currently uses a custom authentication controller and a nonstandard customer primary key, so framework verification integration must respect the existing user model and session flow.
- The current active schema does not contain the verification timestamp even though an old backup migration does; implementation must change the active migration path safely.
- The current environment uses the log mailer. Real inbox delivery requires operators to switch to SMTP and provide valid environment settings.
- Both local development via the Composer development command and Docker deployment already have queue-worker paths. Documentation must also state the standalone queue-worker command for local setups that do not use the combined command.
- The application’s configured application URL must be externally correct because signed verification links are generated from it.
- The agreed domain definitions are recorded in the repository glossary.
