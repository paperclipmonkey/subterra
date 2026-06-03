# How the Callout System Works

Subterra's callout system is designed to keep cavers safe underground. When you set a callout, you're creating a safety net — if you don't check in by your expected return time, the system will automatically alert Duty Officers and begin an emergency response.

This document explains exactly how the system works, the design decisions behind it, and why you can trust it.

---

## Overview

The callout system has **three independent layers** that monitor your safety:

1. **Subterra Server** — primary application, running scheduled checks every minute
2. **GCP Watchdog** — a completely separate, cloud-hosted backup system on Google Cloud
3. **Duty Officer Rota** — real humans on-call who are responsible for responding

If any one layer fails, the others continue to function independently.

---

## The Callout Lifecycle

### Creating a Callout

When you submit a callout through the app, the following happens:

```mermaid
sequenceDiagram
    actor User as Caver (You)
    participant UI as Subterra App
    participant API as Subterra Server
    participant DB as Database
    participant Watchdog as GCP Watchdog<br/>(Independent Backup)
    participant SMS as SMS Service
    participant Email as Email Service
    participant Slack as Slack

    User->>UI: Fill in callout details<br/>(cave, return time, vehicle,<br/>trip plan, participants)
    UI->>API: POST /api/callouts

    Note over API: Validates:<br/>✅ DO on-call at return time<br/>✅ No duplicate active callouts

    API->>DB: Create callout record<br/>(status: active)
    API->>DB: Store participants

    par Register backup watchdog
        API->>Watchdog: Register callout<br/>(callout ID, time, contacts)
        Note over Watchdog: Stores independently<br/>in Google Firestore
    and Send email confirmations
        API->>Email: Callout Started email<br/>to all participants
    and Alert Slack
        API->>Slack: "#callouts-open: New Callout"
    end

    API-->>UI: ✅ Callout activated
    UI-->>User: Shows callout confirmation<br/>with cancellation link
```

> **Backup registration is non-blocking.** Registering with the GCP Watchdog happens during creation, but if the watchdog is unreachable the callout is still created and monitored by the Subterra server — the failure is logged and the callout is flagged as lacking backup coverage. The backup being unavailable can never prevent you from setting a callout.

### What You Need to Provide

| Field | Required | Purpose |
|---|---|---|
| Cave / Location | Yes | So rescuers know where to look |
| Expected return time | Yes | The deadline — if you're not back, alerts fire |
| Trip plan | Yes | Route details for rescue teams |
| Vehicle registration | Yes | To locate your car at the parking spot |
| Car parking location | Yes | Where rescuers should start looking |
| Participants | Yes (≥1) | Everyone underground, with phone numbers |

---

## While You're Underground

Once your callout is active, two independent systems are watching the clock:

```mermaid
flowchart TB
    subgraph Subterra["Subterra Server (Primary)"]
        Cron["⏱️ Scheduled Task<br/>Runs every 1 minute"]
        Cron --> CheckImminent["Check: Any callouts<br/>due in 15 minutes?"]
        Cron --> CheckOverdue["Check: Any callouts<br/>past their return time?"]
        Cron --> CheckEscalation["Check: Any incidents<br/>unmanaged for 15 min?"]
    end

    subgraph GCP["GCP Watchdog (Independent Backup)"]
        GCPCron["⏱️ Cloud Scheduler<br/>Runs every 5 minutes"]
        GCPCron --> GCPCheck["Check Firestore<br/>for overdue callouts"]
        GCPCheck --> GCPAlert["Send SMS + Email<br/>via TextMagic + SMTP"]
    end

    style Subterra fill:#1a365d,color:#fff
    style GCP fill:#2d3748,color:#fff
```

> **Why two systems?** If Subterra goes down (server crash, database issue, network outage), the GCP Watchdog operates entirely independently — different server, different database, different SMS provider. It will still send alerts.

---

## The Timeline: What Happens and When

