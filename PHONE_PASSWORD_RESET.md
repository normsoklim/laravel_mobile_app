# Firebase Phone OTP — Password Recovery

The customer resets their Tinh Shop password with their **phone number**:
Firebase Phone Authentication sends the SMS and verifies the OTP, the
Laravel API verifies the Firebase ID token and updates the password in
PostgreSQL.

```
Flutter (phone → SMS OTP) → Firebase ID token → Laravel API → PostgreSQL
```

The existing Laravel login (phone/email + password) is untouched.

## 1. Firebase console

1. **Authentication → Sign-in method → Phone**: enable the Phone provider.
2. The Flutter app must be registered with its SHA-1 fingerprint
   (Firebase console → Project settings → Your apps → Android app) or the
   SMS cannot be delivered.
3. For development, add *fictional* phone numbers + fixed codes in
   **Authentication → Phone → Phone numbers for testing**.
4. The project id of the app is `tinh-shop` (see
   `frontend/lib/firebase_options.dart`). The backend MUST use the same
   project id, otherwise the ID tokens are rejected.

## 2. Backend configuration (`.env`)

```dotenv
# The Firebase project the Flutter app uses (issuer + audience check).
FIREBASE_PROJECT_ID=tinh-shop

# The Firebase app ids that may issue ID tokens (comma separated).
FIREBASE_ALLOWED_AUDIENCES=1:1005743556864:android:b16df68ec11794ceeb8be5,1:1005743556864:ios:3cdbef06c1a5ce4ceb8be5

# A phone-authentication session older than this (seconds) is rejected.
FIREBASE_PHONE_AUTH_MAX_AGE=600

# Country code used to normalise locally formatted phone numbers.
FIREBASE_DEFAULT_COUNTRY_CODE=855
```

No service-account file is required for the token verification: the API
validates the ID token with the public keys Google publishes for Firebase
(`config/firebase.php` → `firebase.id_token.*`). The existing
`FIREBASE_CREDENTIALS` service account is only used for the push
notifications (FCM) and stays as it is.

## 3. Database

```bash
cd backend
php artisan migrate            # creates consumed_phone_reset_authentications
```

The `users.phone` column is unique and may still contain locally formatted
numbers (`012345678`). The reset endpoint matches the verified E.164
number against every representation of the stored number and rewrites the
column in the canonical `+855…` format after a successful reset.

## 4. API

```
POST /api/v1/auth/password/phone/reset
Authorization: Bearer <FIREBASE_ID_TOKEN>
Content-Type: application/json

{
  "password": "NewSecurePassword123",
  "password_confirmation": "NewSecurePassword123"
}
```

| Status | Meaning |
| ------ | ------- |
| 200 | `Password reset successfully. Please sign in again.` |
| 401 | Missing / invalid / expired / not-recent Firebase token |
| 409 | The same phone-authentication session was already used |
| 422 | The phone number does not belong to a customer / weak password |
| 429 | Too many reset attempts for this phone number |

The phone number is **never** read from the request body — it comes from
the verified Firebase token, so the client cannot reset somebody else's
account.

## 5. Flutter

| File | Role |
| ---- | ---- |
| `lib/services/firebase_phone_recovery_service.dart` | Firebase Phone Auth on a dedicated app instance (`tinhShopPasswordRecovery`) |
| `lib/services/password_reset_api.dart` | Sends the Firebase ID token + the new password to the API |
| `lib/screens/auth/phone_forgot_password_screen.dart` | Phone → OTP → new password screen |

The screen is reachable from the login screen (“Forgot the password ?”)
and from **Profile → Security → Change password** (route
`AppRoutes.phoneForgotPassword`).

## 6. Automated tests

The tests run against a dedicated PostgreSQL database (`tinh_shop_test`)
so the development data is never touched. The schema of that database is
copied from the development database:

```bash
cd backend
php sync_test_database.php     # creates/refreshes tinh_shop_test
php artisan test --filter=PhonePasswordResetTest
```

`phpunit.xml` points the test suite to `pgsql` / `tinh_shop_test`; every
test runs inside a transaction that is rolled back afterwards.
