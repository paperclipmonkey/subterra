<?php

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

Welcome to Subterra. By using our platform, you agree to the following terms. Please read them carefully.

## 1. Community & Purpose
Subterra is a community-driven platform built for cavers, by cavers. It is a not-for-profit initiative designed to help the caving community plan trips, share information, and stay safe.

## 2. User Conduct
We expect all users to behave respectfully and responsibly.
*   **Be Helpful:** Trip Reports should be factual, accurate, and helpful to other community members.
*   **No Abuse:** Harassment, hate speech, and abusive behavior will not be tolerated.
*   **Accountability:** You are responsible for the content you post and the actions you take on the platform.

**Consequences:** Violation of these terms may result in immediate account removal and a ban from the platform.

## 3. Callout Functionality
The "Callout" feature is a critical safety tool designed to assist in emergency situations.
*   **How it works:** This feature allows you to notify designated contacts or Duty Officers if you are overdue from a trip.
*   **Voluntary Service:** Please understand that Duty Officers perform this role on a voluntary basis. They are fellow cavers donating their time to help keep you safe.
*   **No Abuse:** This feature must NOT be abused or used for trivial matters.
*   **Emergency Protocol:** Misuse of the callout system, including false alarms or pranks, is a serious offense and may be reported to the police.

## 4. Liability
While we strive to provide a reliable service, Subterra provides this platform "as is" without any warranties. Caving is an inherently dangerous activity, and you are solely responsible for your own safety decisions.

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
