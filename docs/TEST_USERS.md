# Test Users

This document lists all test users created by the `UserSeeder` for testing purposes.

## Available Test Users

All test users use the domain `@subterra.test` for easy identification.

### 1. Admin User
- **Email:** `admin@subterra.test`
- **Role:** Platform Administrator
- **Status:** Active, Approved
- **Club:** Active Club (Admin)
- **Visibility:** Public
- **Use for:** Testing platform-wide admin features

### 2. Club Admin
- **Email:** `clubadmin@subterra.test`
- **Role:** Club Administrator
- **Status:** Active, Approved
- **Club:** Active Club (Admin)
- **Visibility:** Public
- **Use for:** Testing club management features

### 3. Regular Member
- **Email:** `member@subterra.test`
- **Role:** Regular Member
- **Status:** Active, Approved
- **Club:** Active Club (Member)
- **Visibility:** Public
- **Use for:** Testing standard user features

### 4. Pending Member
- **Email:** `pending@subterra.test`
- **Role:** Pending Member
- **Status:** Active, Not Approved
- **Club:** Active Club (Pending)
- **Visibility:** Public
- **Use for:** Testing club approval workflow

### 5. Private User
- **Email:** `private@subterra.test`
- **Role:** Regular Member
- **Status:** Active, Approved
- **Club:** Active Club (Member)
- **Visibility:** Private
- **Email Preferences:** All disabled
- **Use for:** Testing privacy features and user search

### 6. Multi Club Member
- **Email:** `multiclub@subterra.test`
- **Role:** Regular Member
- **Status:** Active, Approved
- **Clubs:** Active Club (Member), Disabled Club (Member)
- **Visibility:** Clubs only
- **Use for:** Testing multi-club functionality

### 7. Inactive User
- **Email:** `inactive@subterra.test`
- **Role:** Regular Member
- **Status:** Inactive, Approved
- **Club:** Active Club (Member)
- **Visibility:** Public
- **Use for:** Testing inactive account handling

### 8. No Club Member
- **Email:** `noclub@subterra.test`
- **Role:** No Club
- **Status:** Active, Approved
- **Club:** None
- **Visibility:** Public
- **Use for:** Testing users without club membership

## Running the Seeder

To create or update these test users:

```bash
vendor/bin/sail artisan db:seed --class=UserSeeder
```

To seed the entire database (including users):

```bash
vendor/bin/sail artisan db:seed
```

## Notes

- All users have phone numbers in the format `+44 7700 90000X`
- The seeder uses `firstOrCreate()` so it's safe to run multiple times
- Users are created after clubs, so clubs must exist first
- The existing `testuser@example.com` from `TestDataSeeder` is still available
