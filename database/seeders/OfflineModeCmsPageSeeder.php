<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;

class OfflineModeCmsPageSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::first() ?? User::factory()->admin()->create();

        $content = <<<'MARKDOWN'
## Using Subterra Offline

Subterra supports offline access so you can view cave data, routes, and images underground — even without phone signal or WiFi.

### How It Works

1. **Download caves** while you have an internet connection by visiting any cave page and tapping **"Save Offline"** in the sidebar
2. **Go offline** — all downloaded cave data, routes, and images are stored on your device
3. **Browse offline caves** at any time from the [Offline Caves](/offline) page
4. A banner at the top of the screen will always tell you when you're offline

### What's Available Offline

- ✅ Cave details (description, coordinates, stats, tags)
- ✅ Cave system information and entrances
- ✅ Routes and route images
- ✅ Cave media and photos
- ✅ Active callout timer (continues counting down)
- ✅ Search and filter your downloaded caves
- ❌ Creating or editing trips (requires internet)
- ❌ Cancelling callouts through the app (see below)
- ❌ Weather data (requires internet)
- ❌ Live map tiles (pre-downloaded tiles may be available)

### Callouts While Offline

If you have an active safety callout and lose internet connection:

- **Your callout timer keeps counting down** on your device
- **You'll see a clear offline warning** on the callout screen
- **You cannot cancel the callout** through the app while offline
- **To cancel:** reconnect to data/WiFi, or send a text message to your duty officer

> ⚠️ **Important:** The callout system monitors independently from your device. Even if your phone is off, the server and GCP Watchdog will still trigger an alert if you don't return on time.

### Installing Subterra as an App

For the best offline experience, install Subterra as a Progressive Web App (PWA) on your device:

#### iPhone / iPad (Safari)
1. Open **subterra.world** in Safari
2. Tap the **Share** button (square with arrow)
3. Scroll down and tap **"Add to Home Screen"**
4. Tap **"Add"**

#### Android (Chrome)
1. Open **subterra.world** in Chrome
2. Tap the **three-dot menu** (⋮) in the top right
3. Tap **"Install app"** or **"Add to Home Screen"**
4. Tap **"Install"**

#### Desktop (Chrome / Edge)
1. Open **subterra.world**
2. Click the **install icon** in the address bar (or the three-dot menu → "Install Subterra")
3. Click **"Install"**

Once installed, Subterra launches like a native app, works offline, and updates automatically.

### Managing Offline Storage

- Visit the [Offline Caves](/offline) page to see all your downloaded caves
- View how much storage you're using
- Remove individual caves or clear all offline data
- Storage is limited by your browser/device — typically several hundred MB

### Automatic Updates

Subterra automatically checks for updates in the background. When a new version is available, you'll see a prompt to update. This ensures you always have the latest features and bug fixes without getting stuck on an old version.

### Tips for Caving

- **Download caves before you go underground** — you can't download while offline
- **Download all entrances** in a system if you plan to traverse
- **Check your callout timer** before going underground
- **Charge your phone** — offline mode uses less battery than mobile data, but your screen still drains power
- **Tell someone your plan** — the callout system is your backup, not your only safety measure
MARKDOWN;

        Page::updateOrCreate(
            ['slug' => 'offline-mode'],
            [
                'title' => 'Offline Mode',
                'content' => $content,
                'user_id' => $admin->id,
                'access_count' => 0,
            ]
        );
    }
}
