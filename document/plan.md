# ApexBulk — Shopify App Plan

## 🎯 Core Philosophy

**"The only bulk editor that thinks before it edits."**
3 core modules only: Price, Inventory, Tags — powered by AI insights.

---

## 📐 Architecture

```
┌──────────────────────────────────────────────┐
│               SHOPIFY ADMIN IFRAME            │
│  ┌────────────────────────────────────────┐  │
│  │     POLARIS UI (Blade + Web Components) │  │
│  │  ┌─────────┐ ┌──────────┐ ┌────────┐  │  │
│  │  │ Price   │ │Inventory │ │ Tags   │  │  │
│  │  │ Editor  │ │ Editor   │ │ Editor │  │  │
│  │  └────┬────┘ └────┬─────┘ └───┬────┘  │  │
│  │       │           │           │        │  │
│  │       └───────────┼───────────┘        │  │
│  │                   │                    │  │
│  │         ┌─────────┴─────────┐          │  │
│  │         │  AI Dashboard     │          │  │
│  │         │  (Suggestions)    │          │  │
│  │         └─────────┬─────────┘          │  │
│  │                   │                    │  │
│  │         ┌─────────┴─────────┐          │  │
│  │         │  Task History     │          │  │
│  │         │  (Copy/Revert)    │          │  │
│  │         └───────────────────┘          │  │
│  └────────────────────────────────────────┘  │
│                   │                           │
│              session token                    │
│                   ↓                           │
│  ┌────────────────────────────────────────┐  │
│  │         LARAVEL BACKEND                │  │
│  │                                        │  │
│  │  Controllers                           │  │
│  │  ├── EditorController  (CRUD tasks)    │  │
│  │  ├── TaskController    (history/revert)│  │
│  │  ├── DashboardController (AI insights) │  │
│  │  └── ApiController     (GraphQL proxy) │  │
│  │                                        │  │
│  │  Jobs (Queue)                          │  │
│  │  ├── ProcessPriceJob                   │  │
│  │  ├── ProcessInventoryJob               │  │
│  │  ├── ProcessTagsJob                    │  │
│  │  ├── GenerateAiInsightsJob             │  │
│  │  └── RevertTaskJob                     │  │
│  │                                        │  │
│  │  Services                              │  │
│  │  ├── ShopifyQueryService               │  │
│  │  ├── PriceCalculator                   │  │
│  │  └── AiService (OpenAI/Gemini)         │  │
│  └────────────────────────────────────────┘  │
│                   │                           │
│              API Client (kyon147)             │
│                   ↓                           │
│  ┌────────────────────────────────────────┐  │
│  │         SHOPIFY ADMIN API              │  │
│  │  • GraphQL mutations/query             │  │
│  │  • REST for bulk data                  │  │
│  │  • Webhook reception                   │  │
│  └────────────────────────────────────────┘  │
└──────────────────────────────────────────────┘
```

---

## 🗄️ Database

### Existing (kyon147 package) — NO change needed:

```
users — shop data (name=domain, password=token, etc.)
```

### New Tables:

#### bulk_edit_tasks

```
id, user_id (FK→users), task_type (price|inventory|tags),
status (pending|running|completed|failed|reverting|reverted),
parameters (JSON), product_ids (JSON|null=all),
scheduled_at, failure_reason, created_at, updated_at
```

#### task_revert_logs

```
id, bulk_edit_task_id (FK), shopify_product_id,
shopify_variant_id (nullable), original_data (JSON),
created_at
```

---

## 🧩 Modules Breakdown

### Module 1: 💰 Price Editor (Priority: 🥇)

| Component  | Path                                          |
| ---------- | --------------------------------------------- |
| View       | `resources/views/editor/price.blade.php`      |
| Controller | `app/Http/Controllers/EditorController@price` |
| Job        | `app/Jobs/ProcessPriceJob.php`                |
| Service    | `app/Services/PriceCalculator.php`            |

