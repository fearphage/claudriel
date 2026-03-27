# Google Sign-In Design

**Date:** 2026-03-27
**Issue:** TBD (create after approval)
**Milestone:** TBD

## Problem

Claudriel only supports email/password registration. Now that Google OAuth is verified, users should be able to sign up and log in with their Google account directly.

## Design

### Flow

1. User clicks "Sign in with Google" on `/login` or `/signup`
2. Redirect to Google OAuth with `openid email profile` scopes only (identity, not service access)
3. Google callback returns email, name, and email_verified status
4. **Existing account (same email):** log them in, link Integration if not already linked
5. **No account:** create one (no password, `status=active`, `email_verified_at=now`), create Integration, log them in, redirect to onboarding
6. Password-less accounts cannot use the email/password login form; show "Use Google sign-in" message

### Scope Separation

Two distinct OAuth flows share the same Google client credentials but request different scopes:

| Flow | Purpose | Scopes | Trigger |
|---|---|---|---|
| **Sign-in** | Identity only | `openid email profile` | `/auth/google/signin` |
| **Connect services** | Gmail, Calendar, Drive access | `gmail.readonly gmail.send calendar.readonly ...` | `/auth/google/redirect` (existing) |

The sign-in flow does NOT request Gmail/Calendar/Drive. Those are requested later when the user explicitly connects services from their settings.

### Files to Modify

| File | Change |
|---|---|
| `GoogleOAuthController` | Add `signin()` and `signinCallback()` methods with identity-only scopes |
| `AccountServiceProvider` | Register new routes: `GET /auth/google/signin`, `GET /auth/google/signin/callback` |
| `PublicAccountSignupService` | Add `createFromGoogle(email, name): Account` method |
| `PublicSessionController::login()` | Reject password-less accounts with "Use Google sign-in" message |
| `templates/public/login.twig` | Add "Sign in with Google" button |
| `templates/public/signup.twig` | Add "Sign in with Google" button |

### Account Creation from Google

```
GoogleOAuthController::signinCallback()
  → fetch userinfo from Google (email, name, email_verified)
  → look up Account by email
  → if found: start session, link Integration if missing
  → if not found: PublicAccountSignupService::createFromGoogle()
      → create Account (password_hash=null, status=active, email_verified_at=now)
      → create Integration (provider=google, account_id=uuid, access_token, refresh_token)
      → start session
      → redirect to onboarding
```

### Edge Cases

- **Google email matches existing password account:** Link and log in. User can still use either method.
- **Google email not verified:** Reject sign-in (should not happen in practice but guard against it).
- **Password-less user tries email/password login:** Show message: "This account uses Google sign-in" with link to the Google sign-in button.
- **User later wants a password:** Use the existing forgot-password flow to set one.
- **Token storage:** Sign-in flow stores access_token and refresh_token in Integration, but with identity-only scopes. When user later connects services, the Integration is updated with broader scopes.

### What Stays Unchanged

- Existing email/password signup and login
- `GoogleTokenManager` (token refresh)
- Integration entity schema
- Session handling (`$_SESSION['claudriel_account_uuid']`)
- CSRF protection

### Testing

- Unit: `createFromGoogle()` creates account with null password_hash, active status, verified email
- Unit: signin callback links existing account when email matches
- Unit: email/password login rejects password-less accounts with correct message
- Integration: full OAuth redirect → callback → session established
