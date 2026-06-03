<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;

class DutyOfficerGuideSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::whereHas('roles', fn ($q) => $q->where('slug', 'platform_admin'))->first();

        $content = <<<MARKDOWN
# Duty Officer Guide

Thank you for volunteering as a Duty Officer (DO). This page explains exactly what to expect when a callout goes overdue, how to acknowledge it, how to **test your phone**, and — importantly — how to set your phone up so an alert can **break through silent mode and Do Not Disturb**.

## Before & during a callout

1. **15 minutes before the callout is due** — a heads-up **SMS and email** go to **both you (the on-call DO) and the cavers**, prompting the party to mark themselves safe (or reply `OUT SAFE`) before any alarm is raised. This is your early warning that a callout may be about to trigger.
2. **At the callout time, if the party hasn't checked in** — an **Incident** is created and **you, the on-call DO**, get an immediate **SMS and email**; a message is posted to **#callouts-overdue** in Slack. (If nobody is on call, all duty officers are alerted.)
3. **For the next 15 minutes** — if you haven't acknowledged, the system places **automated voice calls to you** (press **1** to acknowledge), repeating every few minutes. This stays with **you** — it does **not** alert the rest of the team yet, giving you time to respond.
4. **After 15 minutes with no acknowledgement** — the incident escalates to **all duty officers** (SMS + email), and the voice calls widen to everyone, until someone takes control.
5. **Independent backup** — a separate system hosted on Google Cloud (the "watchdog") also messages and calls **all** duty officers, using a **different phone provider**, so a single system or provider outage cannot silence every alert.

## How to acknowledge an incident

Acknowledging makes you the **Incident Controller** and **stops the escalation** (no more repeat calls). You can acknowledge in any of these ways:

- **Press 1** when you receive the automated voice call.
- **Reply `ACK`** to the alert SMS.
- Click **Acknowledge** on the incident in the Subterra admin dashboard.

Once you're the controller, open the incident in Subterra to see the trip plan, participants, vehicle details and contact numbers, and to coordinate the response.

## Test your phone (do this now, and periodically)

On the **Callout Dashboard** (Admin → Callout), click **Test notifications**. You can:

- **Test my phone** — sends a real test SMS *and* places a real test voice call to **your** number.
- **Test all duty officers** — does the same for **everyone** on the rota (use sparingly, and ideally let the team know first).

Run the test once normally, then **enable the overrides below and test again with your phone on silent / Do Not Disturb** to confirm the call still rings through.

## Make alerts break through silent mode & Do Not Disturb

A ringing phone is the hardest alert to miss — but only if your phone is allowed to ring. Save the Subterra alert number to your contacts (e.g. as **"Subterra Callout"**) first, then:

### iPhone
- Edit the **Subterra Callout** contact → **Ringtone** → turn **Emergency Bypass ON**. Repeat for **Text Tone**. This lets that number ring/alert even when your phone is silenced or in a Focus/Do Not Disturb mode.
- In **Settings → Focus → Do Not Disturb**, enable **Allow Repeated Calls** (a second call from the same number within three minutes always rings) — the escalation's repeat calls rely on this.

### Android
- Save the number as a contact and mark it a **Starred / Favourite** contact.
- **Settings → Sound & vibration → Do Not Disturb → People** → allow **Calls** and **Messages** from **Starred contacts**, and turn on **Repeat callers**.

### All phones
- Don't block or silence the number. Consider a distinctive ringtone so you recognise a callout instantly.

## WhatsApp coordination group

The duty officers have a WhatsApp group for real-time coordination during an incident. Open it with the **DO WhatsApp** button at the top of the **Callout Dashboard** (Admin → Callout) — the link is kept current there.

> **Note:** WhatsApp is for **human coordination between duty officers**, not an automated alerting channel — the platform cannot guarantee delivery to a WhatsApp group, so it never replaces the SMS, email and voice alerts above.

## Quick reference

| Stage | Channel | Action |
|---|---|---|
| Overdue | SMS + email + Slack | Read the alert |
| +3 min, no ack | Automated voice call | **Press 1** to acknowledge |
| Any time | SMS reply | Send **ACK** |
| Any time | Dashboard | Click **Acknowledge**, then coordinate |
| Caver safe | — | The caver replies **OUT SAFE**; you still verify and **Resolve** the incident |

If you ever stop receiving test alerts, tell a platform admin — don't assume it's working.
MARKDOWN;

        Page::updateOrCreate(
            ['slug' => 'duty-officer-guide'],
            [
                'title' => 'Duty Officer Guide',
                'content' => $content,
                'user_id' => $admin?->id,
            ]
        );
    }
}