**Actions:**

- Set specific price
- Increase/Decrease by amount ($)
- Increase/Decrease by percentage (%)
- Rounding: nearest .01, whole number, .99, custom

**Product Selection:**

- Manual pick (resource picker)
- All products
- Filter by: collection, vendor, type, tag, price range

**AI Insight:**

- "Products priced below $10 → 38% margin loss, suggested $14.99"
- "Similar products in market avg $34 → yours at $28, room for +$6"

---

### Module 2: 📦 Inventory Editor (Priority: 🥈)

| Component  | Path                                              |
| ---------- | ------------------------------------------------- |
| View       | `resources/views/editor/inventory.blade.php`      |
| Controller | `app/Http/Controllers/EditorController@inventory` |
| Job        | `app/Jobs/ProcessInventoryJob.php`                |

**Actions:**

- Set quantity
- Add/Remove quantity
- Track inventory: on/off
- Continue selling when out of stock: on/off

**Product Selection:**

- Manual pick
- All products
- Filter by: inventory level, collection, vendor

**AI Insight:**

- "5 products will stock out in <7 days at current sales rate"
- "Variant X: 500 units, selling 2/week = 250 weeks — dead stock"

---

### Module 3: 🏷️ Tags Editor (Priority: 🥈)

| Component  | Path                                         |
| ---------- | -------------------------------------------- |
| View       | `resources/views/editor/tags.blade.php`      |
| Controller | `app/Http/Controllers/EditorController@tags` |
| Job        | `app/Jobs/ProcessTagsJob.php`                |

**Actions:**

- Add tags
- Remove tags
- Replace all tags
- Clear all tags

**Product Selection:**

- Manual pick
- All products
- Filter by: existing tags, collection, vendor

**AI Insight:**

- "47 products missing seasonal tags — suggest: summer, winter"
- "Merge conflict: `tshirt`, `T-Shirt`, `TSHIRT` → cleanup recommended"

---

### Module 4: 🤖 AI Dashboard (Priority: 🥇 — USP)

| Component  | Path                                           |
| ---------- | ---------------------------------------------- |
| View       | `resources/views/dashboard/insights.blade.php` |
| Controller | `app/Http/Controllers/DashboardController`     |
| Job        | `app/Jobs/GenerateAiInsightsJob.php`           |
| Service    | `app/Services/AiService.php`                   |

**Auto-scans store on load:**

1. Fetch all products (basic data)
2. Send to AI for analysis
3. Return categorized suggestions

**Output:**

```
💰 PRICE SUGGESTIONS (12)
├── 8 products overpriced vs competitors
├── 3 products below margin threshold
└── 1 product price anomaly (typo?)

📦 INVENTORY ALERTS (5)
├── 3 products stockout in <7 days
└── 2 products dead stock (0 sales 90+ days)

🏷️ TAG SUGGESTIONS (47)
├── 30 products missing SEO-relevant tags
└── 17 products with duplicate/inconsistent tags
```

**Key: Each suggestion is an actionable button → opens the editor pre-filled.**

---

### Module 5: 📋 Task History (Priority: 🥈)

| Component  | Path                                    |
| ---------- | --------------------------------------- |
| View       | `resources/views/tasks/index.blade.php` |
| Controller | `app/Http/Controllers/TaskController`   |

**Features:**

- Paginated list (50/page)
- Filter by type, status
- Copy any task → opens editor with pre-filled parameters
- Revert completed task → dispatches RevertTaskJob

---

## 🛣️ Routes

```
GET  /                       → Dashboard (AI insights)
GET  /editor/price           → Price editor
POST /editor/price           → Submit price task
GET  /editor/inventory       → Inventory editor
POST /editor/inventory       → Submit inventory task
GET  /editor/tags            → Tags editor
POST /editor/tags            → Submit tags task
GET  /tasks                  → Task history
POST /tasks/{task}/revert    → Revert task
POST /tasks/{task}/copy      → Copy task
POST /api/graphql            → GraphQL proxy
GET  /api/insights           → AI insights (AJAX)
```