```mermaid
timeline
    title Callout Timeline
    section Active Period
        Callout Created : Callout saved to database
            : GCP Watchdog registered
            : Email sent to all participants
            : Slack notification posted
    section 15 Minutes Before Due
        Warning Alerts : Duty Officer gets SMS + Email warning
            : All participants get SMS + Email
            : "Your callout is close — mark safe or reply OUT SAFE"
    section Return Time Reached
        Callout Triggered : Status changed to TRIGGERED
            : Incident record created
            : Duty Officer on-call alerted via SMS + Email
            : Slack @channel alert posted
            : GCP Watchdog also sends independent alerts
    section ~3 Minutes After Trigger
        Voice escalation : If still unacknowledged
            : Automated voice call to the on-call DO
            : "Press 1 to acknowledge"
            : Repeats to the on-call DO (NOT the whole team yet)
    section 15 Minutes After Trigger
        Escalation : If no Duty Officer has taken control
            : ALL Duty Officers re-alerted (SMS + Email)
            : Voice calls now widen to ALL Duty Officers
            : "CRITICAL — Unmanaged Incident"
```

> The on-call DO gets the **full 15 minutes** to respond — the automated calls during that window go **only to them**. Escalation to every duty officer (and widening the voice calls) happens **only after** 15 minutes unmanaged.

> **Acknowledgement stops escalation.** A DO becomes the Incident Controller — and the voice calls stop — by pressing 1 on the call, replying `ACK` to the SMS, or clicking Acknowledge in the dashboard. All three routes share the same logic. Timings are configurable in `config/callouts.php`.

---

## 15-Minute Warning

Fifteen minutes before your return time, the system sends a heads-up to both you and the Duty Officer:

**To the Duty Officer:**
> *ALERT: Callout at [Cave Name] due in 15 mins (HH:MM). Please stand by. Subterra.*

**To you and your participants (SMS):**
> *Your callout is close. Please mark yourself safe or reply "OUT SAFE"*

**To you and your participants (Email):**
> *Your registered callout is due in approximately 15 minutes. If you are safe out of the cave, please check in IMMEDIATELY to prevent a rescue callout from being initiated.*

This gives everyone time to act before the full alert triggers.

---

## When the Clock Runs Out (Triggering)

If nobody checks in by the return time, the system escalates automatically:

```mermaid
flowchart TD
    Overdue["⏰ Return time reached<br/>User has NOT checked in"]
    Overdue --> Trigger["Callout status → TRIGGERED"]
    Trigger --> Incident["Create Incident record<br/>(status: open)"]

    Incident --> NotifyDO["📱 Alert on-call Duty Officer<br/>(or ALL Duty Officers as a<br/>fallback if nobody is on-call)<br/>SMS + Email"]
    Incident --> NotifyParticipants["📱 Alert all participants<br/>SMS + Email"]
    Incident --> SlackAlert["🔴 Slack @channel alert<br/>#callouts-overdue"]

    NotifyDO --> Wait15["⏱️ 15 minutes pass..."]
    Wait15 --> CheckController{"Has a Duty Officer<br/>taken control?"}
    CheckController -->|No| Escalate["🚨 ESCALATION<br/>Re-alert ALL Duty Officers<br/>'CRITICAL: Unmanaged Incident'"]
    CheckController -->|Yes| Managed["✅ Incident is being managed"]

    style Overdue fill:#c53030,color:#fff
    style Escalate fill:#c53030,color:#fff
    style Managed fill:#38a169,color:#fff
```

---

## Cancelling a Callout (Marking Safe)

When you're safely out of the cave:

```mermaid
sequenceDiagram
    actor User as Caver (You)
    participant UI as Subterra App
    participant API as Subterra Server
    participant Watchdog as GCP Watchdog
    participant Email as Email Service

    User->>UI: Click "I'm Safe" / Cancel
    Note over UI: Captures IP, User Agent,<br/>and GPS location (if available)
    UI->>API: POST /api/callouts/{id}/cancel

    API->>API: Create Trip record<br/>from callout data
    API->>Watchdog: Cancel watchdog timer
    API->>Email: Send "Callout Cancelled (Safe)"<br/>to all participants

    alt Incident already exists (rescue underway)
        API->>API: Mark callout as cancelled
        API->>API: Add system note:<br/>"USER MARKED THEMSELVES SAFE"
        Note over API: Incident stays open<br/>for DO to verify and close
    else No incident yet
        API->>API: Mark callout as cancelled
    end

    API-->>UI: ✅ Callout cancelled
    Note over UI: Shows trip record link
```

**Important:** If a rescue is already underway (an incident exists), cancelling your callout does **not** close the incident. The Duty Officer must verify you are safe and close it manually. This prevents false "all clear" signals during an active rescue.

---

## Design Decisions: Why It Works This Way

### 1. Fail-Safe, Not Fail-Secure

