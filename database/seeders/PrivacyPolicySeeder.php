<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;

class PrivacyPolicySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::whereHas('roles', function ($q) {
            $q->where('slug', 'platform_admin');
        })->first();
        $adminId = $admin ? $admin->id : null;

        $content = <<<'MARKDOWN'
# Privacy Policy

**Last Updated: June 2026**

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

When you add another person's details (for example, a trip participant or emergency contact), you are responsible for ensuring you have their consent to share that information with us and for them to be contacted in connection with a callout.

### 1.3 Photos and Media
When you upload photos, videos, or other media (to caves, trips, routes, or collections), we store the file together with any metadata you provide, such as title, photographer, copyright, and the date taken. Please see our Terms of Service for details of how this media may be used, including to represent caves and in marketing materials.

### 1.4 Pip (AI Assistant) Data
If you use Pip, our AI assistant, we collect and store the messages you send and the responses generated, including any feedback you provide. Your messages are sent to a third-party AI provider to generate responses (see Data Sharing below). Please avoid including sensitive personal information in your messages to Pip.

### 1.5 Usage Data
We collect logs of your interactions with the site, including IP addresses and access counts for pages.

## 2. Why We Process Your Data

We process your data based on the following legal grounds:
- **Vital Interests**: We process Callout and Emergency Contact data to facilitate rescue operations in the event of an overdue trip.
- **Contract / Legitimate Interest**: We process account data, your content, and AI assistant interactions to provide you with access to the Subterra platform, its community features, and to improve our services.
- **Consent**: For optional features like your profile bio and photo.

## 3. Data Sharing

We share your data with:
- **Duty Officers**: Volunteer rescuers who monitor callouts.
- **Service Providers**: Such as AWS and Google Cloud (hosting and media/callout processing), Slack (notifications to Duty Officers), and SMS/email providers (callout notifications).
- **AI Provider**: When you use Pip, your messages are processed by Anthropic to generate responses. Anthropic acts as a processor on our behalf.
- **Other Users**: Your name and bio are visible to other logged-in members. Trip reports and the media attached to them are visible to other members according to the visibility you set for each trip (public, club-only, or private). Sensitive callout data is only visible to Duty Officers and Admins.

## 4. Data Retention

- **Account Data**: Retained as long as your account is active.
- **Sensitive Callout Data**: Purged or anonymized 30 days after a trip is resolved.
- **Photos and Media**: Retained while your account is active; note that media incorporated into shared cave records or published materials may be retained as described in our Terms of Service.
- **Pip Conversations**: Stored conversations may be retained to monitor, improve, and continue training the assistant.
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
