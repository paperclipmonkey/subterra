# Duty Officer Guide

Thank you for volunteering as a duty officer. You are the human at the end of the safety net: when a caver goes overdue, you're who the system calls. This page explains exactly what you'll experience, how to respond, and — importantly — how to set your phone up so an alert can **break through silent mode and Do Not Disturb**.

> This is the guide for volunteers on the rota. For the plain overview cavers see, read [Callouts explained](/pages/callouts-explained); for every technical detail, see [How the callout system works](/pages/callout-system). All three follow the same structure.

---

## What a callout is

A caver records where they're going and when they expect to be back. If they don't check in by that time, the system raises the alarm and **you** are alerted. Everything below is about that moment — making sure you receive the alert, and knowing what to do with it.

## Setting a callout

There's nothing for you to do when a callout is created — but note that **a callout can only be created if a duty officer is on call** at the caver's return time. That's why keeping the rota covered matters: an uncovered slot means cavers can't set a callout at all.

## While the trip is underway

Nothing is required of you while a trip is in progress. Two independent systems watch the clock (Subterra and an independent backup on Google Cloud). Your first involvement is usually the 15-minute heads-up below.

## When a callout goes overdue

This is the sequence you'll experience. **Acknowledging at any point makes you the Incident Controller and stops the escalation.**

1. **15 minutes before the callout is due** — a heads-up **text and email** go to **you (the on-call duty officer) and the cavers**, prompting the party to mark themselves safe (reply `OUT SAFE`) before any alarm is raised. This is your early warning.
2. **At the callout time, if the party hasn't checked in** — an **incident** is created and **you, the on-call duty officer**, get an immediate **text and email**; a message is posted to **#callouts-overdue** in Slack.
3. **For the first ~12 minutes** — if you haven't acknowledged, the system places **automated voice calls to you** (press **1** to acknowledge), repeating every few minutes. These stay with **you** at first, giving you time to respond.
4. **From about 12 minutes** — if it's still unacknowledged, those voice calls **widen to ring every duty officer**. A ringing phone is the hardest alert to miss, so this makes an overdue incident very hard to ignore.
5. **At 15 minutes** — every duty officer is also alerted by **text and email**, until someone takes control.

Alongside all of this, the **independent backup system** also texts and emails **all** duty officers, using a **different phone provider** (it doesn't place voice calls — that's the primary system only), so a single system or provider outage cannot silence every alert.

### How to acknowledge an incident

Acknowledging makes you the **Incident Controller** and **stops the escalation** (no more repeat calls). Any one of these works — they all share the same logic:

- **Press 1** when you receive the automated voice call.
- **Reply `ACK`** to the alert text.
- Click **Acknowledge** on the incident in the Subterra admin dashboard.

Once you're the controller, open the incident in Subterra to see the trip plan, participants, vehicle details and contact numbers, and to coordinate the response. Phone calls to actually check on the party are made by **you** — the system won't robocall the caver on your behalf.

### If you can't make contact: call for rescue

If you cannot reach the party and cannot quickly confirm they're safe, **don't wait — raise a rescue straight away.** It's far better to stand a team down than to lose time.

1. **Call 999** and ask for the **Police**, then ask for **Cave Rescue — *not* Mountain Rescue**. In the UK the police coordinate the cave rescue organisations — give them the **correct region/area** so the right team is paged.
2. **Have the incident open in Subterra** as you call — it has everything the rescue controller will ask for.

Subterra walks you through a **call script** on the incident page so you don't have to remember it under pressure. It even shows the **police force and cave rescue team that cover the cave's region** (and, if the region isn't known, prompts you to ask which area the cave is in). Tick off each step as you go. Be ready to give:

- The **region and cave / location** (and entrance), and the car parking location
- **How many** people are underground, and **who** they are
- The **expected return time** and how long they're now overdue
- The **trip plan / intended route**
- **Vehicle** make and registration
- A **callback number** for you as incident controller

Log every call and decision as an **incident note** as you go.

## Marking safe

When the caver marks themselves safe (in-app or by replying `OUT SAFE`), the countdown is cancelled and their callout becomes a trip record. **But if an incident was already open, it stays open** with a system note that the user marked themselves safe — you still need to **verify** they're safe and **Resolve** the incident yourself. This prevents a false "all clear" during an active rescue.

## Why you can trust it

Two completely separate systems watch every callout — Subterra itself, and an independent backup on Google Cloud — using different servers and **different phone providers** (so one provider's outage can't silence all alerts). But the backup is only as good as your phone actually ringing, which is why the next two sections matter.

The dashboard also shows the **remaining SMS credit** on both providers, and the incident page shows **per-recipient delivery** for the alerts that went out (delivered / failed) — so you can see at a glance that messages actually landed. If credit ever runs low, new callouts are blocked and a Slack alert fires (auto-top-up should mean this never happens).

---

## Set your phone up to receive alerts

A ringing phone is the hardest alert to miss — but only if your phone is allowed to ring. The **primary (Twilio)** and **backup (TextMagic)** alert numbers are shown in the **Test notifications** dialog on the Callout Dashboard — save both to your contacts (e.g. as **"Subterra Callout"**) first, then:

### iPhone
- Edit the **Subterra Callout** contact → **Ringtone** → turn **Emergency Bypass ON**. Repeat for **Text Tone**. This lets that number ring/alert even when your phone is silenced or in a Focus/Do Not Disturb mode.
- In **Settings → Focus → Do Not Disturb**, enable **Allow Repeated Calls** (a second call from the same number within three minutes always rings) — the escalation's repeat calls rely on this.

### Android
- Save the number as a contact and mark it a **Starred / Favourite** contact.
- **Settings → Sound & vibration → Do Not Disturb → People** → allow **Calls** and **Messages** from **Starred contacts**, and turn on **Repeat callers**.

### All phones
- Don't block or silence the number. Consider a distinctive ringtone so you recognise a callout instantly.

## Test your phone (do this now, and periodically)

On the **Callout Dashboard** (Admin → Callout), click **Test notifications**. You can:

- **Test my phone** — sends a real test text *and* places a real test voice call to **your** number.
- **Test all duty officers** — does the same for **everyone** on the rota (use sparingly, and ideally let the team know first).

Run the test once normally, then **enable the silent-mode overrides above and test again with your phone on silent / Do Not Disturb** to confirm the call still rings through.

## Coordinating during an incident

The duty officers have a WhatsApp group for real-time coordination during an incident. Open it with the **DO WhatsApp** button at the top of the **Callout Dashboard** (Admin → Callout) — the link is kept current there.

> **Note:** WhatsApp is for **human coordination between duty officers**, not an automated alerting channel — the platform cannot guarantee delivery to a WhatsApp group, so it never replaces the text, email and voice alerts above.

## Quick reference

| Stage | Channel | Action |
|---|---|---|
| Overdue | Text + email + Slack | Read the alert |
| ~3 min, no ack | Automated voice call | **Press 1** to acknowledge |
| Any time | Text reply | Send **ACK** |
| Any time | Dashboard | Click **Acknowledge**, then coordinate |
| Caver safe | — | The caver replies **OUT SAFE**; you still verify and **Resolve** the incident |

If you ever stop receiving test alerts, tell a platform admin — don't assume it's working.