The system is designed to **fail safe** — meaning if anything goes wrong, it errs on the side of raising the alarm rather than staying silent.

| Scenario | What happens |
|---|---|
| GCP Watchdog registration fails | Callout **still created** — the failure is logged and the callout is flagged as lacking backup coverage (`watchdog_registered_at` is left null), but the Subterra server monitors it independently. The backup being down never blocks the primary safety net. |
| A notification (SMS/email) fails when a callout triggers | The triggered status and Incident are committed to the database **before** any notification is sent, and each notification is delivered in isolation. One failed recipient or a downed provider is logged but never rolls back the incident or stops the other alerts. |
| Email fails to send | Callout **still created** — email is secondary to SMS |
| Slack fails | Callout **still created** — Slack is informational only |
| Subterra server goes down | GCP Watchdog **independently** monitors and alerts via TextMagic SMS + SMTP email |
| Watchdog cancellation fails (when you mark safe) | Watchdog may still fire — a false alarm is better than a missed emergency |



### 2. Two Independent Monitoring Systems

```mermaid
flowchart LR
    subgraph Primary["Primary: Subterra Server"]
        direction TB
        P1["Laravel Scheduler<br/>(every 1 minute)"]
        P2["Postgres Database"]
        P3["Twilio (SMS + Voice)"]
        P4["Application Email (SMTP)"]
    end

    subgraph Backup["Backup: GCP Watchdog"]
        direction TB
        B1["GCP Cloud Scheduler<br/>(every 5 minutes)"]
        B2["Google Firestore"]
        B3["TextMagic SMS"]
        B4["SMTP Email"]
    end

    Primary ~~~ Backup

    style Primary fill:#2b6cb0,color:#fff
    style Backup fill:#2d3748,color:#fff
```

The two systems share **no infrastructure**:
- Different servers (Fly.io hosting vs Google Cloud Run)
- Different databases (Postgres vs Firestore)
- **Different SMS providers — Twilio (Fly/primary) vs TextMagic (GCP/backup)**
- Different email systems (application email vs direct SMTP)
- Different schedulers (Laravel cron vs GCP Cloud Scheduler)

> **SMS-provider redundancy is deliberate.** Because the primary sends SMS/voice via Twilio and the backup sends SMS via TextMagic, a total outage at *one* provider cannot silence all alerting — the other system, on the other provider, still gets messages through.

Both must independently fail for an alert to be missed.

> **Overlap protection.** The primary check runs every minute and is guarded by a lock (`withoutOverlapping`), so if one run is slow (for example, while waiting on an SMS or email provider) the next minute's tick will not start a second concurrent run. This prevents duplicate alerts and races on incident creation.

### 3. Duty Officer Rota

A callout can only be created if there is a Duty Officer on-call at the expected return time. This is checked at creation time — if nobody is covering that time slot, the system refuses to create the callout and tells you why.

Duty Officers are real people who have agreed to be available. When an alert fires, they receive automated SMS and emails. During an active incident, the system expects a Duty Officer to "take control" of the incident within 15 minutes. If nobody does, every Duty Officer gets an escalation alert.

### 4. Contact During an Incident

Communication during an incident works in layers:

```mermaid
flowchart TD
    Trigger["🚨 Incident Created"] --> Auto["Automated Contact"]
    Auto --> SMS1["📱 SMS + Email to on-call DO<br/>(or ALL DOs if nobody on-call)"]
    Auto --> Slack1["💬 Slack @channel alert"]

    Trigger --> Manual["Manual Contact<br/>(by Duty Officer)"]
    Manual --> Call1["📞 Call the caver's phone"]
    Manual --> Call2["📞 Call participant phones"]
    Manual --> Call3["📞 Call emergency services"]

    SMS1 --> Escalate{"No response<br/>in 15 min?"}
    Escalate -->|Yes| ReAlert["🚨 Re-alert ALL DOs<br/>CRITICAL: Unmanaged"]
    Escalate -->|No| TakeControl["DO takes control<br/>of incident"]

    TakeControl --> DOActions["DO Actions:"]
    DOActions --> Action1["View trip plan + participant details"]
    DOActions --> Action2["Call caver / participants"]
    DOActions --> Action3["Coordinate rescue"]
    DOActions --> Action4["Add incident notes"]
    DOActions --> Action5["Resolve when safe"]

    style Trigger fill:#c53030,color:#fff
    style ReAlert fill:#c53030,color:#fff
    style TakeControl fill:#38a169,color:#fff
```

