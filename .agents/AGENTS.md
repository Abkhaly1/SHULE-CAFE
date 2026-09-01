# SHULE CAFE - Custom Agent Rules & Project Directives

## 1. GitHub Code Publishing & Revision Directive
- **Automatic Versioning & Commits**: Every code edit, feature addition, or bug fix must be tracked cleanly and prepared for publication to GitHub repository.
- **Git Commits**: Use descriptive commit messages detailing exact changes made (e.g. `feat(auth): add anti-bot honeypot and rate limiting`).

## 2. Codebase Anti-Theft & Intellectual Property Protection
- **Proprietary License Notice**: All source files must include the proprietary copyright banner protecting SHULE CAFE software intellectual property.
- **Domain & Signature Lock (`license_guard.php`)**: Maintain hardware/domain license key verification to prevent unauthorized cloning, stolen database reuse, or unauthorized third-party hosting.
- **Environment Integrity**: Ensure sensitive database credentials (`.env`, `db.php`) remain protected and isolated from repository leaks.

## 3. English-Only System Directive (Strict No-Swahili Rule)
- **100% English UI**: SHULE CAFE must be 100% in English. DO NOT add, re-introduce, or write Kiswahili / Swahili words, translations, labels, buttons, messages, modal titles, or options anywhere in the system codebase, UI, database seeds, or templates.
- **Language Lock**: The system language must remain strictly English (`en`). All UI elements, comments, tooltips, placeholders, select options, and dynamic notifications must be exclusively in English.

## 4. Standard SVG / Vector Icons Directive (Strict No-Keyboard / No-Emoji Icons Rule)
- **100% Standard SVG Icons**: SHULE CAFE must use clean, professional standard SVG vector icons (`<svg viewBox="0 0 24 24">...</svg>`). DO NOT use emoji keyboard characters (such as 📊, 👥, 🏫, 📝, 👨‍🏫, 🖨️, ⚙️, 📈, 🔔, 🏆, 🥇, ⚠️, ❌, ✅, ⬅, ➡️, 🔍, 📥, 🎓, 👦, 👧, 🟢, 🟡, 🔴, etc.) anywhere in UI buttons, stat cards, sidebar menus, navigation tabs, banners, headers, table badges, or notifications.
- **Visual Elegance & Professionalism**: All iconography across all role portals, dashboards, reports, and workspaces must be crisp, high-resolution SVG icons with appropriate `stroke`, `fill`, `width`, `height`, and semantic CSS styling.
