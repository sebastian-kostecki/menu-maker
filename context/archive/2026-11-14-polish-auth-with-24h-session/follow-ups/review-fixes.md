# Follow-ups from implementation review

## F3 — Environment defaults outside local dev (carry into F-02, `public-deploy-with-queue-worker`)

- Production `.env` must set `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, credentials and `MAIL_FROM_ADDRESS` to a real SMTP provider. The new `.env.example` default (`smtp` on host `mailpit`) makes `POST /forgot-password` return HTTP 500 for existing accounts when no mail server is reachable.
- Production `.env` must set `SESSION_LIFETIME=1440` or leave it unset. An `.env` copied from the old example keeps `120` and overrides the new config default.
- Local `.env` still has `SESSION_LIFETIME=120` and `MAIL_FROM_ADDRESS="hello@example.com"`: update by hand before manual checks 1.7 and 3.6.
- `/register` and `/forgot-password` have no route throttle beyond the 60 s broker throttle. Review once real SMTP is live.