The system provides automated text alerts, but **phone calls to check on the party are done manually by the Duty Officer**. This is intentional — an automated robocall can't assess a situation; a real person can ask questions, judge tone of voice, and make decisions.

### 5. Data Privacy

Callout data contains sensitive personal information (phone numbers, vehicle details, location data). The system automatically scrubs this data from resolved callouts after 30 days, retaining only the anonymised trip record.

### 6. Monthly Watchdog Testing

The GCP Watchdog is tested monthly with a synthetic test alert (`watchdog:test-alert`) to verify the entire backup pipeline is functioning. This sends a test callout through the watchdog that triggers within 1 minute, confirming SMS and email delivery without involving real emergency contacts.

### 7. Synchronous, Timeout-Bounded Alerting

Alerts are sent **synchronously** within the scheduled check, not handed to a background queue worker. This is a deliberate safety choice: a queued alert depends on a separate worker process being alive, and if that worker dies, alerts would pile up silently and never be delivered — a worse failure mode than sending them inline. Keeping the alerting path inline means an alert is attempted immediately, within the run that detected the overdue callout.

To stop a slow or unresponsive provider from blocking that inline path, the safety mechanisms are:

- **Bounded timeouts** — outbound SMS calls have a hard timeout, so a hung provider cannot stall the check indefinitely.
- **Automatic retries** — transient connection failures are retried a few times with a short backoff before giving up.
- **Per-recipient isolation** — each notification is delivered independently; one failed recipient or a downed channel is logged but never aborts the remaining sends, and never rolls back the triggered status or incident record.
- **Overlap protection** — `withoutOverlapping` (see §2) prevents a slow run from being started again by the next minute's tick.

> If you later choose to move alerting onto a dedicated, **supervised** queue worker (e.g. a `callouts` queue), the notifications are written to support it — but only do so once that worker is monitored as carefully as the scheduler, since the GCP Watchdog is the only backup if it stalls.

### 8. Continuous Watchdog-Sync Monitoring

The backup is only useful if it is actually present and tracking the right callouts. A scheduled monitor (`callouts:check-watchdog-sync`, every 15 minutes) continuously verifies this and raises a Slack alert if:

- the GCP Watchdog is **unreachable** or **not configured** while active callouts exist;
- the watchdog's active-callout count **diverges** from Subterra's; or
- any active callout has **no backup coverage** (its watchdog registration failed at creation, recorded by a null `watchdog_registered_at`).

This means a loss of redundancy is surfaced proactively, rather than being discovered only when an admin happens to open the dashboard.

---

## Complete System Diagram

```mermaid
flowchart TB
    subgraph User["👤 Caver"]
        CreateCallout["Create Callout"]
        CancelCallout["Cancel / Mark Safe"]
    end

    subgraph Subterra["Subterra Platform"]
        API["API Server"]
        DB[(Database)]
        Scheduler["⏱️ 1-min Scheduler"]
        Twilio["Twilio (SMS + Voice)"]
        EmailSvc["Email"]
        SlackSvc["Slack"]
    end

    subgraph GCPWatchdog["GCP Watchdog (Independent)"]
        WatchdogAPI["Cloud Run API"]
        Firestore[(Firestore)]
        GCPScheduler["⏱️ 5-min Scheduler"]
        TextMagic["TextMagic SMS"]
        SMTP["SMTP Email"]
    end

    subgraph DutyOfficer["👮 Duty Officer"]
        DODashboard["Admin Dashboard"]
        DOPhone["Phone"]
        DOEmail["Email"]
    end

    CreateCallout --> API
    API --> DB
    API --> WatchdogAPI
    WatchdogAPI --> Firestore
    API --> Twilio
    API --> EmailSvc
    API --> SlackSvc

    Scheduler -->|"Check every 1 min"| DB
    Scheduler -->|"15 min warning"| Twilio
    Scheduler -->|"15 min warning"| EmailSvc
    Scheduler -->|"Trigger / Escalate"| Twilio
    Scheduler -->|"Trigger / Escalate"| EmailSvc
    Scheduler -->|"Trigger / Escalate"| SlackSvc

    GCPScheduler -->|"Check every 5 min"| Firestore
    GCPScheduler -->|"Overdue alert"| TextMagic
    GCPScheduler -->|"Overdue alert"| SMTP

    Twilio --> DOPhone
    EmailSvc --> DOEmail
    TextMagic --> DOPhone
    SMTP --> DOEmail

    CancelCallout --> API
    API -->|"Cancel watchdog"| WatchdogAPI

    DODashboard -->|"Take control /<br/>Resolve incident"| API

    style GCPWatchdog fill:#2d3748,color:#fff
    style Subterra fill:#1a365d,color:#fff
```

