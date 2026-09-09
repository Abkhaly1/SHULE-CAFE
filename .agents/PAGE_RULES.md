# SHULE CAFE — Official Page Design & Frontend UI Architecture Rules

This document defines the strict, non-negotiable frontend design patterns and page layout rules for SHULE CAFE. Every portal (Headmaster, Teacher, Super Admin, Parent, Regional Officer) must strictly adhere to these standards.

---

## 1. Single-Row Compact Header Standard (Strict No-Subtitle Rule)
- **Eliminate Subtitles & Multi-Line Headers**: DO NOT use `<app-page-header>` with descriptive paragraph subtitles or stacked headings. Header descriptions take up unnecessary vertical space.
- **Single-Row Compact Box**: Every page workspace must use the standard single-row compact header card:
  ```html
  <div class="card" style="margin-bottom: 6px; padding: 6px 14px; background: white; border-radius: 8px; border: 1px solid var(--c-primary-border); box-shadow: 0 1px 2px rgba(0,0,0,0.03); display: flex; align-items: center; justify-content: space-between;">
      <div style="display: flex; align-items: center; gap: 8px;">
          <div style="width: 26px; height: 26px; border-radius: 6px; background: var(--c-primary); color: white; display: flex; align-items: center; justify-content: center;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                  <!-- Vector SVG Icon -->
              </svg>
          </div>
          <h2 style="margin: 0; font-size: 14px; font-weight: 800; color: var(--c-text-primary);">Page Title</h2>
      </div>

      <!-- Right-Aligned Embedded Workspace Tabs or Action Buttons (Same Line) -->
      <app-workspace-tabs active-tab="tab1">
          <button class="workspace-tab active" data-target="tab1" style="font-weight: 700; font-size: 12px;">...</button>
          <button class="workspace-tab" data-target="tab2" style="font-weight: 700; font-size: 12px;">...</button>
      </app-workspace-tabs>
  </div>
  ```
- **Same-Row Navigation**: When a workspace contains tabs, those tabs (`<app-workspace-tabs>`) MUST be embedded directly within the header card on the right-hand side, never as a separate floating row underneath.

---

## 2. Standard Vector SVG Icons Directive (Strict No-Emoji Rule)
- **100% Vector SVG Icons**: All icons across all buttons, cards, table cells, headers, badges, alerts, and navigation links must use crisp vector SVG elements (`<svg viewBox="0 0 24 24" ...>`).
- **Zero Keyboard Emojis**: NEVER use raw emoji characters (such as 📊, 👥, 🏫, 📝, 👨‍🏫, 🖨️, ⚙️, 📈, 🔔, 🏆, 🥇, ⚠️, ❌, ✅, ⬅, ➡️, 🔍, 📥, 🎓, 👦, 👧, 🟢, 🟡, 🔴, etc.) anywhere in UI buttons, stat cards, sidebar menus, navigation tabs, banners, headers, table badges, or notifications.

---

## 3. English-Only System Directive (Strict No-Swahili Rule)
- **100% English UI**: All labels, button text, headers, table columns, tooltips, select placeholders, filter labels, and notification toasts must be written strictly in English (`en`).
- **No Swahili Terms**: Swahili words (e.g. *Mwalimu*, *Darasa*, *Wanafunzi*, *Mahudhurio*, *Matokeo*, *Muhula*, etc.) are strictly prohibited anywhere in the system UI, codebase, seed data, or responses.

---

## 4. Standard Page Shell & Layout Hierarchy
All portal pages must strictly follow this outer DOM structure:
```html
<div class="app-layout">
    <!-- 1. Standard Sidebar -->
    <app-sidebar role="tenant_admin" active-path="/headmaster/..."></app-sidebar>
    
    <div class="app-main">
        <!-- 2. Standard Topbar -->
        <app-topbar username="Headmaster Portal"></app-topbar>
        
        <!-- 3. Content Wrapper with Zero-Padding Content Container -->
        <main class="app-content-wrapper">
            <div class="app-content" style="padding: 0;">
                
                <!-- 4. Compact Breadcrumb Ribbon -->
                <div style="margin-bottom: 6px;">
                    <app-breadcrumb path="Headmaster, Section Name"></app-breadcrumb>
                </div>

                <!-- 5. Single-Row Compact Header Box -->
                ...

                <!-- 6. Main Workspace Area -->
                <div class="workspace-main-area">
                    ...
                </div>

            </div>
        </main>
    </div>
</div>
```

---

## 5. KPI & Stat Card Guidelines
- Cards must use clean 8px-12px rounded borders with subtle shadows: `border: 1px solid var(--c-border-light); border-radius: 10px; background: white;`.
- Use a 4px solid accent border on the left or top indicating state (`#047857` for emerald success, `#3b82f6` for primary blue, `#f59e0b` for amber warning, `#ef4444` for red alert).
- Number values must be bold and readable (`font-size: 24px-26px; font-weight: 800; color: #0f172a;`).
- Label captions must be crisp and compact (`font-size: 11px-12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;`).

---

## 6. Table & Workspace Standards
- **Unified Filter Bar**: Filter controls (academic year dropdown, class stream selector, search input, pagination size) must be contained in a single neat white filter card above the table.
- **Search Inputs**: Search boxes must have clear placeholder text, proper padding without icon overlapping (`padding-left: 36px` when using prefix icon), and instant responsive filtering.
- **Badges & Statuses**: Use standard CSS badges (`badge badge-success`, `badge badge-danger`, `badge badge-warning`) with semantic SVG icons.
- **Empty States**: Tables with 0 rows must display a clean, reassuring empty state message with an SVG icon, rather than broken layout.

---

## 7. Multi-Tenant Scoping & Security
- Every data request to the backend must be scoped to `$_SESSION['school_id']` and the active academic year.
- Error alerts must be formatted in red (`border-color: #fca5a5; background: #fff5f5; color: #b91c1c;`).