---

## 🔨 Build Order (Micro Steps)

---

### Phase 1: Foundation 🏗️

| #    | Step                                            | File                                        | Time  |
| ---- | ----------------------------------------------- | ------------------------------------------- | ----- |
| 1.1  | Create migration: `bulk_edit_tasks` table       | `database/migrations/...`                   | 2 min |
| 1.2  | Create migration: `task_revert_logs` table      | `database/migrations/...`                   | 2 min |
| 1.3  | Run `php artisan migrate`                       | —                                           | 1 min |
| 1.4  | Create `BulkEditTask` model                     | `app/Models/BulkEditTask.php`               | 5 min |
| 1.5  | Create `TaskRevertLog` model                    | `app/Models/TaskRevertLog.php`              | 3 min |
| 1.6  | Add relationship: User → BulkEditTask           | `app/Models/User.php`                       | 2 min |
| 1.7  | Create `EditorController` (empty shell)         | `app/Http/Controllers/EditorController.php` | 3 min |
| 1.8  | Add route: `GET /editor/price`                  | `routes/web.php`                            | 2 min |
| 1.9  | Create `editor/` directory                      | `resources/views/editor/`                   | 1 min |
| 1.10 | Create `price.blade.php` (Polaris layout)       | `resources/views/editor/price.blade.php`    | 5 min |
| 1.11 | Create `tasks/` directory + `index.blade.php`   | `resources/views/tasks/`                    | 5 min |
| 1.12 | Update `welcome.blade.php` → redirect to editor | `resources/views/welcome.blade.php`         | 2 min |

> **Phase 1 Result:** Clean app with working navigation. Clicking "Price Editor" shows blank form page.

---

### Phase 2: Price Module 💰

| #    | Step                                                      | File                               | Time   |
| ---- | --------------------------------------------------------- | ---------------------------------- | ------ |
| 2.1  | Create `PriceCalculator` service                          | `app/Services/PriceCalculator.php` | 10 min |
| 2.2  | Unit test: `calculate(10, 'increase_percent', 20)` → `12` | —                                  | 5 min  |
| 2.3  | Create `ProcessPriceJob` skeleton                         | `app/Jobs/ProcessPriceJob.php`     | 5 min  |
| 2.4  | Add GraphQL: fetch variant prices                         | Inside Job                         | 10 min |
| 2.5  | Add GraphQL: update variant prices                        | Inside Job                         | 10 min |
| 2.6  | Save revert logs while processing                         | Inside Job                         | 5 min  |
| 2.7  | Add route: `POST /editor/price`                           | `routes/web.php`                   | 3 min  |
| 2.8  | Add `submitPrice()` to EditorController                   | `EditorController.php`             | 10 min |
| 2.9  | Build price form UI (Polaris components)                  | `price.blade.php`                  | 15 min |
| 2.10 | Add "Select Products" using resource picker               | `price.blade.php`                  | 10 min |
| 2.11 | Add preview calculation (AJAX)                            | `price.blade.php` + Controller     | 10 min |
| 2.12 | Connect form → submit → dispatch job                      | `price.blade.php`                  | 5 min  |
| 2.13 | Test: Change price of 1 product                           | Manual                             | 5 min  |
| 2.14 | Test: Bulk price on 10 products                           | Manual                             | 5 min  |

> **Phase 2 Result:** Merchant selects products, sets price rules, clicks execute → prices update.

---

### Phase 3: Inventory Editor 📦

