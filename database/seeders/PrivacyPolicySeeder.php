<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;

class PrivacyPolicySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::whereHas('roles', function ($q) { $q->where('slug', 'platform_admin'); })->first();
        $adminId = $admin ? $admin->id : null;

        $content = <<<'MARKDOWN'
# Privacy Policy

**Last Updated: January 2026**

Subterra ("we", "us", "our") is committed to protecting your privacy. This policy explains how we collect, use, and safeguard your personal data in accordance with the UK General Data Protection Regulation (UK GDPR).

## 1. Information We Collect

### 1.1 Account Information
When you register, we collect your:
- Name
- Email Address
- Profile Photo (optional)
- Short Bio (optional)
- Club Memberships and BCA status

### 1.2 Callout Information
For the purpose of your safety during caving trips, you may provide:
- Trip plans and descriptions
- Car details and registration numbers
- Team member names and contact details
- Emergency contact name and phone number

### 1.3 Usage Data
We collect logs of your interactions with the site, including IP addresses and access counts for pages.

## 2. Why We Process Your Data

We process your data based on the following legal grounds:
- **Vital Interests**: We process Callout and Emergency Contact data to facilitate rescue operations in the event of an overdue trip.
- **Contract / Legitimate Interest**: We process account data to provide you with access to the Subterra platform and its community features.
- **Consent**: For optional features like your profile bio and photo.

## 3. Data Sharing

We share your data with:
- **Duty Officers**: Volunteer rescuers who monitor callouts.
- **Service Providers**: Such as AWS (hosting) and Slack (notifications to Duty Officers).
- **Other Users**: Your name and bio are visible to other logged-in members. Sensitive callout data is only visible to Duty Officers and Admins.

## 4. Data Retention

- **Account Data**: Retained as long as your account is active.
- **Sensitive Callout Data**: Purged or anonymized 30 days after a trip is resolved.
- **Logs**: Retained for administrative and security purposes for up to 1 year.

## 5. Your Rights

Under UK GDPR, you have the following rights:
- **Access**: The right to request a copy of your data.
- **Erasure**: The right to request deletion of your account.
- **Portability**: The right to download your data in a machine-readable format.
- **Correction**: The right to update inaccurate information.

To exercise these rights, please use the settings in your profile or contact an administrator.

## 6. Security

We implement industry-standard security measures to protect your data. However, please be aware that no system is 100% secure.

## 7. Contact Us

If you have questions about this policy, please contact us via the platform or our GitHub repository.
MARKDOWN;

        Page::updateOrCreate(
            ['slug' => 'privacy-policy'],
            [
                'title' => 'Privacy Policy',
                'content' => $content,
                'user_id' => $adminId,
            ]
        );
    }
}
