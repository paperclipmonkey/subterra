<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::first() ?? User::factory()->admin()->create();

        $tosContent = <<<'EOT'
# Terms of Service

**Last Updated: June 2026**

Welcome to Subterra. By using our platform, you agree to the following terms. Please read them carefully.

## 1. Community & Purpose
Subterra is a community-driven platform built for cavers, by cavers. It is a not-for-profit initiative designed to help the caving community plan trips, share information, and stay safe.

## 2. User Conduct
We expect all users to behave respectfully and responsibly.
*   **Be Helpful:** Trip Reports should be factual, accurate, and helpful to other community members.
*   **No Abuse:** Harassment, hate speech, and abusive behavior will not be tolerated.
*   **Accountability:** You are responsible for the content you post and the actions you take on the platform.

**Consequences:** Violation of these terms may result in immediate account removal and a ban from the platform.

## 3. Your Content & Photos

### 3.1 Ownership
You retain ownership of the content you create or upload, including trip reports, photos, videos, and collections. Subterra does not claim ownership of your content.

### 3.2 Licence You Grant Us
By uploading photos, videos, or other media to Subterra, you grant us a worldwide, non-exclusive, royalty-free, perpetual, irrevocable, and sub-licensable licence to host, store, reproduce, adapt, resize, reformat (for example, converting images to web-optimised formats), publish, and display that media. This licence specifically includes the right to:
*   use your media to **represent and illustrate caves, routes, and locations** on the platform (including as hero, entrance, and gallery imagery); and
*   use your media in **marketing, promotional, and outreach materials** for Subterra and the caving community, on and off the platform.

This licence continues even if you stop using the platform, to the extent your media has been incorporated into cave records or published materials. Where you provide photographer or copyright information, we will make reasonable efforts to display that attribution.

### 3.3 Your Warranties
By uploading media, you confirm that you own it or have the necessary rights and permissions to upload it and to grant the licence above, and that the media does not infringe the rights (including privacy and intellectual property rights) of any other person. You must not upload media of identifiable individuals without their consent.

### 3.4 Removal
You may request removal of your media. We will remove it from active display within a reasonable period, though copies may persist in backups, and we may retain media already incorporated into shared cave records or previously published marketing materials.

## 4. Cave Data
The cave database, including cave descriptions, access information, and associated metadata, is curated and maintained by Subterra and its administrators. It is provided for your reference and may not be reproduced or redistributed except as permitted by us.

## 5. Pip (AI Assistant)
Subterra offers an AI-powered assistant called "Pip" to help with trip planning, cave information, and general caving questions.
*   **Beta Feature:** Pip is provided as a beta feature and may be changed or withdrawn at any time.
*   **General Advice Only:** Pip can make mistakes and provides general information only. It is **not** a substitute for your own judgement. Always independently verify conditions, access, gear, and any safety-critical information before relying on it.
*   **Third-Party Processing:** To generate responses, your messages are processed by a third-party AI provider (Anthropic). Do not share information you do not wish to be processed in this way. See our Privacy Policy for details.
*   **Conversations & Training:** Conversations may be stored and used to monitor, improve, and continue training the assistant.
*   **Fair Use:** Access to Pip may be rate-limited, and misuse may result in access being withdrawn.

## 6. Callout Functionality
The "Callout" feature is a safety tool designed to assist in emergency situations.
*   **How it works:** This feature allows you to notify designated contacts or Duty Officers if you are overdue from a trip.
*   **Not a Guaranteed or Emergency Service:** Subterra is **not** an emergency service. The Callout feature depends on third-party messaging and email networks and on the availability of volunteers, and we **do not guarantee** that any notification will be delivered, received, or acted upon. You must **not** rely on it as your sole safety mechanism, and it does **not** replace proper, formal cave-rescue callout procedures. In a genuine emergency, contact the emergency services directly.
*   **Third-Party Details:** If you add other people (for example, trip participants or emergency contacts) and their contact details, you confirm that you have their consent to provide that information and to have them contacted in connection with a callout.
*   **Voluntary Service:** Duty Officers perform this role on a voluntary basis. They are fellow cavers donating their time to help keep you safe.
*   **No Abuse:** This feature must NOT be abused or used for trivial matters. Misuse of the callout system, including false alarms or pranks, is a serious offence and may be reported to the police.

## 7. Liability
While we strive to provide a reliable service, Subterra provides this platform, including all features described above, "as is" and "as available" without any warranties of any kind. Caving is an inherently dangerous activity, and you are solely responsible for your own safety decisions. To the fullest extent permitted by law, Subterra and its volunteers accept no liability for any loss or harm arising from your use of, or reliance on, the platform or any of its features. Nothing in these terms excludes liability that cannot be excluded by law.

## 8. Changes to These Terms
We may update these terms from time to time. Where changes are material, we will take reasonable steps to notify you. Continued use of the platform after changes take effect constitutes acceptance of the updated terms.

## 9. Governing Law
These terms are governed by the laws of England and Wales, and any disputes are subject to the exclusive jurisdiction of the courts of England and Wales.

By creating an account, you acknowledge that you have read, understood, and agree to these terms.
EOT;

        Page::updateOrCreate(
            ['slug' => 'terms-of-service'],
            [
                'title' => 'Terms of Service',
                'content' => $tosContent,
                'user_id' => $admin->id,
                'access_count' => 0,
            ]
        );
    }
}