---

## Summary

| Feature | How it works |
|---|---|
| **Callout creation** | Submit via app → confirmed via Email |
| **15-min warning** | Automated SMS + Email to you AND the Duty Officer |
| **Overdue trigger** | Automated alert to On-Call Duty Officer + Slack |
| **Backup monitoring** | Independent GCP Watchdog with its own SMS + Email |
| **Escalation** | 15 min after trigger with no response → re-alert everyone |
| **Cancellation** | Mark safe in-app → watchdog cancelled → trip record created |
| **Data privacy** | Sensitive data scrubbed after 30 days |
| **Monthly testing** | Synthetic test alert through full watchdog pipeline |

The system is designed so that the only way an alert is missed is if **both** the Subterra server **and** the GCP Watchdog independently fail at the same time — and even then, the Duty Officer rota means a real person is expecting to be on standby.

---

## Known Limitations & Recommended Improvements

The core path is reliable and well-tested, but the following are known gaps worth addressing as the feature moves toward heavy reliance. None of these currently break the primary safety guarantee, but each reduces a margin of safety.

### Backup watchdog (`gcp-watchdog`)

- **Per-callout isolation (resolved).** The `/check` loop now processes each overdue callout in its own `try/catch`, and each SMS/email send is individually isolated. A failure alerting one callout (or one recipient) no longer aborts the cycle — the remaining callouts are still alerted, and any failures are reported in the response and logged to Slack.
- **Retry on total failure (resolved).** A callout is only marked alerted when at least one channel was delivered (or there were no contacts to reach). If every send fails, it is left un-alerted so the next 5-minute cycle retries it rather than silently giving up. A callout with no duty-officer contacts is surfaced loudly to Slack.
- **Duty officers only.** The backup alerts duty officers, not participants. This is reasonable for a backup focused on rescue coordination, but should be a conscious decision, not an accident.
- **Dedicated Slack alert (resolved).** The backup now posts a structured `@channel` Slack alert when it detects overdue callouts (via `SLACK_CALLOUTS_WEBHOOK_URL`, falling back to `SLACK_WEBHOOK_URL`), matching the prominence of the primary's alert — rather than relying only on forwarded `console.warn` log lines.

### Subterra primary

- **Alerting is synchronous by design.** Alerts are sent inline within the scheduled run (with bounded timeouts, retries, and per-recipient isolation) rather than via a background worker, to avoid depending on a separate worker process staying alive. If alert volume ever grows enough to make inline sending slow, revisit this with a **supervised, monitored** `callouts` queue worker (the notifications are written to support it).
- **No SMS delivery confirmation.** The system knows Twilio *accepted* a message, not that a duty officer's phone *received* it. Twilio delivery-status callbacks could be wired in to close this gap. Inbound "OUT SAFE"/"ACK" handling and press-1 voice acknowledgement already exist via the Twilio webhooks.
- **Watchdog-sync monitor can briefly false-positive.** If a triggered callout's watchdog cancellation failed, the watchdog's active count can exceed Subterra's for a short window, producing an "out of sync" alert. This errs on the side of over-alerting (acceptable), but a small grace/tolerance could reduce noise.
- **`createTripFromCallout` edge case.** When a callout's cave has no parent cave system, marking safe returns an unsaved, empty `Trip`, so the post-cancellation "trip record" link has no target. Handle system-less locations explicitly (create a minimal trip or omit the link).
- **No technical rate-limiting on callout creation.** Abuse is addressed in the Terms of Service and by the "one active callout per person" guard, but there is no per-user throttle on creation attempts.

### Operational

- **Secrets management.** Twilio and watchdog credentials must be provided via the environment / a secret manager in production (the repo `.env` is git-ignored). Rotate keys if ever exposed.
- **End-to-end coverage.** Each side has good unit/feature coverage (Subterra: PHPUnit; watchdog: Jest), but there is no automated test exercising a real callout flowing through *both* systems together. The monthly `watchdog:test-alert` partially covers the backup pipeline in production.
