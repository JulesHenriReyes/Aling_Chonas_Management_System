# Staff design-field alignment

The staff customization fields now use a shared label row so the theme input and design-instruction textarea start at the same vertical position when shown side by side. The fields stack inside the staff content container at narrow widths. Public customization keeps its original grid through the non-staff `display: contents` wrapper.

Verification:

- `layout-smoke.mjs` passed at 390, 768, and 1440px using the checked-in stylesheet. Staff controls align at 1440px, stack at narrow widths, and the public controls keep separate columns at 1440px and stack at 390px.
- `php artisan view:cache` passed after the Blade component change.
- `git diff --check` passed.

The full guarded browser preview was already verified for the modernization flow. A follow-up live preview run was not repeated because the environment approval quota blocked localhost browser access; no business database was used.