| #    | Step                                     | File                                         | Time   |
| ---- | ---------------------------------------- | -------------------------------------------- | ------ |
| 3.1  | Create `ProcessInventoryJob`             | `app/Jobs/ProcessInventoryJob.php`           | 10 min |
| 3.2  | Add GraphQL: fetch inventory levels      | Inside Job                                   | 5 min  |
| 3.3  | Add GraphQL: update inventory quantities | Inside Job                                   | 10 min |
| 3.4  | Save revert logs while processing        | Inside Job                                   | 5 min  |
| 3.5  | Add route: `GET /editor/inventory`       | `routes/web.php`                             | 2 min  |
| 3.6  | Add route: `POST /editor/inventory`      | `routes/web.php`                             | 2 min  |
| 3.7  | Add `inventory()` + `submitInventory()`  | `EditorController.php`                       | 10 min |
| 3.8  | Build inventory form UI                  | `resources/views/editor/inventory.blade.php` | 15 min |
| 3.9  | Connect form → submit → dispatch job     | Same view                                    | 5 min  |
| 3.10 | Test: Set inventory on 5 products        | Manual                                       | 5 min  |

> **Phase 3 Result:** Price + Inventory both working. Two editors ready.

---

### Phase 4: Tags Editor 🏷️

| #   | Step                                 | File                                    | Time   |
| --- | ------------------------------------ | --------------------------------------- | ------ |
| 4.1 | Create `ProcessTagsJob`              | `app/Jobs/ProcessTagsJob.php`           | 10 min |
| 4.2 | Add GraphQL: fetch product tags      | Inside Job                              | 5 min  |
| 4.3 | Add GraphQL: update product tags     | Inside Job                              | 5 min  |
| 4.4 | Add routes: `GET/POST /editor/tags`  | `routes/web.php`                        | 3 min  |
| 4.5 | Add `tags()` + `submitTags()`        | `EditorController.php`                  | 10 min |
| 4.6 | Build tags form UI                   | `resources/views/editor/tags.blade.php` | 15 min |
| 4.7 | Connect form → submit → dispatch job | Same view                               | 5 min  |
| 4.8 | Test: Add/remove tags on 10 products | Manual                                  | 5 min  |

> **Phase 4 Result:** All 3 editors working. Core app functional.

---

### Phase 5: Task History 📋

| #    | Step                                                 | File                                      | Time   |
| ---- | ---------------------------------------------------- | ----------------------------------------- | ------ |
| 5.1  | Add route: `GET /tasks`                              | `routes/web.php`                          | 2 min  |
| 5.2  | Create `TaskController`                              | `app/Http/Controllers/TaskController.php` | 5 min  |
| 5.3  | Build task list UI (Polaris table)                   | `resources/views/tasks/index.blade.php`   | 15 min |
| 5.4  | Add status badges (pending/running/completed/failed) | Same view                                 | 5 min  |
| 5.5  | Add filter: by task type & status                    | Same view                                 | 10 min |
| 5.6  | Add action buttons: Copy & Revert                    | Same view                                 | 5 min  |
| 5.7  | Add route: `POST /tasks/{task}/copy`                 | `routes/web.php`                          | 3 min  |
| 5.8  | Implement `copy()` — duplicate task as pending       | `TaskController.php`                      | 5 min  |
| 5.9  | Add route: `POST /tasks/{task}/revert`               | `routes/web.php`                          | 3 min  |
| 5.10 | Create `RevertTaskJob`                               | `app/Jobs/RevertTaskJob.php`              | 15 min |
| 5.11 | Implement `revert()` — dispatch revert job           | `TaskController.php`                      | 5 min  |
| 5.12 | Update welcome blade: links to all sections          | `welcome.blade.php`                       | 5 min  |

> **Phase 5 Result:** Full task management. View history, copy tasks, revert changes.

---

### Phase 6: AI Dashboard 🤖

| #    | Step                                               | File                                           | Time   |
| ---- | -------------------------------------------------- | ---------------------------------------------- | ------ |
| 6.1  | Add `OPENAI_API_KEY` to `.env`                     | `.env`                                         | 1 min  |
| 6.2  | Create `AiService`                                 | `app/Services/AiService.php`                   | 15 min |
| 6.3  | Create `GenerateAiInsightsJob`                     | `app/Jobs/GenerateAiInsightsJob.php`           | 15 min |
| 6.4  | Create `DashboardController`                       | `app/Http/Controllers/DashboardController.php` | 5 min  |
| 6.5  | Build AI dashboard UI (Polaris cards)              | `resources/views/dashboard/insights.blade.php` | 20 min |
| 6.6  | Add "Generate Insights" button → dispatches job    | Same view                                      | 5 min  |
| 6.7  | Show insights in categorized cards                 | Same view                                      | 15 min |
| 6.8  | Each insight = clickable → opens editor pre-filled | Same view                                      | 10 min |
| 6.9  | Update `/` route → show dashboard                  | `routes/web.php`                               | 2 min  |
| 6.10 | Test: Generate insights for test store             | Manual                                         | 5 min  |

> **Phase 6 Result:** AI scans store → shows what needs fixing → one click to fix.

---

### Phase 7: Polish & Ship 🚀

| #   | Step                                          | File             | Time   |
| --- | --------------------------------------------- | ---------------- | ------ |
| 7.1 | Add loading skeletons for all pages           | All views        | 10 min |
| 7.2 | Add error handling (try/catch in all jobs)    | All Jobs         | 10 min |
| 7.3 | Add task progress tracking (x of y processed) | All Jobs + Views | 15 min |
| 7.4 | Navigation menu (Polaris sidebar or tabs)     | Layout           | 15 min |
| 7.5 | Responsive design check                       | All views        | 10 min |
| 7.6 | Update `.env.example` with all env vars       | `.env.example`   | 5 min  |
| 7.7 | Final manual test: full flow                  | Manual           | 15 min |
| 7.8 | Ready for production 🎉                       | —                | —      |

> **Phase 7 Result:** Production-ready app. Deploy and submit to Shopify App Store.

---

## ⏱️ Total Estimated Time

| Phase     | Name         | Steps        | Estimated      |
| --------- | ------------ | ------------ | -------------- |
| 1         | Foundation   | 12 steps     | ~30 min        |
| 2         | Price Module | 14 steps     | ~90 min        |
| 3         | Inventory    | 10 steps     | ~50 min        |
| 4         | Tags         | 8 steps      | ~50 min        |
| 5         | Task History | 12 steps     | ~60 min        |
| 6         | AI Dashboard | 10 steps     | ~90 min        |
| 7         | Polish       | 8 steps      | ~90 min        |
| **Total** |              | **74 steps** | **~7-8 hours** |

---

## 📊 File Structure

```
app/
├── Http/
│   └── Controllers/
│       ├── DashboardController.php
│       ├── EditorController.php
│       └── TaskController.php
├── Jobs/
│   ├── ProcessPriceJob.php
│   ├── ProcessInventoryJob.php
│   ├── ProcessTagsJob.php
│   ├── GenerateAiInsightsJob.php
│   └── RevertTaskJob.php
├── Models/
│   ├── BulkEditTask.php
│   └── TaskRevertLog.php
└── Services/
    ├── PriceCalculator.php
    ├── ShopifyQueryService.php
    └── AiService.php

database/migrations/
├── xxxx_create_bulk_edit_tasks_table.php
└── xxxx_create_task_revert_logs_table.php

resources/views/
├── welcome.blade.php         (redirect → dashboard)
├── dashboard/
│   └── insights.blade.php    (AI dashboard)
├── editor/
│   ├── price.blade.php
│   ├── inventory.blade.php
│   └── tags.blade.php
└── tasks/
    └── index.blade.php       (history)
```

---

## 🎯 Final USP Statement

**ApexBulk doesn't just edit — it tells you WHAT to edit and WHY.**

- Competitors: "Here's a form, fill it."
- ApexBulk: "Here are 12 products that need price changes, 5 that will stock out, and 47 with broken tags. Want me to fix them all?"

That's the moat. 🚀
